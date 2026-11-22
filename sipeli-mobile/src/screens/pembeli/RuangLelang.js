import { useCallback, useRef, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, Vibration, View } from 'react-native';
import { buatKunci, lelang } from '../../api';
import { DialogKonfirmasi, KolomTeks, KotakPesan, Tombol } from '../../components/Dasar';
import FormGulir from '../../components/FormGulir';
import FotoIkan from '../../components/FotoIkan';
import { PanelLelang } from '../../components/Lelang';
import { Bagian, Info, Tabel } from '../../components/Tata';
import { formatKg, formatRupiah } from '../../format';
import { usePolling } from '../../polling';
import { JEDA_PENGGANTI, AMAN_DIAM, useKanalRealtime, PERISTIWA_RUANG_LELANG } from '../../realtime';
import { ukuran, warna } from '../../tema';
import { hanyaAngka } from '../../validasi';

const POSISI = {
  TERTINGGI: { teks: 'Anda penawar tertinggi', latar: warna.suksesLatar, warna: warna.sukses },
  TERTINGGAL: { teks: 'Anda tertinggal, ajukan tawaran baru', latar: warna.galatLatar, warna: warna.galat },
  BELUM_MENAWAR: { teks: 'Anda belum menawar', latar: warna.abu, warna: warna.teksRedup },
};

/**
 * UC-04 Ruang Lelang Pembeli.
 *
 * NFR-10: peristiwa PENAWARAN_BARU datang lewat kanal real-time pada kanal TPI; selama
 * kanal sehat layar tidak melakukan pengambilan berkala. Bila kanal terputus, layar
 * berpindah ke polling 2 detik sampai kanal pulih (JEDA_PENGGANTI). Status awal tetap
 * diminta sekali lewat HTTP agar layar terisi sebelum peristiwa pertama tiba.
 * Getar + pesan saat posisi berubah menjadi tertinggal (FR-22).
 * Kunci idempotensi dibuat per tawaran; bila koneksi putus, kirim ulang memakai kunci yang sama (FR-20).
 */
export default function RuangLelang({ idSesi, idTpi, idPembeli, onKembali, onLihatTagihan }) {
  const [data, setData] = useState(null);
  const [galat, setGalat] = useState(null);
  const [harga, setHarga] = useState('');
  const [galatHarga, setGalatHarga] = useState(null);
  const [pesan, setPesan] = useState(null);
  const [mengirim, setMengirim] = useState(false);
  const [tanyaTawaran, setTanyaTawaran] = useState(false);
  const [tanyaAmbil, setTanyaAmbil] = useState(false);
  // Waktu data terakhir diterima; dipakai jam berdetak supaya sisa waktu terus bergerak
  // walau peristiwa kanal belum datang (lihat formatSisaSekarang di components/Lelang.js).
  const diterimaPada = useRef(Date.now());
  const posisiLalu = useRef(null);
  const percobaan = useRef(null); // { harga, kunci } tawaran terakhir yang gagal karena koneksi
  const diubahPengguna = useRef(false);

  const terima = useCallback((d) => {
    if (posisiLalu.current === 'TERTINGGI' && d.posisi === 'TERTINGGAL') {
      Vibration.vibrate([0, 250, 120, 250]);
      setPesan({ jenis: 'galat', teks: 'Tawaran Anda telah dilampaui peserta lain.' });
      diubahPengguna.current = false; // isian lama sudah usang -> isi ulang dengan minimum baru
    }
    posisiLalu.current = d.posisi;
    diterimaPada.current = Date.now();
    setData(d);
    // Isi otomatis harga minimum HANYA bila pengguna belum mengetik sendiri.
    // Angka yang sedang diketik tidak pernah ditimpa; bila terlalu rendah, ditolak saat TAWAR ditekan.
    if (!diubahPengguna.current) {
      setHarga(String(Math.round(d.harga_minimum_berikut)));
    }
  }, []);

  const muat = useCallback(async () => {
    try {
      const hasil = await lelang.status(idSesi);
      terima(hasil.data);
      setGalat(null);
    } catch (e) {
      if (e.status !== 401) setGalat(e.status === 0 ? 'Koneksi terputus. Mencoba lagi...' : e.message);
    }
  }, [idSesi, terima]);
  const aktif = !data || data.status === 'AKTIF';
  const kanalId = aktif ? (idTpi ? `tpi.${idTpi}` : null) : null;
  // Tabel IV.7: selain tawaran baru, layar mengikuti perpanjangan waktu, hasil lelang,
  // dan penutupan sesi sehingga sisa waktu dan status pemenang berubah seketika.
  const tersambung = useKanalRealtime(kanalId, PERISTIWA_RUANG_LELANG, (muatan, nama) => {
    // FR-22: OUTBID dikirim ke kanal TPI dengan id_pembeli penerima. Hanya pembeli yang
    // disebut yang diberi tahu, dan hanya bila ia benar-benar bukan penawar tertinggi.
    if (nama === 'OUTBID') {
      const saya = muatan?.id_pembeli;
      if (saya && saya === idPembeli) {
        Vibration.vibrate([0, 200, 100, 200]);
        setPesan({
          jenis: 'galat',
          teks: muatan?.pesan || 'Tawaran Anda tertinggal. Ajukan tawaran baru.',
        });
      }
      return;
    }
    muat();
  });
  // Kanal sehat -> tidak ada pengambilan berkala; kanal putus -> polling 2 detik (NFR-10).
  usePolling(muat, JEDA_PENGGANTI, aktif && !tersambung);
  // Jam berdetak dari angka terakhir; penyegaran ringan ini menjaga angkanya tidak melenceng
  // bila peristiwa kanal lama tidak datang (mis. sesi diperpanjang di luar layar ini).
  usePolling(muat, AMAN_DIAM, aktif && tersambung);

  function periksa() {
    setPesan(null);
    const n = Number(harga);
    if (!/^\d+$/.test(harga)) return setGalatHarga('Isi harga per kg dengan angka saja.');
    if (n < data.harga_minimum_berikut) {
      return setGalatHarga(`Tawaran minimal ${formatRupiah(data.harga_minimum_berikut)} per kg.`);
    }
    setTanyaTawaran(true);
  }

  async function ambil() {
    setTanyaAmbil(false);
    setMengirim(true); setPesan(null);
    try { const r = await lelang.ambil(idSesi, buatKunci()); diterimaPada.current = Date.now(); setData(r.data); setPesan({ jenis: 'sukses', teks: r.pesan }); }
    catch (e) { setPesan({ jenis: 'galat', teks: e.message }); muat(); }
    finally { setMengirim(false); }
  }

  async function kirim() {
    setTanyaTawaran(false);
    const n = Number(harga);
    const kunci = percobaan.current && percobaan.current.harga === n ? percobaan.current.kunci : buatKunci();
    setMengirim(true);
    try {
      const hasil = await lelang.tawar(idSesi, n, kunci);
      percobaan.current = null;
      diubahPengguna.current = false;
      posisiLalu.current = 'TERTINGGI';
      diterimaPada.current = Date.now();
      setData(hasil.data);
      setHarga(String(Math.round(hasil.data.harga_minimum_berikut)));
      setPesan({ jenis: 'sukses', teks: `${hasil.pesan} (${formatRupiah(n)}/kg)` });
    } catch (e) {
      if (e.status === 0) {
        percobaan.current = { harga: n, kunci };
        setPesan({ jenis: 'galat', teks: 'Koneksi terputus. Tekan TAWAR lagi; tawaran tidak akan tercatat dua kali.' });
      } else {
        percobaan.current = null;
        setGalatHarga(e.galatKolom?.harga_per_kg || null);
        if (!e.galatKolom?.harga_per_kg) setPesan({ jenis: 'galat', teks: e.message });
        muat();
      }
    } finally {
      setMengirim(false);
    }
  }

  function naikkan(kali) {
    diubahPengguna.current = true;
    setGalatHarga(null);
    setHarga(String(Math.round(data.harga_minimum_berikut + (kali - 1) * data.kenaikan_minimum)));
  }

  if (!data) {
    return (
      <View style={s.tengah}>
        {galat ? <KotakPesan teks={galat} /> : <ActivityIndicator size="large" color={warna.utama} />}
        <Tombol judul="Kembali" jenis="teks" onPress={onKembali} gaya={{ marginTop: 12 }} />
      </View>
    );
  }

  const lot = data.lot;
  const pos = POSISI[data.posisi];
  const hasil = data.hasil;
  const total = Number(harga) * lot.berat_aktual;

  return (
    <View style={{ flex: 1, backgroundColor: warna.latar }}>
      <FormGulir gayaIsi={s.isi}>
        <Bagian>
          <View style={s.barisLot}>
            <FotoIkan lot={lot} lebar={108} tinggi={72} kecil />
            <View style={{ flex: 1 }}>
              <View style={s.barisJudul}>
                <Text style={s.kode}>{lot.kode_lot}</Text>
                <Text style={s.psn}>{data.kode_pemesanan}</Text>
              </View>
              <Text style={s.jenis}>
                {lot.nama_jenis} <Text style={{ color: warna.aksen }}>Grade {lot.kode_grade}</Text> | {formatKg(lot.berat_aktual)}
              </Text>
            </View>
          </View>
          <View style={{ marginTop: 12 }}>
            <PanelLelang
              sisa={data.sisa_detik}
              diterimaPada={diterimaPada.current}
              status={data.status}
              perpanjangan={data.jumlah_perpanjangan}
              maksPerpanjangan={data.maks_perpanjangan}
              labelHarga={data.mode === 'TURUN' ? 'Harga berjalan (Turun)' : (data.harga_tertinggi ? 'Harga tertinggi' : 'Harga pembukaan')}
              harga={formatRupiah(data.harga_tertinggi ?? data.harga_pembukaan)}
              info={data.mode === 'TURUN' ? `Turun ${formatRupiah(data.penurunan_per_langkah)}/kg tiap ${data.interval_detik} detik | batas ${formatRupiah(data.harga_batas_bawah)}` : `${data.jumlah_penawaran} tawaran | ${data.jumlah_penawar} penawar | kenaikan ${formatRupiah(data.kenaikan_minimum)}`}
            />
          </View>
          {!hasil ? (
            <View style={[s.posisi, { backgroundColor: pos.latar, borderLeftColor: pos.warna }]}>
              <Text style={[s.teksPosisi, { color: pos.warna }]}>{pos.teks}</Text>
              {data.tawaran_saya ? <Text style={s.tawaranSaya}>Tawaran terakhir Anda {formatRupiah(data.tawaran_saya)}/kg</Text> : null}
            </View>
          ) : null}
          {galat ? <KotakPesan teks={galat} gaya={{ marginTop: 12 }} /> : null}
        </Bagian>

        {hasil ? (
          <Bagian>
            <View style={[s.hasil, { borderLeftColor: hasil.menang ? warna.sukses : warna.garis }]}>
              <Text style={[s.judulHasil, { color: hasil.menang ? warna.sukses : warna.teks }]}>
                {hasil.menang ? 'Selamat, Anda menang' : hasil.terjual ? 'Lelang selesai' : 'Lot tidak terjual'}
              </Text>
              {hasil.terjual ? <Info label="Harga akhir" nilai={`${formatRupiah(hasil.harga_akhir_per_kg)}/kg`} tebal /> : null}
              {hasil.menang ? (
                <>
                  <Info label="Nomor transaksi" nilai={hasil.kode_transaksi} tebal />
                  <Info label="Total tagihan" nilai={formatRupiah(hasil.total_tagihan)} besar garis />
                  <Text style={s.catatan}>
                    Lunasi sebelum {hasil.batas_bayar?.slice(0, 16).replace('T', ' ')} melalui saldo aplikasi atau kasir TPI.
                  </Text>
                </>
              ) : hasil.terjual ? (
                <Text style={s.catatan}>Lot dimenangkan peserta lain. Terima kasih telah ikut lelang.</Text>
              ) : (
                <Text style={s.catatan}>Tidak ada tawaran yang masuk sampai sesi ditutup.</Text>
              )}
            </View>
            {hasil.menang ? (
              <Tombol judul="Lihat Tagihan" onPress={() => onLihatTagihan(hasil.id_transaksi)} gaya={{ marginTop: 12 }} />
            ) : null}
          </Bagian>
        ) : (
          (data.mode === 'TURUN' ? <Bagian judul="Ambil harga berjalan">
            <KotakPesan jenis={pesan?.jenis === 'sukses' ? 'sukses' : 'galat'} teks={pesan?.teks} gaya={{ marginBottom: 12 }} />
            <Text style={s.minimal}>Harga saat ini {formatRupiah(data.harga_tertinggi)}/kg</Text>
            <Tombol judul="Ambil Sekarang" onPress={() => setTanyaAmbil(true)} memuat={mengirim} gaya={{ marginTop: 12 }} />
            <Text style={s.catatan}>Pembeli pertama yang menekan tombol akan memenangkan lot pada harga yang tampil.</Text>
          </Bagian> : <Bagian judul="Ajukan tawaran">
            <KotakPesan jenis={pesan?.jenis === 'sukses' ? 'sukses' : 'galat'} teks={pesan?.teks} gaya={{ marginBottom: 12 }} />
            <KolomTeks
              label="Harga per kg (Rp)"
              value={harga}
              onChangeText={(t) => {
                diubahPengguna.current = true;
                setHarga(hanyaAngka(t).slice(0, 8));
                setGalatHarga(null);
              }}
              keyboardType="number-pad"
              galat={galatHarga}
              accessibilityLabel="Harga tawaran per kg"
              gayaWadah={{ marginBottom: 6 }}
            />
            <View style={s.barisMinimal}>
              <Text style={[s.minimal, Number(harga) < data.harga_minimum_berikut && { color: warna.galat }]}>
                Minimal saat ini {formatRupiah(data.harga_minimum_berikut)}/kg
              </Text>
              <Text style={s.perkiraan}>Total {formatRupiah(Math.round(total || 0))}</Text>
            </View>
            <View style={s.cepat}>
              {[1, 2, 5].map((k) => (
                <Pressable key={k} onPress={() => naikkan(k)} style={({ pressed }) => [s.cip, pressed && { backgroundColor: warna.utamaMuda }]}>
                  <Text style={s.teksCip}>{k === 1 ? 'Minimum' : `+${k - 1}x kenaikan`}</Text>
                </Pressable>
              ))}
            </View>
            <Tombol
              judul={`Tawar ${formatRupiah(Number(harga) || 0)}/kg`}
              onPress={periksa}
              memuat={mengirim}
              nonaktif={data.posisi === 'TERTINGGI'}
              gaya={{ marginTop: 12 }}
            />
            {data.posisi === 'TERTINGGI' ? <Text style={s.catatan}>Anda sedang memimpin.</Text> : null}
          </Bagian>)
        )}

        <Bagian judul="Riwayat tawaran">
          <Tabel
            label="Riwayat tawaran"
            kosong="Belum ada tawaran. Jadilah yang pertama."
            data={data.riwayat}
            sorot={(r) => r.tertinggi}
            kolom={[
              { judul: 'Waktu', flex: 0.9, redup: true, render: (r) => r.waktu },
              {
                judul: 'Penawar', flex: 1.4,
                render: (r) => (
                  <Text style={[s.namaTawar, r.penawar === 'Anda' && { fontWeight: '700', color: warna.utama }]} numberOfLines={1}>
                    {r.penawar}
                  </Text>
                ),
              },
              {
                judul: 'Harga/kg', flex: 1.2, kanan: true,
                render: (r) => <Text style={[s.nilaiTawar, r.tertinggi && { color: warna.sukses }]}>{formatRupiah(r.harga_per_kg)}</Text>,
              },
            ]}
          />
        </Bagian>
        <View style={{ paddingHorizontal: 16 }}>
          <Tombol judul="Kembali" jenis={hasil ? 'garis' : 'teks'} onPress={onKembali} />
        </View>
      </FormGulir>

      <DialogKonfirmasi
        tampil={tanyaTawaran}
        judul="Konfirmasi tawaran"
        pesan={`Tawaran Anda ${formatRupiah(Number(harga))}/kg, perkiraan nilai lot ${formatRupiah(Math.round(total))}. Jika menang, Anda wajib melunasi tagihan. Tawaran yang sudah diterima tidak dapat dibatalkan. Lanjutkan?`}
        teksYa="Ya, Tawar"
        onYa={kirim}
        onTidak={() => setTanyaTawaran(false)}
      />
      <DialogKonfirmasi
        tampil={tanyaAmbil}
        judul="Konfirmasi ambil harga"
        pesan={`Anda akan mengambil harga berjalan ${formatRupiah(data.harga_tertinggi)}/kg, dengan perkiraan nilai lot ${formatRupiah(Math.round(Number(data.harga_tertinggi) * lot.berat_aktual))}. Jika berhasil, Anda wajib melunasi tagihan. Lanjutkan?`}
        teksYa="Ya, Ambil"
        onYa={ambil}
        onTidak={() => setTanyaAmbil(false)}
      />
    </View>
  );
}

const s = StyleSheet.create({
  barisLot: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  tengah: { flex: 1, backgroundColor: warna.latar, alignItems: 'center', justifyContent: 'center', padding: 16 },
  isi: { paddingTop: 0, paddingBottom: 32 },
  barisJudul: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: 'center', columnGap: 8 },
  kode: { fontSize: 17, fontWeight: '700', color: warna.teks, fontVariant: ['tabular-nums'] },
  psn: { fontSize: 12, fontWeight: '600', color: warna.sukses },
  jenis: { fontSize: 14, color: warna.teks, marginTop: 2 },
  posisi: { borderRadius: 4, borderLeftWidth: 3, paddingVertical: 10, paddingHorizontal: 12, marginTop: 12 },
  teksPosisi: { fontSize: 14, fontWeight: '700' },
  tawaranSaya: { fontSize: 12, color: warna.teksRedup, marginTop: 2 },
  barisMinimal: { flexDirection: 'row', justifyContent: 'space-between', gap: 8, marginBottom: 10 },
  minimal: { fontSize: 12, fontWeight: '600', color: warna.teksRedup, flexShrink: 1 },
  perkiraan: { fontSize: 12, color: warna.teksRedup },
  cepat: { flexDirection: 'row', gap: 8 },
  cip: {
    flex: 1, minHeight: 38, paddingHorizontal: 6, borderRadius: 999, borderWidth: 1, borderColor: warna.utama,
    alignItems: 'center', justifyContent: 'center', backgroundColor: warna.putih,
  },
  teksCip: { fontSize: 12, fontWeight: '600', color: warna.utama },
  catatan: { fontSize: 12, color: warna.teksRedup, lineHeight: 17, marginTop: 6 },
  hasil: { borderLeftWidth: 3, paddingLeft: 12 },
  judulHasil: { fontSize: 16, fontWeight: '700', marginBottom: 4 },
  namaTawar: { fontSize: 13, color: warna.teks },
  nilaiTawar: { fontSize: 13, fontWeight: '600', color: warna.teks, fontVariant: ['tabular-nums'] },
});

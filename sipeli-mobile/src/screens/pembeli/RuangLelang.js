import { useCallback, useRef, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, Vibration, View } from 'react-native';
import { buatKunci, lelang } from '../../api';
import { DialogKonfirmasi, KolomTeks, KotakPesan, Tombol } from '../../components/Dasar';
import FormGulir from '../../components/FormGulir';
import { BarisRincian, JamMundur } from '../../components/Lelang';
import { formatKg, formatRupiah } from '../../format';
import { usePolling } from '../../polling';
import { ukuran, warna } from '../../tema';
import { hanyaAngka } from '../../validasi';

const POSISI = {
  TERTINGGI: { teks: 'Anda penawar tertinggi', latar: warna.suksesLatar, warna: warna.sukses },
  TERTINGGAL: { teks: 'Anda tertinggal, ajukan tawaran baru', latar: warna.galatLatar, warna: warna.galat },
  BELUM_MENAWAR: { teks: 'Anda belum menawar', latar: warna.abu, warna: warna.teksRedup },
};

/**
 * UC-04 Ruang Lelang Pembeli. Status diminta setiap 1 detik (FR-17).
 * Getar + pesan saat posisi berubah menjadi tertinggal (FR-22).
 * Kunci idempotensi dibuat per tawaran; bila koneksi putus, kirim ulang memakai kunci yang sama (FR-20).
 */
export default function RuangLelang({ idSesi, onKembali, onLihatTagihan }) {
  const [data, setData] = useState(null);
  const [galat, setGalat] = useState(null);
  const [harga, setHarga] = useState('');
  const [galatHarga, setGalatHarga] = useState(null);
  const [pesan, setPesan] = useState(null);
  const [mengirim, setMengirim] = useState(false);
  const [tanyaBesar, setTanyaBesar] = useState(false);
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
  usePolling(muat, 1000, aktif);

  function periksa() {
    setPesan(null);
    const n = Number(harga);
    if (!/^\d+$/.test(harga)) return setGalatHarga('Isi harga per kg dengan angka saja.');
    if (n < data.harga_minimum_berikut) {
      return setGalatHarga(`Tawaran minimal ${formatRupiah(data.harga_minimum_berikut)} per kg.`);
    }
    if (n >= 2 * data.harga_minimum_berikut) return setTanyaBesar(true);
    kirim();
  }

  async function kirim() {
    setTanyaBesar(false);
    const n = Number(harga);
    const kunci = percobaan.current && percobaan.current.harga === n ? percobaan.current.kunci : buatKunci();
    setMengirim(true);
    try {
      const hasil = await lelang.tawar(idSesi, n, kunci);
      percobaan.current = null;
      diubahPengguna.current = false;
      posisiLalu.current = 'TERTINGGI';
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
        <View style={s.kartu}>
          <View style={s.barisJudul}>
            <Text style={s.kode}>{lot.kode_lot}</Text>
            <Text style={s.psn}>{data.kode_pemesanan}</Text>
          </View>
          <Text style={s.jenis}>
            {lot.nama_jenis} <Text style={{ color: warna.aksen }}>Grade {lot.kode_grade}</Text> | {formatKg(lot.berat_aktual)}
          </Text>

          <View style={{ marginTop: 12 }}>
            <JamMundur sisa={data.sisa_detik} status={data.status} perpanjangan={data.jumlah_perpanjangan} maksPerpanjangan={data.maks_perpanjangan} />
          </View>

          <View style={s.hargaKotak}>
            <Text style={s.labelHarga}>{data.harga_tertinggi ? 'Harga tertinggi' : 'Harga pembukaan'}</Text>
            <Text style={s.harga} accessibilityLabel="Harga tertinggi">
              {formatRupiah(data.harga_tertinggi ?? data.harga_pembukaan)}
              <Text style={s.perKg}> /kg</Text>
            </Text>
            <Text style={s.info}>
              {data.jumlah_penawaran} tawaran | {data.jumlah_penawar} penawar | kenaikan {formatRupiah(data.kenaikan_minimum)}
            </Text>
          </View>

          {!hasil ? (
            <View style={[s.posisi, { backgroundColor: pos.latar }]}>
              <Text style={[s.teksPosisi, { color: pos.warna }]}>{pos.teks}</Text>
              {data.tawaran_saya ? <Text style={s.tawaranSaya}>Tawaran terakhir Anda {formatRupiah(data.tawaran_saya)}/kg</Text> : null}
            </View>
          ) : null}
          {galat ? <KotakPesan teks={galat} gaya={{ marginTop: 12 }} /> : null}
        </View>

        {hasil ? (
          <View style={[s.kartu, s.hasil, hasil.menang ? s.hasilMenang : null]}>
            <Text style={[s.judulHasil, { color: hasil.menang ? warna.sukses : warna.teks }]}>
              {hasil.menang ? 'Selamat, Anda menang' : hasil.terjual ? 'Lelang selesai' : 'Lot tidak terjual'}
            </Text>
            {hasil.terjual ? (
              <BarisRincian label="Harga akhir" nilai={`${formatRupiah(hasil.harga_akhir_per_kg)}/kg`} tebal />
            ) : null}
            {hasil.menang ? (
              <>
                <BarisRincian label="Nomor transaksi" nilai={hasil.kode_transaksi} tebal />
                <BarisRincian label="Total tagihan" nilai={formatRupiah(hasil.total_tagihan)} besar />
                <Text style={s.catatan}>Bayar di Kasir TPI dengan nomor transaksi di atas.</Text>
                <Tombol judul="Lihat Tagihan" ikon="dompet" onPress={() => onLihatTagihan(hasil.id_transaksi)} gaya={{ marginTop: 6 }} />
              </>
            ) : hasil.terjual ? (
              <Text style={s.catatan}>Lot dimenangkan peserta lain. Terima kasih telah ikut lelang.</Text>
            ) : (
              <Text style={s.catatan}>Tidak ada tawaran yang masuk sampai sesi ditutup.</Text>
            )}
          </View>
        ) : (
          <View style={s.kartu}>
            <Text style={s.subjudul}>Ajukan Tawaran</Text>
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
            />
            <Text style={[s.minimal, Number(harga) < data.harga_minimum_berikut && { color: warna.galat }]}>
              Minimal saat ini {formatRupiah(data.harga_minimum_berikut)}/kg
            </Text>
            <View style={s.cepat}>
              {[1, 2, 5].map((k) => (
                <Pressable key={k} onPress={() => naikkan(k)} style={({ pressed }) => [s.cip, pressed && { backgroundColor: warna.utamaMuda }]}>
                  <Text style={s.teksCip}>{k === 1 ? 'Minimum' : `+${k - 1}x kenaikan`}</Text>
                </Pressable>
              ))}
            </View>
            <Text style={s.perkiraan}>Perkiraan total: {formatRupiah(Math.round(total || 0))}</Text>
            <Tombol
              judul={`Tawar ${formatRupiah(Number(harga) || 0)}/kg`}
              onPress={periksa}
              memuat={mengirim}
              nonaktif={data.posisi === 'TERTINGGI'}
              gaya={{ marginTop: 10 }}
            />
            {data.posisi === 'TERTINGGI' ? (
              <Text style={s.catatan}>Anda sedang memimpin.</Text>
            ) : null}
          </View>
        )}

        <View style={s.kartu}>
          <Text style={s.subjudul}>Riwayat Tawaran</Text>
          {data.riwayat.length === 0 ? (
            <Text style={s.catatan}>Belum ada tawaran. Jadilah yang pertama.</Text>
          ) : (
            data.riwayat.map((r, i) => (
              <View key={i} style={[s.barisTawar, r.tertinggi && s.barisTertinggi]}>
                <Text style={s.waktu}>{r.waktu}</Text>
                <Text style={[s.namaTawar, r.penawar === 'Anda' && { fontWeight: '700', color: warna.utama }]}>{r.penawar}</Text>
                <Text style={[s.nilaiTawar, r.tertinggi && { color: warna.sukses }]}>{formatRupiah(r.harga_per_kg)}</Text>
              </View>
            ))
          )}
        </View>
        <Tombol judul="Kembali" jenis={hasil ? 'garis' : 'teks'} onPress={onKembali} />
      </FormGulir>

      <DialogKonfirmasi
        tampil={tanyaBesar}
        judul="Tawaran jauh di atas minimum"
        pesan={`Anda akan menawar ${formatRupiah(Number(harga))}/kg (perkiraan total ${formatRupiah(Math.round(total))}). Tawaran yang diterima tidak dapat dibatalkan. Lanjutkan?`}
        teksYa="Ya, Tawar"
        onYa={kirim}
        onTidak={() => setTanyaBesar(false)}
      />
    </View>
  );
}

const s = StyleSheet.create({
  tengah: { flex: 1, backgroundColor: warna.latar, alignItems: 'center', justifyContent: 'center', padding: 16 },
  isi: { padding: 16, paddingBottom: 32, gap: 12 },
  kartu: { backgroundColor: warna.kartu, borderRadius: 6, padding: 16, borderWidth: 1, borderColor: '#E4EBF1' },
  barisJudul: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 },
  kode: { fontSize: 17, fontWeight: '700', color: warna.teks },
  psn: { fontSize: 12, fontWeight: '600', color: warna.sukses },
  jenis: { fontSize: 14, color: warna.teks, fontWeight: '500', marginTop: 4 },
  hargaKotak: { alignItems: 'center', marginTop: 14 },
  labelHarga: { fontSize: 12, fontWeight: '700', color: warna.teksRedup },
  harga: { fontSize: 26, fontWeight: '700', color: warna.teks, marginTop: 2 },
  perKg: { fontSize: 16, fontWeight: '500', color: warna.teksRedup },
  info: { fontSize: 12, color: warna.teksRedup, marginTop: 2, textAlign: 'center' },
  posisi: { borderRadius: ukuran.radius, padding: 12, marginTop: 14, alignItems: 'center' },
  teksPosisi: { fontSize: 14, fontWeight: '700', textAlign: 'center' },
  tawaranSaya: { fontSize: 12, color: warna.teksRedup, marginTop: 2 },
  subjudul: { fontSize: 15, fontWeight: '700', color: warna.teks, marginBottom: 10 },
  minimal: { fontSize: 12, fontWeight: '600', color: warna.teksRedup, marginTop: -8, marginBottom: 10 },
  cepat: { flexDirection: 'row', gap: 8, flexWrap: 'wrap' },
  cip: {
    minHeight: 44, paddingHorizontal: 12, borderRadius: 999, borderWidth: 1, borderColor: warna.utama,
    alignItems: 'center', justifyContent: 'center', backgroundColor: warna.putih,
  },
  teksCip: { fontSize: 13, fontWeight: '600', color: warna.utama },
  perkiraan: { fontSize: 13, color: warna.teksRedup, marginTop: 10 },
  catatan: { fontSize: 12, color: warna.teksRedup, lineHeight: 17, marginTop: 6 },
  hasil: { gap: 8, borderWidth: 1, borderColor: warna.garis },
  hasilMenang: { backgroundColor: warna.suksesLatar, borderColor: warna.sukses },
  judulHasil: { fontSize: 16, fontWeight: '700', textAlign: 'center', marginBottom: 4 },
  barisTawar: {
    flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 9, paddingHorizontal: 8,
    borderBottomWidth: 1, borderBottomColor: '#E4EBF1',
  },
  barisTertinggi: { backgroundColor: warna.suksesLatar, borderRadius: 4 },
  waktu: { fontSize: 12, color: warna.teksRedup, fontVariant: ['tabular-nums'] },
  namaTawar: { flex: 1, fontSize: 14, color: warna.teks },
  nilaiTawar: { fontSize: 14, fontWeight: '600', color: warna.teks },
});

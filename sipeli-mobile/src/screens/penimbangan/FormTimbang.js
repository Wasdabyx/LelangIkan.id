import { useEffect, useRef, useState } from 'react';
import { BackHandler, Pressable, StyleSheet, Text, View } from 'react-native';
import { api } from '../../api';
import { DialogKonfirmasi, KolomTeks, KotakPesan, Tombol } from '../../components/Dasar';
import FormGulir from '../../components/FormGulir';
import { Bagian, Segmen, Tabel } from '../../components/Tata';
import Ikon from '../../components/Ikon';
import { formatAngka, formatKg, formatRupiah, keAngka } from '../../format';
import { ukuran, warna } from '../../tema';

const NAMA_TIMBANGAN = 'Timbangan Simulasi';
const LAMA_STABIL = 1500; // DPPL UC-02: berat dianggap stabil bila tidak berubah 1,5 detik

/**
 * Timbangan SIMULASI (pengganti timbangan digital Bluetooth).
 * Saat keranjang diletakkan: angka naik, bergoyang, lalu diam di satu nilai.
 * "Stabil" = angka tidak berubah selama 1,5 detik.
 */
function useTimbanganSimulasi() {
  const [bacaan, setBacaan] = useState(0);
  const [stabil, setStabil] = useState(false);
  const [adaBeban, setAdaBeban] = useState(false);
  const target = useRef(0);
  const langkah = useRef(0);
  const nilaiTerakhir = useRef(0);
  const waktuBerubah = useRef(Date.now());

  useEffect(() => {
    const id = setInterval(() => {
      let nilai = 0;
      if (target.current > 0) {
        langkah.current += 1;
        const n = langkah.current;
        if (n === 1) nilai = target.current * 0.35;
        else if (n === 2) nilai = target.current * 0.8;
        else if (n <= 7) nilai = target.current + (Math.random() - 0.5) * target.current * 0.06 * ((8 - n) / 5);
        else nilai = target.current;
        nilai = Math.max(0, Math.round(nilai * 20) / 20); // ketelitian 0,05 kg
      }
      if (nilai !== nilaiTerakhir.current) {
        nilaiTerakhir.current = nilai;
        waktuBerubah.current = Date.now();
        setBacaan(nilai);
      }
      setStabil(nilai > 0 && Date.now() - waktuBerubah.current >= LAMA_STABIL);
    }, 200);
    return () => clearInterval(id);
  }, []);

  function letakkan(kg) {
    target.current = Math.round(kg * 20) / 20;
    langkah.current = 0;
    setAdaBeban(true);
  }
  function angkat() {
    target.current = 0;
    setAdaBeban(false);
  }
  return { bacaan, stabil, adaBeban, letakkan, angkat };
}

/**
 * Alur utama UC-02 langkah (3)-(6): timbang per keranjang, cek selisih, isi harga, buat lot
 */
export default function FormTimbang({ kedatangan, baris, onBatal, onSelesai }) {
  const timbangan = useTimbanganSimulasi();
  const [sumber, setSumber] = useState('timbangan'); // 'timbangan' | 'manual'
  const [keranjang, setKeranjang] = useState([]); // [{ berat, manual }]
  const [teksManual, setTeksManual] = useState('');
  const [galatManual, setGalatManual] = useState(null);
  const [alasan, setAlasan] = useState('');
  const usulan = baris.usulan_harga || {};
  const [harga, setHarga] = useState(usulan.harga ? String(Math.round(usulan.harga)) : '');
  const [galat, setGalat] = useState({});
  const [pesan, setPesan] = useState(null);
  const [mencobaSimpan, setMencobaSimpan] = useState(false);
  const [menyimpan, setMenyimpan] = useState(false);
  const [tanyaBatal, setTanyaBatal] = useState(false);
  const [tanyaUlang, setTanyaUlang] = useState(false);

  // ---- Perhitungan ----
  const estimasi = Number(baris.berat_estimasi);
  const ambang = Number(kedatangan.ambang_selisih) || 15;
  const total = Math.round(keranjang.reduce((n, k) => n + k.berat, 0) * 100) / 100;
  const selisih = estimasi > 0 && total > 0 ? Math.round((Math.abs(total - estimasi) / estimasi) * 10000) / 100 : 0;
  const lewatAmbang = total > 0 && selisih > ambang;
  const lengkap = keranjang.length >= baris.jumlah_keranjang;
  const tampilPanelSelisih = lewatAmbang && (lengkap || total > estimasi || mencobaSimpan);
  const adaManual = keranjang.some((k) => k.manual) || sumber === 'manual';
  const perluAlasan = adaManual || tampilPanelSelisih;
  const hargaAngka = keAngka(harga);
  const hargaValid = harga.trim() !== '' && !isNaN(hargaAngka) && hargaAngka >= 100;

  // Tombol kembali HP: minta konfirmasi bila sudah ada keranjang tercatat
  const jumlahRef = useRef(0);
  jumlahRef.current = keranjang.length;
  useEffect(() => {
    const sub = BackHandler.addEventListener('hardwareBackPress', () => {
      mintaBatal();
      return true;
    });
    return () => sub.remove();
  }, []);

  function mintaBatal() {
    if (jumlahRef.current > 0) setTanyaBatal(true);
    else onBatal();
  }

  // ---- Timbangan ----
  function letakkanKeranjang() {
    const rata = estimasi / Math.max(1, baris.jumlah_keranjang);
    timbangan.letakkan(rata * (0.9 + Math.random() * 0.2)); // +-10% dari rata-rata estimasi
  }

  function catatDariTimbangan() {
    if (!timbangan.stabil || timbangan.bacaan <= 0) return;
    setKeranjang((d) => [...d, { berat: timbangan.bacaan, manual: false }]);
    timbangan.angkat();
    setPesan(null);
  }

  function tambahManual() {
    const n = keAngka(teksManual);
    if (isNaN(n) || n <= 0) {
      setGalatManual('Berat harus angka lebih dari 0 kg. Contoh: 30,5');
      return;
    }
    if (n > 9999.99) {
      setGalatManual('Berat keranjang terlalu besar.');
      return;
    }
    setKeranjang((d) => [...d, { berat: Math.round(n * 100) / 100, manual: true }]);
    setTeksManual('');
    setGalatManual(null);
    setPesan(null);
  }

  function gantiSumber(baru) {
    if (baru === sumber) return;
    timbangan.angkat();
    setSumber(baru);
    setGalatManual(null);
  }

  function hapusKeranjang(i) {
    setKeranjang((d) => d.filter((_, j) => j !== i));
  }

  function timbangUlang() {
    setTanyaUlang(false);
    setKeranjang([]);
    timbangan.angkat();
    setMencobaSimpan(false);
    setGalat({});
    setPesan(null);
  }

  // ---- Simpan ----
  async function simpan() {
    setPesan(null);
    setMencobaSimpan(true);
    const g = {};
    if (keranjang.length === 0) {
      setPesan('Catat minimal satu keranjang sebelum membuat lot.');
      return;
    }
    const manualTercatat = keranjang.some((k) => k.manual);
    if (manualTercatat && !alasan.trim()) g.alasan = 'Alasan input manual wajib diisi.';
    else if (lewatAmbang && !alasan.trim())
      g.alasan = `Selisih ${formatAngka(selisih, 1)}% melebihi ${ambang}%. Isi alasan konfirmasi atau Timbang Ulang.`;
    if (harga.trim() !== '' && !hargaValid) g.harga = 'Harga pembukaan minimal Rp100 per kg (angka saja).';
    setGalat(g);
    if (Object.keys(g).length) {
      setPesan('Periksa kembali isian yang ditandai merah.');
      return;
    }

    const semuaManual = keranjang.every((k) => k.manual);
    setMenyimpan(true);
    try {
      const hasil = await api.post('/lot', {
        id_tangkapan: baris.id_tangkapan,
        berat_keranjang: keranjang.map((k) => k.berat),
        timbang_manual: manualTercatat,
        perangkat_timbang: semuaManual ? 'Input Manual' : manualTercatat ? `${NAMA_TIMBANGAN} + Manual` : NAMA_TIMBANGAN,
        alasan: alasan.trim() || null,
        harga_pembukaan: hargaValid ? hargaAngka : null,
      });
      onSelesai(hasil.data);
    } catch (e) {
      if (e.status === 401) return;
      const gk = e.galatKolom || {};
      setGalat({ alasan: gk.alasan, harga: gk.harga_pembukaan });
      setPesan(gk.alasan || gk.harga_pembukaan ? 'Periksa kembali isian yang ditandai merah.' : e.message);
      setMenyimpan(false);
    }
  }

  // ---- Tampilan ----
  const statusBacaan = timbangan.stabil
    ? { teks: 'Stabil', latar: '#D1FADF', warna: warna.sukses }
    : timbangan.adaBeban
      ? { teks: 'Membaca...', latar: '#FEF0C7', warna: warna.peringatan }
      : { teks: 'Kosong', latar: '#E4EBF1', warna: '#344054' };

  const labelAlasan =
    keranjang.some((k) => k.manual) || sumber === 'manual'
      ? tampilPanelSelisih
        ? 'Alasan input manual dan selisih berat (wajib)'
        : 'Alasan input manual (wajib)'
      : 'Alasan konfirmasi selisih berat (wajib)';

  return (
    <View style={{ flex: 1 }}>
      <View style={s.bilahKembali}>
        <Pressable onPress={mintaBatal} style={s.tombolKembali} accessibilityRole="button">
          <Ikon nama="kembali" ukuran={20} warna={warna.utama} />
          <Text style={s.teksKembali}>{kedatangan.nomor_kedatangan}</Text>
        </Pressable>
      </View>

      <FormGulir gayaIsi={s.isi}>
        <Bagian>
          <Text style={s.judul}>
            {baris.nama_jenis} <Text style={s.grade}>Grade {baris.kode_grade}</Text>
          </Text>
          <Text style={s.redup}>
            {kedatangan.nama_kapal} | Estimasi {formatKg(estimasi)} | {baris.jumlah_keranjang} keranjang
          </Text>
          <View style={{ marginTop: 12 }}>
            <Segmen
              label="Sumber berat"
              pilihan={[
                { kunci: 'timbangan', judul: 'Timbangan' },
                { kunci: 'manual', judul: 'Input Manual' },
              ]}
              nilai={sumber}
              onGanti={gantiSumber}
            />
          </View>

          {sumber === 'timbangan' ? (
            <View>
              {/* Layar timbangan: angka besar >= 40pt (DPPL UC-02) */}
              <View style={s.kartuBerat}>
                <View style={s.barisStatus}>
                  <View style={s.titikHijau} />
                  <Text style={s.teksTerhubung} numberOfLines={1}>
                    Terhubung | {NAMA_TIMBANGAN}
                  </Text>
                  <Text style={[s.teksChip, { color: timbangan.stabil ? '#6CE9A6' : '#FEC84B' }]}>{statusBacaan.teks}</Text>
                </View>
                <View style={s.barisAngka}>
                  <Text style={s.angkaBerat} accessibilityLabel={`Berat ${formatAngka(timbangan.bacaan)} kilogram`}>
                    {formatAngka(timbangan.bacaan)}
                  </Text>
                  <Text style={s.satuan}>kg</Text>
                </View>
                <Text style={s.petunjuk}>
                  {timbangan.stabil
                    ? 'Berat stabil. Tekan Catat Keranjang.'
                    : timbangan.adaBeban
                      ? 'Tunggu angka berhenti berubah...'
                      : `Letakkan keranjang ke-${keranjang.length + 1} di atas timbangan.`}
                </Text>
              </View>

              {timbangan.adaBeban ? (
                <View style={s.barisTombol}>
                  <Tombol
                    judul="Catat Keranjang"
                    onPress={catatDariTimbangan}
                    nonaktif={!timbangan.stabil}
                    gaya={{ flex: 1 }}
                  />
                  <Tombol judul="Angkat" jenis="garis" onPress={timbangan.angkat} gaya={{ minWidth: 96 }} />
                </View>
              ) : (
                <Tombol
                  judul="Letakkan Keranjang (Simulasi)"
                  jenis="garis"
                  onPress={letakkanKeranjang}
                  gaya={{ marginTop: 10 }}
                />
              )}
            </View>
          ) : (
            <View style={{ marginTop: 12 }}>
              <Text style={s.catatanManual}>
                Gunakan input manual hanya bila timbangan tidak dapat dipakai. Alasan wajib diisi dan tercatat.
              </Text>
              <View style={s.barisManual}>
                <KolomTeks
                  label={`Berat keranjang ke-${keranjang.length + 1} (kg)`}
                  gayaWadah={{ flex: 1, marginBottom: 0 }}
                  value={teksManual}
                  onChangeText={(t) => {
                    setTeksManual(t);
                    setGalatManual(null);
                  }}
                  placeholder="Contoh: 30,5"
                  keyboardType="decimal-pad"
                  returnKeyType="done"
                  onSubmitEditing={tambahManual}
                />
                <Tombol judul="Tambah" onPress={tambahManual} gaya={{ minHeight: ukuran.tinggiInput, minWidth: 88 }} />
              </View>
              {galatManual ? <Text style={s.teksGalat}>{galatManual}</Text> : null}
            </View>
          )}
        </Bagian>

        {/* Daftar keranjang */}
        <Bagian
          judul="Keranjang tercatat"
          kanan={
            <Text style={[s.hitungan, lengkap && { color: warna.sukses }]}>
              {keranjang.length} dari {baris.jumlah_keranjang}
            </Text>
          }
        >
          <Tabel
            label="Daftar keranjang"
            kosong="Belum ada keranjang yang dicatat."
            data={keranjang}
            kolom={[
              { judul: 'No', flex: 0.5, render: (_, i) => String(i + 1), redup: true },
              { judul: 'Sumber', flex: 1.2, render: (k) => (k.manual ? 'Manual' : 'Timbangan'), redup: true },
              { judul: 'Berat', flex: 1.3, kanan: true, tebal: true, render: (k) => formatKg(k.berat) },
              {
                judul: '',
                flex: 0.6,
                kanan: true,
                render: (_, i) => (
                  <Pressable
                    onPress={() => hapusKeranjang(i)}
                    style={s.tombolHapus}
                    accessibilityLabel={`Hapus keranjang ${i + 1}`}
                    hitSlop={6}
                  >
                    <Ikon nama="hapus" ukuran={18} warna={warna.galat} />
                  </Pressable>
                ),
              },
            ]}
          />
          <View style={s.barisTotal}>
            <View style={{ flex: 1 }}>
              <Text style={s.labelKecil}>Berat aktual</Text>
              <Text style={s.total}>{formatKg(total)}</Text>
            </View>
            <View style={{ alignItems: 'flex-end' }}>
              <Text style={s.labelKecil}>Selisih dari estimasi</Text>
              <Text style={[s.selisih, lewatAmbang && tampilPanelSelisih && { color: warna.galat }]}>
                {total > 0 ? `${total >= estimasi ? '+' : '-'}${formatAngka(selisih, 1)}%` : '-'}
              </Text>
            </View>
          </View>
          {!lengkap && keranjang.length > 0 && !tampilPanelSelisih ? (
            <Text style={s.info}>Masih {baris.jumlah_keranjang - keranjang.length} keranjang lagi sesuai catatan pendataan.</Text>
          ) : null}
        </Bagian>

        {/* Panel selisih > ambang (ALG-08, alur A2) */}
        {tampilPanelSelisih ? (
          <View style={s.panelSelisih}>
            <Text style={s.judulSelisih}>
              Selisih {formatAngka(selisih, 1)}% melebihi batas {ambang}%
            </Text>
            <Text style={s.teksSelisih}>
              Estimasi {formatKg(estimasi)}, aktual {formatKg(total)}. Timbang ulang, atau konfirmasi berat ini
              dengan mengisi alasan. Lot akan ditandai perlu verifikasi.
            </Text>
            <Tombol
              judul="Timbang Ulang"
              jenis="bahaya"
              kecil
              onPress={() => setTanyaUlang(true)}
              gaya={{ marginTop: 10, alignSelf: 'flex-start', minWidth: 140 }}
            />
          </View>
        ) : null}

        {perluAlasan ? (
          <View style={s.wadahAlasan}>
            <KolomTeks
              label={labelAlasan}
              value={alasan}
              onChangeText={(t) => {
                setAlasan(t);
                setGalat((g) => ({ ...g, alasan: null }));
              }}
              placeholder="Contoh: timbangan rusak / es mencair"
              maxLength={200}
              galat={galat.alasan}
              gayaWadah={{ marginBottom: 0 }}
            />
          </View>
        ) : null}

        {/* Harga pembukaan (ALG-06) */}
        <Bagian judul="Harga pembukaan">
          {usulan.harga ? (
            <View style={s.kotakUsulan}>
              <View style={{ flex: 1 }}>
                <Text style={s.teksUsulan}>
                  Usulan sistem: <Text style={{ fontWeight: '700' }}>{formatRupiah(usulan.harga)}/kg</Text>
                </Text>
                <Text style={s.keteranganUsulan}>{usulan.keterangan}</Text>
              </View>
              {keAngka(harga) !== Number(usulan.harga) ? (
                <Pressable onPress={() => setHarga(String(Math.round(usulan.harga)))} style={s.tombolUsulan}>
                  <Text style={s.teksTombolUsulan}>Pakai harga usulan</Text>
                </Pressable>
              ) : null}
            </View>
          ) : (
            <Text style={[s.keteranganUsulan, { marginBottom: 10 }]}>
              {usulan.keterangan || 'Belum ada riwayat harga.'} Isi harga secara manual.
            </Text>
          )}
          <KolomTeks
            label="Harga per kg (Rp)"
            value={harga}
            onChangeText={(t) => {
              setHarga(t.replace(/[^0-9]/g, ''));
              setGalat((g) => ({ ...g, harga: null }));
            }}
            placeholder="Contoh: 28000"
            keyboardType="number-pad"
            maxLength={9}
            galat={galat.harga}
            gayaWadah={{ marginBottom: 4 }}
          />
          {hargaValid ? (
            <Text style={s.info}>
              {formatRupiah(hargaAngka)} per kg
              {total > 0 ? ` | perkiraan nilai lot ${formatRupiah(hargaAngka * total)}` : ''}
            </Text>
          ) : harga.trim() === '' ? (
            <KotakPesan
              jenis="galat"
              teks="Tanpa harga, lot disimpan sebagai DRAFT HARGA dan belum bisa dilelang."
              gaya={{ marginTop: 4 }}
            />
          ) : null}
        </Bagian>

        <KotakPesan teks={pesan} gaya={{ marginHorizontal: 16, marginTop: 10 }} />
      </FormGulir>

      {/* Tombol utama dalam jangkauan ibu jari */}
      <View style={s.bilahBawah}>
        <View style={s.barisRingkasBawah}>
          <Text style={s.ringkasBawah}>{keranjang.length} keranjang</Text>
          <Text style={s.ringkasBawahTebal}>{formatKg(total)}</Text>
        </View>
        <Tombol
          judul="Buat Lot dan Cetak"
          onPress={simpan}
          memuat={menyimpan}
          nonaktif={keranjang.length === 0}
        />
      </View>

      <DialogKonfirmasi
        tampil={tanyaBatal}
        judul="Batalkan penimbangan?"
        pesan="Berat keranjang yang sudah dicatat akan hilang."
        teksYa="Ya, Batalkan"
        teksTidak="Lanjut Menimbang"
        bahaya
        onYa={() => {
          setTanyaBatal(false);
          onBatal();
        }}
        onTidak={() => setTanyaBatal(false)}
      />
      <DialogKonfirmasi
        tampil={tanyaUlang}
        judul="Timbang ulang?"
        pesan="Semua keranjang yang tercatat dihapus dan penimbangan dimulai dari awal."
        teksYa="Ya, Timbang Ulang"
        bahaya
        onYa={timbangUlang}
        onTidak={() => setTanyaUlang(false)}
      />
    </View>
  );
}

const s = StyleSheet.create({
  bilahKembali: {
    backgroundColor: warna.putih, borderBottomWidth: 1, borderBottomColor: warna.garisHalus,
    paddingHorizontal: 8, paddingVertical: 2,
  },
  tombolKembali: { flexDirection: 'row', alignItems: 'center', gap: 4, minHeight: 44, paddingHorizontal: 8, alignSelf: 'flex-start' },
  teksKembali: { color: warna.utama, fontSize: ukuran.hurufNormal, fontWeight: '600' },
  isi: { paddingBottom: 24 },
  judul: { fontSize: 18, fontWeight: '700', color: warna.teks },
  grade: { fontWeight: '400', color: warna.teksRedup },
  redup: { fontSize: 12, color: warna.teksRedup, marginTop: 2 },
  kartuBerat: { backgroundColor: '#102A43', borderRadius: 6, paddingHorizontal: 14, paddingVertical: 12, marginTop: 12 },
  barisStatus: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  titikHijau: { width: 8, height: 8, borderRadius: 4, backgroundColor: '#32D583' },
  teksTerhubung: { flex: 1, color: '#BCCCDC', fontSize: 12 },
  teksChip: { fontSize: 12, fontWeight: '700' },
  barisAngka: { flexDirection: 'row', alignItems: 'flex-end', justifyContent: 'center', marginVertical: 6 },
  angkaBerat: { color: warna.putih, fontSize: 60, fontWeight: '700', fontVariant: ['tabular-nums'] },
  satuan: { color: '#BCCCDC', fontSize: 18, fontWeight: '600', marginLeft: 8, marginBottom: 12 },
  petunjuk: { color: '#BCCCDC', fontSize: 13, textAlign: 'center' },
  barisTombol: { flexDirection: 'row', gap: 8, marginTop: 10 },
  catatanManual: { fontSize: 12, color: warna.teksRedup, marginBottom: 10, lineHeight: 17 },
  barisManual: { flexDirection: 'row', gap: 8, alignItems: 'flex-end' },
  teksGalat: { color: warna.galat, fontSize: 12, marginTop: 6 },
  hitungan: { fontSize: 13, fontWeight: '700', color: warna.teksRedup },
  tombolHapus: { width: 32, height: 28, alignItems: 'flex-end', justifyContent: 'center' },
  barisTotal: { flexDirection: 'row', alignItems: 'flex-end', marginTop: 10 },
  labelKecil: { fontSize: 12, color: warna.teksRedup },
  total: { fontSize: 22, fontWeight: '700', color: warna.utama, marginTop: 2, fontVariant: ['tabular-nums'] },
  selisih: { fontSize: 16, fontWeight: '700', color: warna.teks, marginTop: 2 },
  info: { fontSize: 12, color: warna.teksRedup, marginTop: 6 },
  panelSelisih: {
    backgroundColor: warna.putih, borderLeftWidth: 3, borderLeftColor: warna.galat,
    paddingHorizontal: 14, paddingVertical: 12, marginTop: 8,
    borderTopWidth: 1, borderBottomWidth: 1, borderColor: warna.garisHalus,
  },
  judulSelisih: { fontSize: 15, fontWeight: '700', color: warna.galat },
  teksSelisih: { fontSize: 13, color: warna.teks, marginTop: 4, lineHeight: 18 },
  wadahAlasan: { backgroundColor: warna.putih, paddingHorizontal: 16, paddingVertical: 12, borderBottomWidth: 1, borderColor: warna.garisHalus },
  kotakUsulan: {
    flexDirection: 'row', alignItems: 'center', gap: 8, paddingBottom: 10, marginBottom: 10,
    borderBottomWidth: 1, borderBottomColor: warna.garisHalus,
  },
  teksUsulan: { fontSize: 14, color: warna.teks },
  keteranganUsulan: { fontSize: 12, color: warna.teksRedup, marginTop: 2 },
  tombolUsulan: { minHeight: 36, justifyContent: 'center' },
  teksTombolUsulan: { color: warna.utama, fontWeight: '600', fontSize: 13 },
  bilahBawah: {
    backgroundColor: warna.putih, borderTopWidth: 1, borderTopColor: warna.garisHalus,
    paddingHorizontal: 16, paddingTop: 8, paddingBottom: 12,
  },
  barisRingkasBawah: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 6 },
  ringkasBawah: { fontSize: 13, color: warna.teksRedup },
  ringkasBawahTebal: { fontSize: 13, color: warna.teks, fontWeight: '700' },
});

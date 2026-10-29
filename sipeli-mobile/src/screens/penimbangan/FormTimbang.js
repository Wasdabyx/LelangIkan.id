import { useEffect, useRef, useState } from 'react';
import { BackHandler, Pressable, StyleSheet, Text, View } from 'react-native';
import { api } from '../../api';
import { DialogKonfirmasi, KolomTeks, KotakPesan, Tombol } from '../../components/Dasar';
import FormGulir from '../../components/FormGulir';
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
          <Ikon nama="kembali" ukuran={22} warna={warna.utama} />
          <Text style={s.teksKembali}>{kedatangan.nomor_kedatangan}</Text>
        </Pressable>
      </View>

      <FormGulir gayaIsi={s.isi}>
        {/* Baris yang ditimbang */}
        <Text style={s.judul}>
          {baris.nama_jenis} <Text style={s.grade}>- Grade {baris.kode_grade}</Text>
        </Text>
        <Text style={s.redup}>
          {kedatangan.nama_kapal} - Estimasi {formatKg(estimasi)} - {baris.jumlah_keranjang} keranjang
        </Text>

        {/* Pilihan sumber berat */}
        <View style={s.segmen}>
          {[
            ['timbangan', 'Timbangan', 'timbangan'],
            ['manual', 'Input Manual', 'pensil'],
          ].map(([kunci, judul, ikon]) => {
            const aktif = sumber === kunci;
            return (
              <Pressable
                key={kunci}
                onPress={() => gantiSumber(kunci)}
                style={[s.pilihSegmen, aktif && s.pilihSegmenAktif]}
                accessibilityRole="button"
                accessibilityState={{ selected: aktif }}
              >
                <Ikon nama={ikon} ukuran={20} warna={aktif ? warna.putih : warna.utama} />
                <Text style={[s.teksSegmen, aktif && { color: warna.putih }]}>{judul}</Text>
              </Pressable>
            );
          })}
        </View>

        {sumber === 'timbangan' ? (
          <View>
            {/* Kartu berat: angka besar >= 40pt (DPPL UC-02) */}
            <View style={s.kartuBerat}>
              <View style={s.barisStatus}>
                <View style={s.titikHijau} />
                <Text style={s.teksTerhubung} numberOfLines={1}>
                  Terhubung - {NAMA_TIMBANGAN}
                </Text>
                <View style={[s.chip, { backgroundColor: statusBacaan.latar }]}>
                  <Text style={[s.teksChip, { color: statusBacaan.warna }]}>{statusBacaan.teks}</Text>
                </View>
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
                  ikon="centang"
                  onPress={catatDariTimbangan}
                  nonaktif={!timbangan.stabil}
                  gaya={{ flex: 1 }}
                />
                <Tombol judul="Angkat" jenis="garis" onPress={timbangan.angkat} gaya={{ minWidth: 100 }} />
              </View>
            ) : (
              <Tombol
                judul="Letakkan Keranjang (Simulasi)"
                ikon="timbangan"
                jenis="garis"
                onPress={letakkanKeranjang}
                gaya={{ marginTop: 12 }}
              />
            )}
          </View>
        ) : (
          <View style={s.kartuManual}>
            <KotakPesan
              jenis="info"
              teks="Gunakan input manual hanya bila timbangan tidak dapat dipakai. Alasan wajib diisi dan tercatat."
              gaya={{ marginBottom: 12 }}
            />
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
              <Tombol judul="Tambah" ikon="tambah" onPress={tambahManual} gaya={{ minHeight: ukuran.tinggiInput }} />
            </View>
            {galatManual ? <Text style={s.teksGalat}>{galatManual}</Text> : null}
          </View>
        )}

        {/* Daftar keranjang */}
        <View style={s.kartu}>
          <View style={s.barisJudulKartu}>
            <Text style={s.judulKartu}>Keranjang tercatat</Text>
            <Text style={[s.hitungan, lengkap && { color: warna.sukses }]}>
              {keranjang.length} dari {baris.jumlah_keranjang}
            </Text>
          </View>
          {keranjang.length === 0 ? (
            <Text style={s.kosong}>Belum ada keranjang yang dicatat.</Text>
          ) : (
            keranjang.map((k, i) => (
              <View key={i} style={s.barisKeranjang}>
                <Text style={s.namaKeranjang}>Keranjang {i + 1}</Text>
                {k.manual ? (
                  <View style={s.tagManual}>
                    <Text style={s.teksTagManual}>Manual</Text>
                  </View>
                ) : null}
                <Text style={s.beratKeranjang}>{formatKg(k.berat)}</Text>
                <Pressable
                  onPress={() => hapusKeranjang(i)}
                  style={s.tombolHapus}
                  accessibilityLabel={`Hapus keranjang ${i + 1}`}
                >
                  <Ikon nama="hapus" ukuran={20} warna={warna.galat} />
                </Pressable>
              </View>
            ))
          )}
          <View style={s.garis} />
          <View style={s.barisTotal}>
            <View style={{ flex: 1 }}>
              <Text style={s.labelKecil}>BERAT AKTUAL</Text>
              <Text style={s.total}>{formatKg(total)}</Text>
            </View>
            <View style={{ alignItems: 'flex-end' }}>
              <Text style={s.labelKecil}>SELISIH</Text>
              <Text style={[s.selisih, lewatAmbang && tampilPanelSelisih && { color: warna.galat }]}>
                {total > 0 ? `${total >= estimasi ? '+' : '-'}${formatAngka(selisih, 1)}%` : '-'}
              </Text>
            </View>
          </View>
          {!lengkap && keranjang.length > 0 && !tampilPanelSelisih ? (
            <Text style={s.info}>Masih {baris.jumlah_keranjang - keranjang.length} keranjang lagi sesuai catatan pendataan.</Text>
          ) : null}
        </View>

        {/* Panel selisih > ambang (ALG-08, alur A2) */}
        {tampilPanelSelisih ? (
          <View style={s.panelSelisih}>
            <View style={{ flexDirection: 'row', gap: 10 }}>
              <Ikon nama="peringatan" ukuran={24} warna={warna.galat} />
              <View style={{ flex: 1 }}>
                <Text style={s.judulSelisih}>
                  Selisih {formatAngka(selisih, 1)}% melebihi batas {ambang}%
                </Text>
                <Text style={s.teksSelisih}>
                  Estimasi {formatKg(estimasi)}, aktual {formatKg(total)}. Timbang ulang, atau konfirmasi berat ini
                  dengan mengisi alasan. Lot akan ditandai perlu verifikasi.
                </Text>
              </View>
            </View>
            <Tombol
              judul="Timbang Ulang"
              ikon="muatUlang"
              jenis="bahaya"
              onPress={() => setTanyaUlang(true)}
              gaya={{ marginTop: 12 }}
            />
          </View>
        ) : null}

        {perluAlasan ? (
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
          />
        ) : null}

        {/* Harga pembukaan (ALG-06) */}
        <View style={s.kartu}>
          <Text style={s.judulKartu}>Harga pembukaan</Text>
          {usulan.harga ? (
            <View style={s.kotakUsulan}>
              <Text style={s.teksUsulan}>
                Usulan sistem: <Text style={{ fontWeight: '800' }}>{formatRupiah(usulan.harga)}/kg</Text>
              </Text>
              <Text style={s.keteranganUsulan}>{usulan.keterangan}</Text>
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
            gayaWadah={{ marginBottom: 8 }}
          />
          {hargaValid ? (
            <Text style={s.info}>
              {formatRupiah(hargaAngka)} per kg
              {total > 0 ? ` - perkiraan nilai lot ${formatRupiah(hargaAngka * total)}` : ''}
            </Text>
          ) : harga.trim() === '' ? (
            <KotakPesan
              jenis="galat"
              teks="Tanpa harga, lot disimpan sebagai DRAFT HARGA dan belum bisa dilelang."
              gaya={{ marginTop: 4 }}
            />
          ) : null}
        </View>

        <KotakPesan teks={pesan} gaya={{ marginBottom: 12 }} />
      </FormGulir>

      {/* Tombol utama dalam jangkauan ibu jari */}
      <View style={s.bilahBawah}>
        <Text style={s.ringkasBawah}>
          {keranjang.length} keranjang - {formatKg(total)}
        </Text>
        <Tombol
          judul="Buat Lot dan Cetak"
          ikon="cetak"
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
    backgroundColor: warna.putih, borderBottomWidth: 1, borderBottomColor: '#E4EBF1',
    paddingHorizontal: 8, paddingVertical: 4,
  },
  tombolKembali: { flexDirection: 'row', alignItems: 'center', gap: 4, minHeight: 48, paddingHorizontal: 8, alignSelf: 'flex-start' },
  teksKembali: { color: warna.utama, fontSize: ukuran.hurufNormal, fontWeight: '700' },
  isi: { padding: 16, paddingBottom: 24 },
  judul: { fontSize: 22, fontWeight: '800', color: warna.teks },
  grade: { fontWeight: '600', color: warna.teksRedup },
  redup: { fontSize: 14, color: warna.teksRedup, marginTop: 2 },
  segmen: {
    flexDirection: 'row', backgroundColor: warna.putih, borderRadius: 12, padding: 4,
    borderWidth: 1.5, borderColor: warna.garis, marginTop: 14, marginBottom: 12,
  },
  pilihSegmen: {
    flex: 1, flexDirection: 'row', gap: 6, alignItems: 'center', justifyContent: 'center',
    minHeight: 48, borderRadius: 9,
  },
  pilihSegmenAktif: { backgroundColor: warna.utama },
  teksSegmen: { fontSize: ukuran.hurufNormal, fontWeight: '700', color: warna.utama },
  kartuBerat: { backgroundColor: warna.teks, borderRadius: 16, padding: 16 },
  barisStatus: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  titikHijau: { width: 12, height: 12, borderRadius: 6, backgroundColor: '#32D583' },
  teksTerhubung: { flex: 1, color: '#D9E8F0', fontSize: 14, fontWeight: '600' },
  chip: { borderRadius: 999, paddingHorizontal: 12, paddingVertical: 4 },
  teksChip: { fontSize: 14, fontWeight: '800' },
  barisAngka: { flexDirection: 'row', alignItems: 'flex-end', justifyContent: 'center', marginVertical: 8 },
  angkaBerat: { color: warna.putih, fontSize: 60, fontWeight: '800', fontVariant: ['tabular-nums'] },
  satuan: { color: '#D9E8F0', fontSize: 24, fontWeight: '700', marginLeft: 8, marginBottom: 12 },
  petunjuk: { color: '#D9E8F0', fontSize: 15, textAlign: 'center' },
  barisTombol: { flexDirection: 'row', gap: 10, marginTop: 12 },
  kartuManual: {
    backgroundColor: warna.putih, borderRadius: 14, padding: 14,
    borderWidth: 1, borderColor: '#E4EBF1',
  },
  barisManual: { flexDirection: 'row', gap: 10, alignItems: 'flex-end' },
  teksGalat: { color: warna.galat, fontSize: 14, marginTop: 6, fontWeight: '600' },
  kartu: {
    backgroundColor: warna.putih, borderRadius: 14, padding: 14, marginTop: 14, marginBottom: 14,
    borderWidth: 1, borderColor: '#E4EBF1',
  },
  barisJudulKartu: { flexDirection: 'row', alignItems: 'center', marginBottom: 8 },
  judulKartu: { flex: 1, fontSize: ukuran.hurufSedang, fontWeight: '800', color: warna.teks, marginBottom: 4 },
  hitungan: { fontSize: 15, fontWeight: '800', color: warna.teksRedup },
  kosong: { fontSize: 15, color: warna.teksRedup, paddingVertical: 8 },
  barisKeranjang: {
    flexDirection: 'row', alignItems: 'center', gap: 8, minHeight: 48,
    borderBottomWidth: 1, borderBottomColor: '#EEF2F6',
  },
  namaKeranjang: { fontSize: ukuran.hurufNormal, color: warna.teks },
  tagManual: { backgroundColor: warna.peringatanLatar, borderRadius: 6, paddingHorizontal: 8, paddingVertical: 2 },
  teksTagManual: { color: warna.peringatan, fontSize: 12, fontWeight: '800' },
  beratKeranjang: { marginLeft: 'auto', fontSize: ukuran.hurufNormal, fontWeight: '800', color: warna.teks },
  tombolHapus: { width: 44, height: 44, alignItems: 'center', justifyContent: 'center' },
  garis: { height: 1, backgroundColor: warna.garis, marginVertical: 10 },
  barisTotal: { flexDirection: 'row', alignItems: 'flex-end' },
  labelKecil: { fontSize: 12, fontWeight: '800', color: warna.teksRedup, letterSpacing: 0.6 },
  total: { fontSize: 28, fontWeight: '800', color: warna.utama, marginTop: 2 },
  selisih: { fontSize: 22, fontWeight: '800', color: warna.teks, marginTop: 2 },
  info: { fontSize: 14, color: warna.teksRedup, marginTop: 8 },
  panelSelisih: {
    backgroundColor: warna.galatLatar, borderWidth: 1.5, borderColor: warna.galat,
    borderRadius: 14, padding: 14, marginBottom: 14,
  },
  judulSelisih: { fontSize: ukuran.hurufSedang, fontWeight: '800', color: warna.galat },
  teksSelisih: { fontSize: 15, color: warna.teks, marginTop: 4, lineHeight: 21 },
  kotakUsulan: { backgroundColor: warna.utamaMuda, borderRadius: 10, padding: 12, marginBottom: 12 },
  teksUsulan: { fontSize: ukuran.hurufNormal, color: warna.utama },
  keteranganUsulan: { fontSize: 14, color: warna.teksRedup, marginTop: 2 },
  tombolUsulan: { minHeight: 44, justifyContent: 'center', alignSelf: 'flex-start' },
  teksTombolUsulan: { color: warna.utama, fontWeight: '800', fontSize: 15, textDecorationLine: 'underline' },
  bilahBawah: {
    backgroundColor: warna.putih, borderTopWidth: 1, borderTopColor: '#E4EBF1',
    paddingHorizontal: 16, paddingTop: 8, paddingBottom: 12,
  },
  ringkasBawah: { fontSize: 14, color: warna.teksRedup, textAlign: 'center', marginBottom: 6, fontWeight: '600' },
});

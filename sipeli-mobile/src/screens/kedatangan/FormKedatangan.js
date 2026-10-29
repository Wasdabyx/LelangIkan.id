import { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, BackHandler, Pressable, StyleSheet, Text, View } from 'react-native';
import { api } from '../../api';
import { DialogKonfirmasi, KolomTeks, KotakPesan, Lencana, Tombol } from '../../components/Dasar';
import FormGulir from '../../components/FormGulir';
import Ikon from '../../components/Ikon';
import PilihJenisIkan from '../../components/PilihJenisIkan';
import PilihWaktu from '../../components/PilihWaktu';
import { formatKg, keAngka, keJam, keTanggalServer, tanggalPendek } from '../../format';
import { ukuran, warna } from '../../tema';

/**
 * Tampilan 2 - FormKedatanganUI (UC-01, DPPL Gambar 3.1 & Tabel 3.2)
 * Tanpa idDraf: kedatangan baru (POST). Dengan idDraf: lengkapi draf (alur A2, PUT).
 */
let nomorBaris = 0;
const barisBaru = () => ({ kunci: ++nomorBaris, id_jenis: null, id_grade: null, berat: '', keranjang: '' });
const barisKosong = (b) => !b.id_jenis && !b.id_grade && !b.berat.trim() && !b.keranjang.trim();
const KOLOM_SERVER = { berat: 'berat_estimasi', keranjang: 'jumlah_keranjang' };

// "2026-10-28" + "05:30" dari server -> Date
function keDate(tanggal, jam) {
  const [t, b, h] = tanggal.split('-').map(Number);
  const [j, m] = jam.split(':').map(Number);
  return new Date(t, b - 1, h, j, m);
}
// "40.50" dari server -> "40,5" untuk kolom isian
const keTeksBerat = (n) => String(Number(n)).replace('.', ',');

// Validasi satu baris tangkapan (sama dengan aturan server)
function periksaBaris(b) {
  const g = {};
  if (!b.id_jenis) g.id_jenis = 'Jenis ikan wajib dipilih.';
  if (!b.id_grade) g.id_grade = 'Grade wajib dipilih.';
  const berat = keAngka(b.berat);
  if (!b.berat.trim()) g.berat_estimasi = 'Berat estimasi wajib diisi.';
  else if (b.berat.trim().startsWith('-')) g.berat_estimasi = 'Berat estimasi harus lebih dari 0 kg.';
  else if (isNaN(berat)) g.berat_estimasi = 'Berat estimasi harus berupa angka.';
  else if (berat <= 0) g.berat_estimasi = 'Berat estimasi harus lebih dari 0 kg.';
  else if (berat > 999999.99) g.berat_estimasi = 'Berat estimasi terlalu besar.';
  const k = b.keranjang.trim();
  if (!k) g.jumlah_keranjang = 'Jumlah keranjang wajib diisi.';
  else if (!/^\d+$/.test(k)) g.jumlah_keranjang = 'Harus bilangan bulat.';
  else if (Number(k) < 1) g.jumlah_keranjang = 'Minimal 1.';
  else if (Number(k) > 999) g.jumlah_keranjang = 'Terlalu besar.';
  return g;
}

export default function FormKedatangan({ idDraf, onBatal, onTersimpan }) {
  const modeDraf = !!idDraf;
  const [nomorDraf, setNomorDraf] = useState('');

  // Data pilihan (jenis ikan & grade)
  const [referensi, setReferensi] = useState(null);
  const [galatReferensi, setGalatReferensi] = useState(null);

  // Pencarian kapal
  const [kataKapal, setKataKapal] = useState('');
  const [hasilKapal, setHasilKapal] = useState([]);
  const [mencari, setMencari] = useState(false);
  const [galatCari, setGalatCari] = useState(null);
  const [kapal, setKapal] = useState(null);
  const urutanCari = useRef(0);

  // Identitas pelayaran
  const [waktu, setWaktu] = useState(new Date());
  const [kapten, setKapten] = useState('');
  const [abk, setAbk] = useState('');
  const [nomorSurat, setNomorSurat] = useState('');
  const refAbk = useRef(null);
  const refSurat = useRef(null);

  // Baris tangkapan
  const [baris, setBaris] = useState([barisBaru()]);
  const [pilihJenisUntuk, setPilihJenisUntuk] = useState(null); // kunci baris

  // Status formulir
  const [galat, setGalat] = useState({});
  const [galatBaris, setGalatBaris] = useState({}); // { kunciBaris: { kolom: pesan } }
  const [statusKirim, setStatusKirim] = useState('belum'); // belum | mengirim | gagal
  const [pesan, setPesan] = useState(null);
  const [konfirmasiBatal, setKonfirmasiBatal] = useState(false);

  const mengirim = statusKirim === 'mengirim';
  const terisi = modeDraf || !!(kapal || kapten || abk || nomorSurat || baris.some((b) => !barisKosong(b)));
  const jumlahBarisValid = baris.filter((b) => Object.keys(periksaBaris(b)).length === 0).length;
  const totalBerat = baris.reduce((n, b) => {
    const v = keAngka(b.berat);
    return v > 0 ? n + v : n;
  }, 0);

  // ---------- Muat data pilihan (+ isi draf bila sedang melengkapi draf) ----------
  async function muatReferensi() {
    setGalatReferensi(null);
    try {
      const [ref, detail] = await Promise.all([
        api.get('/referensi/tangkapan'),
        modeDraf ? api.get(`/kedatangan/${idDraf}`) : null,
      ]);
      if (detail) isiDariDraf(detail.data);
      setReferensi(ref);
    } catch (e) {
      if (e.status === 401) return; // sesi habis: App kembali ke layar masuk
      setGalatReferensi(e.message);
    }
  }
  useEffect(() => {
    muatReferensi();
  }, []);

  function isiDariDraf(d) {
    if (!d.dapat_diubah) {
      throw new Error(`Kedatangan ${d.nomor_kedatangan} sudah berstatus ${d.status} sehingga tidak dapat diubah lagi.`);
    }
    setNomorDraf(d.nomor_kedatangan);
    setKapal(d.kapal);
    setWaktu(keDate(d.tanggal, d.jam));
    setKapten(d.nama_kapten);
    setAbk(String(d.jumlah_abk));
    setNomorSurat(d.nomor_surat);
    setBaris(
      d.tangkapan.length
        ? d.tangkapan.map((t) => ({
            kunci: ++nomorBaris,
            id_jenis: t.id_jenis,
            id_grade: t.id_grade,
            berat: keTeksBerat(t.berat_estimasi),
            keranjang: String(t.jumlah_keranjang),
          }))
        : [barisBaru()]
    );
  }

  // ---------- Cari kapal (tunggu 0,4 detik setelah berhenti mengetik) ----------
  useEffect(() => {
    const kata = kataKapal.trim();
    setGalatCari(null);
    if (kata.length < 3) {
      setHasilKapal([]);
      setMencari(false);
      return;
    }
    setMencari(true);
    const nomor = ++urutanCari.current;
    const tunda = setTimeout(async () => {
      try {
        const hasil = await api.get(`/kapal?cari=${encodeURIComponent(kata)}`);
        if (nomor === urutanCari.current) setHasilKapal(hasil.data);
      } catch (e) {
        if (nomor === urutanCari.current) setGalatCari(e.message);
      } finally {
        if (nomor === urutanCari.current) setMencari(false);
      }
    }, 400);
    return () => clearTimeout(tunda);
  }, [kataKapal]);

  // ---------- Tombol kembali Android = Batal ----------
  useEffect(() => {
    const sub = BackHandler.addEventListener('hardwareBackPress', () => {
      if (!mengirim) tekanBatal();
      return true;
    });
    return () => sub.remove();
  });

  function tekanBatal() {
    if (terisi) setKonfirmasiBatal(true);
    else onBatal();
  }

  function hapusGalat(kolom) {
    if (galat[kolom]) setGalat({ ...galat, [kolom]: null });
  }

  function pilihKapal(k) {
    setKapal(k);
    setKataKapal('');
    setHasilKapal([]);
    hapusGalat('id_kapal');
  }

  function ubahBaris(kunci, kolom, nilai) {
    setBaris((daftar) => daftar.map((b) => (b.kunci === kunci ? { ...b, [kolom]: nilai } : b)));
    const kolomGalat = KOLOM_SERVER[kolom] || kolom;
    if (galatBaris[kunci]?.[kolomGalat]) {
      setGalatBaris({ ...galatBaris, [kunci]: { ...galatBaris[kunci], [kolomGalat]: null } });
    }
    if (galat.tangkapan) hapusGalat('tangkapan');
  }

  function hapusBaris(kunci) {
    setBaris((daftar) => daftar.filter((b) => b.kunci !== kunci));
  }

  // ---------- Validasi di perangkat ----------
  function periksaSemua(draf) {
    const g = {};
    if (!kapal) g.id_kapal = 'Kapal wajib dipilih.';
    if (waktu.getTime() > Date.now() + 5 * 60 * 1000) {
      g.jam = 'Tanggal dan jam kedatangan tidak boleh melebihi waktu sekarang.';
    }
    if (!kapten.trim()) g.nama_kapten = 'Nama kapten wajib diisi.';
    const a = abk.trim();
    if (!a) g.jumlah_abk = 'Jumlah ABK wajib diisi.';
    else if (!/^\d+$/.test(a)) g.jumlah_abk = 'Jumlah ABK harus bilangan bulat.';
    else if (Number(a) > 60) g.jumlah_abk = 'Jumlah ABK harus antara 0 dan 60.';
    if (!nomorSurat.trim()) g.nomor_surat = 'Nomor surat wajib diisi.';

    const gb = {};
    const dikirim = baris.filter((b) => !barisKosong(b)); // baris yang benar-benar kosong diabaikan
    dikirim.forEach((b) => {
      const e = periksaBaris(b);
      if (Object.keys(e).length) gb[b.kunci] = e;
    });
    if (!draf && dikirim.length === 0) g.tangkapan = 'Tambahkan minimal satu baris tangkapan.';
    return { g, gb, dikirim };
  }

  const hitungGalat = (g, gb) =>
    Object.values(g).filter(Boolean).length +
    Object.values(gb).reduce((n, e) => n + Object.values(e).filter(Boolean).length, 0);

  // ---------- Simpan (baru: POST /api/kedatangan, draf: PUT /api/kedatangan/{id}) ----------
  async function simpan(draf) {
    if (mengirim) return;
    const { g, gb, dikirim } = periksaSemua(draf);
    setGalat(g);
    setGalatBaris(gb);
    const jumlah = hitungGalat(g, gb);
    if (jumlah > 0) {
      setPesan(`Ada ${jumlah} isian yang perlu diperbaiki (ditandai merah).`);
      return;
    }

    setPesan(null);
    setStatusKirim('mengirim');
    const muatan = {
      id_kapal: kapal.id_kapal,
      tanggal: keTanggalServer(waktu),
      jam: keJam(waktu),
      nama_kapten: kapten.trim(),
      jumlah_abk: Number(abk),
      nomor_surat: nomorSurat.trim(),
      status: draf ? 'DRAF' : 'TERDAFTAR',
      tangkapan: dikirim.map((b) => ({
        id_jenis: b.id_jenis,
        id_grade: b.id_grade,
        berat_estimasi: keAngka(b.berat),
        jumlah_keranjang: Number(b.keranjang),
      })),
    };

    try {
      const hasil = modeDraf
        ? await api.put(`/kedatangan/${idDraf}`, muatan)
        : await api.post('/kedatangan', muatan);
      onTersimpan(hasil); // pindah ke layar sukses
    } catch (e) {
      if (e.status === 401) return; // sesi habis: App kembali ke layar masuk
      if (e.status === 422) {
        // Petakan galat server "tangkapan.N.kolom" ke kartu baris yang tepat
        const gS = {};
        const gbS = {};
        for (const [kolom, isi] of Object.entries(e.galatKolom)) {
          const cocok = kolom.match(/^tangkapan\.(\d+)\.(\w+)$/);
          if (cocok && dikirim[Number(cocok[1])]) {
            const kunci = dikirim[Number(cocok[1])].kunci;
            gbS[kunci] = { ...gbS[kunci], [cocok[2]]: isi };
          } else {
            gS[kolom] = isi;
          }
        }
        setGalat(gS);
        setGalatBaris(gbS);
        setStatusKirim('belum');
        const n = hitungGalat(gS, gbS);
        setPesan(n > 1 ? `${e.message} (dan ${n - 1} isian lain ditandai merah)` : e.message);
      } else {
        setStatusKirim('gagal');
        setPesan(`${e.message} Data masih ada di formulir, tekan Simpan untuk mengirim ulang.`);
      }
    }
  }

  // ---------- Tampilan ----------
  if (!referensi) {
    return (
      <View style={s.tengah}>
        {galatReferensi ? (
          <>
            <KotakPesan teks={galatReferensi} />
            <Tombol judul="Coba Lagi" ikon="muatUlang" onPress={muatReferensi} gaya={{ marginTop: 16, alignSelf: 'stretch' }} />
            <Tombol judul="Kembali" jenis="teks" onPress={onBatal} gaya={{ marginTop: 8, alignSelf: 'stretch' }} />
          </>
        ) : (
          <ActivityIndicator size="large" color={warna.utama} />
        )}
      </View>
    );
  }

  const namaJenis = (id) => referensi.jenis_ikan.find((j) => j.id_jenis === id)?.nama_jenis;
  const STATUS_KIRIM = {
    belum: { teks: 'Belum terkirim', latar: warna.abu, warna: '#344054' },
    mengirim: { teks: 'Mengirim ke server...', latar: warna.utamaMuda, warna: warna.utama },
    gagal: { teks: 'Gagal terkirim', latar: warna.galatLatar, warna: warna.galat },
  }[statusKirim];

  return (
    <View style={{ flex: 1 }}>
      {/* Judul formulir + tombol kembali */}
      <View style={s.kepala}>
        <Pressable onPress={tekanBatal} disabled={mengirim} style={s.tombolKembali} accessibilityLabel="Kembali">
          <Ikon nama="kembali" ukuran={26} warna={warna.utama} />
        </Pressable>
        <Text style={s.judulKepala} numberOfLines={1}>
          {modeDraf ? `Lengkapi Draf ${nomorDraf}` : 'Form Kedatangan dan Tangkapan'}
        </Text>
      </View>

      <FormGulir gayaIsi={s.isi}>
        {modeDraf ? (
          <KotakPesan
            jenis="info"
            teks="Draf belum dapat ditimbang. Lengkapi datanya lalu tekan Simpan Kedatangan agar berstatus TERDAFTAR."
            gaya={{ marginBottom: 14 }}
          />
        ) : null}

        {/* ===== 1. Kapal ===== */}
        <Text style={s.judulBagian}>1. Kapal</Text>
        <View style={s.kartu}>
          {kapal ? (
            <View>
              <View style={s.barisKapal}>
                <View style={s.ikonBulat}>
                  <Ikon nama="kapal" ukuran={26} warna={warna.utama} />
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={s.namaKapal}>{kapal.nama_kapal}</Text>
                  <Text style={s.teksRedup}>{kapal.nomor_registrasi} - GT {Number(kapal.gt)}</Text>
                </View>
              </View>

              {/* Kartu data pemilik (FR-02) */}
              <View style={s.kartuPemilik}>
                <Text style={s.labelKecil}>PEMILIK KAPAL</Text>
                <Text style={s.namaPemilik}>{kapal.pemilik.nama}</Text>
                <View style={s.barisInfo}>
                  <Ikon nama="telepon" ukuran={16} warna={warna.teksRedup} />
                  <Text style={s.teksInfo}>{kapal.pemilik.no_telepon || '-'}</Text>
                </View>
                <View style={s.barisInfo}>
                  <Ikon nama="dompet" ukuran={16} warna={warna.teksRedup} />
                  <Text style={s.teksInfo}>
                    {kapal.pemilik.nama_bank || '-'} {kapal.pemilik.no_rekening || ''}
                  </Text>
                </View>
                <Text style={[s.labelKecil, { marginTop: 10 }]}>KEDATANGAN TERAKHIR</Text>
                <Text style={s.teksInfo}>
                  {kapal.kedatangan_terakhir
                    ? `${tanggalPendek(kapal.kedatangan_terakhir.tanggal)} - Kapten ${kapal.kedatangan_terakhir.nama_kapten} - ${formatKg(kapal.kedatangan_terakhir.total_berat_estimasi)}`
                    : 'Belum pernah tercatat'}
                </Text>
              </View>
              <Tombol judul="Ganti Kapal" jenis="garis" onPress={() => setKapal(null)} gaya={{ marginTop: 12, minHeight: 48 }} />
            </View>
          ) : (
            <View>
              <KolomTeks
                label="Cari kapal (nama atau nomor registrasi)"
                value={kataKapal}
                onChangeText={setKataKapal}
                placeholder="contoh: Sinar atau PLR-001"
                autoCorrect={false}
                galat={galat.id_kapal}
                gayaWadah={{ marginBottom: 6 }}
              />
              {kataKapal.trim().length > 0 && kataKapal.trim().length < 3 ? (
                <Text style={s.teksRedup}>Ketik minimal 3 karakter.</Text>
              ) : null}
              {mencari ? <ActivityIndicator color={warna.utama} style={{ marginVertical: 10 }} /> : null}
              {galatCari ? <KotakPesan teks={galatCari} gaya={{ marginTop: 8 }} /> : null}
              {!mencari && !galatCari && kataKapal.trim().length >= 3 && hasilKapal.length === 0 ? (
                <Text style={[s.teksRedup, { marginTop: 6 }]}>
                  Kapal tidak ditemukan. Kapal baru didaftarkan oleh Administrator.
                </Text>
              ) : null}
              {hasilKapal.map((k) => (
                <Pressable
                  key={k.id_kapal}
                  onPress={() => pilihKapal(k)}
                  style={({ pressed }) => [s.saranKapal, pressed && { backgroundColor: warna.utamaMuda }]}
                >
                  <Ikon nama="kapal" ukuran={22} warna={warna.utama} />
                  <View style={{ flex: 1 }}>
                    <Text style={s.namaSaran}>{k.nama_kapal}</Text>
                    <Text style={s.teksRedup}>{k.nomor_registrasi} - {k.pemilik.nama}</Text>
                  </View>
                  <Ikon nama="kanan" ukuran={20} warna={warna.teksRedup} />
                </Pressable>
              ))}
            </View>
          )}
        </View>

        {/* ===== 2. Data pelayaran ===== */}
        <Text style={s.judulBagian}>2. Data Pelayaran</Text>
        <View style={s.kartu}>
          <PilihWaktu
            nilai={waktu}
            onUbah={(w) => {
              setWaktu(w);
              hapusGalat('jam');
              hapusGalat('tanggal');
            }}
            galat={galat.jam || galat.tanggal}
          />
          <KolomTeks
            label="Nama Kapten"
            value={kapten}
            onChangeText={(t) => {
              setKapten(t);
              hapusGalat('nama_kapten');
            }}
            placeholder="Nama lengkap kapten"
            autoCapitalize="words"
            maxLength={100}
            returnKeyType="next"
            onSubmitEditing={() => refAbk.current?.focus()}
            galat={galat.nama_kapten}
            gayaWadah={kapal?.kedatangan_terakhir && !kapten ? { marginBottom: 6 } : undefined}
          />
          {kapal?.kedatangan_terakhir && !kapten ? (
            <Pressable onPress={() => setKapten(kapal.kedatangan_terakhir.nama_kapten)} style={s.saranKapten}>
              <Text style={s.teksSaranKapten}>
                Pakai kapten terakhir: {kapal.kedatangan_terakhir.nama_kapten}
              </Text>
            </Pressable>
          ) : null}
          <KolomTeks
            label="Jumlah ABK (0-60)"
            inputRef={refAbk}
            value={abk}
            onChangeText={(t) => {
              setAbk(t.replace(/[^0-9]/g, ''));
              hapusGalat('jumlah_abk');
            }}
            placeholder="contoh: 8"
            keyboardType="number-pad"
            maxLength={2}
            returnKeyType="next"
            onSubmitEditing={() => refSurat.current?.focus()}
            galat={galat.jumlah_abk}
          />
          <KolomTeks
            label="Nomor Surat (SPB / SIB)"
            inputRef={refSurat}
            value={nomorSurat}
            onChangeText={(t) => {
              setNomorSurat(t);
              hapusGalat('nomor_surat');
            }}
            placeholder="contoh: SPB/001/X/2026"
            autoCapitalize="characters"
            autoCorrect={false}
            maxLength={50}
            galat={galat.nomor_surat}
            gayaWadah={{ marginBottom: 0 }}
          />
        </View>

        {/* ===== 3. Baris tangkapan ===== */}
        <Text style={s.judulBagian}>3. Data Tangkapan</Text>
        {baris.map((b, i) => {
          const gb = galatBaris[b.kunci] || {};
          const grade = referensi.grade_mutu.find((x) => x.id_grade === b.id_grade);
          return (
            <View key={b.kunci} style={[s.kartu, s.kartuBaris]}>
              <View style={s.kepalaBaris}>
                <Text style={s.judulBaris}>Baris {i + 1}</Text>
                <Pressable onPress={() => hapusBaris(b.kunci)} style={s.tombolHapus} accessibilityLabel={`Hapus baris ${i + 1}`}>
                  <Ikon nama="hapus" ukuran={20} warna={warna.galat} />
                  <Text style={s.teksHapus}>Hapus</Text>
                </Pressable>
              </View>

              {/* Jenis ikan */}
              <Text style={s.label}>Jenis Ikan</Text>
              <Pressable
                onPress={() => setPilihJenisUntuk(b.kunci)}
                style={[s.pilihan, gb.id_jenis && s.galatBingkai]}
              >
                <Ikon nama="ikan" ukuran={22} warna={b.id_jenis ? warna.utama : warna.teksRedup} />
                <Text style={[s.teksPilihan, !b.id_jenis && { color: warna.teksRedup }]}>
                  {namaJenis(b.id_jenis) || 'Pilih jenis ikan'}
                </Text>
                <Ikon nama="bawah" ukuran={20} warna={warna.teksRedup} />
              </Pressable>
              {gb.id_jenis ? <Text style={s.teksGalat}>{gb.id_jenis}</Text> : null}

              {/* Grade */}
              <Text style={[s.label, { marginTop: 14 }]}>Grade Mutu</Text>
              <View style={[s.segmen, gb.id_grade && s.galatBingkai]}>
                {referensi.grade_mutu.map((g) => {
                  const aktif = g.id_grade === b.id_grade;
                  return (
                    <Pressable
                      key={g.id_grade}
                      onPress={() => ubahBaris(b.kunci, 'id_grade', g.id_grade)}
                      style={[s.itemSegmen, aktif && s.itemSegmenAktif]}
                    >
                      <Text style={[s.teksSegmen, aktif && { color: warna.putih }]}>{g.kode_grade}</Text>
                    </Pressable>
                  );
                })}
              </View>
              {gb.id_grade ? <Text style={s.teksGalat}>{gb.id_grade}</Text> : null}
              {grade ? <Text style={[s.teksRedup, { marginTop: 4 }]}>{grade.deskripsi}</Text> : null}

              {/* Berat & keranjang */}
              <View style={[s.baris2, { marginTop: 14 }]}>
                <KolomTeks
                  label="Berat (kg)"
                  value={b.berat}
                  onChangeText={(t) => ubahBaris(b.kunci, 'berat', t.replace(/[^0-9.,]/g, ''))}
                  placeholder="0,0"
                  keyboardType="decimal-pad"
                  maxLength={9}
                  galat={gb.berat_estimasi}
                  gayaWadah={{ flex: 1.3, marginBottom: 0 }}
                />
                <KolomTeks
                  label="Keranjang"
                  value={b.keranjang}
                  onChangeText={(t) => ubahBaris(b.kunci, 'keranjang', t.replace(/[^0-9]/g, ''))}
                  placeholder="0"
                  keyboardType="number-pad"
                  maxLength={3}
                  galat={gb.jumlah_keranjang}
                  gayaWadah={{ flex: 1, marginBottom: 0 }}
                />
              </View>
            </View>
          );
        })}
        {galat.tangkapan ? <KotakPesan teks={galat.tangkapan} gaya={{ marginBottom: 12 }} /> : null}
        <Tombol judul="Tambah Baris Tangkapan" ikon="tambah" jenis="garis" onPress={() => setBaris([...baris, barisBaru()])} />

        {/* ===== Ringkasan & sinkronisasi ===== */}
        <View style={[s.kartu, s.ringkasan]}>
          <View style={{ flex: 1 }}>
            <Text style={s.labelKecil}>TOTAL BERAT ESTIMASI</Text>
            <Text style={s.total}>{formatKg(totalBerat)}</Text>
            <Text style={s.teksRedup}>{baris.length} baris, {jumlahBarisValid} lengkap</Text>
          </View>
          <View style={{ alignItems: 'flex-end', gap: 6 }}>
            <Text style={s.labelKecil}>STATUS</Text>
            <Lencana ikon="awan" teks={STATUS_KIRIM.teks} latar={STATUS_KIRIM.latar} warnaTeks={STATUS_KIRIM.warna} />
          </View>
        </View>

        <KotakPesan teks={pesan} jenis="galat" gaya={{ marginBottom: 12 }} />

        <Tombol
          judul="Simpan Kedatangan"
          ikon="centang"
          onPress={() => simpan(false)}
          memuat={mengirim}
          nonaktif={jumlahBarisValid === 0}
        />
        {jumlahBarisValid === 0 ? (
          <Text style={[s.teksRedup, { textAlign: 'center', marginTop: 6 }]}>
            Lengkapi minimal satu baris tangkapan untuk menyimpan.
          </Text>
        ) : null}
        <Tombol judul={modeDraf ? 'Simpan Perubahan Draf' : 'Simpan sebagai Draf'} jenis="garis" onPress={() => simpan(true)} nonaktif={mengirim} gaya={{ marginTop: 10 }} />
        <Tombol judul="Batal" jenis="teks" onPress={tekanBatal} nonaktif={mengirim} gaya={{ marginTop: 6 }} />
      </FormGulir>

      <PilihJenisIkan
        tampil={pilihJenisUntuk !== null}
        daftar={referensi.jenis_ikan}
        terpilih={baris.find((b) => b.kunci === pilihJenisUntuk)?.id_jenis}
        onPilih={(j) => {
          ubahBaris(pilihJenisUntuk, 'id_jenis', j.id_jenis);
          setPilihJenisUntuk(null);
        }}
        onTutup={() => setPilihJenisUntuk(null)}
      />

      <DialogKonfirmasi
        tampil={konfirmasiBatal}
        judul={modeDraf ? 'Tinggalkan draf ini?' : 'Batalkan pengisian?'}
        pesan={
          modeDraf
            ? 'Perubahan yang belum disimpan akan hilang. Draf tetap tersimpan seperti sebelumnya.'
            : 'Data yang sudah diisi pada formulir ini akan hilang.'
        }
        teksYa="Ya, Batalkan"
        teksTidak="Lanjut Mengisi"
        bahaya
        onYa={() => {
          setKonfirmasiBatal(false);
          onBatal();
        }}
        onTidak={() => setKonfirmasiBatal(false)}
      />
    </View>
  );
}

const s = StyleSheet.create({
  tengah: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 24 },
  kepala: {
    flexDirection: 'row', alignItems: 'center', backgroundColor: warna.putih,
    borderBottomWidth: 1, borderBottomColor: warna.garis, paddingRight: 16,
  },
  tombolKembali: { width: 52, height: 52, alignItems: 'center', justifyContent: 'center' },
  judulKepala: { flex: 1, fontSize: ukuran.hurufSedang, fontWeight: '700', color: warna.teks },
  isi: { padding: 16, paddingBottom: 40 },
  judulBagian: { fontSize: 15, fontWeight: '800', color: warna.teksRedup, marginBottom: 8, marginTop: 6, letterSpacing: 0.3 },
  kartu: { backgroundColor: warna.kartu, borderRadius: 14, padding: 16, marginBottom: 16, borderWidth: 1, borderColor: '#E4EBF1' },
  kartuBaris: { marginBottom: 12 },
  label: { fontSize: ukuran.hurufNormal, fontWeight: '600', color: warna.teks, marginBottom: 6 },
  labelKecil: { fontSize: 12, fontWeight: '800', color: warna.teksRedup, letterSpacing: 0.6 },
  teksRedup: { fontSize: 14, color: warna.teksRedup },
  teksGalat: { color: warna.galat, fontSize: 14, marginTop: 6, fontWeight: '600' },
  galatBingkai: { borderColor: warna.galat, borderWidth: 2 },
  barisKapal: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  ikonBulat: {
    width: 48, height: 48, borderRadius: 24, backgroundColor: warna.utamaMuda,
    alignItems: 'center', justifyContent: 'center',
  },
  namaKapal: { fontSize: 20, fontWeight: '800', color: warna.teks },
  kartuPemilik: { backgroundColor: warna.latar, borderRadius: 10, padding: 12, marginTop: 12 },
  namaPemilik: { fontSize: ukuran.hurufSedang, fontWeight: '700', color: warna.teks, marginTop: 2, marginBottom: 4 },
  barisInfo: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 4 },
  teksInfo: { fontSize: 15, color: warna.teks, marginTop: 2 },
  saranKapal: {
    flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: 60,
    paddingHorizontal: 8, borderTopWidth: 1, borderTopColor: warna.abu,
  },
  namaSaran: { fontSize: ukuran.hurufNormal, fontWeight: '700', color: warna.teks },
  saranKapten: {
    alignSelf: 'flex-start', backgroundColor: warna.utamaMuda, borderRadius: 999,
    paddingHorizontal: 12, minHeight: 36, justifyContent: 'center', marginBottom: 14,
  },
  teksSaranKapten: { color: warna.utama, fontWeight: '700', fontSize: 14 },
  kepalaBaris: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 8 },
  judulBaris: { fontSize: ukuran.hurufSedang, fontWeight: '800', color: warna.utama },
  tombolHapus: { flexDirection: 'row', alignItems: 'center', gap: 4, minHeight: 44, paddingHorizontal: 8 },
  teksHapus: { color: warna.galat, fontWeight: '700', fontSize: 15 },
  pilihan: {
    height: ukuran.tinggiInput, borderWidth: 1.5, borderColor: warna.garis, borderRadius: ukuran.radius,
    paddingHorizontal: 12, flexDirection: 'row', alignItems: 'center', gap: 10, backgroundColor: warna.putih,
  },
  teksPilihan: { flex: 1, fontSize: ukuran.hurufSedang, color: warna.teks },
  segmen: {
    flexDirection: 'row', borderWidth: 1.5, borderColor: warna.garis, borderRadius: ukuran.radius, overflow: 'hidden',
  },
  itemSegmen: { flex: 1, height: 48, alignItems: 'center', justifyContent: 'center', backgroundColor: warna.putih },
  itemSegmenAktif: { backgroundColor: warna.utama },
  teksSegmen: { fontSize: 20, fontWeight: '800', color: warna.utama },
  baris2: { flexDirection: 'row', gap: 10, alignItems: 'flex-start' },
  ringkasan: { flexDirection: 'row', alignItems: 'center', marginTop: 16, backgroundColor: warna.utamaMuda, borderColor: '#C5DCE8' },
  total: { fontSize: 28, fontWeight: '800', color: warna.utama, marginVertical: 2 },
});

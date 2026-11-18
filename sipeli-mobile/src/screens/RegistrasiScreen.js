import { useEffect, useRef, useState } from 'react';
import { BackHandler, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { daftar } from '../api';
import { KotakPesan, Tombol } from '../components/Dasar';
import FormGulir, { useGulirKeKolom } from '../components/FormGulir';
import Ikon from '../components/Ikon';
import { ukuran, warna } from '../tema';
import {
  SYARAT_SANDI,
  filterHp,
  hanyaAngka,
  periksaKonfirmasi,
  periksaNamaOrang,
  periksaNamaPengguna,
  periksaNik,
  periksaNoHp,
  periksaSandi,
  periksaSurel,
  rapikanHp,
  rapikanSpasi,
  validasiRegistrasi,
} from '../validasi';

/**
 * Tugas Week 9 - Formulir Registrasi Pembeli (UC-11, AS-03 SKPL)
 * Setiap kolom divalidasi dengan Regular Expression dari src/validasi.js:
 * - saat kolom ditinggalkan (onBlur), lalu ulang setiap kali diketik setelah itu
 * - semua kolom sekaligus saat tombol DAFTAR ditekan
 * Akun yang berhasil dibuat berstatus NONAKTIF; Tugas 2: kode aktivasi dikirim ke surel,
 * lalu pengguna langsung dibawa ke layar Aktivasi (AktivasiScreen).
 */
const KOSONG = {
  nama_lengkap: '',
  nik: '',
  no_hp: '',
  surel: '',
  nama_pengguna: '',
  kata_sandi: '',
  konfirmasi_sandi: '',
};

// Pemeriksa untuk setiap kolom (dipanggil saat kolom ditinggalkan / diketik ulang)
const PERIKSA = {
  nama_lengkap: (d) => periksaNamaOrang(d.nama_lengkap),
  nik: (d) => periksaNik(d.nik),
  no_hp: (d) => periksaNoHp(d.no_hp),
  surel: (d) => periksaSurel(d.surel),
  nama_pengguna: (d) => periksaNamaPengguna(d.nama_pengguna),
  kata_sandi: (d) => periksaSandi(d.kata_sandi),
  konfirmasi_sandi: (d) => periksaKonfirmasi(d.kata_sandi, d.konfirmasi_sandi),
};

export default function RegistrasiScreen({ onKembali, onSelesai }) {
  const [data, setData] = useState(KOSONG);
  const [galat, setGalat] = useState({});
  const [disentuh, setDisentuh] = useState({}); // kolom yang sudah pernah ditinggalkan
  const [lihatSandi, setLihatSandi] = useState(false);
  const [memuat, setMemuat] = useState(false);
  const [pesan, setPesan] = useState(null);

  const ref = {
    nik: useRef(null),
    no_hp: useRef(null),
    surel: useRef(null),
    nama_pengguna: useRef(null),
    kata_sandi: useRef(null),
    konfirmasi_sandi: useRef(null),
  };

  // Tombol kembali Android = kembali ke layar Masuk
  useEffect(() => {
    const sub = BackHandler.addEventListener('hardwareBackPress', () => {
      if (!memuat) onKembali();
      return true;
    });
    return () => sub.remove();
  });

  // Ubah nilai kolom; bila kolom sudah pernah disentuh, periksa ulang langsung (validasi seketika)
  function ubah(kolom, nilai) {
    const baru = { ...data, [kolom]: nilai };
    setData(baru);
    setPesan(null);
    const g = { ...galat };
    if (disentuh[kolom]) g[kolom] = PERIKSA[kolom](baru);
    // konfirmasi ikut diperiksa ulang bila kata sandi berubah
    if (kolom === 'kata_sandi' && disentuh.konfirmasi_sandi) {
      g.konfirmasi_sandi = PERIKSA.konfirmasi_sandi(baru);
    }
    setGalat(g);
  }

  // Kolom ditinggalkan: tandai disentuh lalu periksa
  function tinggalkan(kolom) {
    setDisentuh({ ...disentuh, [kolom]: true });
    setGalat({ ...galat, [kolom]: PERIKSA[kolom](data) });
  }

  async function tekanDaftar() {
    if (memuat) return;
    // 1. Validasi RegEx semua kolom di perangkat
    const g = validasiRegistrasi(data);
    setGalat(g);
    setDisentuh(Object.fromEntries(Object.keys(KOSONG).map((k) => [k, true])));
    const jumlah = Object.keys(g).length;
    if (jumlah > 0) {
      setPesan(`Ada ${jumlah} isian yang perlu diperbaiki (ditandai merah).`);
      return;
    }

    // 2. Kirim ke server: POST /api/registrasi (server memeriksa ulang dengan pola yang sama)
    setPesan(null);
    setMemuat(true);
    try {
      const respons = await daftar({
        nama_lengkap: rapikanSpasi(data.nama_lengkap),
        nik: data.nik.trim(),
        no_hp: rapikanHp(data.no_hp),
        surel: data.surel.trim().toLowerCase(),
        nama_pengguna: data.nama_pengguna.trim(),
        kata_sandi: data.kata_sandi,
        konfirmasi_sandi: data.konfirmasi_sandi,
      });
      // Tugas 2: lanjut ke layar Aktivasi (kata sandi hanya disimpan di memori untuk meminta/memeriksa kode)
      onSelesai({
        namaPengguna: respons.pengguna.nama_pengguna,
        kataSandi: data.kata_sandi,
        info: {
          pesan: respons.pesan,
          surelTerkirim: respons.aktivasi.surel_terkirim,
          surelTersamar: respons.aktivasi.surel_tersamar,
          berlakuDetik: respons.aktivasi.berlaku_detik,
          kirimUlangDetik: respons.aktivasi.kirim_ulang_detik,
        },
      });
      return;
    } catch (e) {
      if (e.status === 422 && Object.keys(e.galatKolom).length > 0) {
        setGalat(e.galatKolom); // contoh: "NIK ini sudah terdaftar."
        setPesan('Periksa kembali isian yang ditandai merah.');
      } else {
        setPesan(e.message);
      }
    } finally {
      setMemuat(false);
    }
  }

  // ---------- Formulir ----------
  return (
    <SafeAreaView style={s.layar} edges={['top', 'bottom']}>
      <View style={s.kepala}>
        <Pressable onPress={onKembali} style={s.tombolKembali} accessibilityLabel="Kembali ke halaman masuk" hitSlop={8}>
          <Ikon nama="kembali" ukuran={26} warna={warna.putih} />
        </Pressable>
        <Text style={s.judulKepala}>
          LelangIkan<Text style={s.judulId}>.id</Text>
        </Text>
      </View>

      <FormGulir gayaIsi={s.isi}>
        <View style={s.kartu}>
          <Text style={s.judulKartu}>Daftar Akun Pembeli</Text>
          <Text style={s.subjudul}>Isi data sesuai KTP. Semua kolom wajib diisi.</Text>

          <Kolom
            label="Nama Lengkap"
            value={data.nama_lengkap}
            onChangeText={(t) => ubah('nama_lengkap', t)}
            onBlur={() => tinggalkan('nama_lengkap')}
            galat={galat.nama_lengkap}
            placeholder="contoh: Siti Nurhaliza"
            autoCapitalize="words"
            maxLength={100}
            returnKeyType="next"
            onSubmitEditing={() => ref.nik.current?.focus()}
          />

          <Kolom
            label="NIK (16 digit)"
            inputRef={ref.nik}
            value={data.nik}
            onChangeText={(t) => ubah('nik', hanyaAngka(t))}
            onBlur={() => tinggalkan('nik')}
            galat={galat.nik}
            petunjuk={`${data.nik.length}/16 digit`}
            placeholder="contoh: 3202125508900002"
            keyboardType="number-pad"
            maxLength={16}
            returnKeyType="next"
            onSubmitEditing={() => ref.no_hp.current?.focus()}
          />

          <Kolom
            label="Nomor HP"
            inputRef={ref.no_hp}
            value={data.no_hp}
            onChangeText={(t) => ubah('no_hp', filterHp(t))}
            onBlur={() => tinggalkan('no_hp')}
            galat={galat.no_hp}
            petunjuk="Diawali 08 atau +62"
            placeholder="contoh: 081234567890"
            keyboardType="phone-pad"
            maxLength={20}
            returnKeyType="next"
            onSubmitEditing={() => ref.surel.current?.focus()}
          />

          <Kolom
            label="Surel (Email)"
            inputRef={ref.surel}
            value={data.surel}
            onChangeText={(t) => ubah('surel', t.replace(/\s/g, ''))}
            onBlur={() => tinggalkan('surel')}
            galat={galat.surel}
            placeholder="contoh: siti@contoh.com"
            keyboardType="email-address"
            autoCapitalize="none"
            autoCorrect={false}
            maxLength={120}
            returnKeyType="next"
            onSubmitEditing={() => ref.nama_pengguna.current?.focus()}
          />

          <Kolom
            label="Nama Pengguna"
            inputRef={ref.nama_pengguna}
            value={data.nama_pengguna}
            onChangeText={(t) => ubah('nama_pengguna', t.toLowerCase().replace(/\s/g, ''))}
            onBlur={() => tinggalkan('nama_pengguna')}
            galat={galat.nama_pengguna}
            petunjuk="4-30 karakter: huruf kecil, angka, titik, garis bawah"
            placeholder="contoh: siti_bakul"
            autoCapitalize="none"
            autoCorrect={false}
            maxLength={30}
            returnKeyType="next"
            onSubmitEditing={() => ref.kata_sandi.current?.focus()}
          />

          <KolomSandi
            label="Kata Sandi"
            inputRef={ref.kata_sandi}
            value={data.kata_sandi}
            onChangeText={(t) => ubah('kata_sandi', t)}
            onBlur={() => tinggalkan('kata_sandi')}
            galat={galat.kata_sandi}
            lihat={lihatSandi}
            onLihat={() => setLihatSandi(!lihatSandi)}
            placeholder="Buat kata sandi"
            returnKeyType="next"
            onSubmitEditing={() => ref.konfirmasi_sandi.current?.focus()}
          />

          {/* Daftar syarat kata sandi: setiap baris diuji dengan RegEx-nya sendiri */}
          <View style={s.syarat}>
            {SYARAT_SANDI.map((x) => {
              const ok = data.kata_sandi.length > 0 && x.pola.test(data.kata_sandi);
              return (
                <View key={x.teks} style={s.barisSyarat}>
                  <Ikon
                    nama={ok ? 'centang' : 'silang'}
                    ukuran={18}
                    warna={ok ? warna.sukses : warna.teksRedup}
                    tebal={2.4}
                  />
                  <Text style={[s.teksSyarat, ok && { color: warna.sukses }]}>{x.teks}</Text>
                </View>
              );
            })}
          </View>

          <KolomSandi
            label="Konfirmasi Kata Sandi"
            inputRef={ref.konfirmasi_sandi}
            value={data.konfirmasi_sandi}
            onChangeText={(t) => ubah('konfirmasi_sandi', t)}
            onBlur={() => tinggalkan('konfirmasi_sandi')}
            galat={galat.konfirmasi_sandi}
            lihat={lihatSandi}
            onLihat={() => setLihatSandi(!lihatSandi)}
            placeholder="Ketik ulang kata sandi"
            returnKeyType="go"
            onSubmitEditing={tekanDaftar}
          />

          <KotakPesan
            jenis="info"
            teks="Akun baru belum aktif. Petugas TPI akan memverifikasi identitas (NIK) Anda sebelum akun dapat dipakai untuk menawar."
            gaya={{ marginTop: 4 }}
          />
          <KotakPesan teks={pesan} jenis="galat" gaya={{ marginTop: 12 }} />

          <Tombol judul="Daftar" onPress={tekanDaftar} memuat={memuat} gaya={{ marginTop: 20 }} />
          <Pressable onPress={onKembali} disabled={memuat} style={s.tautan} hitSlop={8}>
            <Text style={s.teksTautan}>
              Sudah punya akun? <Text style={s.tebal}>Masuk</Text>
            </Text>
          </Pressable>
        </View>
      </FormGulir>
    </SafeAreaView>
  );
}

/* ---------- Komponen kecil ---------- */
function Kolom({ label, galat, petunjuk, inputRef, onFocus, ...props }) {
  const gulirKeKolom = useGulirKeKolom();
  return (
    <View style={{ marginBottom: 14 }}>
      <Text style={s.label}>{label}</Text>
      <TextInput
        ref={inputRef}
        style={[s.input, galat && s.inputGalat]}
        placeholderTextColor={warna.teksRedup}
        onFocus={(e) => {
          gulirKeKolom();
          onFocus && onFocus(e);
        }}
        {...props}
      />
      {galat ? <Text style={s.teksGalat}>{galat}</Text> : petunjuk ? <Text style={s.petunjuk}>{petunjuk}</Text> : null}
    </View>
  );
}

function KolomSandi({ label, galat, inputRef, lihat, onLihat, ...props }) {
  const gulirKeKolom = useGulirKeKolom();
  return (
    <View style={{ marginBottom: 14 }}>
      <Text style={s.label}>{label}</Text>
      <View style={[s.input, s.barisSandi, galat && s.inputGalat]}>
        <TextInput
          ref={inputRef}
          style={s.inputSandi}
          placeholderTextColor={warna.teksRedup}
          secureTextEntry={!lihat}
          autoCapitalize="none"
          autoCorrect={false}
          maxLength={64}
          onFocus={() => gulirKeKolom()}
          {...props}
        />
        <Pressable onPress={onLihat} style={s.tombolLihat} hitSlop={8}>
          <Text style={s.teksLihat}>{lihat ? 'Sembunyikan' : 'Lihat'}</Text>
        </Pressable>
      </View>
      {galat ? <Text style={s.teksGalat}>{galat}</Text> : null}
    </View>
  );
}

const s = StyleSheet.create({
  layar: { flex: 1, backgroundColor: warna.utama },
  kepala: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 8, paddingVertical: 6 },
  tombolKembali: { width: 48, height: 48, alignItems: 'center', justifyContent: 'center' },
  judulKepala: { fontSize: 20, fontWeight: '700', color: warna.putih, marginLeft: 4 },
  judulId: { color: '#8FD3F4' },
  isi: { padding: 16, paddingTop: 4, paddingBottom: 40 },
  kartu: { backgroundColor: warna.kartu, borderRadius: 6, padding: 16, borderWidth: 1, borderColor: '#E4EBF1' },
  judulKartu: { fontSize: 18, fontWeight: '600', color: warna.teks },
  subjudul: { fontSize: 14, color: warna.teksRedup, marginTop: 4, marginBottom: 18 },
  label: { fontSize: ukuran.hurufNormal, fontWeight: '500', color: warna.teks, marginBottom: 6 },
  input: {
    height: ukuran.tinggiInput, borderWidth: 1, borderColor: warna.garis,
    borderRadius: ukuran.radius, paddingHorizontal: 14, fontSize: ukuran.hurufSedang,
    color: warna.teks, backgroundColor: warna.putih,
  },
  inputGalat: { borderColor: warna.galat, borderWidth: 2 },
  teksGalat: { color: warna.galat, fontSize: 13, marginTop: 6, fontWeight: '500' },
  petunjuk: { color: warna.teksRedup, fontSize: 12, marginTop: 5 },
  barisSandi: { flexDirection: 'row', alignItems: 'center', paddingRight: 4 },
  inputSandi: { flex: 1, fontSize: ukuran.hurufSedang, color: warna.teks, height: '100%' },
  tombolLihat: { paddingHorizontal: 10, minHeight: 44, justifyContent: 'center' },
  teksLihat: { color: warna.utama, fontWeight: '600', fontSize: 13 },
  syarat: {
    backgroundColor: warna.latar, borderRadius: ukuran.radius, padding: 12,
    marginTop: -4, marginBottom: 16, gap: 6,
  },
  barisSyarat: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  teksSyarat: { fontSize: 13, color: warna.teksRedup, fontWeight: '500' },
  tautan: { alignItems: 'center', minHeight: 44, justifyContent: 'center', marginTop: 8 },
  teksTautan: { color: warna.teksRedup, fontSize: 14 },
  tebal: { color: warna.utama, fontWeight: '700' },
});

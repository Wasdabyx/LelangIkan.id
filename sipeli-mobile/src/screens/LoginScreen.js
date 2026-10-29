import { useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Image,
  Keyboard,
  KeyboardAvoidingView,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { masuk } from '../api';
import { ukuran, warna } from '../tema';

/**
 * Tampilan 1 - FormMasukUI (DPPL Tabel 3.77)
 * Atribut: namaPengguna, kataSandi, pesanKesalahan
 * Metode : masuk(namaPengguna, kataSandi), tampilkanKesalahan(pesan)
 */
export default function LoginScreen({ onBerhasil, pesanAwal }) {
  const [namaPengguna, setNamaPengguna] = useState('');
  const [kataSandi, setKataSandi] = useState('');
  const [lihatSandi, setLihatSandi] = useState(false);
  const [galat, setGalat] = useState(pesanAwal ? { umum: pesanAwal } : {}); // { nama_pengguna, kata_sandi, kredensial, umum }
  const [memuat, setMemuat] = useState(false);
  const [keyboardTampil, setKeyboardTampil] = useState(false);
  const inputSandi = useRef(null);
  const gulirRef = useRef(null);

  // Saat keyboard muncul: ringkas kepala & geser formulir ke atas keyboard
  useEffect(() => {
    const tampil = Keyboard.addListener('keyboardDidShow', () => {
      setKeyboardTampil(true);
      setTimeout(() => gulirRef.current?.scrollToEnd({ animated: true }), 80);
    });
    const sembunyi = Keyboard.addListener('keyboardDidHide', () => setKeyboardTampil(false));
    return () => {
      tampil.remove();
      sembunyi.remove();
    };
  }, []);

  // Hapus sorotan merah ketika pengguna mulai mengetik ulang
  function bersihkanGalat(kolom) {
    if (galat[kolom] || galat.kredensial || galat.umum) {
      setGalat({ ...galat, [kolom]: null, kredensial: null, umum: null });
    }
  }

  async function tekanMasuk() {
    // 1. Validasi bentuk isian di perangkat
    const g = {};
    if (!namaPengguna.trim()) g.nama_pengguna = 'Nama pengguna wajib diisi.';
    if (!kataSandi) g.kata_sandi = 'Kata sandi wajib diisi.';
    setGalat(g);
    if (Object.keys(g).length > 0) return;

    // 2. Kirim ke server: POST /api/masuk
    setMemuat(true);
    try {
      const hasil = await masuk(namaPengguna.trim(), kataSandi);
      await onBerhasil(hasil.token, hasil.pengguna);
    } catch (e) {
      tampilkanKesalahan(e);
      setMemuat(false);
    }
  }

  function tampilkanKesalahan(e) {
    const kolom = e.galatKolom || {};
    if (e.status === 422 && kolom.kata_sandi && !kolom.nama_pengguna && kataSandi) {
      // Nama pengguna / kata sandi salah: sorot KEDUA kolom, pesan cukup sekali
      setGalat({ kredensial: kolom.kata_sandi });
    } else if (e.status === 422 && Object.keys(kolom).length > 0) {
      setGalat(kolom); // galat ditampilkan tepat di bawah kolom bermasalah
    } else {
      setGalat({ umum: e.message });
    }
  }

  return (
    <SafeAreaView style={gaya.layar} edges={['top', 'bottom']}>
      <KeyboardAvoidingView
        style={{ flex: 1 }}
        behavior="padding"
      >
        <ScrollView
          ref={gulirRef}
          contentContainerStyle={gaya.isi}
          keyboardShouldPersistTaps="handled"
        >
          {/* Kepala (diringkas saat keyboard tampil agar formulir tetap terlihat) */}
          <View style={[gaya.kepala, keyboardTampil && { marginBottom: 12 }]}>
            {!keyboardTampil && (
              <View style={gaya.logo}>
                <Image
                  source={require('../../assets/logo-putih.png')}
                  style={gaya.gambarLogo}
                  accessibilityLabel="Logo LelangIkan.id"
                />
              </View>
            )}
            <Text style={[gaya.judul, keyboardTampil && { fontSize: 26 }]}>
              LelangIkan<Text style={gaya.judulId}>.id</Text>
            </Text>
            {!keyboardTampil && (
              <Text style={gaya.subjudul}>Sistem Informasi Pelelangan Ikan</Text>
            )}
          </View>

          {/* Kartu formulir */}
          <View style={gaya.kartu}>
            <Text style={gaya.judulKartu}>Masuk</Text>

            {galat.umum ? (
              <View style={gaya.kotakGalat}>
                <Text style={gaya.teksKotakGalat}>{galat.umum}</Text>
              </View>
            ) : null}

            {/* Nama pengguna */}
            <Text style={gaya.label}>Nama Pengguna</Text>
            <TextInput
              style={[gaya.input, (galat.nama_pengguna || galat.kredensial) && gaya.inputGalat]}
              value={namaPengguna}
              onChangeText={(t) => {
                setNamaPengguna(t);
                bersihkanGalat('nama_pengguna');
              }}
              placeholder="contoh: pendata"
              placeholderTextColor={warna.teksRedup}
              autoCapitalize="none"
              autoCorrect={false}
              returnKeyType="next"
              onSubmitEditing={() => inputSandi.current?.focus()}
              editable={!memuat}
            />
            {galat.nama_pengguna ? <Text style={gaya.teksGalat}>{galat.nama_pengguna}</Text> : null}

            {/* Kata sandi */}
            <Text style={[gaya.label, { marginTop: 16 }]}>Kata Sandi</Text>
            <View
              style={[gaya.input, gaya.barisSandi, (galat.kata_sandi || galat.kredensial) && gaya.inputGalat]}
            >
              <TextInput
                ref={inputSandi}
                style={gaya.inputSandi}
                value={kataSandi}
                onChangeText={(t) => {
                  setKataSandi(t);
                  bersihkanGalat('kata_sandi');
                }}
                onFocus={() => setTimeout(() => gulirRef.current?.scrollToEnd({ animated: true }), 80)}
                placeholder="Masukkan kata sandi"
                placeholderTextColor={warna.teksRedup}
                secureTextEntry={!lihatSandi}
                autoCapitalize="none"
                returnKeyType="go"
                onSubmitEditing={tekanMasuk}
                editable={!memuat}
              />
              <Pressable
                onPress={() => setLihatSandi(!lihatSandi)}
                style={gaya.tombolLihat}
                hitSlop={8}
              >
                <Text style={gaya.teksLihat}>{lihatSandi ? 'Sembunyikan' : 'Lihat'}</Text>
              </Pressable>
            </View>
            {galat.kata_sandi ? <Text style={gaya.teksGalat}>{galat.kata_sandi}</Text> : null}
            {galat.kredensial ? <Text style={gaya.teksGalat}>{galat.kredensial}</Text> : null}

            {/* Tombol masuk */}
            <Pressable
              onPress={tekanMasuk}
              disabled={memuat}
              style={({ pressed }) => [
                gaya.tombol,
                pressed && { backgroundColor: warna.utamaTekan },
                memuat && { opacity: 0.7 },
              ]}
            >
              {memuat ? (
                <ActivityIndicator color={warna.putih} />
              ) : (
                <Text style={gaya.teksTombol}>MASUK</Text>
              )}
            </Pressable>
          </View>

        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const gaya = StyleSheet.create({
  layar: { flex: 1, backgroundColor: warna.utama },
  isi: { flexGrow: 1, justifyContent: 'center', padding: 20 },
  kepala: { alignItems: 'center', marginBottom: 24 },
  logo: { marginBottom: 12 },
  gambarLogo: { width: 96, height: 96 },
  judul: { fontSize: 34, fontWeight: '800', color: warna.putih, letterSpacing: 0.5 },
  judulId: { color: '#8FD3F4' },
  subjudul: { fontSize: ukuran.hurufNormal, color: '#D9E8F0', marginTop: 4 },
  kartu: { backgroundColor: warna.kartu, borderRadius: 16, padding: 20 },
  judulKartu: { fontSize: 22, fontWeight: '700', color: warna.teks, marginBottom: 16 },
  label: { fontSize: ukuran.hurufNormal, fontWeight: '600', color: warna.teks, marginBottom: 6 },
  input: {
    height: ukuran.tinggiInput, borderWidth: 1.5, borderColor: warna.garis,
    borderRadius: ukuran.radius, paddingHorizontal: 14, fontSize: ukuran.hurufSedang,
    color: warna.teks, backgroundColor: warna.putih,
  },
  inputGalat: { borderColor: warna.galat, borderWidth: 2 },
  barisSandi: { flexDirection: 'row', alignItems: 'center', paddingRight: 4 },
  inputSandi: { flex: 1, fontSize: ukuran.hurufSedang, color: warna.teks, height: '100%' },
  tombolLihat: { paddingHorizontal: 10, minHeight: 44, justifyContent: 'center' },
  teksLihat: { color: warna.utama, fontWeight: '700', fontSize: 14 },
  teksGalat: { color: warna.galat, fontSize: 14, marginTop: 6, fontWeight: '600' },
  kotakGalat: {
    backgroundColor: warna.galatLatar, borderColor: warna.galat, borderWidth: 1,
    borderRadius: ukuran.radius, padding: 12, marginBottom: 16,
  },
  teksKotakGalat: { color: warna.galat, fontSize: 15, fontWeight: '600' },
  tombol: {
    height: ukuran.tinggiTombol, backgroundColor: warna.utama, borderRadius: ukuran.radius,
    alignItems: 'center', justifyContent: 'center', marginTop: 24,
  },
  teksTombol: { color: warna.putih, fontSize: ukuran.hurufSedang, fontWeight: '800', letterSpacing: 1 },
});

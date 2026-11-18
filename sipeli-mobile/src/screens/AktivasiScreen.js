import { useEffect, useRef, useState } from 'react';
import { BackHandler, KeyboardAvoidingView, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { aktivasi, kirimUlangAktivasi } from '../api';
import { KotakPesan, Tombol } from '../components/Dasar';
import Ikon from '../components/Ikon';
import { formatMenit, KolomOtp, useHitungMundur } from '../components/KolomOtp';
import { ukuran, warna } from '../tema';
import { periksaKodeOtp } from '../validasi';

/**
 * Tugas 2 - Aktivasi Akun Pembeli lewat surel.
 * Setelah registrasi (atau saat masuk dengan akun yang belum aktivasi), kode 6 digit dikirim ke surel.
 * Kode berlaku 10 menit, Kirim Ulang tersedia setelah 60 detik, maksimal 5 kali salah.
 * Kode benar -> akun langsung aktif dan pengguna langsung masuk.
 */
export default function AktivasiScreen({ namaPengguna, kataSandi, info, onBerhasil, onKembali }) {
  const [kode, setKode] = useState('');
  const [galat, setGalat] = useState(null);
  const [pesan, setPesan] = useState(
    info?.pesan ? { jenis: info.surelTerkirim === false ? 'galat' : 'sukses', teks: info.pesan } : null
  );
  const [surel, setSurel] = useState(info?.surelTersamar || '');
  const [memuat, setMemuat] = useState(false);
  const [mengirim, setMengirim] = useState(false);
  const [sisaBerlaku, mulaiBerlaku] = useHitungMundur(info?.berlakuDetik ?? 0);
  const [sisaKirim, mulaiKirim] = useHitungMundur(info?.kirimUlangDetik ?? 0);
  const refKode = useRef(null);
  const sudahMinta = useRef(false);

  // Datang dari layar Masuk (belum ada kode baru): langsung minta kode
  useEffect(() => {
    if (info?.mintaKode && !sudahMinta.current) {
      sudahMinta.current = true;
      kirimUlang();
    }
    const sub = BackHandler.addEventListener('hardwareBackPress', () => {
      onKembali();
      return true;
    });
    return () => sub.remove();
  }, []);

  async function periksa(kodeDiisi = kode) {
    if (memuat) return;
    const g = periksaKodeOtp(kodeDiisi); // RegEx /^\d{6}$/
    setGalat(g);
    if (g) return;

    setMemuat(true);
    setPesan(null);
    try {
      const hasil = await aktivasi(namaPengguna, kataSandi, kodeDiisi);
      await onBerhasil(hasil.token, hasil.pengguna);
    } catch (e) {
      setMemuat(false);
      setKode('');
      if (e.galatKolom?.kode_otp) setGalat(e.galatKolom.kode_otp);
      else setPesan({ jenis: 'galat', teks: e.message });
      setTimeout(() => refKode.current?.focus(), 100);
    }
  }

  async function kirimUlang() {
    if (mengirim) return;
    setMengirim(true);
    setGalat(null);
    try {
      const hasil = await kirimUlangAktivasi(namaPengguna, kataSandi);
      setSurel(hasil.surel_tersamar);
      mulaiBerlaku(hasil.berlaku_detik);
      mulaiKirim(hasil.kirim_ulang_detik);
      setKode('');
      setPesan({ jenis: 'sukses', teks: hasil.pesan });
    } catch (e) {
      if (e.data?.tunggu_detik) mulaiKirim(e.data.tunggu_detik);
      setPesan({ jenis: 'galat', teks: e.message });
    } finally {
      setMengirim(false);
    }
  }

  const kedaluwarsa = sisaBerlaku === 0;

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

      <KeyboardAvoidingView style={{ flex: 1 }} behavior="padding">
        <ScrollView contentContainerStyle={s.isi} keyboardShouldPersistTaps="handled">
          <View style={s.kartu}>
            <View style={s.lingkaran}>
              <Ikon nama="surel" ukuran={36} warna={warna.utama} tebal={2.2} />
            </View>
            <Text style={s.judul}>Aktivasi Akun</Text>
            <Text style={s.teks}>
              Masukkan 6 digit kode aktivasi yang dikirim ke{' '}
              <Text style={s.tebal}>{surel || 'surel Anda'}</Text>
            </Text>

            {pesan ? <KotakPesan jenis={pesan.jenis} teks={pesan.teks} gaya={{ marginTop: 16 }} /> : null}

            <View style={{ marginTop: 20 }}>
              <KolomOtp
                inputRef={refKode}
                nilai={kode}
                onUbah={(k) => {
                  setKode(k);
                  if (galat) setGalat(null);
                }}
                onLengkap={periksa}
                galat={galat}
                nonaktif={memuat}
              />
            </View>

            <Text style={[s.waktu, kedaluwarsa && { color: warna.galat }]}>
              {mengirim
                ? 'Mengirim kode...'
                : kedaluwarsa
                ? 'Kode sudah kedaluwarsa. Tekan Kirim Ulang Kode.'
                : `Kode berlaku ${formatMenit(sisaBerlaku)} lagi`}
            </Text>

            <Tombol judul="AKTIFKAN AKUN" onPress={() => periksa()} memuat={memuat} gaya={{ marginTop: 16 }} />

            <Tombol
              judul={sisaKirim > 0 ? `Kirim Ulang Kode (${sisaKirim} dtk)` : 'Kirim Ulang Kode'}
              jenis="garis"
              ikon="muatUlang"
              onPress={kirimUlang}
              memuat={mengirim}
              nonaktif={sisaKirim > 0 || memuat}
              gaya={{ marginTop: 10 }}
            />

            <Text style={s.catatan}>
              Tidak menerima surel? Periksa folder Spam atau Promosi, lalu tunggu hingga tombol Kirim Ulang Kode aktif.
            </Text>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const s = StyleSheet.create({
  layar: { flex: 1, backgroundColor: warna.utama },
  kepala: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 8, paddingVertical: 6 },
  tombolKembali: { width: 48, height: 48, alignItems: 'center', justifyContent: 'center' },
  judulKepala: { fontSize: 24, fontWeight: '800', color: warna.putih, marginLeft: 4 },
  judulId: { color: '#8FD3F4' },
  isi: { flexGrow: 1, justifyContent: 'center', padding: 16, paddingBottom: 32 },
  kartu: { backgroundColor: warna.kartu, borderRadius: 16, padding: 20 },
  lingkaran: {
    width: 72, height: 72, borderRadius: 36, backgroundColor: warna.utamaMuda,
    alignItems: 'center', justifyContent: 'center', alignSelf: 'center', marginBottom: 12,
  },
  judul: { fontSize: 22, fontWeight: '700', color: warna.teks, textAlign: 'center' },
  teks: { fontSize: 15, color: warna.teksRedup, textAlign: 'center', marginTop: 8, lineHeight: 22 },
  tebal: { color: warna.teks, fontWeight: '700' },
  waktu: { fontSize: 15, color: warna.teksRedup, textAlign: 'center', marginTop: 14, fontWeight: '600' },
  catatan: { fontSize: 13, color: warna.teksRedup, textAlign: 'center', marginTop: 16, lineHeight: 19 },
});

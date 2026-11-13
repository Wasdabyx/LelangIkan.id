import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { ambilSaya, aturSaatSesiHabis, aturToken, keluar } from './src/api';
import { Logo } from './src/components/Ikon';
import BerandaSementara from './src/screens/BerandaSementara';
import KedatanganScreen from './src/screens/kedatangan/KedatanganScreen';
import PenimbanganScreen from './src/screens/penimbangan/PenimbanganScreen';
import LoginScreen from './src/screens/LoginScreen';
import RegistrasiScreen from './src/screens/RegistrasiScreen';
import { ambilSesi, hapusSesi, simpanSesi } from './src/sesi';
import { warna } from './src/tema';

// id_peran sesuai seeder: 1 = Petugas Pendataan (A1), 2 = Petugas Timbang (A2)
const PERAN_PENDATAAN = 1;
const PERAN_TIMBANG = 2;

export default function App() {
  const [memuat, setMemuat] = useState(true);
  const [pengguna, setPengguna] = useState(null);
  const [pesanMasuk, setPesanMasuk] = useState(null);
  // Week 9: layar untuk tamu (belum masuk): 'masuk' atau 'registrasi'
  const [layarTamu, setLayarTamu] = useState('masuk');
  const [namaAwal, setNamaAwal] = useState('');
  const [infoMasuk, setInfoMasuk] = useState(null);

  useEffect(() => {
    // Bila server membalas 401 di layar mana pun: hapus sesi & kembali ke layar masuk
    aturSaatSesiHabis(async () => {
      aturToken(null);
      await hapusSesi();
      setPesanMasuk('Sesi Anda berakhir. Silakan masuk kembali.');
      setPengguna(null);
    });

    // Saat aplikasi dibuka: pulihkan sesi yang tersimpan (bila ada)
    (async () => {
      const sesi = await ambilSesi();
      if (sesi && sesi.token) {
        aturToken(sesi.token);
        try {
          const hasil = await ambilSaya(); // cek token masih berlaku
          setPengguna(hasil.pengguna);
          await simpanSesi(sesi.token, hasil.pengguna);
        } catch (e) {
          if (e.status !== 401) {
            setPengguna(sesi.pengguna); // server tak terjangkau: pakai data tersimpan
          }
        }
      }
      setMemuat(false);
    })();
  }, []);

  async function saatMasuk(token, dataPengguna) {
    aturToken(token);
    await simpanSesi(token, dataPengguna);
    setPesanMasuk(null);
    setInfoMasuk(null);
    setPengguna(dataPengguna);
  }

  // Registrasi selesai: kembali ke layar Masuk dengan nama pengguna terisi
  function saatDaftarSelesai(namaPengguna) {
    setNamaAwal(namaPengguna);
    setInfoMasuk('Pendaftaran berhasil. Anda dapat masuk setelah akun diaktifkan petugas TPI.');
    setPesanMasuk(null);
    setLayarTamu('masuk');
  }

  async function saatKeluar() {
    try {
      await keluar(); // POST /api/keluar (cabut token di server)
    } catch (e) {
      // tetap keluar walau server tak terjangkau
    }
    await hapusSesi();
    aturToken(null);
    setPesanMasuk(null);
    setPengguna(null);
  }

  let layar;
  if (memuat) {
    layar = (
      <View style={gaya.memuat}>
        <Logo ukuran={84} />
        <ActivityIndicator size="large" color={warna.putih} style={{ marginTop: 20 }} />
        <Text style={gaya.teksMemuat}>Memuat LelangIkan.id...</Text>
      </View>
    );
  } else if (!pengguna && layarTamu === 'registrasi') {
    layar = <RegistrasiScreen onKembali={() => setLayarTamu('masuk')} onSelesai={saatDaftarSelesai} />;
  } else if (!pengguna) {
    layar = (
      <LoginScreen
        key={namaAwal + (infoMasuk || '')}
        onBerhasil={saatMasuk}
        pesanAwal={pesanMasuk}
        infoAwal={infoMasuk}
        namaAwal={namaAwal}
        onDaftar={() => {
          setInfoMasuk(null);
          setLayarTamu('registrasi');
        }}
      />
    );
  } else if (pengguna.id_peran === PERAN_PENDATAAN) {
    layar = <KedatanganScreen pengguna={pengguna} onKeluar={saatKeluar} />;
  } else if (pengguna.id_peran === PERAN_TIMBANG) {
    layar = <PenimbanganScreen pengguna={pengguna} onKeluar={saatKeluar} />;
  } else {
    layar = (
      <BerandaSementara
        pengguna={pengguna}
        pesan="Peran Anda belum tersedia di aplikasi mobile tahap ini. Gunakan aplikasi web LelangIkan.id."
        onKeluar={saatKeluar}
      />
    );
  }

  return (
    <SafeAreaProvider>
      <StatusBar style="light" />
      {layar}
    </SafeAreaProvider>
  );
}

const gaya = StyleSheet.create({
  memuat: { flex: 1, backgroundColor: warna.utama, alignItems: 'center', justifyContent: 'center' },
  teksMemuat: { color: warna.putih, marginTop: 12, fontSize: 16 },
});

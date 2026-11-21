import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { ambilSaya, aturSaatSesiHabis, aturToken, keluar } from './src/api';
import { Logo } from './src/components/Ikon';
import BerandaSementara from './src/screens/BerandaSementara';
import AktivasiScreen from './src/screens/AktivasiScreen';
import KedatanganScreen from './src/screens/kedatangan/KedatanganScreen';
import PembeliScreen from './src/screens/pembeli/PembeliScreen';
import PenimbanganScreen from './src/screens/penimbangan/PenimbanganScreen';
import LoginScreen from './src/screens/LoginScreen';
import RegistrasiScreen from './src/screens/RegistrasiScreen';
import { ambilSesi, hapusSesi, simpanSesi } from './src/sesi';
import { warna } from './src/tema';

// id_peran sesuai seeder: 1 = Petugas Pendataan (A1), 2 = Petugas Timbang (A2),
// 3 = Operator Lelang (A3), 4 = Pembeli (A4), 5 = Kasir TPI (A5), 6 = Pimpinan
//
// Pembagian platform mengikuti SKPL v1.1 Bagian I.B dan DPPL v1.1 Bab 3.1:
//   aplikasi mobile : UC-01 pendataan (A1), UC-02 penimbangan (A2), UC-04 penawaran (A4);
//   antarmuka web   : UC-03/UC-05 operator (A3), UC-06/UC-07/UC-08 kasir (A5),
//                     UC-09 sampai UC-12 pimpinan (A6) dan administrator (A7).
// Peran A3, A5, dan A6 karena itu tidak lagi memiliki layar di aplikasi mobile;
// layar mereka dihapus dan pengguna diarahkan ke antarmuka web.
const PERAN_PENDATAAN = 1;
const PERAN_TIMBANG = 2;
const PERAN_OPERATOR = 3;
const PERAN_PEMBELI = 4; // UC-04 penawaran real-time
const PERAN_KASIR = 5;
const PERAN_PIMPINAN = 6;

// Peran yang dilayani antarmuka web (SKPL v1.1 I.B). Dipakai untuk menolak masuk di
// aplikasi mobile; API tetap menerima token mereka seperti biasa (SKPL Tabel IV.6).
const PERAN_WEB = [PERAN_OPERATOR, PERAN_KASIR, PERAN_PIMPINAN];

export default function App() {
  const [memuat, setMemuat] = useState(true);
  const [pengguna, setPengguna] = useState(null);
  const [pesanMasuk, setPesanMasuk] = useState(null);
  // Week 9: layar untuk tamu (belum masuk): 'masuk', 'registrasi', atau 'aktivasi' (Tugas 2)
  const [layarTamu, setLayarTamu] = useState('masuk');
  const [dataAktivasi, setDataAktivasi] = useState(null); // { namaPengguna, kataSandi, info }
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
          if (PERAN_WEB.includes(hasil.pengguna?.id_peran)) {
            // Sesi lama milik peran web (mis. dari versi aplikasi sebelumnya): buang.
            aturToken(null);
            await hapusSesi();
            setPesanMasuk(
              'Peran ' + (hasil.pengguna?.nama_peran || 'ini') +
                ' dilayani antarmuka web LelangIkan.id. Silakan masuk dengan akun petugas atau pembeli.'
            );
          } else {
            setPengguna(hasil.pengguna);
            await simpanSesi(sesi.token, hasil.pengguna);
          }
        } catch (e) {
          if (e.status !== 401) {
            setPengguna(sesi.pengguna); // server tak terjangkau: pakai data tersimpan
          }
        }
      }
      setMemuat(false);
    })();
  }, []);

  /**
   * Penolakan peran web ditangani SEBELUM token diterbitkan diperiksa, yaitu di layar masuk
   * (LoginScreen menerima prop tolakPengguna). Bila ditangani di sini, me-render ulang App
   * terjadi saat LoginScreen masih memegang state `memuat=true`, sehingga indikator berhenti
   * sendiri dan pesan galat ikut hilang. Jadi fungsi ini hanya untuk peran yang dilayani mobile.
   */
  async function saatMasuk(token, dataPengguna) {
    aturToken(token);
    await simpanSesi(token, dataPengguna);
    setPesanMasuk(null);
    setInfoMasuk(null);
    setDataAktivasi(null);
    setLayarTamu('masuk');
    setPengguna(dataPengguna);
  }

  // Tugas 2: registrasi selesai / akun belum aktivasi -> layar Aktivasi (kode 6 digit dari surel)
  function bukaAktivasi(data) {
    setNamaAwal(data.namaPengguna);
    setDataAktivasi(data);
    setPesanMasuk(null);
    setLayarTamu('aktivasi');
  }

  function batalAktivasi() {
    setDataAktivasi(null);
    setInfoMasuk('Akun belum aktif. Masuk kembali kapan saja untuk melanjutkan aktivasi.');
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
    layar = <RegistrasiScreen onKembali={() => setLayarTamu('masuk')} onSelesai={bukaAktivasi} />;
  } else if (!pengguna && layarTamu === 'aktivasi' && dataAktivasi) {
    layar = (
      <AktivasiScreen
        namaPengguna={dataAktivasi.namaPengguna}
        kataSandi={dataAktivasi.kataSandi}
        info={dataAktivasi.info}
        onBerhasil={saatMasuk}
        onKembali={batalAktivasi}
      />
    );
  } else if (!pengguna) {
    layar = (
      <LoginScreen
        key={namaAwal + (infoMasuk || '') + (pesanMasuk || '')}
        onBerhasil={saatMasuk}
        tolakPengguna={PERAN_WEB}
        pesanAwal={pesanMasuk}
        infoAwal={infoMasuk}
        namaAwal={namaAwal}
        onPerluAktivasi={bukaAktivasi}
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
  } else if (pengguna.id_peran === PERAN_PEMBELI) {
    layar = <PembeliScreen pengguna={pengguna} onKeluar={saatKeluar} />;
  } else if (
    pengguna.id_peran === PERAN_OPERATOR ||
    pengguna.id_peran === PERAN_KASIR ||
    pengguna.id_peran === PERAN_PIMPINAN
  ) {
    // Jaring pengaman: peran web sudah ditolak saat masuk, cabang ini hanya untuk
    // keadaan tak terduga (mis. data sesi diubah manual).
    layar = (
      <BerandaSementara
        pengguna={pengguna}
        pesan="Peran ini dilayani antarmuka web LelangIkan.id, bukan aplikasi mobile."
        onKeluar={saatKeluar}
      />
    );
  } else {
    layar = (
      <BerandaSementara
        pengguna={pengguna}
        pesan="Peran Anda belum tersedia di aplikasi mobile. Gunakan antarmuka web LelangIkan.id."
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
  teksMemuat: { color: warna.putih, marginTop: 12, fontSize: 15 },
});

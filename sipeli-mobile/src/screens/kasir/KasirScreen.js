import { useEffect, useState } from 'react';
import { BackHandler, StyleSheet } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { BilahAtas, DialogKonfirmasi } from '../../components/Dasar';
import { TabAtas } from '../../components/Lelang';
import { warna } from '../../tema';
import DaftarTagihanKasir from './DaftarTagihanKasir';
import FormBayar from './FormBayar';
import PeriksaNota from './PeriksaNota';
import KelolaSaldo from './KelolaSaldo';

/**
 * Layar utama Kasir TPI (A5) - UC-06 Pembayaran dan Penyelesaian Transaksi (simulasi, tanpa payment gateway).
 * Tab: Tagihan | Lunas Hari Ini | Periksa Nota.  mode 'bayar' = rincian + pencatatan pembayaran.
 */
export default function KasirScreen({ pengguna, onKeluar }) {
  const [tab, setTab] = useState('tagihan');
  const [tagihan, setTagihan] = useState(null); // rincian transaksi yang dibuka
  const [tanyaKeluar, setTanyaKeluar] = useState(false);

  useEffect(() => {
    if (!tagihan) return undefined;
    const sub = BackHandler.addEventListener('hardwareBackPress', () => {
      setTagihan(null);
      return true;
    });
    return () => sub.remove();
  }, [tagihan]);

  let isi;
  if (tagihan) {
    isi = <FormBayar awal={tagihan} onKembali={(lunas) => { setTagihan(null); if (lunas) setTab('lunas'); }} />;
  } else {
    isi = (
      <>
        <TabAtas
          daftar={[['tagihan', 'Tagihan'], ['lunas', 'Lunas Hari Ini'], ['saldo', 'Saldo'], ['nota', 'Periksa Nota']]}
          aktif={tab}
          onGanti={setTab}
        />
        {tab === 'nota' ? <PeriksaNota /> : tab === 'saldo' ? <KelolaSaldo /> : <DaftarTagihanKasir tab={tab} onBuka={setTagihan} />}
      </>
    );
  }

  return (
    <SafeAreaView style={s.layar} edges={['top', 'bottom']}>
      <BilahAtas pengguna={pengguna} onKeluar={() => setTanyaKeluar(true)} />
      {isi}
      <DialogKonfirmasi
        tampil={tanyaKeluar}
        judul="Keluar dari aplikasi?"
        pesan="Pastikan semua pembayaran sudah dicatat."
        teksYa="Keluar"
        bahaya
        onYa={() => {
          setTanyaKeluar(false);
          onKeluar();
        }}
        onTidak={() => setTanyaKeluar(false)}
      />
    </SafeAreaView>
  );
}

const s = StyleSheet.create({
  layar: { flex: 1, backgroundColor: warna.utama },
});

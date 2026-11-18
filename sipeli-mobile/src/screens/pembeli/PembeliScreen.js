import { useEffect, useState } from 'react';
import { BackHandler, StyleSheet } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { pembeli } from '../../api';
import { BilahAtas, DialogKonfirmasi } from '../../components/Dasar';
import { TabAtas } from '../../components/Lelang';
import { warna } from '../../tema';
import BuktiPemesanan from './BuktiPemesanan';
import DaftarLelangPembeli from './DaftarLelangPembeli';
import DaftarLotPembeli from './DaftarLotPembeli';
import DetailTagihan from './DetailTagihan';
import OtpPemesanan from './OtpPemesanan';
import RuangLelang from './RuangLelang';

/**
 * Layar utama Pembeli (A4).
 * Tab: Lot (pesan dengan OTP, Tugas 2) | Pesanan | Lelang (UC-04) | Tagihan (hasil UC-05)
 * mode: 'daftar' | 'otp' | 'bukti' | 'ruang' | 'tagihan'
 */
export default function PembeliScreen({ pengguna, onKeluar }) {
  const [mode, setMode] = useState('daftar');
  const [tab, setTab] = useState('lot');
  const [lotDipilih, setLotDipilih] = useState(null);
  const [infoOtp, setInfoOtp] = useState(null);
  const [pemesanan, setPemesanan] = useState(null);
  const [buktiBaru, setBuktiBaru] = useState(false);
  const [idSesi, setIdSesi] = useState(null);
  const [idTagihan, setIdTagihan] = useState(null);
  const [tanyaKeluar, setTanyaKeluar] = useState(false);

  useEffect(() => {
    if (mode === 'daftar') return undefined;
    const sub = BackHandler.addEventListener('hardwareBackPress', () => {
      kembaliKeDaftar();
      return true;
    });
    return () => sub.remove();
  }, [mode]);

  function kembaliKeDaftar(tabTujuan) {
    if (tabTujuan) setTab(tabTujuan);
    setMode('daftar');
    setLotDipilih(null);
    setInfoOtp(null);
    setIdSesi(null);
    setIdTagihan(null);
  }

  /** Tugas 2 langkah 1: "Ikut Lelang Lot Ini" -> OTP dikirim ke surel */
  async function mintaOtp(lot) {
    const hasil = await pembeli.mintaOtp(lot.id_lot);
    setLotDipilih(hasil.lot);
    setInfoOtp(hasil);
    setMode('otp');
  }

  let isi;
  if (mode === 'otp' && lotDipilih) {
    isi = (
      <OtpPemesanan
        lot={lotDipilih}
        info={infoOtp}
        onBerhasil={(data) => {
          setPemesanan(data);
          setBuktiBaru(true);
          setMode('bukti');
        }}
        onBatal={() => kembaliKeDaftar()}
      />
    );
  } else if (mode === 'bukti' && pemesanan) {
    isi = <BuktiPemesanan data={pemesanan} baru={buktiBaru} onKembali={() => kembaliKeDaftar(buktiBaru ? 'pesanan' : undefined)} />;
  } else if (mode === 'ruang' && idSesi) {
    isi = (
      <RuangLelang
        idSesi={idSesi}
        onKembali={() => kembaliKeDaftar('lelang')}
        onLihatTagihan={(id) => {
          setIdTagihan(id);
          setMode('tagihan');
        }}
      />
    );
  } else if (mode === 'tagihan' && idTagihan) {
    isi = <DetailTagihan idTransaksi={idTagihan} onKembali={() => kembaliKeDaftar('tagihan')} />;
  } else {
    isi = (
      <>
        <TabAtas
          daftar={[['lot', 'Lot'], ['pesanan', 'Pesanan'], ['lelang', 'Lelang'], ['tagihan', 'Tagihan']]}
          aktif={tab}
          onGanti={setTab}
        />
        {tab === 'lot' || tab === 'pesanan' ? (
          <DaftarLotPembeli
            tab={tab}
            onPesan={mintaOtp}
            onLihatBukti={(data) => {
              setPemesanan(data);
              setBuktiBaru(false);
              setMode('bukti');
            }}
          />
        ) : (
          <DaftarLelangPembeli
            tab={tab}
            onMasukRuang={(id) => {
              setIdSesi(id);
              setMode('ruang');
            }}
            onBukaTagihan={(id) => {
              setIdTagihan(id);
              setMode('tagihan');
            }}
          />
        )}
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
        pesan="Tawaran yang sudah diterima tetap berlaku walaupun Anda keluar."
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

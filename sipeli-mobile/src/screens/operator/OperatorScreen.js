import { useEffect, useState } from 'react';
import { BackHandler, StyleSheet } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { BilahAtas, DialogKonfirmasi } from '../../components/Dasar';
import { TabAtas } from '../../components/Lelang';
import { warna } from '../../tema';
import AntreanLot from './AntreanLot';
import FormBukaSesi from './FormBukaSesi';
import PantauSesi from './PantauSesi';

/**
 * Layar utama Operator Lelang (A3) - UC-03 Pembukaan Sesi dan UC-05 Penutupan Sesi.
 * mode: 'daftar' (antrean / sesi hari ini) -> 'atur' (durasi & kenaikan) -> 'pantau' (sesi berjalan)
 */
export default function OperatorScreen({ pengguna, onKeluar }) {
  const [mode, setMode] = useState('daftar');
  const [tab, setTab] = useState('antrean');
  const [lot, setLot] = useState(null);
  const [idSesi, setIdSesi] = useState(null);
  const [tanyaKeluar, setTanyaKeluar] = useState(false);

  useEffect(() => {
    if (mode === 'daftar') return undefined;
    const sub = BackHandler.addEventListener('hardwareBackPress', () => {
      kembali();
      return true;
    });
    return () => sub.remove();
  }, [mode]);

  function kembali(tabTujuan) {
    if (tabTujuan) setTab(tabTujuan);
    setMode('daftar');
    setLot(null);
    setIdSesi(null);
  }

  let isi;
  if (mode === 'atur' && lot) {
    isi = (
      <FormBukaSesi
        lot={lot}
        onBatal={() => kembali()}
        onDibuka={(sesi) => {
          setIdSesi(sesi.id_sesi);
          setMode('pantau');
        }}
      />
    );
  } else if (mode === 'pantau' && idSesi) {
    isi = <PantauSesi idSesi={idSesi} onKembali={() => kembali('sesi')} />;
  } else {
    isi = (
      <>
        <TabAtas
          daftar={[['antrean', 'Antrean Lot'], ['sesi', 'Sesi Hari Ini']]}
          aktif={tab}
          onGanti={setTab}
        />
        <AntreanLot
          tab={tab}
          idTpi={pengguna.id_tpi}
          onPilihLot={(l) => {
            setLot(l);
            setMode('atur');
          }}
          onBukaSesi={(id) => {
            setIdSesi(id);
            setMode('pantau');
          }}
        />
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
        pesan="Sesi lelang yang sedang berjalan tetap berlanjut dan akan ditutup otomatis saat waktunya habis."
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

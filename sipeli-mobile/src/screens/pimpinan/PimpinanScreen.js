import { useState } from 'react';
import { StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { BilahAtas, DialogKonfirmasi } from '../../components/Dasar';
import { TabAtas } from '../../components/Lelang';
import { warna } from '../../tema';
import DashboardPimpinan from './DashboardPimpinan';
import PersetujuanSaldo from './PersetujuanSaldo';

export default function PimpinanScreen({ pengguna, onKeluar }) {
  const [tab, setTab] = useState('ringkasan');
  const [tanyaKeluar, setTanyaKeluar] = useState(false);

  return (
    <SafeAreaView style={s.layar} edges={['top', 'bottom']}>
      <BilahAtas pengguna={pengguna} onKeluar={() => setTanyaKeluar(true)} />
      <TabAtas
        daftar={[
          ['ringkasan', 'Ringkasan'],
          ['persetujuan', 'Persetujuan saldo'],
        ]}
        aktif={tab}
        onGanti={setTab}
      />
      <View style={s.isi}>
        {tab === 'ringkasan'
          ? <DashboardPimpinan />
          : <PersetujuanSaldo pengguna={pengguna} onKeluar={onKeluar} tampilkanBilah={false} />}
      </View>
      <DialogKonfirmasi
        tampil={tanyaKeluar}
        judul="Keluar dari aplikasi?"
        pesan="Pastikan tidak ada keputusan saldo yang belum disimpan."
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
  isi: { flex: 1, backgroundColor: warna.latar },
});

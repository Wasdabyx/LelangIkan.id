import { useEffect, useState } from 'react';
import { BackHandler, StyleSheet } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { api } from '../../api';
import { BilahAtas, DialogKonfirmasi } from '../../components/Dasar';
import { warna } from '../../tema';
import DetailKedatangan from './DetailKedatangan';
import FormTimbang from './FormTimbang';
import LabelLot from './LabelLot';
import PilihKedatangan from './PilihKedatangan';

const POLA_KEDATANGAN = /^(KDT-?)?\d{1,9}$/; // KDT-00001, KDT1, atau 1
const POLA_LOT = /^[A-Z]{2,5}-\d{6}-\d{4}$/; // PLR-261026-0001

/**
 * Layar utama Petugas Timbang (A2) - UC-02 Penimbangan & Label Lot
 * mode: 'pilih' -> 'kedatangan' -> 'timbang' -> 'label'
 */
export default function PenimbanganScreen({ pengguna, onKeluar }) {
  const [mode, setMode] = useState('pilih');
  const [kedatangan, setKedatangan] = useState(null);
  const [baris, setBaris] = useState(null);
  const [lot, setLot] = useState(null);
  const [lotBaru, setLotBaru] = useState(false);
  const [asalLabel, setAsalLabel] = useState('kedatangan'); // 'kedatangan' | 'pilih'
  const [tanyaKeluar, setTanyaKeluar] = useState(false);

  // Tombol kembali HP (mode timbang ditangani FormTimbang sendiri)
  useEffect(() => {
    if (mode === 'pilih' || mode === 'timbang') return undefined;
    const sub = BackHandler.addEventListener('hardwareBackPress', () => {
      if (mode === 'kedatangan') setMode('pilih');
      else if (mode === 'label') setMode(asalLabel);
      return true;
    });
    return () => sub.remove();
  }, [mode, asalLabel]);

  async function muatKedatangan(kode) {
    const hasil = await api.get(`/penimbangan/${encodeURIComponent(kode)}`);
    setKedatangan(hasil.data);
    return hasil.data;
  }

  /** Dari pindai QR / ketik nomor / ketuk daftar */
  async function buka(teks) {
    const kode = String(teks).trim().toUpperCase();
    if (POLA_LOT.test(kode)) {
      // QR label lot -> tampilkan label (untuk cetak ulang)
      const hasil = await api.get(`/lot/${encodeURIComponent(kode)}`);
      setLot(hasil.data);
      setLotBaru(false);
      setAsalLabel('pilih');
      setMode('label');
      return;
    }
    if (!POLA_KEDATANGAN.test(kode)) {
      throw new Error(`Kode "${teks}" tidak dikenali. Gunakan QR kedatangan (contoh KDT-00001) atau label lot.`);
    }
    await muatKedatangan(kode);
    setMode('kedatangan');
  }

  function saatLotDibuat(dataLot) {
    setLot(dataLot);
    setLotBaru(true);
    setAsalLabel('kedatangan');
    setMode('label');
    // segarkan daftar baris di belakang layar
    muatKedatangan(kedatangan.nomor_kedatangan).catch(() => {});
  }

  const masihAdaBaris = kedatangan?.tangkapan?.some((t) => t.status === 'BELUM_DITIMBANG');

  return (
    <SafeAreaView style={s.layar} edges={['top', 'bottom']}>
      <BilahAtas pengguna={pengguna} onKeluar={() => setTanyaKeluar(true)} />

      {mode === 'pilih' && <PilihKedatangan onBuka={buka} />}

      {mode === 'kedatangan' && kedatangan && (
        <DetailKedatangan
          kedatangan={kedatangan}
          onMuatUlang={() => muatKedatangan(kedatangan.nomor_kedatangan)}
          onTimbang={(t) => {
            setBaris(t);
            setMode('timbang');
          }}
          onLihatLabel={(l) => {
            setLot(l);
            setLotBaru(false);
            setAsalLabel('kedatangan');
            setMode('label');
          }}
          onKembali={() => setMode('pilih')}
        />
      )}

      {mode === 'timbang' && kedatangan && baris && (
        <FormTimbang
          kedatangan={kedatangan}
          baris={baris}
          onBatal={() => setMode('kedatangan')}
          onSelesai={saatLotDibuat}
        />
      )}

      {mode === 'label' && lot && (
        <LabelLot
          key={lot.kode_lot}
          lot={lot}
          baru={lotBaru}
          namaTpi={pengguna.nama_tpi}
          teksKembali={
            asalLabel === 'pilih'
              ? 'Kembali'
              : masihAdaBaris
                ? 'Timbang Baris Berikutnya'
                : 'Kembali ke Daftar Baris'
          }
          onKembali={() => setMode(asalLabel)}
        />
      )}

      <DialogKonfirmasi
        tampil={tanyaKeluar}
        judul="Keluar dari aplikasi?"
        pesan={mode === 'timbang' ? 'Berat yang sudah dicatat tetapi belum dibuat lot akan hilang.' : 'Anda perlu masuk kembali untuk memakai aplikasi.'}
        teksYa="Ya, Keluar"
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
  layar: { flex: 1, backgroundColor: warna.latar },
});

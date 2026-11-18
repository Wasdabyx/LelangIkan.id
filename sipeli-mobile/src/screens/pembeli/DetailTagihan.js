import { useCallback, useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { lelang } from '../../api';
import { KotakPesan, Tombol } from '../../components/Dasar';
import FormGulir from '../../components/FormGulir';
import KodeQR from '../../components/KodeQR';
import RincianTagihan from '../../components/RincianTagihan';
import { Bagian } from '../../components/Tata';
import { bagikanNotaPdf } from '../../cetakNota';
import { usePolling } from '../../polling';
import { warna } from '../../tema';

/** Tagihan pembeli. Diperbarui tiap 3 detik sehingga status berubah sendiri saat kasir mencatat pembayaran. */
export default function DetailTagihan({ idTransaksi, onKembali }) {
  const [t, setT] = useState(null);
  const [galat, setGalat] = useState(null);
  const [membagikan, setMembagikan] = useState(false);

  const muat = useCallback(async () => {
    try {
      const hasil = await lelang.detailTagihan(idTransaksi);
      setT(hasil.data);
      setGalat(null);
    } catch (e) {
      if (e.status !== 401) setGalat(e.message);
    }
  }, [idTransaksi]);
  usePolling(muat, 3000, !t || t.status !== 'LUNAS');

  async function bagikan() {
    setMembagikan(true);
    try {
      await bagikanNotaPdf(t);
    } catch (e) {
      setGalat(e.message);
    } finally {
      setMembagikan(false);
    }
  }

  if (!t) {
    return (
      <View style={s.tengah}>
        {galat ? <KotakPesan teks={galat} /> : <ActivityIndicator size="large" color={warna.utama} />}
        <Tombol judul="Kembali" jenis="teks" onPress={onKembali} gaya={{ marginTop: 12 }} />
      </View>
    );
  }
  const lunas = t.status === 'LUNAS';

  return (
    <View style={{ flex: 1, backgroundColor: warna.latar }}>
    <FormGulir gayaIsi={s.isi}>
      <Bagian>
        <RincianTagihan t={t} />
        {galat ? <KotakPesan teks={galat} gaya={{ marginTop: 12 }} /> : null}
        {lunas ? (
          <>
            <View style={s.qr}>
              <KodeQR nilai={t.qr_nota} ukuran={170} />
              <Text style={s.teksQr}>QR verifikasi nota. Tunjukkan saat mengambil ikan.</Text>
            </View>
            <Tombol judul="Simpan / Bagikan Nota PDF" ikon="bagikan" onPress={bagikan} memuat={membagikan} gaya={{ marginTop: 12 }} />
          </>
        ) : (
          <KotakPesan
            jenis="info"
            teks={`Bayar ke Kasir TPI dengan menyebutkan nomor ${t.kode_transaksi}. Pembayaran dapat tunai, transfer bank, atau QRIS.`}
            gaya={{ marginTop: 14 }}
          />
        )}
      </Bagian>
      <View style={{ paddingHorizontal: 16 }}>
        <Tombol judul="Kembali" jenis="garis" onPress={onKembali} />
      </View>
    </FormGulir>
    </View>
  );
}

const s = StyleSheet.create({
  tengah: { flex: 1, backgroundColor: warna.latar, alignItems: 'center', justifyContent: 'center', padding: 16 },
  isi: { paddingBottom: 32 },
  kartu: { backgroundColor: warna.kartu, borderRadius: 6, padding: 16, borderWidth: 1, borderColor: '#E4EBF1' },
  qr: { alignItems: 'center', marginTop: 16, paddingTop: 16, gap: 8, borderTopWidth: 1, borderTopColor: warna.garisHalus },
  teksQr: { fontSize: 12, color: warna.teksRedup, textAlign: 'center' },
});

import { Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { Lencana, Tombol } from '../../components/Dasar';
import KodeQR from '../../components/KodeQR';
import { formatKg } from '../../format';
import { warna } from '../../tema';

/**
 * Jendela QR kedatangan (berukuran besar) untuk dipindai Petugas Timbang (UC-02 langkah 1).
 * Isi QR = nomor kedatangan, contoh "KDT-00001".
 */
export default function LembarQR({ kedatangan, onTutup }) {
  const k = kedatangan;
  return (
    <Modal visible={!!k} transparent animationType="fade" onRequestClose={onTutup} statusBarTranslucent>
      <Pressable style={s.latar} onPress={onTutup} accessibilityLabel="Tutup">
        {k ? (
          // Pressable dalam agar ketukan di kartu tidak menutup jendela
          <Pressable style={s.kartu} onPress={() => {}}>
            <Text style={s.labelKecil}>QR kedatangan</Text>
            <Text style={s.nomor}>{k.nomor_kedatangan}</Text>
            <View style={s.wadahQR}>
              <KodeQR nilai={k.nomor_kedatangan} ukuran={240} />
            </View>
            <Text style={s.kapal}>{k.nama_kapal}</Text>
            <Text style={s.redup}>
              {k.jam ? `${k.jam} - ` : ''}
              {k.jumlah_baris} baris - {formatKg(k.total_berat_estimasi)}
            </Text>
            <View style={{ flexDirection: 'row', justifyContent: 'center', marginTop: 8 }}>
              <Lencana status={k.status} />
            </View>
            <Text style={s.petunjuk}>Tunjukkan QR ini ke Petugas Timbang untuk dipindai.</Text>
            <Tombol judul="Tutup" jenis="garis" onPress={onTutup} gaya={{ marginTop: 16, alignSelf: 'stretch' }} />
          </Pressable>
        ) : null}
      </Pressable>
    </Modal>
  );
}

const s = StyleSheet.create({
  latar: {
    flex: 1, backgroundColor: 'rgba(16,42,67,0.6)',
    alignItems: 'center', justifyContent: 'center', padding: 16,
  },
  kartu: {
    width: '100%', maxWidth: 380, backgroundColor: warna.putih, borderRadius: 6,
    padding: 16, alignItems: 'center', borderWidth: 1, borderColor: '#E4EBF1' },
  labelKecil: { fontSize: 11, fontWeight: '700', color: warna.teksRedup },
  nomor: { fontSize: 22, fontWeight: '700', color: warna.utama, marginTop: 4 },
  wadahQR: {
    marginTop: 12, padding: 8, borderRadius: 4, borderWidth: 1, borderColor: '#E4EBF1', backgroundColor: warna.putih,
  },
  kapal: { fontSize: 16, fontWeight: '700', color: warna.teks, marginTop: 12, textAlign: 'center' },
  redup: { fontSize: 14, color: warna.teksRedup, marginTop: 2, textAlign: 'center' },
  petunjuk: { fontSize: 14, color: warna.teks, marginTop: 14, textAlign: 'center', lineHeight: 19 },
});

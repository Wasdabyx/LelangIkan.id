import { Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Ikon from '../components/Ikon';
import { ukuran, warna } from '../tema';

/**
 * Layar SEMENTARA setelah login.
 * Akan diganti oleh Form Kedatangan (Langkah 10) dan Form Penimbangan (Langkah 11).
 */
export default function BerandaSementara({ pengguna, judul, pesan, onKeluar }) {
  return (
    <SafeAreaView style={gaya.layar} edges={['top', 'bottom']}>
      <View style={gaya.bilah}>
        <View style={{ flex: 1 }}>
          <Text style={gaya.nama}>{pengguna.nama_lengkap}</Text>
          <Text style={gaya.peran}>
            {pengguna.nama_peran}
            {pengguna.nama_tpi ? ` - ${pengguna.nama_tpi}` : ''}
          </Text>
        </View>
        <Pressable onPress={onKeluar} style={gaya.tombolKeluar}>
          <Text style={gaya.teksKeluar}>Keluar</Text>
        </Pressable>
      </View>

      <View style={gaya.isi}>
        <View style={gaya.kartu}>
          <View style={gaya.centang}>
            <Ikon nama="centang" ukuran={40} warna={warna.sukses} tebal={3} />
          </View>
          <Text style={gaya.judul}>Login berhasil</Text>
          {judul ? (
            <Text style={gaya.teks}>
              Tampilan <Text style={{ fontWeight: '600' }}>{judul}</Text> akan dibuat pada langkah berikutnya.
            </Text>
          ) : (
            <Text style={gaya.teks}>{pesan}</Text>
          )}
        </View>
      </View>
    </SafeAreaView>
  );
}

const gaya = StyleSheet.create({
  layar: { flex: 1, backgroundColor: warna.latar },
  bilah: {
    backgroundColor: warna.utama, paddingHorizontal: 16, paddingVertical: 14,
    flexDirection: 'row', alignItems: 'center',
  },
  nama: { color: warna.putih, fontSize: ukuran.hurufSedang, fontWeight: '600' },
  peran: { color: '#D9E8F0', fontSize: 13, marginTop: 2 },
  tombolKeluar: {
    borderWidth: 1, borderColor: warna.putih, borderRadius: 4,
    paddingHorizontal: 14, minHeight: 44, justifyContent: 'center',
  },
  teksKeluar: { color: warna.putih, fontWeight: '600', fontSize: 14 },
  isi: { flex: 1, padding: 16, justifyContent: 'center' },
  kartu: { backgroundColor: warna.kartu, borderRadius: 6, padding: 24, alignItems: 'center', borderWidth: 1, borderColor: '#E4EBF1' },
  centang: {
    width: 72, height: 72, borderRadius: 36, backgroundColor: warna.suksesLatar,
    alignItems: 'center', justifyContent: 'center',
  },
  judul: { fontSize: 18, fontWeight: '600', color: warna.teks, marginTop: 8 },
  teks: { fontSize: ukuran.hurufNormal, color: warna.teksRedup, textAlign: 'center', marginTop: 8, lineHeight: 20 },
});

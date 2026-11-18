import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { Tombol } from '../../components/Dasar';
import Ikon from '../../components/Ikon';
import KodeQR from '../../components/KodeQR';
import { formatKg, formatRupiah, waktuPendek } from '../../format';
import { ukuran, warna } from '../../tema';

/** Tugas 2 - Bukti pemesanan: kode pemesanan + QR untuk ditunjukkan ke petugas TPI. */
export default function BuktiPemesanan({ data, baru, onKembali }) {
  return (
    <ScrollView style={s.wadah} contentContainerStyle={s.isi}>
      <View style={s.kartu}>
        {baru ? (
          <>
            <View style={s.lingkaran}>
              <Ikon nama="centang" ukuran={40} warna={warna.sukses} tebal={2.6} />
            </View>
            <Text style={s.judul}>Pemesanan Berhasil</Text>
            <Text style={s.teks}>Anda terdaftar sebagai peserta lelang lot ini. Bukti juga dikirim ke surel Anda.</Text>
          </>
        ) : (
          <Text style={s.judul}>Bukti Pemesanan</Text>
        )}

        <Text style={s.labelKode}>KODE PEMESANAN</Text>
        <Text style={s.kode} selectable>{data.kode_pemesanan}</Text>

        <View style={s.qr}>
          <KodeQR nilai={data.kode_pemesanan} ukuran={180} />
        </View>

        <View style={s.ringkasan}>
          <Baris label="Nomor lot" nilai={data.kode_lot} tebal />
          <Baris label="Jenis ikan" nilai={`${data.nama_jenis} (Grade ${data.kode_grade})`} />
          <Baris label="Berat" nilai={formatKg(data.berat_aktual)} />
          <Baris label="Harga pembukaan" nilai={`${formatRupiah(data.harga_pembukaan)}/kg`} />
          <Baris label="Kapal" nilai={data.nama_kapal} />
          <Baris label="Waktu pemesanan" nilai={waktuPendek(data.waktu_pemesanan)} />
        </View>

        <Text style={s.catatan}>
          Kode ini adalah izin Anda untuk menawar. Saat operator membuka sesi, lot muncul di tab Lelang. Harga akhir ditentukan melalui lelang.
        </Text>

        <Tombol judul={baru ? 'Lihat Pemesanan Saya' : 'Kembali'} onPress={onKembali} gaya={{ marginTop: 16 }} />
      </View>
    </ScrollView>
  );
}

function Baris({ label, nilai, tebal }) {
  return (
    <View style={s.baris}>
      <Text style={s.label}>{label}</Text>
      <Text style={[s.nilai, tebal && { fontWeight: '800' }]}>{nilai}</Text>
    </View>
  );
}

const s = StyleSheet.create({
  wadah: { flex: 1, backgroundColor: warna.latar },
  isi: { padding: 16, paddingBottom: 32 },
  kartu: { backgroundColor: warna.kartu, borderRadius: 16, padding: 20 },
  lingkaran: {
    width: 72, height: 72, borderRadius: 36, backgroundColor: warna.suksesLatar,
    alignItems: 'center', justifyContent: 'center', alignSelf: 'center', marginBottom: 10,
  },
  judul: { fontSize: 22, fontWeight: '700', color: warna.teks, textAlign: 'center' },
  teks: { fontSize: 15, color: warna.teksRedup, textAlign: 'center', marginTop: 6, lineHeight: 22 },
  labelKode: { fontSize: 13, color: warna.teksRedup, textAlign: 'center', marginTop: 18, letterSpacing: 1 },
  kode: { fontSize: 28, fontWeight: '800', color: warna.sukses, textAlign: 'center', marginTop: 4, letterSpacing: 1 },
  qr: { alignItems: 'center', marginTop: 14 },
  ringkasan: { backgroundColor: warna.latar, borderRadius: ukuran.radius, padding: 12, marginTop: 16, gap: 8 },
  baris: { flexDirection: 'row', justifyContent: 'space-between', gap: 12 },
  label: { fontSize: 14, color: warna.teksRedup },
  nilai: { fontSize: 14, color: warna.teks, fontWeight: '600', flexShrink: 1, textAlign: 'right' },
  catatan: { fontSize: 13, color: warna.teksRedup, textAlign: 'center', marginTop: 14, lineHeight: 19 },
});

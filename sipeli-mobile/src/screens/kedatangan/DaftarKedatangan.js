import { useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { api } from '../../api';
import { KotakPesan, Lencana, Tombol } from '../../components/Dasar';
import Ikon from '../../components/Ikon';
import { formatKg, tanggalPanjang } from '../../format';
import { ukuran, warna } from '../../tema';
import LembarQR from './LembarQR';

/**
 * Alur utama (2): daftar kedatangan hari ini beserta statusnya.
 * Ketuk kartu: DRAF -> lengkapi draf (alur A2), lainnya -> tampilkan QR.
 */
export default function DaftarKedatangan({ onTambah, onLengkapi }) {
  const [data, setData] = useState(null);
  const [galat, setGalat] = useState(null);
  const [menyegarkan, setMenyegarkan] = useState(false);
  const [qr, setQr] = useState(null); // kedatangan yang QR-nya sedang ditampilkan

  async function muat() {
    setGalat(null);
    try {
      const hasil = await api.get('/kedatangan');
      setData(hasil.data);
    } catch (e) {
      if (e.status !== 401) setGalat(e.message);
    }
  }

  async function segarkan() {
    setMenyegarkan(true);
    await muat();
    setMenyegarkan(false);
  }

  useEffect(() => {
    muat();
  }, []);

  const total = (data || []).reduce((n, k) => n + Number(k.total_berat_estimasi), 0);

  return (
    <ScrollView
      contentContainerStyle={s.isi}
      refreshControl={<RefreshControl refreshing={menyegarkan} onRefresh={segarkan} colors={[warna.utama]} />}
    >
      <View style={s.barisJudul}>
        <View style={{ flex: 1 }}>
          <Text style={s.judul}>Kedatangan Hari Ini</Text>
          <Text style={s.tanggal}>{tanggalPanjang(new Date())}</Text>
        </View>
        <Pressable onPress={segarkan} style={s.tombolSegar} accessibilityLabel="Muat ulang">
          <Ikon nama="muatUlang" ukuran={22} warna={warna.utama} />
        </Pressable>
      </View>

      {/* Ringkasan */}
      <View style={s.ringkasan}>
        <View style={s.kotakAngka}>
          <Text style={s.angka}>{data ? data.length : '-'}</Text>
          <Text style={s.labelAngka}>Kapal</Text>
        </View>
        <View style={s.pemisah} />
        <View style={s.kotakAngka}>
          <Text style={s.angka}>{data ? formatKg(total) : '-'}</Text>
          <Text style={s.labelAngka}>Total estimasi</Text>
        </View>
      </View>

      <Tombol judul="Tambah Kedatangan" ikon="tambah" onPress={onTambah} gaya={{ marginBottom: 20 }} />

      {galat ? (
        <View>
          <KotakPesan teks={galat} />
          <Tombol judul="Coba Lagi" jenis="garis" ikon="muatUlang" onPress={segarkan} gaya={{ marginTop: 12 }} />
        </View>
      ) : !data ? (
        <ActivityIndicator size="large" color={warna.utama} style={{ marginTop: 24 }} />
      ) : data.length === 0 ? (
        <View style={s.kosong}>
          <View style={s.lingkaranKosong}>
            <Ikon nama="kapal" ukuran={48} warna={warna.teksRedup} tebal={1.6} />
          </View>
          <Text style={s.judulKosong}>Belum ada kedatangan hari ini</Text>
          <Text style={s.teksKosong}>Tekan "Tambah Kedatangan" saat kapal bersandar.</Text>
        </View>
      ) : (
        data.map((k) => {
          const draf = k.status === 'DRAF';
          return (
            <Pressable
              key={k.id_kedatangan}
              onPress={() => (draf ? onLengkapi(k) : setQr(k))}
              style={({ pressed }) => [s.kartu, pressed && { backgroundColor: warna.abu }]}
              accessibilityRole="button"
              accessibilityLabel={`${k.nomor_kedatangan} ${k.nama_kapal}. ${draf ? 'Ketuk untuk melengkapi draf' : 'Ketuk untuk menampilkan QR'}`}
            >
              <View style={s.barisAtas}>
                <Text style={s.nomor}>{k.nomor_kedatangan}</Text>
                <Lencana status={k.status} />
              </View>
              <Text style={s.namaKapal}>{k.nama_kapal}</Text>
              <Text style={s.redup}>{k.nomor_registrasi} - Kapten {k.nama_kapten}</Text>
              <View style={s.barisBawah}>
                <View style={s.info}>
                  <Ikon nama="jam" ukuran={16} warna={warna.teksRedup} />
                  <Text style={s.teksInfo}>{k.jam}</Text>
                </View>
                <View style={s.info}>
                  <Ikon nama="ikan" ukuran={16} warna={warna.teksRedup} />
                  <Text style={s.teksInfo}>{k.jumlah_baris} baris</Text>
                </View>
                <Text style={s.berat}>{formatKg(k.total_berat_estimasi)}</Text>
              </View>
              <View style={s.barisQR}>
                <Ikon nama={draf ? 'pensil' : 'qr'} ukuran={18} warna={warna.utama} />
                <Text style={s.teksQR}>{draf ? 'Lengkapi Draf' : 'Tampilkan QR'}</Text>
              </View>
            </Pressable>
          );
        })
      )}

      <LembarQR kedatangan={qr} onTutup={() => setQr(null)} />
    </ScrollView>
  );
}

const s = StyleSheet.create({
  isi: { padding: 16, paddingBottom: 40 },
  barisJudul: { flexDirection: 'row', alignItems: 'center', marginBottom: 12 },
  judul: { fontSize: 24, fontWeight: '800', color: warna.teks },
  tanggal: { fontSize: 15, color: warna.teksRedup, marginTop: 2 },
  tombolSegar: {
    width: 48, height: 48, borderRadius: 24, borderWidth: 1.5, borderColor: warna.garis,
    alignItems: 'center', justifyContent: 'center', backgroundColor: warna.putih,
  },
  ringkasan: {
    flexDirection: 'row', backgroundColor: warna.putih, borderRadius: 14, paddingVertical: 14,
    marginBottom: 14, borderWidth: 1, borderColor: '#E4EBF1',
  },
  kotakAngka: { flex: 1, alignItems: 'center' },
  pemisah: { width: 1, backgroundColor: warna.garis },
  angka: { fontSize: 22, fontWeight: '800', color: warna.utama },
  labelAngka: { fontSize: 13, color: warna.teksRedup, marginTop: 2 },
  kosong: { alignItems: 'center', paddingVertical: 24 },
  lingkaranKosong: {
    width: 96, height: 96, borderRadius: 48, backgroundColor: warna.abu,
    alignItems: 'center', justifyContent: 'center', marginBottom: 12,
  },
  judulKosong: { fontSize: ukuran.hurufSedang, fontWeight: '700', color: warna.teks },
  teksKosong: { fontSize: 15, color: warna.teksRedup, marginTop: 4, textAlign: 'center' },
  kartu: {
    backgroundColor: warna.putih, borderRadius: 14, padding: 14, marginBottom: 10,
    borderWidth: 1, borderColor: '#E4EBF1',
  },
  barisAtas: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  nomor: { fontSize: 14, fontWeight: '800', color: warna.teksRedup, letterSpacing: 0.5 },
  namaKapal: { fontSize: ukuran.hurufSedang, fontWeight: '800', color: warna.teks, marginTop: 6 },
  redup: { fontSize: 14, color: warna.teksRedup, marginTop: 2 },
  barisBawah: { flexDirection: 'row', alignItems: 'center', gap: 14, marginTop: 10 },
  info: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  teksInfo: { fontSize: 14, color: warna.teks },
  berat: { marginLeft: 'auto', fontSize: ukuran.hurufNormal, fontWeight: '800', color: warna.utama },
  barisQR: {
    flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 10, paddingTop: 10,
    borderTopWidth: 1, borderTopColor: '#EEF2F6',
  },
  teksQR: { fontSize: 15, fontWeight: '700', color: warna.utama },
});

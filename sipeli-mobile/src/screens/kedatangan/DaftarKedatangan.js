import { useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { api } from '../../api';
import { KotakPesan, Lencana, Tombol } from '../../components/Dasar';
import Ikon from '../../components/Ikon';
import { BarisDaftar, Ringkasan } from '../../components/Tata';
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

  const jumlahDraf = data ? data.filter((k) => k.status === 'DRAF').length : 0;

  return (
    <ScrollView
      contentContainerStyle={s.isi}
      refreshControl={<RefreshControl refreshing={menyegarkan} onRefresh={segarkan} colors={[warna.utama]} />}
    >
      <View style={s.kepala}>
        <View style={s.barisJudul}>
          <View style={{ flex: 1 }}>
            <Text style={s.judul}>Kedatangan Hari Ini</Text>
            <Text style={s.tanggal}>{tanggalPanjang(new Date())}</Text>
          </View>
          <Pressable onPress={segarkan} style={s.tombolSegar} accessibilityLabel="Muat ulang" hitSlop={8}>
            <Ikon nama="muatUlang" ukuran={16} warna={warna.utama} />
            <Text style={s.teksSegar}>Muat ulang</Text>
          </Pressable>
        </View>
        <Tombol judul="Tambah Kedatangan" ikon="tambah" onPress={onTambah} gaya={{ marginTop: 12 }} />
      </View>

      <Ringkasan
        data={[
          ['Kapal', data ? String(data.length) : '-'],
          ['Total estimasi', data ? formatKg(total) : '-'],
          ['Draf', data ? String(jumlahDraf) : '-', jumlahDraf > 0 ? warna.peringatan : undefined],
        ]}
      />

      {galat ? (
        <View style={{ padding: 16 }}>
          <KotakPesan teks={galat} />
          <Tombol judul="Coba Lagi" jenis="garis" onPress={segarkan} gaya={{ marginTop: 12 }} />
        </View>
      ) : !data ? (
        <ActivityIndicator size="large" color={warna.utama} style={{ marginTop: 24 }} />
      ) : data.length === 0 ? (
        <View style={s.kosong}>
          <Text style={s.judulKosong}>Belum ada kedatangan hari ini</Text>
          <Text style={s.teksKosong}>Tekan "Tambah Kedatangan" saat kapal bersandar.</Text>
        </View>
      ) : (
        <>
          <Text style={s.subjudul}>Ketuk baris untuk menampilkan QR atau melengkapi draf.</Text>
          <View style={s.daftar}>
            {data.map((k, i) => {
              const draf = k.status === 'DRAF';
              return (
                <BarisDaftar
                  key={k.id_kedatangan}
                  terakhir={i === data.length - 1}
                  onPress={() => (draf ? onLengkapi(k) : setQr(k))}
                  kanan={
                    <View style={{ alignItems: 'flex-end', gap: 4 }}>
                      <Text style={s.berat}>{formatKg(k.total_berat_estimasi)}</Text>
                      <Lencana status={k.status} />
                      <Text style={s.teksQR}>{draf ? 'Lengkapi Draf' : 'Tampilkan QR'}</Text>
                    </View>
                  }
                >
                  <View accessibilityLabel={`${k.nomor_kedatangan} ${k.nama_kapal}. ${draf ? 'Ketuk untuk melengkapi draf' : 'Ketuk untuk menampilkan QR'}`}>
                    <Text style={s.nomor}>
                      {k.nomor_kedatangan} | {k.jam}
                    </Text>
                    <Text style={s.namaKapal}>{k.nama_kapal}</Text>
                    <Text style={s.redup}>
                      {k.nomor_registrasi} | Kapten {k.nama_kapten}
                    </Text>
                    <Text style={s.redup}>{k.jumlah_baris} baris tangkapan</Text>
                  </View>
                </BarisDaftar>
              );
            })}
          </View>
        </>
      )}

      <LembarQR kedatangan={qr} onTutup={() => setQr(null)} />
    </ScrollView>
  );
}

const s = StyleSheet.create({
  isi: { paddingBottom: 40 },
  kepala: { backgroundColor: warna.putih, padding: 16, borderBottomWidth: 1, borderColor: warna.garisHalus },
  barisJudul: { flexDirection: 'row', alignItems: 'center' },
  judul: { fontSize: 20, fontWeight: '700', color: warna.teks },
  tanggal: { fontSize: 13, color: warna.teksRedup, marginTop: 2 },
  tombolSegar: { flexDirection: 'row', alignItems: 'center', gap: 4, minHeight: 32 },
  teksSegar: { fontSize: 13, fontWeight: '600', color: warna.utama },
  subjudul: { fontSize: 12, color: warna.teksRedup, paddingHorizontal: 16, paddingTop: 12, paddingBottom: 6 },
  kosong: { alignItems: 'center', paddingVertical: 24 },
  judulKosong: { fontSize: ukuran.hurufSedang, fontWeight: '600', color: warna.teks },
  teksKosong: { fontSize: 14, color: warna.teksRedup, marginTop: 4, textAlign: 'center' },
  daftar: { backgroundColor: warna.putih, borderTopWidth: 1, borderBottomWidth: 1, borderColor: warna.garisHalus },
  nomor: { fontSize: 12, fontWeight: '600', color: warna.teksRedup, fontVariant: ['tabular-nums'] },
  namaKapal: { fontSize: 15, fontWeight: '600', color: warna.teks, marginTop: 2 },
  redup: { fontSize: 12, color: warna.teksRedup, marginTop: 1 },
  berat: { fontSize: 14, fontWeight: '700', color: warna.teks },
  teksQR: { fontSize: 12, fontWeight: '600', color: warna.utama },
});

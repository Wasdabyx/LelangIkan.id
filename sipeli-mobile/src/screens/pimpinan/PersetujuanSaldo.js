import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { pimpinan } from '../../api';
import { formatRupiah } from '../../format';
import { warna } from '../../tema';
import { BilahAtas } from '../../components/Dasar';

export default function PersetujuanSaldo({ pengguna, onKeluar }) {
  const [daftar, setDaftar] = useState([]);
  const [catatan, setCatatan] = useState({});
  const [memuat, setMemuat] = useState(true);
  const [memproses, setMemproses] = useState(null);
  const [galat, setGalat] = useState(null);
  const [pesan, setPesan] = useState(null);

  const muat = useCallback(async () => {
    try {
      const hasil = await pimpinan.penyesuaianSaldo();
      setDaftar(hasil.data || []);
      setGalat(null);
    } catch (e) {
      setGalat(e.message);
    } finally {
      setMemuat(false);
    }
  }, []);

  useEffect(() => {
    muat();
  }, [muat]);

  async function putuskan(id, keputusan) {
    setMemproses(id);
    setGalat(null);
    setPesan(null);
    try {
      const hasil = await pimpinan.putuskanPenyesuaianSaldo(id, keputusan, catatan[id] || null);
      setPesan(hasil.pesan);
      await muat();
    } catch (e) {
      setGalat(e.galatKolom ? Object.values(e.galatKolom).flat().join(' ') : e.message);
    } finally {
      setMemproses(null);
    }
  }

  return (
    <View style={s.layar}>
      <BilahAtas pengguna={pengguna} onKeluar={onKeluar} />
      <ScrollView contentContainerStyle={s.isi} refreshControl={<RefreshControl refreshing={memuat} onRefresh={muat} />}>
        <Text style={s.judul}>Persetujuan Penyesuaian Saldo</Text>
        {pesan ? <Text style={s.sukses}>{pesan}</Text> : null}
        {galat ? <Text style={s.galat}>{galat}</Text> : null}
        {memuat && !daftar.length ? <ActivityIndicator color={warna.utama} /> : null}
        {!memuat && !daftar.length ? <Text style={s.redup}>Tidak ada permintaan menunggu persetujuan.</Text> : null}
        {daftar.map((item) => (
          <View key={item.id_mutasi} style={s.kartu}>
            <View style={s.baris}><Text style={s.redup}>Pembeli</Text><Text style={s.nama}>{item.nama_pembeli}</Text></View>
            <View style={s.baris}><Text style={s.redup}>Arah</Text><Text style={s.nilai}>{item.arah === 'KREDIT' ? 'Tambah saldo' : 'Kurangi saldo'}</Text></View>
            <View style={s.baris}><Text style={s.redup}>Nominal</Text><Text style={s.nilai}>{formatRupiah(item.nominal)}</Text></View>
            <View style={s.baris}><Text style={s.redup}>Saldo saat ini</Text><Text style={s.nilai}>{formatRupiah(item.saldo_sekarang)}</Text></View>
            <Text style={s.redup}>Diajukan {item.nama_kasir} · {item.waktu}</Text>
            <Text style={s.keterangan}>{item.keterangan}</Text>
            <TextInput
              value={catatan[item.id_mutasi] || ''}
              onChangeText={(v) => setCatatan((lama) => ({ ...lama, [item.id_mutasi]: v }))}
              placeholder="Catatan keputusan (wajib jika menolak)"
              style={s.input}
            />
            <View style={s.barisTombol}>
              <Pressable disabled={memproses === item.id_mutasi} onPress={() => putuskan(item.id_mutasi, 'SETUJU')} style={[s.tombol, s.setuju]}>
                {memproses === item.id_mutasi ? <ActivityIndicator color="#fff" /> : <Text style={s.teksTombol}>Setujui</Text>}
              </Pressable>
              <Pressable disabled={memproses === item.id_mutasi} onPress={() => putuskan(item.id_mutasi, 'TOLAK')} style={[s.tombol, s.tolak]}>
                <Text style={s.teksTombol}>Tolak</Text>
              </Pressable>
            </View>
          </View>
        ))}
      </ScrollView>
    </View>
  );
}

const s = StyleSheet.create({
  layar: { flex: 1, backgroundColor: warna.latar },
  isi: { padding: 12, paddingBottom: 32, gap: 10 },
  judul: { fontSize: 18, fontWeight: '700', color: warna.teks },
  kartu: { backgroundColor: warna.putih, borderWidth: 1, borderColor: warna.garisHalus, padding: 14, gap: 9 },
  baris: { flexDirection: 'row', justifyContent: 'space-between', gap: 8 },
  barisTombol: { flexDirection: 'row', gap: 8 },
  nama: { color: warna.teks, fontWeight: '700' },
  nilai: { color: warna.utama, fontWeight: '700' },
  redup: { color: warna.teksRedup, fontSize: 12 },
  keterangan: { color: warna.teks, fontSize: 13 },
  input: { borderWidth: 1, borderColor: warna.garis, borderRadius: 4, paddingHorizontal: 10, minHeight: 42, color: warna.teks },
  tombol: { flex: 1, minHeight: 44, alignItems: 'center', justifyContent: 'center', borderRadius: 4 },
  setuju: { backgroundColor: warna.sukses },
  tolak: { backgroundColor: warna.galat },
  teksTombol: { color: warna.putih, fontWeight: '700' },
  sukses: { color: warna.sukses, fontWeight: '600' },
  galat: { color: warna.galat, fontWeight: '600' },
});

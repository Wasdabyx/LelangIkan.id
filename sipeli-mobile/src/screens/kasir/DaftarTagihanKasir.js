import { useCallback, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { kasir } from '../../api';
import { KolomTeks, KotakPesan, Lencana, Tombol } from '../../components/Dasar';
import Ikon from '../../components/Ikon';
import { WARNA_TRANSAKSI } from '../../components/Lelang';
import { formatRupiah, waktuPendek } from '../../format';
import { usePolling } from '../../polling';
import { warna } from '../../tema';
import PemindaiQR from '../penimbangan/PemindaiQR';

/** UC-06 langkah 1: cari transaksi (ketik nomor / pindai QR nota) atau pilih dari daftar tagihan terbuka. */
export default function DaftarTagihanKasir({ tab, onBuka }) {
  const [data, setData] = useState(null);
  const [galat, setGalat] = useState(null);
  const [segar, setSegar] = useState(false);
  const [kode, setKode] = useState('');
  const [galatKode, setGalatKode] = useState(null);
  const [mencari, setMencari] = useState(false);
  const [pindai, setPindai] = useState(false);

  const muat = useCallback(async () => {
    try {
      const hasil = await kasir.daftar();
      setData(hasil);
      setGalat(null);
    } catch (e) {
      if (e.status !== 401) setGalat(e.message);
    } finally {
      setSegar(false);
    }
  }, []);
  usePolling(muat, 5000);

  async function cari(isi) {
    const k = (isi ?? kode).trim();
    if (!k) return setGalatKode('Ketik nomor transaksi, contoh TRX-000001 atau 1.');
    if (!/^(LELANGIKAN\|.+|(TRX-?)?\d{1,6})$/i.test(k)) return setGalatKode('Format nomor transaksi: TRX-000001 atau angkanya saja.');
    setGalatKode(null);
    setMencari(true);
    try {
      const hasil = await kasir.cari(k);
      setKode('');
      onBuka(hasil.data);
    } catch (e) {
      if (e.status !== 401) setGalatKode(e.message);
    } finally {
      setMencari(false);
    }
  }

  if (!data) {
    return (
      <View style={s.tengah}>
        {galat ? <KotakPesan teks={galat} /> : <ActivityIndicator size="large" color={warna.utama} />}
      </View>
    );
  }
  const daftar = tab === 'tagihan' ? data.data : data.lunas_hari_ini;
  const totalLunas = data.lunas_hari_ini.reduce((a, t) => a + Number(t.total_tagihan), 0);

  return (
    <View style={{ flex: 1, backgroundColor: warna.latar }}>
      <FlatList
        data={daftar}
        keyExtractor={(t) => String(t.id_transaksi)}
        contentContainerStyle={s.isi}
        keyboardShouldPersistTaps="handled"
        refreshControl={<RefreshControl refreshing={segar} onRefresh={() => { setSegar(true); muat(); }} />}
        ListHeaderComponent={
          <>
            {tab === 'tagihan' ? (
              <View style={s.kartuCari}>
                <Text style={s.judul}>Cari Transaksi</Text>
                <KolomTeks
                  value={kode}
                  onChangeText={(t) => {
                    setKode(t.toUpperCase().replace(/[^A-Z0-9-]/g, '').slice(0, 10));
                    setGalatKode(null);
                  }}
                  placeholder="TRX-000001"
                  autoCapitalize="characters"
                  returnKeyType="search"
                  onSubmitEditing={() => cari()}
                  galat={galatKode}
                  accessibilityLabel="Nomor transaksi"
                />
                <View style={s.barisTombol}>
                  <Tombol judul="Cari" ikon="cari" onPress={() => cari()} memuat={mencari} gaya={{ flex: 1 }} />
                  <Tombol judul="Pindai QR" ikon="qr" jenis="garis" onPress={() => setPindai(true)} gaya={{ flex: 1 }} />
                </View>
              </View>
            ) : (
              <View style={s.ringkas}>
                <Text style={s.labelRingkas}>{data.lunas_hari_ini.length} transaksi lunas hari ini</Text>
                <Text style={s.nilaiRingkas}>{formatRupiah(totalLunas)}</Text>
              </View>
            )}
            {galat ? <KotakPesan teks={galat} gaya={{ marginTop: 12 }} /> : null}
            <Text style={s.keterangan}>
              {tab === 'tagihan' ? `Tagihan belum lunas (${data.data.length})` : 'Ketuk untuk melihat atau mencetak ulang nota.'}
            </Text>
          </>
        }
        ListEmptyComponent={
          <View style={s.kosong}>
            <Ikon nama="dompet" ukuran={44} warna={warna.teksRedup} />
            <Text style={s.teksKosong}>{tab === 'tagihan' ? 'Tidak ada tagihan terbuka.' : 'Belum ada transaksi lunas hari ini.'}</Text>
          </View>
        }
        renderItem={({ item }) => <KartuTagihan t={item} onPress={() => cari(item.kode_transaksi)} />}
      />
      <PemindaiQR
        tampil={pindai}
        petunjuk="Arahkan kamera ke QR nota pembeli"
        alasanIzin="Kamera dipakai untuk memindai QR nota pembayaran. Foto tidak disimpan."
        onTutup={() => setPindai(false)}
        onHasil={(isi) => {
          setPindai(false);
          cari(isi);
        }}
      />
    </View>
  );
}

function KartuTagihan({ t, onPress }) {
  const w = WARNA_TRANSAKSI[t.status];
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [s.kartu, pressed && { opacity: 0.85 }]}>
      <View style={s.barisAtas}>
        <Text style={s.kode}>{t.kode_transaksi}</Text>
        <Lencana teks={w.label} latar={w.latar} warnaTeks={w.teks} />
      </View>
      <Text style={s.pembeli}>{t.nama_pembeli}</Text>
      <Text style={s.redup}>
        {t.kode_lot} | {t.nama_jenis} | {waktuPendek(t.waktu_transaksi)}
      </Text>
      <View style={s.barisAtas}>
        <Text style={s.redup}>{t.status === 'LUNAS' ? 'Total' : 'Sisa tagihan'}</Text>
        <Text style={s.total}>{formatRupiah(t.status === 'LUNAS' ? t.total_tagihan : t.sisa_tagihan)}</Text>
      </View>
    </Pressable>
  );
}

const s = StyleSheet.create({
  tengah: { flex: 1, backgroundColor: warna.latar, alignItems: 'center', justifyContent: 'center', padding: 16 },
  isi: { padding: 16, paddingBottom: 32, gap: 10 },
  kartuCari: { backgroundColor: warna.kartu, borderRadius: 6, padding: 16, borderWidth: 1, borderColor: '#E4EBF1' },
  judul: { fontSize: 16, fontWeight: '700', color: warna.teks, marginBottom: 10 },
  barisTombol: { flexDirection: 'row', gap: 10 },
  ringkas: { backgroundColor: warna.suksesLatar, borderRadius: 6, padding: 16, alignItems: 'center' },
  labelRingkas: { fontSize: 13, color: warna.sukses, fontWeight: '600' },
  nilaiRingkas: { fontSize: 20, color: warna.sukses, fontWeight: '700', marginTop: 2 },
  keterangan: { fontSize: 13, color: warna.teksRedup, marginTop: 14, fontWeight: '500' },
  kosong: { alignItems: 'center', padding: 32, gap: 12 },
  teksKosong: { fontSize: 14, color: warna.teksRedup, textAlign: 'center' },
  kartu: { backgroundColor: warna.kartu, borderRadius: 6, padding: 16, borderWidth: 1, borderColor: '#E4EBF1' },
  barisAtas: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 },
  kode: { fontSize: 16, fontWeight: '700', color: warna.teks },
  pembeli: { fontSize: 15, fontWeight: '600', color: warna.teks, marginTop: 6 },
  redup: { fontSize: 13, color: warna.teksRedup, marginTop: 4 },
  total: { fontSize: 16, fontWeight: '700', color: warna.teks, marginTop: 4 },
});

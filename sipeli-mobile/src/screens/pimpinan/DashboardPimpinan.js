import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { pimpinan } from '../../api';
import { formatRupiah, keTanggalServer, tanggalPanjang } from '../../format';
import { KotakPesan } from '../../components/Dasar';
import { warna } from '../../tema';

function ubahTanggal(teks, selisih) {
  const tanggal = new Date(`${teks}T12:00:00`);
  tanggal.setDate(tanggal.getDate() + selisih);
  return keTanggalServer(tanggal);
}

function KartuMetrik({ judul, nilai, keterangan }) {
  return (
    <View style={s.kartu}>
      <Text style={s.label}>{judul}</Text>
      <Text style={s.nilai}>{nilai}</Text>
      {keterangan ? <Text style={s.keterangan}>{keterangan}</Text> : null}
    </View>
  );
}

export default function DashboardPimpinan() {
  const [tanggal, setTanggal] = useState(() => keTanggalServer(new Date()));
  const [data, setData] = useState(null);
  const [memuat, setMemuat] = useState(true);
  const [galat, setGalat] = useState(null);

  const muat = useCallback(async () => {
    setMemuat(true);
    try {
      const hasil = await pimpinan.ringkasan(tanggal);
      setData(hasil.data);
      setGalat(null);
    } catch (e) {
      setGalat(e.message || 'Ringkasan belum dapat dimuat.');
    } finally {
      setMemuat(false);
    }
  }, [tanggal]);

  useEffect(() => {
    muat();
  }, [muat]);

  const hariIni = keTanggalServer(new Date());
  const dataTanggal = data?.tanggal === tanggal ? data : null;

  return (
    <ScrollView
      contentContainerStyle={s.isi}
      refreshControl={<RefreshControl refreshing={memuat && dataTanggal !== null} onRefresh={muat} />}
    >
      <Text style={s.judul}>Ringkasan Operasional</Text>
      <View style={s.pilihTanggal}>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Hari sebelumnya"
          onPress={() => setTanggal((lama) => ubahTanggal(lama, -1))}
          style={s.tombolTanggal}
        >
          <Text style={s.teksTombolTanggal}>‹</Text>
        </Pressable>
        <View style={s.tanggalTengah}>
          <Text style={s.teksTanggal}>{tanggalPanjang(new Date(`${tanggal}T12:00:00`))}</Text>
          {tanggal !== hariIni ? (
            <Pressable onPress={() => setTanggal(hariIni)} accessibilityRole="button">
              <Text style={s.keHariIni}>Kembali ke hari ini</Text>
            </Pressable>
          ) : null}
        </View>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Hari berikutnya"
          disabled={tanggal >= hariIni}
          onPress={() => setTanggal((lama) => ubahTanggal(lama, 1))}
          style={[s.tombolTanggal, tanggal >= hariIni && s.tombolTanggalMati]}
        >
          <Text style={s.teksTombolTanggal}>›</Text>
        </Pressable>
      </View>

      {galat ? <KotakPesan teks={galat} /> : null}
      {memuat && !dataTanggal ? <ActivityIndicator color={warna.utama} style={s.memuat} /> : null}
      {dataTanggal ? (
        <>
          <Text style={s.bagian}>Aktivitas lelang</Text>
          <View style={s.grid}>
            <KartuMetrik judul="Sesi aktif" nilai={dataTanggal.sesi.aktif} />
            <KartuMetrik judul="Sesi ditutup" nilai={dataTanggal.sesi.ditutup} />
            <KartuMetrik judul="Terjual" nilai={dataTanggal.lot.terjual} keterangan="Sesi dengan transaksi" />
            <KartuMetrik judul="Tidak terjual" nilai={dataTanggal.lot.tidak_terjual} keterangan="Sesi tanpa penjualan" />
          </View>

          <Text style={s.bagian}>Keuangan</Text>
          <View style={s.grid}>
            <KartuMetrik judul="Nilai penjualan" nilai={formatRupiah(dataTanggal.penjualan.subtotal)} />
            <KartuMetrik judul="Pembayaran diterima" nilai={formatRupiah(dataTanggal.pembayaran.diterima)} />
            <KartuMetrik
              judul="Tagihan belum lunas"
              nilai={dataTanggal.tagihan.belum_lunas}
              keterangan={formatRupiah(dataTanggal.tagihan.sisa) + ' tersisa'}
            />
          </View>
        </>
      ) : !memuat && !galat ? (
        <Text style={s.kosong}>Belum ada data untuk tanggal ini.</Text>
      ) : null}
    </ScrollView>
  );
}

const s = StyleSheet.create({
  isi: { padding: 12, paddingBottom: 30, gap: 10 },
  judul: { fontSize: 18, fontWeight: '700', color: warna.teks },
  pilihTanggal: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', backgroundColor: warna.putih, borderRadius: 6, padding: 8 },
  tombolTanggal: { width: 42, height: 42, alignItems: 'center', justifyContent: 'center', borderRadius: 6, backgroundColor: warna.utamaMuda },
  tombolTanggalMati: { opacity: 0.4 },
  teksTombolTanggal: { color: warna.utama, fontSize: 28, lineHeight: 32 },
  tanggalTengah: { flex: 1, alignItems: 'center', gap: 3, paddingHorizontal: 6 },
  teksTanggal: { color: warna.teks, fontWeight: '600', textAlign: 'center' },
  keHariIni: { color: warna.utama, fontSize: 12, fontWeight: '600' },
  bagian: { color: warna.teks, fontSize: 15, fontWeight: '700', marginTop: 6 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  kartu: { width: '48%', flexGrow: 1, minHeight: 92, backgroundColor: warna.putih, borderWidth: 1, borderColor: warna.garisHalus, borderRadius: 6, padding: 12, justifyContent: 'center', gap: 4 },
  label: { color: warna.teksRedup, fontSize: 12 },
  nilai: { color: warna.utama, fontSize: 20, fontWeight: '700' },
  keterangan: { color: warna.teksRedup, fontSize: 11 },
  memuat: { padding: 24 },
  kosong: { color: warna.teksRedup, textAlign: 'center', padding: 20 },
});

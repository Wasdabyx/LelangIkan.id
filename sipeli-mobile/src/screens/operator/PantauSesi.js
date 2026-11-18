import { useCallback, useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, Text, View } from 'react-native';
import { operator } from '../../api';
import { KolomTeks, KotakPesan, Tombol } from '../../components/Dasar';
import FormGulir from '../../components/FormGulir';
import { BarisRincian, JamMundur } from '../../components/Lelang';
import { formatKg, formatRupiah } from '../../format';
import { usePolling } from '../../polling';
import { ukuran, warna } from '../../tema';

/**
 * Pantauan sesi lelang untuk Operator (UC-03 langkah 6, UC-05).
 * Status diminta setiap 1 detik. Sesi ditutup otomatis oleh server saat waktu habis;
 * operator juga dapat menutup lebih awal dengan alasan (dicatat ke log audit).
 */
export default function PantauSesi({ idSesi, onKembali }) {
  const [data, setData] = useState(null);
  const [galat, setGalat] = useState(null);
  const [formTutup, setFormTutup] = useState(false);
  const [alasan, setAlasan] = useState('');
  const [galatAlasan, setGalatAlasan] = useState(null);
  const [menutup, setMenutup] = useState(false);

  const muat = useCallback(async () => {
    try {
      const hasil = await operator.status(idSesi);
      setData(hasil.data);
      setGalat(null);
    } catch (e) {
      if (e.status !== 401) setGalat(e.status === 0 ? 'Koneksi terputus. Mencoba lagi...' : e.message);
    }
  }, [idSesi]);
  const aktif = !data || data.status === 'AKTIF';
  usePolling(muat, 1000, aktif);

  async function tutup() {
    const a = alasan.trim();
    if (a.length < 5) {
      setGalatAlasan('Alasan penutupan minimal 5 karakter.');
      return;
    }
    setMenutup(true);
    try {
      const hasil = await operator.tutup(idSesi, a);
      setData(hasil.data);
      setFormTutup(false);
    } catch (e) {
      if (e.galatKolom?.alasan) setGalatAlasan(e.galatKolom.alasan);
      else setGalat(e.message);
    } finally {
      setMenutup(false);
    }
  }

  if (!data) {
    return (
      <View style={s.tengah}>
        {galat ? <KotakPesan teks={galat} /> : <ActivityIndicator size="large" color={warna.utama} />}
        <Tombol judul="Kembali" jenis="teks" onPress={onKembali} gaya={{ marginTop: 12 }} />
      </View>
    );
  }

  const lot = data.lot;
  const hasil = data.hasil;

  return (
    <View style={{ flex: 1, backgroundColor: warna.latar }}>
      <FormGulir gayaIsi={s.isi}>
        <View style={s.kartu}>
          <View style={s.barisJudul}>
            <Text style={s.kode}>{lot.kode_lot}</Text>
            <Text style={s.peserta}>{data.jumlah_peserta} peserta terdaftar</Text>
          </View>
          <Text style={s.jenis}>
            {lot.nama_jenis} <Text style={{ color: warna.aksen }}>Grade {lot.kode_grade}</Text> | {formatKg(lot.berat_aktual)}
          </Text>

          <View style={{ marginTop: 12 }}>
            <JamMundur
              sisa={data.sisa_detik}
              status={data.status}
              perpanjangan={data.jumlah_perpanjangan}
              maksPerpanjangan={data.maks_perpanjangan}
            />
          </View>
          {galat ? <KotakPesan teks={galat} gaya={{ marginTop: 12 }} /> : null}

          <View style={s.hargaKotak}>
            <Text style={s.labelHarga}>{data.harga_tertinggi ? 'HARGA TERTINGGI' : 'HARGA PEMBUKAAN'}</Text>
            <Text style={s.harga} accessibilityLabel="Harga tertinggi">
              {formatRupiah(data.harga_tertinggi ?? data.harga_pembukaan)}
              <Text style={s.perKg}> /kg</Text>
            </Text>
            <Text style={s.penawar}>
              {data.penawar_tertinggi ? `oleh ${data.penawar_tertinggi}` : 'Belum ada tawaran'}
            </Text>
          </View>

          <View style={s.rincian}>
            <BarisRincian label="Kenaikan minimum" nilai={`${formatRupiah(data.kenaikan_minimum)}/kg`} />
            <BarisRincian label="Jumlah tawaran" nilai={`${data.jumlah_penawaran} dari ${data.jumlah_penawar} penawar`} />
            {data.harga_tertinggi ? (
              <BarisRincian label="Perkiraan nilai" nilai={formatRupiah(Math.round(data.harga_tertinggi * lot.berat_aktual))} tebal />
            ) : null}
          </View>

          {hasil ? (
            <View style={[s.hasil, hasil.terjual ? s.hasilTerjual : s.hasilTidak]}>
              <Text style={[s.judulHasil, { color: hasil.terjual ? warna.sukses : warna.teksRedup }]}>
                {hasil.terjual ? 'LOT TERJUAL' : 'LOT TIDAK TERJUAL'}
              </Text>
              {hasil.terjual ? (
                <>
                  <BarisRincian label="Pemenang" nilai={hasil.pemenang} tebal />
                  <BarisRincian label="Harga akhir" nilai={`${formatRupiah(hasil.harga_akhir_per_kg)}/kg`} />
                  <BarisRincian label="Nomor transaksi" nilai={hasil.kode_transaksi} tebal />
                  <BarisRincian label="Total tagihan" nilai={formatRupiah(hasil.total_tagihan)} besar />
                  <Text style={s.catatanHasil}>Pemenang membayar ke Kasir TPI dengan menyebutkan nomor transaksi.</Text>
                </>
              ) : (
                <Text style={s.catatanHasil}>Tidak ada tawaran yang masuk. Lot tidak terjual.</Text>
              )}
              {data.alasan_tutup ? <Text style={s.catatanHasil}>Ditutup manual: {data.alasan_tutup}</Text> : null}
            </View>
          ) : null}
        </View>

        <View style={s.kartu}>
          <Text style={s.subjudul}>Riwayat Tawaran</Text>
          {data.riwayat.length === 0 ? (
            <Text style={s.kosong}>Menunggu tawaran pertama...</Text>
          ) : (
            data.riwayat.map((r, i) => (
              <View key={i} style={[s.barisTawar, r.tertinggi && s.barisTertinggi]}>
                <Text style={s.waktu}>{r.waktu}</Text>
                <Text style={[s.namaTawar, r.tertinggi && { fontWeight: '800' }]} numberOfLines={1}>
                  {r.penawar}
                </Text>
                <Text style={[s.nilaiTawar, r.tertinggi && { color: warna.sukses }]}>{formatRupiah(r.harga_per_kg)}</Text>
              </View>
            ))
          )}
        </View>

        {aktif ? (
          formTutup ? (
            <View style={s.kartu}>
              <Text style={s.subjudul}>Tutup Sesi Lebih Awal</Text>
              <Text style={s.kosong}>
                Penawar tertinggi saat ini menjadi pemenang. Alasan dicatat ke log audit.
              </Text>
              <KolomTeks
                label="Alasan penutupan"
                value={alasan}
                onChangeText={(t) => {
                  setAlasan(t.slice(0, 255));
                  setGalatAlasan(null);
                }}
                placeholder="Contoh: cuaca buruk, listrik padam"
                galat={galatAlasan}
                accessibilityLabel="Alasan penutupan"
              />
              <Tombol judul="Tutup Sesi Sekarang" jenis="bahaya" onPress={tutup} memuat={menutup} />
              <Tombol judul="Batal" jenis="teks" onPress={() => setFormTutup(false)} nonaktif={menutup} gaya={{ marginTop: 6 }} />
            </View>
          ) : (
            <Tombol judul="Tutup Sesi Lebih Awal" jenis="garis" onPress={() => setFormTutup(true)} />
          )
        ) : null}
        <Tombol judul={aktif ? 'Kembali ke Daftar (sesi tetap berjalan)' : 'Kembali ke Daftar'} jenis={aktif ? 'teks' : 'utama'} onPress={onKembali} />
      </FormGulir>
    </View>
  );
}

const s = StyleSheet.create({
  tengah: { flex: 1, backgroundColor: warna.latar, alignItems: 'center', justifyContent: 'center', padding: 20 },
  isi: { padding: 16, paddingBottom: 32, gap: 12 },
  kartu: { backgroundColor: warna.kartu, borderRadius: 16, padding: 16 },
  barisJudul: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 },
  kode: { fontSize: 20, fontWeight: '800', color: warna.teks },
  peserta: { fontSize: 13, color: warna.teksRedup, fontWeight: '600' },
  jenis: { fontSize: 15, color: warna.teks, fontWeight: '600', marginTop: 4 },
  hargaKotak: { alignItems: 'center', marginTop: 14 },
  labelHarga: { fontSize: 13, fontWeight: '800', color: warna.teksRedup, letterSpacing: 1 },
  harga: { fontSize: 34, fontWeight: '800', color: warna.teks, marginTop: 2 },
  perKg: { fontSize: 18, fontWeight: '600', color: warna.teksRedup },
  penawar: { fontSize: 15, color: warna.teksRedup, marginTop: 2 },
  rincian: { backgroundColor: warna.latar, borderRadius: ukuran.radius, padding: 12, marginTop: 14, gap: 8 },
  hasil: { borderRadius: ukuran.radius, padding: 14, marginTop: 14, gap: 8, borderWidth: 1.5 },
  hasilTerjual: { backgroundColor: warna.suksesLatar, borderColor: warna.sukses },
  hasilTidak: { backgroundColor: warna.abu, borderColor: warna.garis },
  judulHasil: { fontSize: 18, fontWeight: '800', textAlign: 'center', letterSpacing: 1 },
  catatanHasil: { fontSize: 13, color: warna.teksRedup, lineHeight: 19 },
  subjudul: { fontSize: 17, fontWeight: '800', color: warna.teks, marginBottom: 8 },
  kosong: { fontSize: 14, color: warna.teksRedup, marginBottom: 10, lineHeight: 20 },
  barisTawar: {
    flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 9, paddingHorizontal: 8,
    borderBottomWidth: 1, borderBottomColor: '#E4EBF1',
  },
  barisTertinggi: { backgroundColor: warna.suksesLatar, borderRadius: 8 },
  waktu: { fontSize: 13, color: warna.teksRedup, fontVariant: ['tabular-nums'] },
  namaTawar: { flex: 1, fontSize: 15, color: warna.teks },
  nilaiTawar: { fontSize: 15, fontWeight: '700', color: warna.teks },
});

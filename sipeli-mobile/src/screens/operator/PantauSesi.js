import { useCallback, useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, Text, View } from 'react-native';
import { operator } from '../../api';
import { KolomTeks, KotakPesan, Tombol } from '../../components/Dasar';
import FormGulir from '../../components/FormGulir';
import { PanelLelang } from '../../components/Lelang';
import { Bagian, Info, Tabel } from '../../components/Tata';
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
        <Bagian>
          <View style={s.barisJudul}>
            <Text style={s.kode}>{lot.kode_lot}</Text>
            <Text style={s.peserta}>{data.jumlah_peserta} peserta terdaftar</Text>
          </View>
          <Text style={s.jenis}>
            {lot.nama_jenis} <Text style={{ color: warna.aksen }}>Grade {lot.kode_grade}</Text> | {formatKg(lot.berat_aktual)}
          </Text>
          <View style={{ marginTop: 12 }}>
            <PanelLelang
              sisa={data.sisa_detik}
              status={data.status}
              perpanjangan={data.jumlah_perpanjangan}
              maksPerpanjangan={data.maks_perpanjangan}
              labelHarga={data.harga_tertinggi ? 'Harga tertinggi' : 'Harga pembukaan'}
              harga={formatRupiah(data.harga_tertinggi ?? data.harga_pembukaan)}
              bawahHarga={data.penawar_tertinggi ? `oleh ${data.penawar_tertinggi}` : 'Belum ada tawaran'}
            />
          </View>
          {galat ? <KotakPesan teks={galat} gaya={{ marginTop: 12 }} /> : null}
          <View style={{ marginTop: 8 }}>
            <Info label="Kenaikan minimum" nilai={`${formatRupiah(data.kenaikan_minimum)}/kg`} />
            <Info label="Jumlah tawaran" nilai={`${data.jumlah_penawaran} dari ${data.jumlah_penawar} penawar`} />
            {data.harga_tertinggi ? (
              <Info label="Perkiraan nilai" nilai={formatRupiah(Math.round(data.harga_tertinggi * lot.berat_aktual))} tebal />
            ) : null}
          </View>
        </Bagian>

        {hasil ? (
          <Bagian judul="Hasil lelang">
            <View style={[s.hasil, { borderLeftColor: hasil.terjual ? warna.sukses : warna.garis }]}>
              <Text style={[s.judulHasil, { color: hasil.terjual ? warna.sukses : warna.teksRedup }]}>
                {hasil.terjual ? 'Lot terjual' : 'Lot tidak terjual'}
              </Text>
              {hasil.terjual ? (
                <>
                  <Info label="Pemenang" nilai={hasil.pemenang} tebal />
                  <Info label="Harga akhir" nilai={`${formatRupiah(hasil.harga_akhir_per_kg)}/kg`} />
                  <Info label="Nomor transaksi" nilai={hasil.kode_transaksi} tebal />
                  <Info label="Total tagihan" nilai={formatRupiah(hasil.total_tagihan)} besar garis />
                  <Text style={s.catatanHasil}>Pemenang membayar ke Kasir TPI dengan menyebutkan nomor transaksi.</Text>
                </>
              ) : (
                <Text style={s.catatanHasil}>Tidak ada tawaran yang masuk. Lot tidak terjual.</Text>
              )}
              {data.alasan_tutup ? <Text style={s.catatanHasil}>Ditutup manual: {data.alasan_tutup}</Text> : null}
            </View>
          </Bagian>
        ) : null}

        <Bagian judul="Riwayat tawaran">
          <Tabel
            label="Riwayat tawaran"
            kosong="Menunggu tawaran pertama..."
            data={data.riwayat}
            sorot={(r) => r.tertinggi}
            kolom={[
              { judul: 'Waktu', flex: 0.9, redup: true, render: (r) => r.waktu },
              {
                judul: 'Penawar', flex: 1.5,
                render: (r) => <Text style={[s.namaTawar, r.tertinggi && { fontWeight: '700' }]} numberOfLines={1}>{r.penawar}</Text>,
              },
              {
                judul: 'Harga/kg', flex: 1.2, kanan: true,
                render: (r) => <Text style={[s.nilaiTawar, r.tertinggi && { color: warna.sukses }]}>{formatRupiah(r.harga_per_kg)}</Text>,
              },
            ]}
          />
        </Bagian>

        <View style={{ paddingHorizontal: 16 }}>
          {aktif ? (
            formTutup ? (
              <View style={s.formTutup}>
                <Text style={s.subjudul}>Tutup Sesi Lebih Awal</Text>
                <Text style={s.kosong}>Penawar tertinggi saat ini menjadi pemenang. Alasan dicatat ke log audit.</Text>
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
              <Tombol judul="Tutup Sesi Lebih Awal" jenis="bahaya" onPress={() => setFormTutup(true)} />
            )
          ) : null}
          <Tombol
            judul={aktif ? 'Kembali ke Daftar (sesi tetap berjalan)' : 'Kembali ke Daftar'}
            jenis={aktif ? 'teks' : 'utama'}
            onPress={onKembali}
            gaya={{ marginTop: 8 }}
          />
        </View>
      </FormGulir>
    </View>
  );
}

const s = StyleSheet.create({
  tengah: { flex: 1, backgroundColor: warna.latar, alignItems: 'center', justifyContent: 'center', padding: 16 },
  isi: { paddingBottom: 32 },
  barisJudul: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 },
  kode: { fontSize: 17, fontWeight: '700', color: warna.teks, fontVariant: ['tabular-nums'] },
  peserta: { fontSize: 12, color: warna.teksRedup },
  jenis: { fontSize: 14, color: warna.teks, marginTop: 2 },
  hasil: { borderLeftWidth: 3, paddingLeft: 12 },
  judulHasil: { fontSize: 16, fontWeight: '700', marginBottom: 4 },
  catatanHasil: { fontSize: 12, color: warna.teksRedup, lineHeight: 17, marginTop: 4 },
  formTutup: {
    backgroundColor: warna.putih, borderWidth: 1, borderColor: warna.garisHalus, borderRadius: ukuran.radiusKartu,
    padding: 14, marginBottom: 4,
  },
  subjudul: { fontSize: 15, fontWeight: '700', color: warna.teks, marginBottom: 4 },
  kosong: { fontSize: 13, color: warna.teksRedup, marginBottom: 10, lineHeight: 18 },
  namaTawar: { fontSize: 13, color: warna.teks },
  nilaiTawar: { fontSize: 13, fontWeight: '600', color: warna.teks, fontVariant: ['tabular-nums'] },
});

import { useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { api } from '../../api';
import { KolomTeks, KotakPesan, Lencana, Tombol } from '../../components/Dasar';
import FormGulir from '../../components/FormGulir';
import { Bagian, BarisDaftar, Ringkasan, TitikStatus } from '../../components/Tata';
import Ikon from '../../components/Ikon';
import { formatKg, tanggalPanjang } from '../../format';
import { ukuran, warna } from '../../tema';
import PemindaiQR from './PemindaiQR';

/**
 * Alur utama UC-02 langkah (1): pilih kedatangan
 * - pindai QR (kamera), ketik nomor kedatangan, atau ketuk dari daftar hari ini
 * onBuka(kode) mengembalikan Promise; bila gagal, pesannya ditampilkan di sini.
 */
export default function PilihKedatangan({ onBuka }) {
  const [data, setData] = useState(null);
  const [galatDaftar, setGalatDaftar] = useState(null);
  const [menyegarkan, setMenyegarkan] = useState(false);
  const [nomor, setNomor] = useState('');
  const [galatNomor, setGalatNomor] = useState(null);
  const [pesan, setPesan] = useState(null);
  const [membuka, setMembuka] = useState(null); // kode yang sedang dibuka
  const [pindai, setPindai] = useState(false);

  async function muat() {
    setGalatDaftar(null);
    try {
      const hasil = await api.get('/kedatangan');
      // yang masih ada baris belum ditimbang tampil paling atas
      const urut = [...hasil.data].sort(
        (a, b) => (b.jumlah_belum_timbang > 0) - (a.jumlah_belum_timbang > 0)
      );
      setData(urut);
    } catch (e) {
      if (e.status !== 401) setGalatDaftar(e.message);
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

  async function buka(kode, dariKetik = false) {
    setPesan(null);
    setGalatNomor(null);
    setMembuka(kode);
    try {
      await onBuka(kode);
    } catch (e) {
      if (e.status === 401) return;
      if (dariKetik) setGalatNomor(e.message);
      else setPesan(e.message);
    } finally {
      setMembuka(null);
    }
  }

  function bukaKetikan() {
    const isi = nomor.trim();
    if (!isi) {
      setGalatNomor('Ketik nomor kedatangan, contoh KDT-00001 atau 1.');
      return;
    }
    buka(isi, true);
  }

  const belum = data ? data.reduce((a, k) => a + k.jumlah_belum_timbang, 0) : 0;
  const selesaiSemua = data ? data.filter((k) => k.jumlah_belum_timbang === 0).length : 0;

  return (
    <FormGulir
      gayaIsi={s.isi}
      refreshControl={<RefreshControl refreshing={menyegarkan} onRefresh={segarkan} colors={[warna.utama]} />}
    >
      <Bagian>
        <Text style={s.judul}>Penimbangan</Text>
        <Text style={s.tanggal}>{tanggalPanjang(new Date())}</Text>
        <Tombol judul="Pindai QR Kedatangan" ikon="qr" onPress={() => setPindai(true)} gaya={{ marginTop: 12 }} />
        <Text style={s.teksPindai}>Bisa juga memindai label lot untuk cetak ulang.</Text>
        <KotakPesan teks={pesan} gaya={{ marginTop: 10 }} />
        <Text style={s.label}>Atau ketik nomor kedatangan</Text>
        <View style={s.barisNomor}>
          <KolomTeks
            gayaWadah={{ flex: 1, marginBottom: 0 }}
            value={nomor}
            onChangeText={(t) => {
              setNomor(t);
              setGalatNomor(null);
            }}
            placeholder="KDT-00001"
            autoCapitalize="characters"
            autoCorrect={false}
            returnKeyType="go"
            onSubmitEditing={bukaKetikan}
          />
          <Tombol
            judul="Buka"
            jenis="garis"
            onPress={bukaKetikan}
            memuat={membuka !== null && membuka === nomor.trim()}
            gaya={{ minWidth: 80, minHeight: ukuran.tinggiInput }}
          />
        </View>
        {galatNomor ? <Text style={s.teksGalat}>{galatNomor}</Text> : null}
      </Bagian>

      {data && data.length > 0 ? (
        <Ringkasan
          data={[
            ['Kedatangan', String(data.length)],
            ['Belum ditimbang', String(belum), belum > 0 ? warna.peringatan : undefined],
            ['Selesai', String(selesaiSemua), warna.sukses],
          ]}
        />
      ) : null}

      <View style={s.barisJudul}>
        <Text style={s.subjudul}>Kedatangan hari ini</Text>
        <Pressable onPress={segarkan} style={s.tombolSegar} accessibilityLabel="Muat ulang" hitSlop={8}>
          <Ikon nama="muatUlang" ukuran={16} warna={warna.utama} />
          <Text style={s.teksSegar}>Muat ulang</Text>
        </Pressable>
      </View>

      {galatDaftar ? (
        <View style={{ paddingHorizontal: 16 }}>
          <KotakPesan teks={galatDaftar} />
          <Tombol judul="Coba Lagi" jenis="garis" onPress={segarkan} gaya={{ marginTop: 12 }} />
        </View>
      ) : !data ? (
        <ActivityIndicator size="large" color={warna.utama} style={{ marginTop: 24 }} />
      ) : data.length === 0 ? (
        <View style={s.kosong}>
          <Text style={s.judulKosong}>Belum ada kedatangan hari ini</Text>
          <Text style={s.teksKosong}>Kedatangan dicatat oleh Petugas Pendataan.</Text>
        </View>
      ) : (
        <View style={s.daftar}>
          {data.map((k, i) => {
            const selesai = k.jumlah_belum_timbang === 0;
            return (
              <BarisDaftar
                key={k.id_kedatangan}
                terakhir={i === data.length - 1}
                onPress={membuka !== null ? undefined : () => buka(k.nomor_kedatangan)}
                kanan={
                  membuka === k.nomor_kedatangan ? (
                    <ActivityIndicator color={warna.utama} />
                  ) : selesai ? (
                    <TitikStatus teks="Selesai ditimbang" w={warna.sukses} />
                  ) : (
                    <TitikStatus teks={`${k.jumlah_belum_timbang} belum ditimbang`} w={warna.peringatan} />
                  )
                }
              >
                <Text style={s.namaKapal}>{k.nama_kapal}</Text>
                <Text style={s.redup}>
                  {k.nomor_kedatangan} | {k.jam} | {k.jumlah_baris} baris | {formatKg(k.total_berat_estimasi)}
                </Text>
              </BarisDaftar>
            );
          })}
        </View>
      )}

      <PemindaiQR
        tampil={pindai}
        onTutup={() => setPindai(false)}
        onHasil={(teks) => {
          setPindai(false);
          buka(teks);
        }}
      />
    </FormGulir>
  );
}

const s = StyleSheet.create({
  isi: { paddingBottom: 40 },
  judul: { fontSize: 20, fontWeight: '700', color: warna.teks },
  tanggal: { fontSize: 13, color: warna.teksRedup, marginTop: 2 },
  teksPindai: { color: warna.teksRedup, fontSize: 12, marginTop: 6 },
  label: { fontSize: 13, fontWeight: '500', color: warna.teks, marginTop: 14, marginBottom: 6 },
  barisNomor: { flexDirection: 'row', gap: 8, alignItems: 'flex-start' },
  teksGalat: { color: warna.galat, fontSize: 12, marginTop: 6 },
  barisJudul: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingTop: 14, paddingBottom: 6 },
  subjudul: { flex: 1, fontSize: 13, fontWeight: '600', color: warna.teksRedup },
  tombolSegar: { flexDirection: 'row', alignItems: 'center', gap: 4, minHeight: 32 },
  teksSegar: { fontSize: 13, fontWeight: '600', color: warna.utama },
  kosong: { alignItems: 'center', paddingVertical: 24 },
  judulKosong: { fontSize: ukuran.hurufSedang, fontWeight: '600', color: warna.teks },
  teksKosong: { fontSize: 14, color: warna.teksRedup, marginTop: 4, textAlign: 'center' },
  daftar: { backgroundColor: warna.putih, borderTopWidth: 1, borderBottomWidth: 1, borderColor: warna.garisHalus },
  namaKapal: { fontSize: 15, fontWeight: '600', color: warna.teks },
  redup: { fontSize: 12, color: warna.teksRedup, marginTop: 2 },
});

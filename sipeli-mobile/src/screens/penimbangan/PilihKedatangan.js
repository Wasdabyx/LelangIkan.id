import { useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { api } from '../../api';
import { KolomTeks, KotakPesan, Lencana, Tombol } from '../../components/Dasar';
import FormGulir from '../../components/FormGulir';
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

  return (
    <FormGulir
      gayaIsi={s.isi}
      refreshControl={<RefreshControl refreshing={menyegarkan} onRefresh={segarkan} colors={[warna.utama]} />}
    >
      <Text style={s.judul}>Penimbangan</Text>
      <Text style={s.tanggal}>{tanggalPanjang(new Date())}</Text>

      {/* Pindai QR */}
      <Pressable
        onPress={() => setPindai(true)}
        style={({ pressed }) => [s.kartuPindai, pressed && { backgroundColor: warna.utamaTekan }]}
        accessibilityRole="button"
      >
        <View style={s.ikonPindai}>
          <Ikon nama="qr" ukuran={34} warna={warna.utama} />
        </View>
        <View style={{ flex: 1 }}>
          <Text style={s.judulPindai}>Pindai QR Kedatangan</Text>
          <Text style={s.teksPindai}>Bisa juga memindai label lot untuk cetak ulang</Text>
        </View>
        <Ikon nama="kanan" ukuran={24} warna={warna.putih} />
      </Pressable>

      <KotakPesan teks={pesan} gaya={{ marginBottom: 12 }} />

      {/* Ketik nomor */}
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
          onPress={bukaKetikan}
          memuat={membuka !== null && membuka === nomor.trim()}
          gaya={{ minWidth: 96, minHeight: ukuran.tinggiInput }}
        />
      </View>
      {galatNomor ? <Text style={s.teksGalat}>{galatNomor}</Text> : null}

      {/* Daftar kedatangan hari ini */}
      <View style={s.barisJudul}>
        <Text style={s.subjudul}>Kedatangan hari ini</Text>
        <Pressable onPress={segarkan} style={s.tombolSegar} accessibilityLabel="Muat ulang">
          <Ikon nama="muatUlang" ukuran={22} warna={warna.utama} />
        </Pressable>
      </View>

      {galatDaftar ? (
        <View>
          <KotakPesan teks={galatDaftar} />
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
          <Text style={s.teksKosong}>Kedatangan dicatat oleh Petugas Pendataan.</Text>
        </View>
      ) : (
        data.map((k) => {
          const selesai = k.jumlah_belum_timbang === 0;
          return (
            <Pressable
              key={k.id_kedatangan}
              onPress={() => buka(k.nomor_kedatangan)}
              disabled={membuka !== null}
              style={({ pressed }) => [s.kartu, pressed && { backgroundColor: warna.utamaMuda }]}
            >
              <View style={s.barisAtas}>
                <Text style={s.nomor}>{k.nomor_kedatangan}</Text>
                {selesai ? (
                  <Lencana status="DITIMBANG" teks="Selesai ditimbang" ikon="centang" />
                ) : (
                  <Lencana
                    teks={`${k.jumlah_belum_timbang} belum ditimbang`}
                    latar={warna.peringatanLatar}
                    warnaTeks={warna.peringatan}
                  />
                )}
              </View>
              <View style={s.barisTengah}>
                <View style={{ flex: 1 }}>
                  <Text style={s.namaKapal}>{k.nama_kapal}</Text>
                  <Text style={s.redup}>
                    {k.jam} - {k.jumlah_baris} baris - {formatKg(k.total_berat_estimasi)}
                  </Text>
                </View>
                {membuka === k.nomor_kedatangan ? (
                  <ActivityIndicator color={warna.utama} />
                ) : (
                  <Ikon nama="kanan" ukuran={24} warna={warna.teksRedup} />
                )}
              </View>
            </Pressable>
          );
        })
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
  isi: { padding: 16, paddingBottom: 40 },
  judul: { fontSize: 24, fontWeight: '800', color: warna.teks },
  tanggal: { fontSize: 15, color: warna.teksRedup, marginTop: 2, marginBottom: 14 },
  kartuPindai: {
    flexDirection: 'row', alignItems: 'center', gap: 14, backgroundColor: warna.utama,
    borderRadius: 14, padding: 16, marginBottom: 16, minHeight: 88,
  },
  ikonPindai: {
    width: 56, height: 56, borderRadius: 12, backgroundColor: warna.putih,
    alignItems: 'center', justifyContent: 'center',
  },
  judulPindai: { color: warna.putih, fontSize: 19, fontWeight: '800' },
  teksPindai: { color: '#D9E8F0', fontSize: 14, marginTop: 2 },
  label: { fontSize: ukuran.hurufNormal, fontWeight: '600', color: warna.teks, marginBottom: 6 },
  barisNomor: { flexDirection: 'row', gap: 10, alignItems: 'flex-start' },
  teksGalat: { color: warna.galat, fontSize: 14, marginTop: 6, fontWeight: '600' },
  barisJudul: { flexDirection: 'row', alignItems: 'center', marginTop: 24, marginBottom: 10 },
  subjudul: { flex: 1, fontSize: 19, fontWeight: '800', color: warna.teks },
  tombolSegar: {
    width: 48, height: 48, borderRadius: 24, borderWidth: 1.5, borderColor: warna.garis,
    alignItems: 'center', justifyContent: 'center', backgroundColor: warna.putih,
  },
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
  barisAtas: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 },
  nomor: { fontSize: 14, fontWeight: '800', color: warna.teksRedup, letterSpacing: 0.5 },
  barisTengah: { flexDirection: 'row', alignItems: 'center', marginTop: 6, gap: 8 },
  namaKapal: { fontSize: ukuran.hurufSedang, fontWeight: '800', color: warna.teks },
  redup: { fontSize: 14, color: warna.teksRedup, marginTop: 2 },
});

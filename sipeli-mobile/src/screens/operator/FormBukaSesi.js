import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { operator } from '../../api';
import { DialogKonfirmasi, KolomTeks, KotakPesan, Tombol } from '../../components/Dasar';
import FormGulir from '../../components/FormGulir';
import { BarisRincian } from '../../components/Lelang';
import { formatKg, formatRupiah } from '../../format';
import { ukuran, warna } from '../../tema';
import { hanyaAngka } from '../../validasi';

const PILIHAN_DURASI = [60, 120, 180, 300];

/** UC-03 langkah 2-4: operator menetapkan durasi (30-600 detik) dan kenaikan minimum (bawaan ALG-02). */
export default function FormBukaSesi({ lot, onBatal, onDibuka }) {
  const [durasi, setDurasi] = useState('120');
  const [kenaikan, setKenaikan] = useState(String(lot.usulan_kenaikan));
  const [galat, setGalat] = useState({});
  const [pesan, setPesan] = useState(null);
  const [tanya, setTanya] = useState(false);
  const [memuat, setMemuat] = useState(false);

  function periksa() {
    const g = {};
    const d = Number(durasi);
    const k = Number(kenaikan);
    if (!/^\d+$/.test(durasi) || d < 30 || d > 600) g.durasi_detik = 'Durasi 30 sampai 600 detik.';
    if (!/^\d+$/.test(kenaikan) || k < 100 || k > 1000000) g.kenaikan_minimum = 'Kenaikan minimum Rp100 sampai Rp1.000.000 per kg.';
    setGalat(g);
    if (Object.keys(g).length === 0) setTanya(true);
  }

  async function mulai() {
    setTanya(false);
    setMemuat(true);
    setPesan(null);
    try {
      const hasil = await operator.buka(lot.id_lot, Number(durasi), Number(kenaikan));
      onDibuka(hasil.data);
    } catch (e) {
      setMemuat(false);
      if (e.status === 422 && Object.keys(e.galatKolom).length) {
        setGalat(e.galatKolom);
        if (e.galatKolom.id_lot) setPesan(e.galatKolom.id_lot);
      } else setPesan(e.message);
    }
  }

  return (
    <View style={{ flex: 1, backgroundColor: warna.latar }}>
      <FormGulir gayaIsi={s.isi}>
        <View style={s.kartu}>
          <Text style={s.judul}>Buka Sesi Lelang</Text>
          <Text style={s.kode}>{lot.kode_lot}</Text>
          <View style={s.rincian}>
            <BarisRincian label="Jenis ikan" nilai={`${lot.nama_jenis} (Grade ${lot.kode_grade})`} />
            <BarisRincian label="Berat aktual" nilai={formatKg(lot.berat_aktual)} />
            <BarisRincian label="Harga pembukaan" nilai={`${formatRupiah(lot.harga_pembukaan)}/kg`} tebal />
            <BarisRincian label="Perkiraan nilai lot" nilai={formatRupiah(lot.nilai_lot)} />
            <BarisRincian label="Kapal" nilai={lot.nama_kapal} />
            <BarisRincian label="Peserta terdaftar" nilai={`${lot.jumlah_peserta} pembeli`} />
          </View>

          {lot.jumlah_peserta === 0 ? (
            <KotakPesan
              jenis="info"
              teks="Belum ada pembeli yang mendaftar ikut lelang lot ini. Sesi tetap bisa dibuka, tetapi lot berpotensi tidak terjual."
              gaya={{ marginTop: 14 }}
            />
          ) : null}
          {pesan ? <KotakPesan teks={pesan} gaya={{ marginTop: 14 }} /> : null}

          <Text style={[s.label, { marginTop: 18 }]}>Durasi sesi (detik)</Text>
          <View style={s.pilihan}>
            {PILIHAN_DURASI.map((d) => (
              <Pressable
                key={d}
                onPress={() => {
                  setDurasi(String(d));
                  setGalat({ ...galat, durasi_detik: null });
                }}
                style={[s.cip, durasi === String(d) && s.cipAktif]}
              >
                <Text style={[s.teksCip, durasi === String(d) && s.teksCipAktif]}>{d >= 60 ? `${d / 60} menit` : `${d} dtk`}</Text>
              </Pressable>
            ))}
          </View>
          <KolomTeks
            value={durasi}
            onChangeText={(t) => {
              setDurasi(hanyaAngka(t).slice(0, 3));
              setGalat({ ...galat, durasi_detik: null });
            }}
            keyboardType="number-pad"
            placeholder="30 - 600"
            galat={galat.durasi_detik}
            accessibilityLabel="Durasi sesi"
          />

          <KolomTeks
            label="Kenaikan minimum (Rp per kg)"
            value={kenaikan}
            onChangeText={(t) => {
              setKenaikan(hanyaAngka(t).slice(0, 7));
              setGalat({ ...galat, kenaikan_minimum: null });
            }}
            keyboardType="number-pad"
            galat={galat.kenaikan_minimum}
            accessibilityLabel="Kenaikan minimum"
          />
          <Text style={s.petunjuk}>
            Usulan sistem (ALG-02): {formatRupiah(lot.usulan_kenaikan)}/kg, menurut nilai lot. Boleh diubah.
          </Text>

          <Tombol judul="MULAI LELANG" onPress={periksa} memuat={memuat} gaya={{ marginTop: 18 }} />
          <Tombol judul="Batal" jenis="teks" onPress={onBatal} nonaktif={memuat} gaya={{ marginTop: 6 }} />
        </View>
      </FormGulir>

      <DialogKonfirmasi
        tampil={tanya}
        judul={`Mulai lelang ${lot.kode_lot}?`}
        pesan={`Durasi ${durasi} detik, kenaikan minimum ${formatRupiah(kenaikan)}/kg. Peserta dapat langsung menawar setelah sesi dibuka.`}
        teksYa="Mulai Lelang"
        onYa={mulai}
        onTidak={() => setTanya(false)}
      />
    </View>
  );
}

const s = StyleSheet.create({
  isi: { padding: 16, paddingBottom: 32 },
  kartu: { backgroundColor: warna.kartu, borderRadius: 16, padding: 20 },
  judul: { fontSize: 22, fontWeight: '700', color: warna.teks },
  kode: { fontSize: 18, fontWeight: '800', color: warna.utama, marginTop: 4 },
  rincian: { backgroundColor: warna.latar, borderRadius: ukuran.radius, padding: 12, marginTop: 12, gap: 8 },
  label: { fontSize: ukuran.hurufNormal, fontWeight: '600', color: warna.teks, marginBottom: 8 },
  pilihan: { flexDirection: 'row', gap: 8, marginBottom: 10, flexWrap: 'wrap' },
  cip: {
    minHeight: 44, paddingHorizontal: 14, borderRadius: 999, borderWidth: 1.5, borderColor: warna.garis,
    alignItems: 'center', justifyContent: 'center', backgroundColor: warna.putih,
  },
  cipAktif: { borderColor: warna.utama, backgroundColor: warna.utamaMuda },
  teksCip: { fontSize: 15, fontWeight: '600', color: warna.teksRedup },
  teksCipAktif: { color: warna.utama, fontWeight: '800' },
  petunjuk: { fontSize: 13, color: warna.teksRedup, marginTop: -6, lineHeight: 19 },
});

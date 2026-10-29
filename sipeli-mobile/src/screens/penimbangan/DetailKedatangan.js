import { useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { KotakPesan, Lencana, Tombol } from '../../components/Dasar';
import Ikon from '../../components/Ikon';
import { formatKg, formatRupiah, tanggalPendek } from '../../format';
import { ukuran, warna } from '../../tema';
import LencanaLot from './LencanaLot';

/**
 * Alur utama UC-02 langkah (2): daftar baris tangkapan sebuah kedatangan
 * Baris BELUM_DITIMBANG -> tombol Timbang; baris DITIMBANG -> info lot + tombol Label
 */
export default function DetailKedatangan({ kedatangan, onMuatUlang, onTimbang, onLihatLabel, onKembali }) {
  const [menyegarkan, setMenyegarkan] = useState(false);
  const [galat, setGalat] = useState(null);
  const k = kedatangan;
  const jumlah = k.tangkapan.length;
  const sudah = k.tangkapan.filter((t) => t.status === 'DITIMBANG').length;

  async function segarkan() {
    setMenyegarkan(true);
    setGalat(null);
    try {
      await onMuatUlang();
    } catch (e) {
      if (e.status !== 401) setGalat(e.message);
    }
    setMenyegarkan(false);
  }

  return (
    <View style={{ flex: 1 }}>
      <View style={s.bilahKembali}>
        <Pressable onPress={onKembali} style={s.tombolKembali} accessibilityRole="button">
          <Ikon nama="kembali" ukuran={22} warna={warna.utama} />
          <Text style={s.teksKembali}>Pilih Kedatangan</Text>
        </Pressable>
      </View>

      <ScrollView
        contentContainerStyle={s.isi}
        refreshControl={<RefreshControl refreshing={menyegarkan} onRefresh={segarkan} colors={[warna.utama]} />}
      >
        {/* Kepala kedatangan */}
        <View style={s.kepala}>
          <View style={s.barisAtas}>
            <Text style={s.nomor}>{k.nomor_kedatangan}</Text>
            <Lencana status={k.status} />
          </View>
          <Text style={s.namaKapal}>{k.nama_kapal}</Text>
          <Text style={s.redup}>
            {k.nomor_registrasi} - Kapten {k.nama_kapten}
          </Text>
          <Text style={s.redup}>
            {tanggalPendek(k.tanggal)}, {k.jam}
          </Text>
          <View style={s.bilahKemajuan}>
            <View style={[s.isiKemajuan, { width: `${jumlah ? (sudah / jumlah) * 100 : 0}%` }]} />
          </View>
          <Text style={s.teksKemajuan}>
            {sudah} dari {jumlah} baris sudah ditimbang
          </Text>
        </View>

        <KotakPesan teks={galat} gaya={{ marginBottom: 12 }} />
        {jumlah > 0 && sudah === jumlah ? (
          <KotakPesan
            jenis="sukses"
            teks="Semua baris kedatangan ini sudah ditimbang dan dibuat lot."
            gaya={{ marginBottom: 12 }}
          />
        ) : null}

        {k.tangkapan.map((t, i) => {
          const lot = t.lot;
          return (
            <View key={t.id_tangkapan} style={[s.kartu, lot && s.kartuSelesai]}>
              <View style={s.barisAtas}>
                <Text style={s.nomorBaris}>BARIS {i + 1}</Text>
                {lot ? <LencanaLot status={lot.status} /> : (
                  <Lencana teks="Belum ditimbang" latar={warna.peringatanLatar} warnaTeks={warna.peringatan} />
                )}
              </View>
              <Text style={s.jenis}>
                {t.nama_jenis} <Text style={s.grade}>- Grade {t.kode_grade}</Text>
              </Text>
              <Text style={s.redup}>
                Estimasi {formatKg(t.berat_estimasi)} - {t.jumlah_keranjang} keranjang
              </Text>

              {lot ? (
                <View style={s.infoLot}>
                  <View style={{ flex: 1 }}>
                    <Text style={s.kodeLot}>{lot.kode_lot}</Text>
                    <Text style={s.redup}>
                      Aktual {formatKg(lot.berat_aktual)}
                      {lot.harga_pembukaan ? ` - ${formatRupiah(lot.harga_pembukaan)}/kg` : ''}
                    </Text>
                  </View>
                  <Tombol
                    judul="Label"
                    ikon="qr"
                    jenis="garis"
                    onPress={() => onLihatLabel(lot)}
                    gaya={{ minHeight: 48, paddingHorizontal: 14 }}
                  />
                </View>
              ) : (
                <Tombol
                  judul="Timbang"
                  ikon="timbangan"
                  onPress={() => onTimbang(t)}
                  gaya={{ marginTop: 12 }}
                />
              )}
            </View>
          );
        })}
      </ScrollView>
    </View>
  );
}

const s = StyleSheet.create({
  bilahKembali: {
    backgroundColor: warna.putih, borderBottomWidth: 1, borderBottomColor: '#E4EBF1',
    paddingHorizontal: 8, paddingVertical: 4,
  },
  tombolKembali: { flexDirection: 'row', alignItems: 'center', gap: 4, minHeight: 48, paddingHorizontal: 8, alignSelf: 'flex-start' },
  teksKembali: { color: warna.utama, fontSize: ukuran.hurufNormal, fontWeight: '700' },
  isi: { padding: 16, paddingBottom: 40 },
  kepala: {
    backgroundColor: warna.putih, borderRadius: 14, padding: 16, marginBottom: 14,
    borderWidth: 1, borderColor: '#E4EBF1',
  },
  barisAtas: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 },
  nomor: { fontSize: 22, fontWeight: '800', color: warna.utama, letterSpacing: 0.5 },
  namaKapal: { fontSize: 20, fontWeight: '800', color: warna.teks, marginTop: 6 },
  redup: { fontSize: 14, color: warna.teksRedup, marginTop: 2 },
  bilahKemajuan: { height: 10, borderRadius: 5, backgroundColor: warna.abu, marginTop: 14, overflow: 'hidden' },
  isiKemajuan: { height: 10, borderRadius: 5, backgroundColor: warna.sukses },
  teksKemajuan: { fontSize: 14, fontWeight: '700', color: warna.teks, marginTop: 6 },
  kartu: {
    backgroundColor: warna.putih, borderRadius: 14, padding: 14, marginBottom: 10,
    borderWidth: 1, borderColor: '#E4EBF1', borderLeftWidth: 5, borderLeftColor: warna.peringatan,
  },
  kartuSelesai: { borderLeftColor: warna.sukses },
  nomorBaris: { fontSize: 12, fontWeight: '800', color: warna.teksRedup, letterSpacing: 0.6 },
  jenis: { fontSize: ukuran.hurufSedang, fontWeight: '800', color: warna.teks, marginTop: 6 },
  grade: { fontWeight: '600', color: warna.teksRedup },
  infoLot: {
    flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 12,
    paddingTop: 12, borderTopWidth: 1, borderTopColor: '#E4EBF1',
  },
  kodeLot: { fontSize: ukuran.hurufNormal, fontWeight: '800', color: warna.teks, letterSpacing: 0.5 },
});

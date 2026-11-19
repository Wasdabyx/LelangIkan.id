import { useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { KotakPesan, Lencana, Tombol } from '../../components/Dasar';
import FotoIkan from '../../components/FotoIkan';
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
            {k.nomor_registrasi} | Kapten {k.nama_kapten}
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

        <KotakPesan teks={galat} gaya={{ marginHorizontal: 16, marginBottom: 8 }} />
        {jumlah > 0 && sudah === jumlah ? (
          <KotakPesan
            jenis="sukses"
            teks="Semua baris kedatangan ini sudah ditimbang dan dibuat lot."
            gaya={{ marginHorizontal: 16, marginBottom: 8 }}
          />
        ) : null}

        {k.tangkapan.map((t, i) => {
          const lot = t.lot;
          return (
            <View key={t.id_tangkapan} style={[s.kartu, lot && s.kartuSelesai]}>
              <View style={s.barisAtas}>
                <Text style={s.nomorBaris}>Baris {i + 1}</Text>
                {lot ? <LencanaLot status={lot.status} /> : (
                  <Text style={s.belum}>Belum ditimbang</Text>
                )}
              </View>
              <Text style={s.jenis}>
                {t.nama_jenis} <Text style={s.grade}>Grade {t.kode_grade}</Text>
              </Text>
              <Text style={s.redup}>
                Estimasi {formatKg(t.berat_estimasi)} | {t.jumlah_keranjang} keranjang
              </Text>

              {lot ? (
                <View style={s.infoLot}>
                  <FotoIkan lot={{ ...lot, nama_jenis: lot.nama_jenis || t.nama_jenis }} lebar={56} tinggi={56} kecil bisaPerbesar={false} />
                  <View style={{ flex: 1 }}>
                    <Text style={s.kodeLot}>{lot.kode_lot}</Text>
                    <Text style={s.redup}>
                      Aktual {formatKg(lot.berat_aktual)}
                      {lot.harga_pembukaan ? ` | ${formatRupiah(lot.harga_pembukaan)}/kg` : ''}
                    </Text>
                  </View>
                  <Tombol
                    judul="Label"
                    jenis="garis"
                    kecil
                    onPress={() => onLihatLabel(lot)}
                  />
                </View>
              ) : (
                <Tombol
                  judul="Timbang"
                  kecil
                  onPress={() => onTimbang(t)}
                  gaya={{ marginTop: 10, alignSelf: 'flex-start', minWidth: 120 }}
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
    backgroundColor: warna.putih, borderBottomWidth: 1, borderBottomColor: warna.garisHalus,
    paddingHorizontal: 8, paddingVertical: 2,
  },
  tombolKembali: { flexDirection: 'row', alignItems: 'center', gap: 4, minHeight: 44, paddingHorizontal: 8, alignSelf: 'flex-start' },
  teksKembali: { color: warna.utama, fontSize: ukuran.hurufNormal, fontWeight: '600' },
  isi: { paddingBottom: 40 },
  kepala: { backgroundColor: warna.putih, padding: 16, marginBottom: 8, borderBottomWidth: 1, borderColor: warna.garisHalus },
  barisAtas: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 },
  nomor: { fontSize: 14, fontWeight: '600', color: warna.utama, fontVariant: ['tabular-nums'] },
  namaKapal: { fontSize: 18, fontWeight: '700', color: warna.teks, marginTop: 2 },
  redup: { fontSize: 12, color: warna.teksRedup, marginTop: 2 },
  bilahKemajuan: { height: 6, borderRadius: 3, backgroundColor: warna.abu, marginTop: 12, overflow: 'hidden' },
  isiKemajuan: { height: 6, borderRadius: 3, backgroundColor: warna.sukses },
  teksKemajuan: { fontSize: 12, fontWeight: '600', color: warna.teks, marginTop: 6 },
  kartu: {
    backgroundColor: warna.putih, paddingHorizontal: 16, paddingVertical: 12,
    borderBottomWidth: 1, borderColor: warna.garisHalus,
  },
  kartuSelesai: {},
  nomorBaris: { fontSize: 12, fontWeight: '600', color: warna.teksRedup },
  belum: { fontSize: 12, fontWeight: '600', color: warna.peringatan },
  jenis: { fontSize: 15, fontWeight: '600', color: warna.teks, marginTop: 2 },
  grade: { fontWeight: '400', color: warna.teksRedup },
  infoLot: {
    flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 8,
    paddingTop: 8, borderTopWidth: 1, borderTopColor: warna.garisHalus,
  },
  kodeLot: { fontSize: 14, fontWeight: '600', color: warna.teks, fontVariant: ['tabular-nums'] },
});

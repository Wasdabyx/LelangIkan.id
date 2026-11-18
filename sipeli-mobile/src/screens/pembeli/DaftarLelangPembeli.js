import { useCallback, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { lelang } from '../../api';
import { KotakPesan, Lencana } from '../../components/Dasar';
import Ikon from '../../components/Ikon';
import { formatSisa, WARNA_TRANSAKSI } from '../../components/Lelang';
import { formatKg, formatRupiah, waktuPendek } from '../../format';
import { usePolling } from '../../polling';
import { warna } from '../../tema';

/**
 * Tab "Lelang" (sesi pada lot yang dipesan, diperbarui tiap 3 detik)
 * dan tab "Tagihan" (transaksi hasil menang lelang).
 */
export default function DaftarLelangPembeli({ tab, onMasukRuang, onBukaTagihan }) {
  const [sesi, setSesi] = useState(null);
  const [tagihan, setTagihan] = useState(null);
  const [galat, setGalat] = useState(null);
  const [segar, setSegar] = useState(false);

  const muat = useCallback(async () => {
    try {
      const [a, b] = await Promise.all([lelang.daftar(), lelang.tagihan()]);
      setSesi(a.data);
      setTagihan(b.data);
      setGalat(null);
    } catch (e) {
      if (e.status !== 401) setGalat(e.message);
    } finally {
      setSegar(false);
    }
  }, []);
  usePolling(muat, 3000);

  if (!sesi || !tagihan) {
    return (
      <View style={s.tengah}>
        {galat ? <KotakPesan teks={galat} /> : <ActivityIndicator size="large" color={warna.utama} />}
      </View>
    );
  }
  const data = tab === 'lelang' ? sesi : tagihan;
  const belumLunas = tagihan.filter((t) => t.status !== 'LUNAS').length;

  return (
    <FlatList
      style={{ flex: 1, backgroundColor: warna.latar }}
      data={data}
      keyExtractor={(x) => String(x.id_sesi ?? x.id_transaksi)}
      contentContainerStyle={s.isi}
      refreshControl={<RefreshControl refreshing={segar} onRefresh={() => { setSegar(true); muat(); }} />}
      ListHeaderComponent={
        <>
          {galat ? <KotakPesan teks={galat} gaya={{ marginBottom: 12 }} /> : null}
          <Text style={s.keterangan}>
            {tab === 'lelang'
              ? 'Lelang untuk lot yang sudah Anda pesan.'
              : belumLunas > 0
                ? `${belumLunas} tagihan belum lunas. Bayar ke Kasir TPI dengan menyebutkan nomor transaksi.`
                : 'Tagihan dari lot yang Anda menangkan.'}
          </Text>
        </>
      }
      ListEmptyComponent={
        <View style={s.kosong}>
          <Ikon nama={tab === 'lelang' ? 'jam' : 'dompet'} ukuran={44} warna={warna.teksRedup} />
          <Text style={s.teksKosong}>
            {tab === 'lelang'
              ? 'Belum ada sesi lelang untuk lot yang Anda pesan.'
              : 'Belum ada tagihan.'}
          </Text>
        </View>
      }
      renderItem={({ item }) =>
        tab === 'lelang' ? (
          <KartuSesi sesi={item} onPress={() => onMasukRuang(item.id_sesi)} />
        ) : (
          <KartuTagihan t={item} onPress={() => onBukaTagihan(item.id_transaksi)} />
        )
      }
    />
  );
}

function KartuSesi({ sesi, onPress }) {
  const aktif = sesi.status === 'AKTIF';
  let lencana;
  if (aktif) lencana = <Lencana teks={`Berjalan ${formatSisa(sesi.sisa_detik)}`} ikon="jam" latar={warna.utamaMuda} warnaTeks={warna.utama} />;
  else if (sesi.menang) lencana = <Lencana teks="Anda menang" ikon="centang" latar={warna.suksesLatar} warnaTeks={warna.sukses} />;
  else lencana = <Lencana teks="Selesai" latar={warna.abu} warnaTeks={warna.teksRedup} />;

  const posisi = { TERTINGGI: 'Anda tertinggi', TERTINGGAL: 'Anda tertinggal', BELUM_MENAWAR: 'Belum menawar' }[sesi.posisi];
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [s.kartu, aktif && s.kartuAktif, pressed && { opacity: 0.85 }]}>
      <View style={s.barisAtas}>
        <Text style={s.kode}>{sesi.lot.kode_lot}</Text>
        {lencana}
      </View>
      <Text style={s.jenis}>
        {sesi.lot.nama_jenis} <Text style={{ color: warna.aksen }}>Grade {sesi.lot.kode_grade}</Text> | {formatKg(sesi.lot.berat_aktual)}
      </Text>
      <Text style={s.redup}>
        {sesi.harga_tertinggi ? `Tertinggi ${formatRupiah(sesi.harga_tertinggi)}/kg` : `Buka ${formatRupiah(sesi.lot.harga_pembukaan)}/kg`}
        {aktif ? ` | ${posisi}` : ''}
      </Text>
      {aktif ? (
        <View style={s.masuk}>
          <Text style={s.teksMasuk}>Masuk Ruang Lelang</Text>
          <Ikon nama="kanan" ukuran={20} warna={warna.putih} />
        </View>
      ) : null}
    </Pressable>
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
      <Text style={s.jenis}>
        {t.kode_lot} | {t.nama_jenis} Grade {t.kode_grade}
      </Text>
      <View style={s.barisAtas}>
        <Text style={s.redup}>{waktuPendek(t.waktu_transaksi)}</Text>
        <Text style={s.total}>{formatRupiah(t.status === 'LUNAS' ? t.total_tagihan : t.sisa_tagihan)}</Text>
      </View>
    </Pressable>
  );
}

const s = StyleSheet.create({
  tengah: { flex: 1, backgroundColor: warna.latar, alignItems: 'center', justifyContent: 'center', padding: 16 },
  isi: { padding: 16, paddingBottom: 32, gap: 10 },
  keterangan: { fontSize: 13, color: warna.teksRedup, lineHeight: 18 },
  kosong: { alignItems: 'center', padding: 32, gap: 12 },
  teksKosong: { fontSize: 14, color: warna.teksRedup, textAlign: 'center', lineHeight: 20 },
  kartu: { backgroundColor: warna.kartu, borderRadius: 6, padding: 16, borderWidth: 1, borderColor: '#E4EBF1' },
  kartuAktif: { borderColor: warna.utama, borderWidth: 2 },
  barisAtas: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 },
  kode: { fontSize: 16, fontWeight: '700', color: warna.teks },
  jenis: { fontSize: 14, fontWeight: '600', color: warna.teks, marginTop: 8 },
  redup: { fontSize: 13, color: warna.teksRedup, marginTop: 4 },
  total: { fontSize: 15, fontWeight: '700', color: warna.teks, marginTop: 4 },
  masuk: {
    marginTop: 12, minHeight: 44, borderRadius: 6, backgroundColor: warna.utama,
    flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8,
  },
  teksMasuk: { color: warna.putih, fontSize: 15, fontWeight: '700' },
});

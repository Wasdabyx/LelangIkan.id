import { useCallback, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { operator } from '../../api';
import { KotakPesan, Lencana, Tombol } from '../../components/Dasar';
import Ikon from '../../components/Ikon';
import { formatSisa } from '../../components/Lelang';
import { formatKg, formatRupiah } from '../../format';
import { usePolling } from '../../polling';
import { warna } from '../../tema';

/** FR-12: antrean lot siap lelang + daftar sesi hari ini (diperbarui tiap 3 detik). */
export default function AntreanLot({ tab, onPilihLot, onBukaSesi }) {
  const [antrean, setAntrean] = useState(null);
  const [sesi, setSesi] = useState(null);
  const [galat, setGalat] = useState(null);
  const [segar, setSegar] = useState(false);

  const muat = useCallback(async () => {
    try {
      const [a, b] = await Promise.all([operator.antrean(), operator.daftarSesi()]);
      setAntrean(a.data);
      setSesi(b.data);
      setGalat(null);
    } catch (e) {
      if (e.status !== 401) setGalat(e.message);
    } finally {
      setSegar(false);
    }
  }, []);
  usePolling(muat, 3000);

  if (!antrean || !sesi) {
    return (
      <View style={s.tengah}>
        {galat ? <KotakPesan teks={galat} /> : <ActivityIndicator size="large" color={warna.utama} />}
      </View>
    );
  }

  const aktif = sesi.filter((x) => x.status === 'AKTIF').length;
  const data = tab === 'antrean' ? antrean : sesi;

  return (
    <FlatList
      style={{ flex: 1, backgroundColor: warna.latar }}
      data={data}
      keyExtractor={(x) => String(x.id_sesi ?? x.id_lot)}
      contentContainerStyle={s.isi}
      refreshControl={<RefreshControl refreshing={segar} onRefresh={() => { setSegar(true); muat(); }} />}
      ListHeaderComponent={
        <>
          {galat ? <KotakPesan teks={galat} gaya={{ marginBottom: 12 }} /> : null}
          <Text style={s.keterangan}>
            {tab === 'antrean'
              ? `${antrean.length} lot menunggu lelang, urut nomor lot. ${aktif > 0 ? `${aktif} sesi sedang berjalan.` : ''}`
              : 'Sesi yang sedang berjalan dan yang sudah ditutup hari ini.'}
          </Text>
        </>
      }
      ListEmptyComponent={
        <View style={s.kosong}>
          <Ikon nama={tab === 'antrean' ? 'ikan' : 'jam'} ukuran={44} warna={warna.teksRedup} />
          <Text style={s.teksKosong}>
            {tab === 'antrean' ? 'Tidak ada lot yang menunggu lelang.' : 'Belum ada sesi lelang hari ini.'}
          </Text>
        </View>
      }
      renderItem={({ item }) =>
        tab === 'antrean' ? <KartuAntrean lot={item} onPilih={() => onPilihLot(item)} /> : <KartuSesi sesi={item} onPress={() => onBukaSesi(item.id_sesi)} />
      }
    />
  );
}

function KartuAntrean({ lot, onPilih }) {
  return (
    <View style={s.kartu}>
      <View style={s.barisAtas}>
        <Text style={s.kode}>{lot.kode_lot}</Text>
        <Lencana
          teks={`${lot.jumlah_peserta} peserta`}
          ikon="pengguna"
          latar={lot.jumlah_peserta > 0 ? warna.suksesLatar : warna.peringatanLatar}
          warnaTeks={lot.jumlah_peserta > 0 ? warna.sukses : warna.peringatan}
        />
      </View>
      <Text style={s.jenis}>
        {lot.nama_jenis} <Text style={s.grade}>Grade {lot.kode_grade}</Text>
      </Text>
      <Text style={s.redup}>
        {formatKg(lot.berat_aktual)} | Buka {formatRupiah(lot.harga_pembukaan)}/kg | {lot.nama_kapal}
      </Text>
      <Tombol judul="Atur dan Mulai Lelang" ikon="kanan" onPress={onPilih} gaya={{ marginTop: 12 }} />
    </View>
  );
}

function KartuSesi({ sesi, onPress }) {
  const aktif = sesi.status === 'AKTIF';
  const terjual = sesi.status_lot === 'TERJUAL' || sesi.status_lot === 'SELESAI';
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [s.kartu, pressed && { opacity: 0.85 }]}>
      <View style={s.barisAtas}>
        <Text style={s.kode}>{sesi.lot.kode_lot}</Text>
        {aktif ? (
          <Lencana teks={`Berjalan ${formatSisa(sesi.sisa_detik)}`} ikon="jam" latar={warna.utamaMuda} warnaTeks={warna.utama} />
        ) : terjual ? (
          <Lencana teks="Terjual" ikon="centang" latar={warna.suksesLatar} warnaTeks={warna.sukses} />
        ) : (
          <Lencana teks="Tidak terjual" latar={warna.abu} warnaTeks={warna.teksRedup} />
        )}
      </View>
      <Text style={s.jenis}>
        {sesi.lot.nama_jenis} <Text style={s.grade}>Grade {sesi.lot.kode_grade}</Text>
      </Text>
      <Text style={s.redup}>
        {sesi.harga_tertinggi ? `Tertinggi ${formatRupiah(sesi.harga_tertinggi)}/kg` : 'Belum ada tawaran'} | {sesi.jumlah_penawaran} tawaran | mulai {sesi.waktu_mulai.slice(11, 16)}
      </Text>
    </Pressable>
  );
}

const s = StyleSheet.create({
  tengah: { flex: 1, backgroundColor: warna.latar, alignItems: 'center', justifyContent: 'center', padding: 20 },
  isi: { padding: 16, paddingBottom: 32, gap: 12 },
  keterangan: { fontSize: 14, color: warna.teksRedup, lineHeight: 20 },
  kosong: { alignItems: 'center', padding: 32, gap: 12 },
  teksKosong: { fontSize: 15, color: warna.teksRedup, textAlign: 'center' },
  kartu: { backgroundColor: warna.kartu, borderRadius: 14, padding: 16, borderWidth: 1, borderColor: '#E4EBF1' },
  barisAtas: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 },
  kode: { fontSize: 18, fontWeight: '800', color: warna.teks },
  jenis: { fontSize: 16, fontWeight: '700', color: warna.teks, marginTop: 8 },
  grade: { color: warna.aksen },
  redup: { fontSize: 14, color: warna.teksRedup, marginTop: 4, lineHeight: 20 },
});

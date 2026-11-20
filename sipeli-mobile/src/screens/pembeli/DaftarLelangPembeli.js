import { useCallback, useState } from 'react';
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { lelang } from '../../api';
import { KotakPesan, Lencana, Tombol } from '../../components/Dasar';
import { Bagian, BarisDaftar, Tabel, TitikStatus } from '../../components/Tata';
import FotoIkan from '../../components/FotoIkan';
import { formatSisa, WARNA_TRANSAKSI } from '../../components/Lelang';
import { formatKg, formatRupiah, waktuPendek } from '../../format';
import { usePolling } from '../../polling';
import { useSesiDimulai } from '../../realtime';
import { warna } from '../../tema';

/**
 * Tab "Lelang" (sesi pada lot yang dipesan, diperbarui tiap 3 detik)
 * dan tab "Tagihan" (transaksi hasil menang lelang).
 */
export default function DaftarLelangPembeli({ tab, idTpi, onMasukRuang, onBukaTagihan }) {
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
  usePolling(muat, 3000); // fallback jika WebSocket tidak tersedia
  useSesiDimulai(idTpi, () => muat());

  if (!sesi || !tagihan) {
    return (
      <View style={s.tengah}>
        {galat ? <KotakPesan teks={galat} /> : <ActivityIndicator size="large" color={warna.utama} />}
      </View>
    );
  }
  const belumLunas = tagihan.filter((t) => t.status !== 'LUNAS');
  const totalSisa = belumLunas.reduce((a, t) => a + Number(t.sisa_tagihan), 0);
  const berjalan = sesi.filter((x) => x.status === 'AKTIF');
  const selesai = sesi.filter((x) => x.status !== 'AKTIF');

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: warna.latar }}
      contentContainerStyle={s.isi}
      refreshControl={<RefreshControl refreshing={segar} onRefresh={() => { setSegar(true); muat(); }} />}
    >
      {galat ? <KotakPesan teks={galat} gaya={{ margin: 12 }} /> : null}
      {tab === 'lelang' ? (
        <>
          <View style={s.kepala}>
            <Text style={s.jumlah}>{berjalan.length} sesi berjalan</Text>
            <Text style={s.keterangan}>Lelang untuk lot yang sudah Anda pesan.</Text>
          </View>
          {sesi.length === 0 ? (
            <Text style={s.teksKosong}>Belum ada sesi lelang untuk lot yang Anda pesan.</Text>
          ) : null}
          {berjalan.map((x) => (
            <SesiBerjalan key={x.id_sesi} sesi={x} onPress={() => onMasukRuang(x.id_sesi)} />
          ))}
          {selesai.length > 0 ? (
            <>
              <Text style={s.judulKelompok}>Selesai hari ini</Text>
              {selesai.map((x, i) => (
                <SesiSelesai key={x.id_sesi} sesi={x} terakhir={i === selesai.length - 1} onPress={() => onMasukRuang(x.id_sesi)} />
              ))}
            </>
          ) : null}
        </>
      ) : (
        <>
          <View style={s.kepala}>
            <Text style={s.jumlah}>
              {belumLunas.length > 0 ? `Sisa tagihan ${formatRupiah(totalSisa)}` : 'Semua tagihan lunas'}
            </Text>
            <Text style={s.keterangan}>
              {belumLunas.length > 0
                ? `${belumLunas.length} tagihan belum lunas. Bayar ke Kasir TPI dengan menyebutkan nomor transaksi.`
                : 'Tagihan dari lot yang Anda menangkan.'}
            </Text>
          </View>
          <Bagian tanpaJarak gaya={{ borderTopWidth: 0 }}>
            <View style={{ paddingHorizontal: 16 }}>
              <Tabel
                label="Daftar tagihan"
                kosong="Belum ada tagihan."
                data={tagihan}
                kunci={(t) => t.id_transaksi}
                onPilih={(t) => onBukaTagihan(t.id_transaksi)}
                kolom={[
                  {
                    judul: 'Transaksi', flex: 1.5,
                    render: (t) => (
                      <View>
                        <Text style={s.selKode}>{t.kode_transaksi}</Text>
                        <Text style={s.selKecil} numberOfLines={1}>{t.nama_jenis} | {t.kode_lot}</Text>
                      </View>
                    ),
                  },
                  {
                    judul: 'Status', flex: 1.2,
                    render: (t) => {
                      const w = WARNA_TRANSAKSI[t.status];
                      return <Lencana teks={w.label} latar={w.latar} warnaTeks={w.teks} />;
                    },
                  },
                  {
                    judul: 'Sisa / total', flex: 1.3, kanan: true, tebal: true,
                    render: (t) => formatRupiah(t.status === 'LUNAS' ? t.total_tagihan : t.sisa_tagihan),
                  },
                ]}
              />
            </View>
          </Bagian>
        </>
      )}
    </ScrollView>
  );
}

function SesiBerjalan({ sesi, onPress }) {
  const posisi = {
    TERTINGGI: ['Anda tertinggi', warna.sukses],
    TERTINGGAL: ['Anda tertinggal', warna.galat],
    BELUM_MENAWAR: ['Belum menawar', warna.teksRedup],
  }[sesi.posisi] || ['', warna.teksRedup];
  const genting = sesi.sisa_detik <= 15;
  return (
    <View style={s.berjalan}>
      <View style={s.barisAtas}>
        <FotoIkan lot={sesi.lot} lebar={72} tinggi={56} kecil />
        <View style={{ flex: 1 }}>
          <Text style={s.kode}>{sesi.lot.kode_lot}</Text>
          <Text style={s.jenis}>
            {sesi.lot.nama_jenis} <Text style={{ color: warna.aksen }}>Grade {sesi.lot.kode_grade}</Text> | {formatKg(sesi.lot.berat_aktual)}
          </Text>
        </View>
        <View style={{ alignItems: 'flex-end' }}>
          <Text style={[s.jam, genting && { color: warna.galat }]}>{formatSisa(sesi.sisa_detik)}</Text>
          <Text style={s.selKecil}>Berjalan</Text>
        </View>
      </View>
      <View style={s.barisHarga}>
        <View>
          <Text style={s.selKecil}>{sesi.harga_tertinggi ? 'Tertinggi' : 'Harga buka'}</Text>
          <Text style={s.harga}>{formatRupiah(sesi.harga_tertinggi ?? sesi.lot.harga_pembukaan)}/kg</Text>
        </View>
        <TitikStatus teks={posisi[0]} w={posisi[1]} />
      </View>
      <Tombol judul="Masuk Ruang Lelang" onPress={onPress} gaya={{ marginTop: 10 }} />
    </View>
  );
}

function SesiSelesai({ sesi, onPress, terakhir }) {
  return (
    <BarisDaftar
      terakhir={terakhir}
      onPress={onPress}
      kiri={<FotoIkan lot={sesi.lot} lebar={48} tinggi={48} kecil bisaPerbesar={false} />}
      kanan={
        <View style={{ alignItems: 'flex-end' }}>
          {sesi.menang ? (
            <Lencana teks="Anda menang" latar={warna.suksesLatar} warnaTeks={warna.sukses} />
          ) : (
            <Text style={s.selKecil}>Selesai</Text>
          )}
          <Text style={[s.selKecil, { marginTop: 4 }]}>
            {sesi.harga_tertinggi ? `${formatRupiah(sesi.harga_tertinggi)}/kg` : 'Tidak terjual'}
          </Text>
        </View>
      }
    >
      <Text style={s.kodeKecil}>{sesi.lot.kode_lot}</Text>
      <Text style={s.selKecil}>
        {sesi.lot.nama_jenis} Grade {sesi.lot.kode_grade} | {formatKg(sesi.lot.berat_aktual)}
      </Text>
    </BarisDaftar>
  );
}

const s = StyleSheet.create({
  tengah: { flex: 1, backgroundColor: warna.latar, alignItems: 'center', justifyContent: 'center', padding: 16 },
  isi: { paddingBottom: 32 },
  kepala: { paddingHorizontal: 16, paddingTop: 12, paddingBottom: 10 },
  jumlah: { fontSize: 14, fontWeight: '600', color: warna.teks },
  keterangan: { fontSize: 12, color: warna.teksRedup, marginTop: 2, lineHeight: 17 },
  teksKosong: { fontSize: 14, color: warna.teksRedup, textAlign: 'center', padding: 32 },
  judulKelompok: { fontSize: 13, fontWeight: '600', color: warna.teksRedup, paddingHorizontal: 16, paddingTop: 14, paddingBottom: 6 },
  berjalan: {
    backgroundColor: warna.putih, padding: 16, marginBottom: 8,
    borderTopWidth: 1, borderBottomWidth: 1, borderColor: warna.garisHalus, borderLeftWidth: 3, borderLeftColor: warna.utama,
  },
  barisAtas: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  kode: { fontSize: 16, fontWeight: '600', color: warna.teks, fontVariant: ['tabular-nums'] },
  kodeKecil: { fontSize: 14, fontWeight: '600', color: warna.teks, fontVariant: ['tabular-nums'] },
  jenis: { fontSize: 13, color: warna.teks, marginTop: 2 },
  jam: { fontSize: 22, fontWeight: '700', color: warna.utama, fontVariant: ['tabular-nums'] },
  barisHarga: {
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-end', marginTop: 10,
    paddingTop: 10, borderTopWidth: 1, borderTopColor: warna.garisHalus,
  },
  harga: { fontSize: 16, fontWeight: '700', color: warna.teks, fontVariant: ['tabular-nums'] },
  selKode: { fontSize: 13, fontWeight: '600', color: warna.teks, fontVariant: ['tabular-nums'] },
  selKecil: { fontSize: 12, color: warna.teksRedup },
});

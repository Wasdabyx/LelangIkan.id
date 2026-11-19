import { useCallback, useState } from 'react';
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { operator } from '../../api';
import { KotakPesan, Lencana, Tombol } from '../../components/Dasar';
import Ikon from '../../components/Ikon';
import { BarisDaftar, Ringkasan, Tabel } from '../../components/Tata';
import FotoIkan from '../../components/FotoIkan';
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

  const berjalan = sesi.filter((x) => x.status === 'AKTIF');
  const selesai = sesi.filter((x) => x.status !== 'AKTIF');
  const terjual = selesai.filter((x) => x.status_lot === 'TERJUAL' || x.status_lot === 'SELESAI').length;

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: warna.latar }}
      contentContainerStyle={s.isi}
      refreshControl={<RefreshControl refreshing={segar} onRefresh={() => { setSegar(true); muat(); }} />}
    >
      <Ringkasan
        data={[
          ['Menunggu lelang', String(antrean.length)],
          ['Sesi berjalan', String(berjalan.length), berjalan.length ? warna.utama : undefined],
          ['Terjual hari ini', String(terjual), warna.sukses],
        ]}
      />
      {galat ? <KotakPesan teks={galat} gaya={{ margin: 12 }} /> : null}

      {tab === 'antrean' ? (
        <>
          <Text style={s.judulKelompok}>
            {antrean.length} lot menunggu lelang. {berjalan.length > 0 ? `${berjalan.length} sesi sedang berjalan.` : ''}
          </Text>
          {antrean.length === 0 ? <Text style={s.teksKosong}>Tidak ada lot yang menunggu lelang.</Text> : null}
          <View style={s.daftar}>
            {antrean.map((lot, i) => (
              <BarisAntrean key={lot.id_lot} lot={lot} terakhir={i === antrean.length - 1} onPilih={() => onPilihLot(lot)} />
            ))}
          </View>
        </>
      ) : (
        <>
          <Text style={s.judulKelompok}>Sesi yang sedang berjalan dan yang sudah ditutup hari ini.</Text>
          {sesi.length === 0 ? <Text style={s.teksKosong}>Belum ada sesi lelang hari ini.</Text> : null}
          {berjalan.length > 0 ? (
            <View style={s.daftar}>
              {berjalan.map((x, i) => (
                <BarisDaftar
                  key={x.id_sesi}
                  terakhir={i === berjalan.length - 1}
                  onPress={() => onBukaSesi(x.id_sesi)}
                  gaya={s.barisBerjalan}
                  kiri={<FotoIkan lot={x.lot} lebar={56} tinggi={56} kecil bisaPerbesar={false} />}
                  kanan={
                    <View style={{ alignItems: 'flex-end' }}>
                      <Text style={[s.jam, x.sisa_detik <= 15 && { color: warna.galat }]}>{formatSisa(x.sisa_detik)}</Text>
                      <Text style={s.kecil}>{x.jumlah_penawaran} tawaran</Text>
                    </View>
                  }
                >
                  <Text style={s.kode}>{x.lot.kode_lot}</Text>
                  <Text style={s.jenis}>
                    {x.lot.nama_jenis} <Text style={s.grade}>Grade {x.lot.kode_grade}</Text>
                  </Text>
                  <Text style={s.kecil}>
                    {x.harga_tertinggi ? `Tertinggi ${formatRupiah(x.harga_tertinggi)}/kg` : 'Belum ada tawaran'} | mulai {x.waktu_mulai.slice(11, 16)}
                  </Text>
                </BarisDaftar>
              ))}
            </View>
          ) : null}
          {selesai.length > 0 ? (
            <>
              <Text style={s.judulKelompok}>Selesai</Text>
              <View style={[s.daftar, { paddingHorizontal: 16 }]}>
                <Tabel
                  label="Sesi selesai"
                  data={selesai}
                  kunci={(x) => x.id_sesi}
                  onPilih={(x) => onBukaSesi(x.id_sesi)}
                  kolom={[
                    {
                      judul: 'Lot', flex: 1.6,
                      render: (x) => (
                        <View>
                          <Text style={s.selKode}>{x.lot.kode_lot}</Text>
                          <Text style={s.kecil} numberOfLines={1}>{x.lot.nama_jenis} Grade {x.lot.kode_grade}</Text>
                        </View>
                      ),
                    },
                    {
                      judul: 'Hasil', flex: 1,
                      render: (x) => {
                        const ok = x.status_lot === 'TERJUAL' || x.status_lot === 'SELESAI';
                        return ok
                          ? <Lencana teks="Terjual" latar={warna.suksesLatar} warnaTeks={warna.sukses} />
                          : <Text style={s.kecil}>Tidak terjual</Text>;
                      },
                    },
                    {
                      judul: 'Harga/kg', flex: 1.1, kanan: true, tebal: true,
                      render: (x) => (x.harga_tertinggi ? formatRupiah(x.harga_tertinggi) : '-'),
                    },
                  ]}
                />
              </View>
            </>
          ) : null}
        </>
      )}
    </ScrollView>
  );
}

function BarisAntrean({ lot, onPilih, terakhir }) {
  const ada = lot.jumlah_peserta > 0;
  return (
    <BarisDaftar
      terakhir={terakhir}
      kiri={<FotoIkan lot={lot} lebar={72} tinggi={72} kecil />}
      kanan={
        <View style={{ alignItems: 'flex-end', gap: 6 }}>
          <Text style={[s.peserta, { color: ada ? warna.sukses : warna.peringatan }]}>{lot.jumlah_peserta} peserta</Text>
          <Tombol judul="Atur Lelang" kecil onPress={onPilih} />
        </View>
      }
    >
      <Text style={s.kode}>{lot.kode_lot}</Text>
      <Text style={s.jenis}>
        {lot.nama_jenis} <Text style={s.grade}>Grade {lot.kode_grade}</Text>
      </Text>
      <Text style={s.kecil}>
        {formatKg(lot.berat_aktual)} | buka {formatRupiah(lot.harga_pembukaan)}/kg
      </Text>
      <Text style={s.kecil} numberOfLines={1}>{lot.nama_kapal}</Text>
    </BarisDaftar>
  );
}

const s = StyleSheet.create({
  tengah: { flex: 1, backgroundColor: warna.latar, alignItems: 'center', justifyContent: 'center', padding: 16 },
  isi: { paddingBottom: 32 },
  judulKelompok: { fontSize: 12, fontWeight: '600', color: warna.teksRedup, paddingHorizontal: 16, paddingTop: 14, paddingBottom: 6 },
  teksKosong: { fontSize: 14, color: warna.teksRedup, textAlign: 'center', padding: 24 },
  daftar: { backgroundColor: warna.putih, borderTopWidth: 1, borderBottomWidth: 1, borderColor: warna.garisHalus },
  barisBerjalan: { borderLeftWidth: 3, borderLeftColor: warna.utama },
  kode: { fontSize: 15, fontWeight: '600', color: warna.teks, fontVariant: ['tabular-nums'] },
  selKode: { fontSize: 13, fontWeight: '600', color: warna.teks, fontVariant: ['tabular-nums'] },
  jenis: { fontSize: 14, color: warna.teks, marginTop: 2 },
  grade: { color: warna.aksen, fontWeight: '500' },
  kecil: { fontSize: 12, color: warna.teksRedup, marginTop: 2 },
  peserta: { fontSize: 12, fontWeight: '600' },
  jam: { fontSize: 20, fontWeight: '700', color: warna.utama, fontVariant: ['tabular-nums'] },
});

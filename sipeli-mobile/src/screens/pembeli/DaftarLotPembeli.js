import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { pembeli } from '../../api';
import { DialogKonfirmasi, KotakPesan, Tombol } from '../../components/Dasar';
import { BarisDaftar, TitikStatus } from '../../components/Tata';
import Ikon from '../../components/Ikon';
import { formatKg, formatRupiah, waktuPendek } from '../../format';
import { ukuran, warna } from '../../tema';

/**
 * Tugas 2 - Daftar lot MENUNGGU_LELANG (tab "Lot") dan riwayat pemesanan (tab "Pesanan").
 * Bilah tab ada di PembeliScreen.
 */
export default function DaftarLotPembeli({ tab, onPesan, onLihatBukti }) {
  const [lot, setLot] = useState([]);
  const [pesanan, setPesanan] = useState([]);
  const [memuat, setMemuat] = useState(true);
  const [segar, setSegar] = useState(false);
  const [galat, setGalat] = useState(null);
  const [dipilih, setDipilih] = useState(null); // lot yang ditanyakan konfirmasinya
  const [mengirim, setMengirim] = useState(false);

  const muat = useCallback(async () => {
    setGalat(null);
    try {
      const [a, b] = await Promise.all([pembeli.daftarLot(), pembeli.pemesananSaya()]);
      setLot(a.data);
      setPesanan(b.data);
    } catch (e) {
      setGalat(e.message);
    } finally {
      setMemuat(false);
      setSegar(false);
    }
  }, []);

  useEffect(() => {
    muat();
  }, [muat]);

  async function setujuPesan() {
    const l = dipilih;
    setMengirim(true);
    try {
      await onPesan(l); // berhasil -> layar OTP (komponen ini dilepas)
    } catch (e) {
      setDipilih(null);
      setMengirim(false);
      setGalat(e.message);
      muat();
    }
  }

  const data = tab === 'lot' ? lot : pesanan;

  return (
    <View style={s.wadah}>
      {memuat ? (
        <View style={s.tengah}>
          <ActivityIndicator size="large" color={warna.utama} />
          <Text style={s.teksRedup}>Memuat data lot...</Text>
        </View>
      ) : (
        <FlatList
          data={data}
          keyExtractor={(x) => String(x.id_pantauan ?? x.id_lot)}
          contentContainerStyle={s.isi}
          style={{ backgroundColor: warna.latar }}
          refreshControl={<RefreshControl refreshing={segar} onRefresh={() => { setSegar(true); muat(); }} />}
          ListHeaderComponent={
            <>
              {galat ? <KotakPesan teks={galat} gaya={{ margin: 12, marginBottom: 0 }} /> : null}
              <View style={s.kepala}>
                <Text style={s.jumlah}>
                  {tab === 'lot' ? `${lot.length} lot menunggu lelang` : `${pesanan.length} pemesanan`}
                </Text>
                <Text style={s.keterangan}>
                  {tab === 'lot'
                    ? 'Kode OTP dikirim ke surel saat Anda ikut lelang.'
                    : 'Lot yang sesinya dibuka akan muncul di tab Lelang.'}
                </Text>
              </View>
            </>
          }
          ListEmptyComponent={
            <View style={s.kosong}>
                            <Text style={s.teksKosong}>
                {tab === 'lot'
                  ? 'Belum ada lot yang menunggu lelang hari ini.'
                  : 'Anda belum memesan lot apa pun.'}
              </Text>
            </View>
          }
          renderItem={({ item, index }) =>
            tab === 'lot' ? (
              <BarisLot lot={item} terakhir={index === data.length - 1} onPesan={() => setDipilih(item)} onLihat={() => {
                const p = pesanan.find((x) => x.id_lot === item.id_lot);
                if (p) onLihatBukti(p);
              }} />
            ) : (
              <BarisPesanan data={item} terakhir={index === data.length - 1} onPress={() => onLihatBukti(item)} />
            )
          }
        />
      )}

      <DialogKonfirmasi
        tampil={!!dipilih}
        judul={dipilih ? `Ikut lelang lot ${dipilih.kode_lot}?` : ''}
        pesan={
          dipilih
            ? `${dipilih.nama_jenis} grade ${dipilih.kode_grade}, ${formatKg(dipilih.berat_aktual)}, harga pembukaan ${formatRupiah(dipilih.harga_pembukaan)}/kg.\n\nKode OTP 6 digit akan dikirim ke surel Anda.`
            : ''
        }
        teksYa={mengirim ? 'Mengirim kode...' : 'Kirim Kode OTP'}
        onYa={() => !mengirim && setujuPesan()}
        onTidak={() => !mengirim && setDipilih(null)}
      />
    </View>
  );
}

function BarisLot({ lot, onPesan, onLihat, terakhir }) {
  const sudah = !!lot.kode_pemesanan;
  return (
    <BarisDaftar
      terakhir={terakhir}
      onPress={sudah ? onLihat : undefined}
      kanan={
        <View style={s.kolomKanan}>
          <Text style={s.harga}>{formatRupiah(lot.harga_pembukaan)}</Text>
          <Text style={s.labelHarga}>harga buka/kg</Text>
          {sudah ? (
            <View style={{ marginTop: 8 }}>
              <TitikStatus teks="Sudah dipesan" w={warna.sukses} />
            </View>
          ) : (
            <Tombol judul="Ikut Lelang" kecil onPress={onPesan} gaya={{ marginTop: 8 }} />
          )}
        </View>
      }
    >
      <Text style={s.kodeLot}>{lot.kode_lot}</Text>
      <Text style={s.jenis}>
        {lot.nama_jenis} <Text style={s.grade}>Grade {lot.kode_grade}</Text>
      </Text>
      <Text style={s.angka}>{formatKg(lot.berat_aktual)}</Text>
      <Text style={s.redup} numberOfLines={1}>
        {lot.nama_kapal} | nilai {formatRupiah(lot.nilai_lot)}
      </Text>
      {sudah ? <Text style={s.tautanKode}>{`Lihat Kode ${lot.kode_pemesanan}`}</Text> : null}
      {sudah ? <Text style={s.kodePesanKecil}>{lot.kode_pemesanan}</Text> : null}
    </BarisDaftar>
  );
}

function BarisPesanan({ data, onPress, terakhir }) {
  return (
    <BarisDaftar terakhir={terakhir} onPress={onPress} kanan={<Ikon nama="kanan" ukuran={18} warna={warna.teksRedup} />}>
      <Text style={s.kodePesan}>{data.kode_pemesanan}</Text>
      <Text style={s.jenis}>
        {data.kode_lot} - {data.nama_jenis} <Text style={s.grade}>Grade {data.kode_grade}</Text>
      </Text>
      <Text style={s.redup}>
        {formatKg(data.berat_aktual)} | Dipesan {waktuPendek(data.waktu_pemesanan)}
      </Text>
    </BarisDaftar>
  );
}

const s = StyleSheet.create({
  wadah: { flex: 1, backgroundColor: warna.latar },
  isi: { paddingBottom: 32 },
  tengah: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12 },
  kepala: {
    paddingHorizontal: 16, paddingTop: 12, paddingBottom: 10,
    borderBottomWidth: 1, borderBottomColor: warna.garisHalus, backgroundColor: warna.latar,
  },
  jumlah: { fontSize: 14, fontWeight: '600', color: warna.teks },
  keterangan: { fontSize: 12, color: warna.teksRedup, marginTop: 2 },
  kosong: { alignItems: 'center', padding: 32, gap: 12 },
  teksKosong: { fontSize: 14, color: warna.teksRedup, textAlign: 'center', lineHeight: 20 },
  kodeLot: { fontSize: 15, fontWeight: '600', color: warna.teks, fontVariant: ['tabular-nums'] },
  kodePesan: { fontSize: 15, fontWeight: '600', color: warna.sukses, fontVariant: ['tabular-nums'] },
  kodePesanKecil: { fontSize: 12, fontWeight: '600', color: warna.sukses, marginTop: 2 },
  jenis: { fontSize: 14, color: warna.teks, marginTop: 2 },
  grade: { color: warna.aksen, fontWeight: '500' },
  angka: { fontSize: 13, fontWeight: '600', color: warna.teks, marginTop: 2 },
  redup: { fontSize: 12, color: warna.teksRedup, marginTop: 2 },
  teksRedup: { fontSize: 13, color: warna.teksRedup, marginTop: 4 },
  kolomKanan: { alignItems: 'flex-end', minWidth: 110 },
  harga: { fontSize: 16, fontWeight: '700', color: warna.teks, fontVariant: ['tabular-nums'] },
  labelHarga: { fontSize: 11, color: warna.teksRedup },
  tautanKode: { fontSize: 13, fontWeight: '600', color: warna.utama, marginTop: 6 },
});

import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { pembeli } from '../../api';
import { DialogKonfirmasi, KotakPesan, Lencana, Tombol } from '../../components/Dasar';
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
          refreshControl={<RefreshControl refreshing={segar} onRefresh={() => { setSegar(true); muat(); }} />}
          ListHeaderComponent={
            <>
              {galat ? <KotakPesan teks={galat} gaya={{ marginBottom: 12 }} /> : null}
              <Text style={s.keterangan}>
                {tab === 'lot'
                  ? 'Kode OTP dikirim ke surel saat Anda ikut lelang.'
                  : 'Lot yang sesinya dibuka akan muncul di tab Lelang.'}
              </Text>
            </>
          }
          ListEmptyComponent={
            <View style={s.kosong}>
              <Ikon nama={tab === 'lot' ? 'ikan' : 'qr'} ukuran={44} warna={warna.teksRedup} />
              <Text style={s.teksKosong}>
                {tab === 'lot'
                  ? 'Belum ada lot yang menunggu lelang hari ini.'
                  : 'Anda belum memesan lot apa pun.'}
              </Text>
            </View>
          }
          renderItem={({ item }) =>
            tab === 'lot' ? (
              <KartuLot lot={item} onPesan={() => setDipilih(item)} onLihat={() => {
                const p = pesanan.find((x) => x.id_lot === item.id_lot);
                if (p) onLihatBukti(p);
              }} />
            ) : (
              <KartuPesanan data={item} onPress={() => onLihatBukti(item)} />
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

function KartuLot({ lot, onPesan, onLihat }) {
  const sudah = !!lot.kode_pemesanan;
  return (
    <View style={s.kartu}>
      <View style={s.barisAtas}>
        <Text style={s.kodeLot}>{lot.kode_lot}</Text>
        {sudah ? (
          <Lencana teks="Sudah dipesan" ikon="centang" latar={warna.suksesLatar} warnaTeks={warna.sukses} />
        ) : (
          <Lencana teks="Menunggu lelang" latar={warna.utamaMuda} warnaTeks={warna.utama} />
        )}
      </View>
      <Text style={s.jenis}>
        {lot.nama_jenis} <Text style={s.grade}>Grade {lot.kode_grade}</Text>
      </Text>
      <View style={s.rincian}>
        <Rincian label="Berat" nilai={formatKg(lot.berat_aktual)} />
        <Rincian label="Harga buka/kg" nilai={formatRupiah(lot.harga_pembukaan)} />
        <Rincian label="Perkiraan nilai" nilai={formatRupiah(lot.nilai_lot)} />
      </View>
      <View style={s.barisKapal}>
        <Ikon nama="kapal" ukuran={16} warna={warna.teksRedup} />
        <Text style={s.kapal}>{lot.nama_kapal}</Text>
      </View>
      {sudah ? (
        <Tombol judul={`Lihat Kode ${lot.kode_pemesanan}`} jenis="garis" ikon="qr" onPress={onLihat} gaya={{ marginTop: 12 }} />
      ) : (
        <Tombol judul="Ikut Lelang Lot Ini" ikon="surel" onPress={onPesan} gaya={{ marginTop: 12 }} />
      )}
    </View>
  );
}

function KartuPesanan({ data, onPress }) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [s.kartu, pressed && { opacity: 0.8 }]}>
      <View style={s.barisAtas}>
        <Text style={s.kodePesan}>{data.kode_pemesanan}</Text>
        <Ikon nama="kanan" ukuran={22} warna={warna.teksRedup} />
      </View>
      <Text style={s.jenis}>
        {data.kode_lot} - {data.nama_jenis} <Text style={s.grade}>Grade {data.kode_grade}</Text>
      </Text>
      <Text style={s.teksRedup}>
        {formatKg(data.berat_aktual)} | Dipesan {waktuPendek(data.waktu_pemesanan)}
      </Text>
    </Pressable>
  );
}

function Rincian({ label, nilai }) {
  return (
    <View style={{ flex: 1 }}>
      <Text style={s.labelRincian}>{label}</Text>
      <Text style={s.nilaiRincian}>{nilai}</Text>
    </View>
  );
}

const s = StyleSheet.create({
  wadah: { flex: 1, backgroundColor: warna.latar },
  isi: { padding: 16, paddingBottom: 32, gap: 10 },
  tengah: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12 },
  keterangan: { fontSize: 13, color: warna.teksRedup, lineHeight: 18, marginBottom: 4 },
  kosong: { alignItems: 'center', padding: 32, gap: 12 },
  teksKosong: { fontSize: 14, color: warna.teksRedup, textAlign: 'center', lineHeight: 20 },
  kartu: { backgroundColor: warna.kartu, borderRadius: 6, padding: 16, borderWidth: 1, borderColor: '#E4EBF1' },
  barisAtas: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 },
  kodeLot: { fontSize: 16, fontWeight: '700', color: warna.teks },
  kodePesan: { fontSize: 16, fontWeight: '700', color: warna.sukses },
  jenis: { fontSize: ukuran.hurufNormal, fontWeight: '600', color: warna.teks, marginTop: 8 },
  grade: { color: warna.aksen },
  rincian: { flexDirection: 'row', gap: 8, marginTop: 10, backgroundColor: warna.latar, borderRadius: 6, padding: 10 },
  labelRincian: { fontSize: 11, color: warna.teksRedup },
  nilaiRincian: { fontSize: 14, fontWeight: '600', color: warna.teks, marginTop: 2 },
  barisKapal: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 10 },
  kapal: { fontSize: 13, color: warna.teksRedup },
  teksRedup: { fontSize: 13, color: warna.teksRedup, marginTop: 4 },
});

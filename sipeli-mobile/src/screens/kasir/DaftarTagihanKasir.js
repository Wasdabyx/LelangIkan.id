import { useCallback, useState } from 'react';
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { kasir } from '../../api';
import { KolomTeks, KotakPesan, Lencana, Tombol } from '../../components/Dasar';
import { Ringkasan, Tabel } from '../../components/Tata';
import { WARNA_TRANSAKSI } from '../../components/Lelang';
import { formatRupiah, waktuPendek } from '../../format';
import { usePolling } from '../../polling';
import { warna } from '../../tema';
import PemindaiQR from '../penimbangan/PemindaiQR';

/** UC-06 langkah 1: cari transaksi (ketik nomor / pindai QR nota) atau pilih dari daftar tagihan terbuka. */
export default function DaftarTagihanKasir({ tab, onBuka }) {
  const [data, setData] = useState(null);
  const [galat, setGalat] = useState(null);
  const [segar, setSegar] = useState(false);
  const [kode, setKode] = useState('');
  const [galatKode, setGalatKode] = useState(null);
  const [mencari, setMencari] = useState(false);
  const [pindai, setPindai] = useState(false);

  const muat = useCallback(async () => {
    try {
      const hasil = await kasir.daftar();
      setData(hasil);
      setGalat(null);
    } catch (e) {
      if (e.status !== 401) setGalat(e.message);
    } finally {
      setSegar(false);
    }
  }, []);
  usePolling(muat, 5000);

  async function cari(isi) {
    const k = (isi ?? kode).trim();
    if (!k) return setGalatKode('Ketik nomor transaksi, contoh TRX-000001 atau 1.');
    if (!/^(LELANGIKAN\|.+|(TRX-?)?\d{1,6})$/i.test(k)) return setGalatKode('Format nomor transaksi: TRX-000001 atau angkanya saja.');
    setGalatKode(null);
    setMencari(true);
    try {
      const hasil = await kasir.cari(k);
      setKode('');
      onBuka(hasil.data);
    } catch (e) {
      if (e.status !== 401) setGalatKode(e.message);
    } finally {
      setMencari(false);
    }
  }

  if (!data) {
    return (
      <View style={s.tengah}>
        {galat ? <KotakPesan teks={galat} /> : <ActivityIndicator size="large" color={warna.utama} />}
      </View>
    );
  }
  const daftar = tab === 'tagihan' ? data.data : data.lunas_hari_ini;
  const totalLunas = data.lunas_hari_ini.reduce((a, t) => a + Number(t.total_tagihan), 0);
  const totalSisa = data.data.reduce((a, t) => a + Number(t.sisa_tagihan), 0);

  return (
    <View style={{ flex: 1, backgroundColor: warna.latar }}>
      <ScrollView
        contentContainerStyle={s.isi}
        keyboardShouldPersistTaps="handled"
        refreshControl={<RefreshControl refreshing={segar} onRefresh={() => { setSegar(true); muat(); }} />}
      >
        {tab === 'tagihan' ? (
          <>
            <View style={s.cari}>
              <Text style={s.judul}>Cari Transaksi</Text>
              <View style={s.barisCari}>
                <KolomTeks
                  value={kode}
                  onChangeText={(t) => {
                    setKode(t.toUpperCase().replace(/[^A-Z0-9-]/g, '').slice(0, 10));
                    setGalatKode(null);
                  }}
                  placeholder="TRX-000001 atau 1"
                  autoCapitalize="characters"
                  returnKeyType="search"
                  onSubmitEditing={() => cari()}
                  accessibilityLabel="Nomor transaksi"
                  gayaWadah={{ flex: 1, marginBottom: 0 }}
                />
                <Tombol judul="Cari" onPress={() => cari()} memuat={mencari} gaya={s.tombolCari} />
                <Tombol judul="QR" ikon="qr" jenis="garis" onPress={() => setPindai(true)} gaya={s.tombolCari} />
              </View>
              {galatKode ? <Text style={s.galat}>{galatKode}</Text> : null}
            </View>
            <Ringkasan
              data={[
                ['Belum lunas', String(data.data.length)],
                ['Total sisa tagihan', formatRupiah(totalSisa), totalSisa > 0 ? warna.galat : undefined],
                ['Lunas hari ini', String(data.lunas_hari_ini.length), warna.sukses],
              ]}
            />
          </>
        ) : (
          <Ringkasan
            data={[
              ['Transaksi lunas', String(data.lunas_hari_ini.length)],
              ['Total diterima', formatRupiah(totalLunas), warna.sukses],
            ]}
          />
        )}
        {galat ? <KotakPesan teks={galat} gaya={{ margin: 12 }} /> : null}

        <View style={s.daftar}>
          <Text style={s.keterangan}>
            {tab === 'tagihan'
              ? `Tagihan belum lunas (${data.data.length})`
              : `${data.lunas_hari_ini.length} transaksi lunas hari ini. Ketuk untuk melihat atau mencetak ulang nota.`}
          </Text>
          <Tabel
            label={tab === 'tagihan' ? 'Tagihan belum lunas' : 'Transaksi lunas hari ini'}
            kosong={tab === 'tagihan' ? 'Tidak ada tagihan terbuka.' : 'Belum ada transaksi lunas hari ini.'}
            data={daftar}
            kunci={(t) => t.id_transaksi}
            onPilih={(t) => cari(t.kode_transaksi)}
            kolom={[
              {
                judul: 'Transaksi', flex: 1.6,
                render: (t) => (
                  <View>
                    <Text style={s.kode}>{t.kode_transaksi}</Text>
                    <Text style={s.pembeli} numberOfLines={1}>{t.nama_pembeli}</Text>
                    <Text style={s.redup} numberOfLines={1}>{t.nama_jenis} | {waktuPendek(t.waktu_transaksi).replace(/^\d+ \w+ \d+, /, '')}</Text>
                  </View>
                ),
              },
              {
                judul: 'Status', flex: 1.1,
                render: (t) => {
                  const w = WARNA_TRANSAKSI[t.status];
                  return <Lencana teks={w.label} latar={w.latar} warnaTeks={w.teks} />;
                },
              },
              {
                judul: tab === 'tagihan' ? 'Sisa' : 'Total', flex: 1.3, kanan: true, tebal: true,
                render: (t) => formatRupiah(t.status === 'LUNAS' ? t.total_tagihan : t.sisa_tagihan),
              },
            ]}
          />
        </View>
      </ScrollView>
      <PemindaiQR
        tampil={pindai}
        petunjuk="Arahkan kamera ke QR nota pembeli"
        alasanIzin="Kamera dipakai untuk memindai QR nota pembayaran. Foto tidak disimpan."
        onTutup={() => setPindai(false)}
        onHasil={(isi) => {
          setPindai(false);
          cari(isi);
        }}
      />
    </View>
  );
}

const s = StyleSheet.create({
  tengah: { flex: 1, backgroundColor: warna.latar, alignItems: 'center', justifyContent: 'center', padding: 16 },
  isi: { paddingBottom: 32 },
  cari: { backgroundColor: warna.putih, paddingHorizontal: 16, paddingTop: 12, paddingBottom: 12, borderBottomWidth: 1, borderBottomColor: warna.garisHalus },
  judul: { fontSize: 13, fontWeight: '600', color: warna.teksRedup, marginBottom: 6 },
  barisCari: { flexDirection: 'row', gap: 8, alignItems: 'center' },
  tombolCari: { minHeight: 44, paddingHorizontal: 14 },
  galat: { color: warna.galat, fontSize: 12, marginTop: 6 },
  daftar: { backgroundColor: warna.putih, marginTop: 8, paddingHorizontal: 16, paddingTop: 12, paddingBottom: 4, borderTopWidth: 1, borderBottomWidth: 1, borderColor: warna.garisHalus },
  keterangan: { fontSize: 13, color: warna.teksRedup, fontWeight: '600', marginBottom: 4 },
  kode: { fontSize: 13, fontWeight: '700', color: warna.teks, fontVariant: ['tabular-nums'] },
  pembeli: { fontSize: 13, color: warna.teks, marginTop: 1 },
  redup: { fontSize: 11, color: warna.teksRedup, marginTop: 1 },
});

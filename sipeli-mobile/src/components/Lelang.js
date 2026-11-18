import { Pressable, StyleSheet, Text, View } from 'react-native';
import { ukuran, warna } from '../tema';

/** Komponen kecil yang dipakai layar Operator, Ruang Lelang, dan Kasir. */

/** 125 -> "02:05" */
export const formatSisa = (detik) =>
  `${String(Math.floor(Math.max(0, detik) / 60)).padStart(2, '0')}:${String(Math.max(0, detik) % 60).padStart(2, '0')}`;

/** Bilah tab di bawah bilah atas. daftar = [[kunci, judul], ...] */
export function TabAtas({ daftar, aktif, onGanti }) {
  return (
    <View style={s.tab}>
      {daftar.map(([kunci, judul]) => (
        <Pressable
          key={kunci}
          onPress={() => onGanti(kunci)}
          style={[s.itemTab, aktif === kunci && s.itemTabAktif]}
          accessibilityRole="tab"
          accessibilityState={{ selected: aktif === kunci }}
        >
          <Text style={[s.teksTab, aktif === kunci && s.teksTabAktif]} numberOfLines={1}>
            {judul}
          </Text>
        </Pressable>
      ))}
    </View>
  );
}

/** Jam hitung mundur besar. Merah saat 15 detik terakhir (masa perpanjangan). */
export function JamMundur({ sisa, status, perpanjangan, maksPerpanjangan }) {
  const aktif = status === 'AKTIF';
  const genting = aktif && sisa <= 15;
  return (
    <View style={[s.jam, genting && s.jamGenting, !aktif && s.jamSelesai]}>
      <Text style={[s.labelJam, genting && { color: warna.galat }]}>
        {aktif ? 'Sisa waktu' : 'Lelang selesai'}
      </Text>
      <Text style={[s.angkaJam, genting && { color: warna.galat }, !aktif && { color: warna.teksRedup }]} accessibilityLabel="Sisa waktu">
        {formatSisa(aktif ? sisa : 0)}
      </Text>
      {perpanjangan > 0 ? (
        <Text style={s.perpanjangan}>
          Diperpanjang {perpanjangan} dari {maksPerpanjangan} kali
        </Text>
      ) : aktif ? (
        <Text style={s.perpanjangan}>Tawaran di 15 detik terakhir menambah 30 detik</Text>
      ) : null}
    </View>
  );
}

/** Satu baris label - nilai (rincian tagihan / lot). */
export function BarisRincian({ label, nilai, tebal, besar, warnaNilai }) {
  return (
    <View style={s.baris}>
      <Text style={[s.label, besar && { fontSize: 15, color: warna.teks, fontWeight: '600' }]}>{label}</Text>
      <Text
        style={[s.nilai, tebal && { fontWeight: '700' }, besar && { fontSize: 16, fontWeight: '700' }, warnaNilai && { color: warnaNilai }]}
      >
        {nilai}
      </Text>
    </View>
  );
}

/** Lencana status transaksi */
export const WARNA_TRANSAKSI = {
  BELUM_DIBAYAR: { latar: warna.galatLatar, teks: warna.galat, label: 'Belum dibayar' },
  DIBAYAR_SEBAGIAN: { latar: warna.peringatanLatar, teks: warna.peringatan, label: 'Dibayar sebagian' },
  LUNAS: { latar: warna.suksesLatar, teks: warna.sukses, label: 'Lunas' },
  DIBATALKAN: { latar: warna.abu, teks: warna.teksRedup, label: 'Dibatalkan' },
};

const s = StyleSheet.create({
  tab: { flexDirection: 'row', backgroundColor: warna.putih, borderBottomWidth: 1, borderBottomColor: warna.garis },
  itemTab: {
    flex: 1, minHeight: 44, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 4,
    borderBottomWidth: 2, borderBottomColor: 'transparent',
  },
  itemTabAktif: { borderBottomColor: warna.utama },
  teksTab: { fontSize: 13, fontWeight: '500', color: warna.teksRedup },
  teksTabAktif: { color: warna.utama, fontWeight: '700' },
  jam: { backgroundColor: warna.utamaMuda, borderRadius: 6, paddingVertical: 12, alignItems: 'center' },
  jamGenting: { backgroundColor: warna.galatLatar },
  jamSelesai: { backgroundColor: warna.abu },
  labelJam: { fontSize: 12, fontWeight: '700', color: warna.utama },
  angkaJam: { fontSize: 34, fontWeight: '700', color: warna.utama, fontVariant: ['tabular-nums'] },
  perpanjangan: { fontSize: 12, color: warna.teksRedup, fontWeight: '500', marginTop: 2, textAlign: 'center', paddingHorizontal: 12 },
  baris: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 12 },
  label: { fontSize: 13, color: warna.teksRedup, flexShrink: 1 },
  nilai: { fontSize: 14, color: warna.teks, fontWeight: '500', textAlign: 'right', flexShrink: 1 },
});

export { ukuran };

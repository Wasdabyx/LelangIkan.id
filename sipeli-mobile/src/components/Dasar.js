import { ActivityIndicator, Modal, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { ukuran, warna } from '../tema';
import { useGulirKeKolom } from './FormGulir';
import Ikon from './Ikon';

/* ---------- Tombol ---------- */
// jenis: 'utama' | 'garis' | 'bahaya' | 'teks'
export function Tombol({ judul, onPress, jenis = 'utama', ikon, memuat, nonaktif, gaya }) {
  const mati = nonaktif || memuat;
  const g = GAYA_TOMBOL[jenis];
  return (
    <Pressable
      onPress={onPress}
      disabled={mati}
      accessibilityRole="button"
      style={({ pressed }) => [
        s.tombol,
        g.wadah,
        pressed && g.tekan,
        nonaktif && !memuat && s.tombolMati,
        gaya,
      ]}
    >
      {memuat ? (
        <ActivityIndicator color={g.teks.color} />
      ) : (
        <View style={s.isiTombol}>
          {ikon ? <Ikon nama={ikon} ukuran={18} warna={nonaktif ? warna.teksRedup : g.teks.color} /> : null}
          <Text style={[s.teksTombol, g.teks, nonaktif && { color: warna.teksRedup }]}>{judul}</Text>
        </View>
      )}
    </Pressable>
  );
}

const GAYA_TOMBOL = {
  utama: {
    wadah: { backgroundColor: warna.utama },
    tekan: { backgroundColor: warna.utamaTekan },
    teks: { color: warna.putih },
  },
  garis: {
    wadah: { backgroundColor: warna.putih, borderWidth: 1, borderColor: warna.utama },
    tekan: { backgroundColor: warna.utamaMuda },
    teks: { color: warna.utama },
  },
  bahaya: {
    wadah: { backgroundColor: warna.putih, borderWidth: 1, borderColor: warna.galat },
    tekan: { backgroundColor: warna.galatLatar },
    teks: { color: warna.galat },
  },
  teks: {
    wadah: { backgroundColor: 'transparent' },
    tekan: { backgroundColor: warna.utamaMuda },
    teks: { color: warna.utama },
  },
};

/* ---------- Kolom isian berlabel ---------- */
export function KolomTeks({ label, galat, inputRef, onFocus, gayaWadah, ...props }) {
  const gulirKeKolom = useGulirKeKolom();
  return (
    <View style={[{ marginBottom: 14 }, gayaWadah]}>
      {label ? <Text style={s.label}>{label}</Text> : null}
      <TextInput
        ref={inputRef}
        style={[s.input, galat && s.inputGalat]}
        placeholderTextColor={warna.teksRedup}
        onFocus={(e) => {
          gulirKeKolom();
          onFocus && onFocus(e);
        }}
        {...props}
      />
      {galat ? <Text style={s.teksGalat}>{galat}</Text> : null}
    </View>
  );
}

/* ---------- Lencana status ---------- */
const WARNA_LENCANA = {
  DRAF: { latar: warna.abu, teks: '#344054', label: 'Draf' },
  TERDAFTAR: { latar: warna.utamaMuda, teks: warna.utama, label: 'Terdaftar' },
  DITIMBANG: { latar: warna.suksesLatar, teks: warna.sukses, label: 'Ditimbang' },
};
export function Lencana({ status, teks, ikon, latar, warnaTeks }) {
  const w = WARNA_LENCANA[status] || { latar: latar || warna.abu, teks: warnaTeks || warna.teks, label: teks };
  return (
    <View style={[s.lencana, { backgroundColor: w.latar }]}>
      {ikon ? <Ikon nama={ikon} ukuran={13} warna={w.teks} tebal={2.4} /> : null}
      <Text style={[s.teksLencana, { color: w.teks }]}>{teks || w.label}</Text>
    </View>
  );
}

/* ---------- Kotak pesan ---------- */
// jenis: 'galat' | 'sukses' | 'info'
export function KotakPesan({ jenis = 'galat', teks, gaya }) {
  if (!teks) return null;
  const w = {
    galat: [warna.galatLatar, warna.galat, 'peringatan'],
    sukses: [warna.suksesLatar, warna.sukses, 'centang'],
    info: [warna.utamaMuda, warna.utama, 'awan'],
  }[jenis];
  return (
    <View style={[s.kotak, { backgroundColor: w[0], borderColor: w[1] }, gaya]}>
      <Ikon nama={w[2]} ukuran={18} warna={w[1]} />
      <Text style={[s.teksKotak, { color: w[1] }]}>{teks}</Text>
    </View>
  );
}

/* ---------- Bilah atas (nama pengguna + Keluar) ---------- */
export function BilahAtas({ pengguna, onKeluar }) {
  return (
    <View style={s.bilah}>
      <View style={s.avatar}>
        <Ikon nama="pengguna" ukuran={18} warna={warna.utama} />
      </View>
      <View style={{ flex: 1 }}>
        <Text style={s.nama} numberOfLines={1}>{pengguna.nama_lengkap}</Text>
        <Text style={s.peran} numberOfLines={1}>
          {pengguna.nama_peran}
          {pengguna.nama_tpi ? ` - ${pengguna.nama_tpi}` : ''}
        </Text>
      </View>
      <Pressable onPress={onKeluar} hitSlop={6} style={({ pressed }) => [s.tombolKeluar, pressed && { opacity: 0.7 }]}>
        <Ikon nama="keluar" ukuran={16} warna={warna.putih} />
        <Text style={s.teksKeluar}>Keluar</Text>
      </Pressable>
    </View>
  );
}

/* ---------- Dialog konfirmasi ---------- */
export function DialogKonfirmasi({ tampil, judul, pesan, teksYa = 'Ya', teksTidak = 'Batal', bahaya, onYa, onTidak }) {
  return (
    <Modal visible={tampil} transparent animationType="fade" onRequestClose={onTidak}>
      <View style={s.latarDialog}>
        <View style={s.dialog}>
          <Text style={s.judulDialog}>{judul}</Text>
          {pesan ? <Text style={s.pesanDialog}>{pesan}</Text> : null}
          <Tombol judul={teksYa} jenis={bahaya ? 'bahaya' : 'utama'} onPress={onYa} gaya={{ marginTop: 18 }} />
          <Tombol judul={teksTidak} jenis="teks" onPress={onTidak} gaya={{ marginTop: 8 }} />
        </View>
      </View>
    </Modal>
  );
}

const s = StyleSheet.create({
  tombol: {
    minHeight: ukuran.tinggiTombol, borderRadius: ukuran.radius,
    alignItems: 'center', justifyContent: 'center', paddingHorizontal: 16,
  },
  tombolMati: { backgroundColor: warna.abu, borderColor: warna.garis },
  isiTombol: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  teksTombol: { fontSize: ukuran.hurufSedang, fontWeight: '600' },
  label: { fontSize: 13, fontWeight: '500', color: warna.teks, marginBottom: 6 },
  input: {
    height: ukuran.tinggiInput, borderWidth: 1, borderColor: warna.garis,
    borderRadius: ukuran.radius, paddingHorizontal: 14, fontSize: ukuran.hurufSedang,
    color: warna.teks, backgroundColor: warna.putih,
  },
  inputGalat: { borderColor: warna.galat, borderWidth: 1.5 },
  teksGalat: { color: warna.galat, fontSize: 12, marginTop: 4 },
  lencana: {
    flexDirection: 'row', alignItems: 'center', gap: 4, alignSelf: 'flex-start',
    paddingHorizontal: 8, paddingVertical: 3, borderRadius: 999,
  },
  teksLencana: { fontSize: 11, fontWeight: '600' },
  kotak: {
    flexDirection: 'row', gap: 10, alignItems: 'flex-start',
    borderWidth: 1, borderRadius: ukuran.radius, padding: 12,
  },
  teksKotak: { flex: 1, fontSize: 13, lineHeight: 18 },
  bilah: {
    backgroundColor: warna.utama, paddingHorizontal: 16, paddingVertical: 8,
    flexDirection: 'row', alignItems: 'center', gap: 10,
  },
  avatar: {
    width: 32, height: 32, borderRadius: 16, backgroundColor: warna.putih,
    alignItems: 'center', justifyContent: 'center',
  },
  nama: { color: warna.putih, fontSize: 14, fontWeight: '600' },
  peran: { color: '#D9E8F0', fontSize: 11, marginTop: 1 },
  tombolKeluar: {
    flexDirection: 'row', alignItems: 'center', gap: 6,
    borderWidth: 1, borderColor: 'rgba(255,255,255,0.6)', borderRadius: 4,
    paddingHorizontal: 10, minHeight: 34,
  },
  teksKeluar: { color: warna.putih, fontWeight: '500', fontSize: 13 },
  latarDialog: { flex: 1, backgroundColor: 'rgba(16,42,67,0.55)', justifyContent: 'center', padding: 24 },
  dialog: { backgroundColor: warna.putih, borderRadius: 6, padding: 16, borderWidth: 1, borderColor: '#E4EBF1' },
  judulDialog: { fontSize: 17, fontWeight: '600', color: warna.teks },
  pesanDialog: { fontSize: 14, color: warna.teksRedup, marginTop: 6, lineHeight: 20 },
});

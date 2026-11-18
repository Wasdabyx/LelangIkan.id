import { Pressable, StyleSheet, Text, View } from 'react-native';
import { ukuran, warna } from '../tema';

/**
 * Komponen tata letak LelangIkan.id.
 * Bagian    : blok putih selebar layar, dipisah latar abu (pengganti kartu untuk isi halaman).
 * Tabel     : tabel ringkas 2-4 kolom untuk data yang dibandingkan (riwayat, tagihan, keranjang).
 * Ringkasan : deret angka ringkas di bagian atas halaman (monitoring).
 * Segmen    : pilihan satu dari beberapa (metode bayar, durasi).
 * BarisDaftar : satu baris daftar yang dapat diketuk, dipisah garis tipis.
 */

export function Bagian({ judul, kanan, children, gaya, tanpaJarak, bawah }) {
  return (
    <View style={[s.bagian, bawah && { marginBottom: 0 }, gaya]}>
      {judul ? (
        <View style={s.kepalaBagian}>
          <Text style={s.judulBagian}>{judul}</Text>
          {kanan || null}
        </View>
      ) : null}
      <View style={tanpaJarak ? null : s.isiBagian}>{children}</View>
    </View>
  );
}

/** kolom: [{ judul, flex=1, kanan, render: (item, i) => node|string, tebal }] */
export function Tabel({ kolom, data, kunci, onPilih, kosong, sorot, label }) {
  return (
    <View accessibilityLabel={label}>
      <View style={s.kepalaTabel}>
        {kolom.map((k, i) => (
          <Text key={i} style={[s.selKepala, { flex: k.flex ?? 1 }, k.kanan && s.kanan]} numberOfLines={1}>
            {k.judul}
          </Text>
        ))}
      </View>
      {data.length === 0 ? (
        <Text style={s.kosongTabel}>{kosong || 'Belum ada data.'}</Text>
      ) : (
        data.map((item, i) => {
          const isi = (
            <>
              {kolom.map((k, j) => {
                const v = k.render ? k.render(item, i) : item[k.kunci];
                return typeof v === 'string' || typeof v === 'number' ? (
                  <Text
                    key={j}
                    style={[s.sel, { flex: k.flex ?? 1 }, k.kanan && s.kanan, k.tebal && s.tebal, k.redup && s.redup]}
                    numberOfLines={k.baris ?? 1}
                  >
                    {v}
                  </Text>
                ) : (
                  <View key={j} style={[{ flex: k.flex ?? 1 }, k.kanan && { alignItems: 'flex-end' }]}>{v}</View>
                );
              })}
            </>
          );
          const gaya = [s.barisTabel, sorot && sorot(item) && s.barisSorot];
          return onPilih ? (
            <Pressable key={kunci ? kunci(item) : i} onPress={() => onPilih(item)} style={({ pressed }) => [...gaya, pressed && s.tekan]}>
              {isi}
            </Pressable>
          ) : (
            <View key={kunci ? kunci(item) : i} style={gaya}>{isi}</View>
          );
        })
      )}
    </View>
  );
}

/** data: [[label, nilai, warnaNilai?], ...] */
export function Ringkasan({ data }) {
  return (
    <View style={s.ringkasan}>
      {data.map(([label, nilai, w], i) => (
        <View key={i} style={[s.selRingkas, i > 0 && s.selRingkasGaris]}>
          <Text style={[s.nilaiRingkas, w && { color: w }]}>{nilai}</Text>
          <Text style={s.labelRingkas} numberOfLines={1}>{label}</Text>
        </View>
      ))}
    </View>
  );
}

/** pilihan: [{ kunci, judul, ket, nonaktif }] */
export function Segmen({ pilihan, nilai, onGanti, label }) {
  return (
    <View style={s.segmen} accessibilityRole="radiogroup" accessibilityLabel={label}>
      {pilihan.map((p, i) => {
        const aktif = p.kunci === nilai;
        return (
          <Pressable
            key={p.kunci}
            onPress={() => !p.nonaktif && onGanti(p.kunci)}
            disabled={!!p.nonaktif}
            accessibilityRole="radio"
            accessibilityState={{ checked: aktif, disabled: !!p.nonaktif }}
            accessibilityLabel={p.ket ? `${p.judul}, ${p.ket}` : p.judul}
            style={[s.itemSegmen, i > 0 && s.itemSegmenGaris, aktif && s.itemSegmenAktif, p.nonaktif && s.itemSegmenMati]}
          >
            <Text style={[s.teksSegmen, aktif && s.teksSegmenAktif, p.nonaktif && { color: warna.teksRedup }]} numberOfLines={1}>
              {p.judul}
            </Text>
            {p.ket ? <Text style={[s.ketSegmen, aktif && { color: '#D9E8F0' }]} numberOfLines={1}>{p.ket}</Text> : null}
          </Pressable>
        );
      })}
    </View>
  );
}

export function BarisDaftar({ onPress, children, kanan, terakhir, gaya }) {
  const isi = (
    <>
      <View style={{ flex: 1 }}>{children}</View>
      {kanan || null}
    </>
  );
  const g = [s.barisDaftar, terakhir && { borderBottomWidth: 0 }, gaya];
  return onPress ? (
    <Pressable onPress={onPress} style={({ pressed }) => [...g, pressed && s.tekan]}>{isi}</Pressable>
  ) : (
    <View style={g}>{isi}</View>
  );
}

/** Pasangan label - nilai dalam satu baris, tanpa kotak. */
export function Info({ label, nilai, tebal, besar, warnaNilai, garis }) {
  return (
    <View style={[s.info, garis && s.infoGaris]}>
      <Text style={[s.labelInfo, besar && s.labelInfoBesar]}>{label}</Text>
      <Text style={[s.nilaiInfo, tebal && { fontWeight: '600' }, besar && s.nilaiInfoBesar, warnaNilai && { color: warnaNilai }]} selectable>
        {nilai}
      </Text>
    </View>
  );
}

export function Garis({ gaya }) {
  return <View style={[s.garis, gaya]} />;
}

/** Status tidak kritis: teks berwarna dengan titik kecil, tanpa latar. */
export function TitikStatus({ teks, w = warna.teksRedup }) {
  return (
    <View style={s.titikStatus}>
      <View style={[s.titik, { backgroundColor: w }]} />
      <Text style={[s.teksTitik, { color: w }]}>{teks}</Text>
    </View>
  );
}

const s = StyleSheet.create({
  bagian: {
    backgroundColor: warna.putih, marginBottom: 8,
    borderTopWidth: 1, borderBottomWidth: 1, borderColor: warna.garisHalus,
  },
  kepalaBagian: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8,
    paddingHorizontal: 16, paddingTop: 12, paddingBottom: 4,
  },
  judulBagian: { fontSize: 13, fontWeight: '600', color: warna.teksRedup },
  isiBagian: { paddingHorizontal: 16, paddingTop: 8, paddingBottom: 14 },

  kepalaTabel: {
    flexDirection: 'row', gap: 8, paddingVertical: 6,
    borderBottomWidth: 1, borderBottomColor: warna.garis,
  },
  selKepala: { fontSize: 11, fontWeight: '600', color: warna.teksRedup },
  barisTabel: {
    flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 9,
    borderBottomWidth: 1, borderBottomColor: warna.garisHalus,
  },
  barisSorot: { backgroundColor: '#F3F8FB' },
  sel: { fontSize: 13, color: warna.teks, fontVariant: ['tabular-nums'] },
  kanan: { textAlign: 'right' },
  tebal: { fontWeight: '600' },
  redup: { color: warna.teksRedup, fontSize: 12 },
  kosongTabel: { fontSize: 13, color: warna.teksRedup, paddingVertical: 12 },
  tekan: { backgroundColor: warna.abu },

  ringkasan: {
    flexDirection: 'row', backgroundColor: warna.putih,
    borderBottomWidth: 1, borderBottomColor: warna.garisHalus, paddingVertical: 10,
  },
  selRingkas: { flex: 1, paddingHorizontal: 16 },
  selRingkasGaris: { borderLeftWidth: 1, borderLeftColor: warna.garisHalus },
  nilaiRingkas: { fontSize: 18, fontWeight: '700', color: warna.teks, fontVariant: ['tabular-nums'] },
  labelRingkas: { fontSize: 11, color: warna.teksRedup, marginTop: 1 },

  segmen: {
    flexDirection: 'row', borderWidth: 1, borderColor: warna.garis, borderRadius: ukuran.radius, overflow: 'hidden',
  },
  itemSegmen: { flex: 1, minHeight: 44, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 4, backgroundColor: warna.putih },
  itemSegmenGaris: { borderLeftWidth: 1, borderLeftColor: warna.garis },
  itemSegmenAktif: { backgroundColor: warna.utama },
  itemSegmenMati: { backgroundColor: warna.abu },
  teksSegmen: { fontSize: 13, fontWeight: '600', color: warna.teks },
  teksSegmenAktif: { color: warna.putih },
  ketSegmen: { fontSize: 10, color: warna.teksRedup, marginTop: 1 },

  barisDaftar: {
    flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12, paddingHorizontal: 16,
    borderBottomWidth: 1, borderBottomColor: warna.garisHalus, backgroundColor: warna.putih,
  },

  info: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline', gap: 12, paddingVertical: 5 },
  infoGaris: { borderTopWidth: 1, borderTopColor: warna.garisHalus, marginTop: 4, paddingTop: 9 },
  labelInfo: { fontSize: 13, color: warna.teksRedup, flexShrink: 1 },
  labelInfoBesar: { fontSize: 14, color: warna.teks, fontWeight: '600' },
  nilaiInfo: { fontSize: 14, color: warna.teks, textAlign: 'right', flexShrink: 1, fontVariant: ['tabular-nums'] },
  nilaiInfoBesar: { fontSize: 17, fontWeight: '700' },

  garis: { height: 1, backgroundColor: warna.garisHalus },
  titikStatus: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  titik: { width: 7, height: 7, borderRadius: 4 },
  teksTitik: { fontSize: 12, fontWeight: '600' },
});

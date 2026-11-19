import { useEffect, useState } from 'react';
import { Image, Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { API_URL } from '../config';
import { warna } from '../tema';
import Ikon from './Ikon';

/**
 * Foto ikan pada lot.
 * - Bila Petugas Timbang sudah memotret lot (lot.foto), foto itu yang tampil.
 * - Bila belum, tampil foto contoh jenis ikan (Wikimedia Commons, lihat README) dengan tanda "Foto contoh".
 * Ketuk foto untuk memperbesar.
 */
const FOTO_JENIS = [
  ['cakalang', require('../../assets/ikan/cakalang.jpg')],
  ['tongkol', require('../../assets/ikan/tongkol.jpg')],
  ['tuna', require('../../assets/ikan/tuna.jpg')],
  ['kembung', require('../../assets/ikan/kembung.jpg')],
  ['layur', require('../../assets/ikan/layur.jpg')],
  ['kakap', require('../../assets/ikan/kakap.jpg')],
  ['udang', require('../../assets/ikan/udang.jpg')],
  ['cumi', require('../../assets/ikan/cumi.jpg')],
];

/** Foto contoh berdasarkan nama jenis ikan (null bila jenis tidak dikenal). */
export function fotoContoh(namaJenis) {
  const nama = String(namaJenis || '').toLowerCase();
  const ketemu = FOTO_JENIS.find(([kunci]) => nama.includes(kunci));
  return ketemu ? ketemu[1] : null;
}

/** Membentuk URL foto lot dari alamat API yang dikonfigurasi. */
export function alamatFotoLot(foto) {
  return foto ? `${API_URL}/${foto}` : null;
}

export default function FotoIkan({ lot, lebar, tinggi, kecil, gaya, bisaPerbesar = true, tanpaTanda }) {
  const [gagal, setGagal] = useState(false);
  const [besar, setBesar] = useState(false);
  useEffect(() => setGagal(false), [lot?.foto]);

  const asli = !!lot?.foto && !gagal;
  const sumber = asli ? { uri: alamatFotoLot(lot.foto) } : fotoContoh(lot?.nama_jenis);
  const ukuranKotak = { width: lebar ?? '100%', ...(tinggi ? { height: tinggi } : { aspectRatio: 3 / 2 }) };

  if (!sumber) {
    return (
      <View style={[s.kotak, s.kosong, ukuranKotak, gaya]}>
        <Ikon nama="ikan" ukuran={kecil ? 22 : 40} warna={warna.garis} />
      </View>
    );
  }

  const gambar = (
    <View style={[s.kotak, ukuranKotak, gaya]}>
      <Image
        source={sumber}
        style={s.isiFoto}
        resizeMode="cover"
        onError={() => asli && setGagal(true)}
        accessibilityLabel={asli ? `Foto lot ${lot?.kode_lot || ''}` : `Foto contoh ${lot?.nama_jenis || 'ikan'}`}
      />
      {!asli && !tanpaTanda ? (
        <Text style={[s.tanda, kecil && s.tandaKecil]}>{kecil ? 'Contoh' : 'Foto contoh'}</Text>
      ) : null}
    </View>
  );

  if (!bisaPerbesar) return gambar;

  return (
    <>
      <Pressable onPress={() => setBesar(true)} accessibilityRole="imagebutton" accessibilityHint="Ketuk untuk memperbesar foto">
        {({ pressed }) => <View style={pressed ? { opacity: 0.8 } : null}>{gambar}</View>}
      </Pressable>
      <Modal visible={besar} transparent animationType="fade" onRequestClose={() => setBesar(false)}>
        <SafeAreaView style={s.layarBesar} edges={['top', 'bottom']}>
          <View style={s.kepalaBesar}>
            <View style={{ flex: 1 }}>
              <Text style={s.judulBesar} numberOfLines={1}>
                {lot?.nama_jenis}{lot?.kode_grade ? ` | Grade ${lot.kode_grade}` : ''}
              </Text>
              <Text style={s.subBesar} numberOfLines={1}>
                {asli ? `Foto lot ${lot?.kode_lot || ''}` : 'Foto contoh jenis ikan, bukan foto lot ini'}
              </Text>
            </View>
            <Pressable onPress={() => setBesar(false)} style={s.tombolTutup} accessibilityLabel="Tutup foto">
              <Ikon nama="silang" ukuran={22} warna={warna.putih} />
              <Text style={s.teksTutup}>Tutup</Text>
            </Pressable>
          </View>
          <Pressable style={{ flex: 1 }} onPress={() => setBesar(false)}>
            <Image source={sumber} style={s.fotoBesar} resizeMode="contain" />
          </Pressable>
        </SafeAreaView>
      </Modal>
    </>
  );
}

const s = StyleSheet.create({
  kotak: {
    borderRadius: 4, overflow: 'hidden', backgroundColor: warna.abu,
    borderWidth: 1, borderColor: warna.garisHalus,
  },
  kosong: { alignItems: 'center', justifyContent: 'center' },
  // lebar & tinggi eksplisit: di web, ukuran asli aset (480x320) tidak boleh ikut terpakai
  isiFoto: { position: 'absolute', top: 0, left: 0, width: '100%', height: '100%' },
  fotoBesar: { flex: 1, width: '100%', height: '100%' },
  tanda: {
    position: 'absolute', left: 6, bottom: 6, backgroundColor: 'rgba(16,42,67,0.75)', color: warna.putih,
    fontSize: 11, fontWeight: '600', paddingHorizontal: 6, paddingVertical: 2, borderRadius: 3, overflow: 'hidden',
  },
  tandaKecil: { left: 0, right: 0, bottom: 0, borderRadius: 0, fontSize: 9, paddingVertical: 1, textAlign: 'center' },
  layarBesar: { flex: 1, backgroundColor: 'rgba(0,0,0,0.96)' },
  kepalaBesar: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 16, paddingVertical: 10 },
  judulBesar: { color: warna.putih, fontSize: 16, fontWeight: '700' },
  subBesar: { color: '#D9E2EC', fontSize: 12, marginTop: 2 },
  tombolTutup: {
    flexDirection: 'row', alignItems: 'center', gap: 6, minHeight: 44, paddingHorizontal: 12,
    borderWidth: 1, borderColor: warna.putih, borderRadius: 4,
  },
  teksTutup: { color: warna.putih, fontWeight: '600', fontSize: 14 },
});

import { CameraView, useCameraPermissions } from 'expo-camera';
import { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Image, Linking, Modal, Platform, Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ukuran, warna } from '../tema';
import { Tombol } from './Dasar';
import Ikon from './Ikon';

/**
 * Pengambilan foto lot oleh Petugas Timbang.
 * - KameraFoto : jendela kamera (HP: kamera belakang, Chrome: webcam), potret lalu periksa hasilnya.
 * - pilihFileWeb: khusus Chrome, memilih berkas foto dari komputer.
 * Hasil berbentuk { uri, nama, tipe, blob? } dan siap dikirim lewat unggahFotoLot() di api.js.
 * Di Chrome foto diperkecil dulu (sisi terpanjang 1280 piksel) agar unggahan ringan.
 */

const SISI_MAKS = 1280;

/** Perkecil gambar (alamat blob:/data:) memakai kanvas peramban, hasil JPEG. Khusus web. */
async function perkecilWeb(alamat) {
  const img = new window.Image();
  img.src = alamat;
  await img.decode();
  const skala = Math.min(1, SISI_MAKS / Math.max(img.naturalWidth, img.naturalHeight));
  const kanvas = document.createElement('canvas');
  kanvas.width = Math.round(img.naturalWidth * skala);
  kanvas.height = Math.round(img.naturalHeight * skala);
  kanvas.getContext('2d').drawImage(img, 0, 0, kanvas.width, kanvas.height);
  const blob = await new Promise((selesai) => kanvas.toBlob(selesai, 'image/jpeg', 0.82));
  if (!blob) throw new Error('Gagal memproses foto.');
  return { blob, uri: URL.createObjectURL(blob), nama: 'foto-lot.jpg', tipe: 'image/jpeg' };
}

/** Chrome: buka pemilih berkas. onDapat(foto) dipanggil bila berkas dipilih. */
export function pilihFileWeb(onDapat, onGalat) {
  if (Platform.OS !== 'web') return;
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/jpeg,image/png,image/webp';
  input.onchange = async () => {
    const berkas = input.files && input.files[0];
    if (!berkas) return;
    try {
      onDapat(await perkecilWeb(URL.createObjectURL(berkas)));
    } catch (e) {
      onGalat('Berkas tidak dapat dibaca sebagai foto. Gunakan foto JPG atau PNG.');
    }
  };
  input.click();
}

export function KameraFoto({ tampil, onPakai, onTutup }) {
  const [izin, mintaIzin] = useCameraPermissions();
  const kamera = useRef(null);
  const [ukuranFoto, setUkuranFoto] = useState(undefined);
  const [siap, setSiap] = useState(false);
  const [memotret, setMemotret] = useState(false);
  const [hasil, setHasil] = useState(null); // foto yang menunggu diperiksa
  const [galat, setGalat] = useState(null);

  useEffect(() => {
    if (tampil) {
      setHasil(null);
      setGalat(null);
      setSiap(false);
    }
  }, [tampil]);

  // HP: pilih resolusi sekitar 1280 piksel agar berkas tidak terlalu besar
  async function saatSiap() {
    setSiap(true);
    if (Platform.OS === 'web' || ukuranFoto !== undefined) return;
    try {
      const daftar = await kamera.current.getAvailablePictureSizesAsync();
      const angka = (daftar || [])
        .map((t) => ({ t, sisi: Math.max(...String(t).split('x').map(Number)) }))
        .filter((x) => x.sisi > 0)
        .sort((a, b) => a.sisi - b.sisi);
      const pas = angka.find((x) => x.sisi >= 1200) || angka[angka.length - 1];
      setUkuranFoto(pas ? pas.t : null);
    } catch (e) {
      setUkuranFoto(null);
    }
  }

  async function potret() {
    if (!kamera.current || memotret) return;
    setMemotret(true);
    setGalat(null);
    try {
      const f = await kamera.current.takePictureAsync({ quality: 0.6, shutterSound: false });
      if (Platform.OS === 'web') {
        setHasil(await perkecilWeb(f.uri));
      } else {
        setHasil({ uri: f.uri, nama: 'foto-lot.jpg', tipe: 'image/jpeg' });
      }
    } catch (e) {
      setGalat('Gagal memotret. Coba lagi.');
    }
    setMemotret(false);
  }

  let isi;
  if (!izin) {
    isi = (
      <View style={s.tengah}>
        <ActivityIndicator size="large" color={warna.putih} />
      </View>
    );
  } else if (!izin.granted) {
    isi = (
      <View style={s.tengah}>
        <View style={s.kartuIzin}>
          <View style={s.lingkaran}>
            <Ikon nama="kamera" ukuran={40} warna={warna.utama} />
          </View>
          <Text style={s.judulIzin}>Izin kamera diperlukan</Text>
          <Text style={s.teksIzin}>Kamera dipakai untuk memotret ikan pada lot ini.</Text>
          {izin.canAskAgain ? (
            <Tombol judul="Izinkan Kamera" ikon="kamera" onPress={mintaIzin} gaya={{ marginTop: 18 }} />
          ) : (
            <Tombol judul="Buka Pengaturan" onPress={() => Linking.openSettings()} gaya={{ marginTop: 18 }} />
          )}
          <Tombol judul="Batal" jenis="teks" onPress={onTutup} gaya={{ marginTop: 8 }} />
        </View>
      </View>
    );
  } else if (hasil) {
    isi = (
      <View style={{ flex: 1 }}>
        <Image source={{ uri: hasil.uri }} style={{ flex: 1 }} resizeMode="contain" />
        <View style={s.barisPeriksa}>
          <Tombol judul="Ulangi" jenis="garis" onPress={() => setHasil(null)} gaya={s.tombolPeriksa} />
          <Tombol judul="Gunakan Foto" ikon="centang" onPress={() => onPakai(hasil)} gaya={s.tombolPeriksa} />
        </View>
      </View>
    );
  } else {
    isi = (
      <View style={{ flex: 1 }}>
        <CameraView
          ref={kamera}
          style={StyleSheet.absoluteFill}
          facing="back"
          pictureSize={ukuranFoto || undefined}
          onCameraReady={saatSiap}
        />
        <View style={s.lapisan} pointerEvents="none">
          <Text style={s.petunjuk}>Potret ikan dalam keranjang dengan cahaya yang cukup</Text>
        </View>
        {galat ? <Text style={s.galat}>{galat}</Text> : null}
        <View style={s.bawah}>
          <Pressable
            onPress={potret}
            disabled={!siap || memotret}
            style={({ pressed }) => [s.rana, (pressed || memotret) && { opacity: 0.6 }]}
            accessibilityLabel="Potret"
          >
            {memotret ? <ActivityIndicator color={warna.utama} /> : <View style={s.ranaDalam} />}
          </Pressable>
          <Text style={s.teksRana}>Potret</Text>
        </View>
      </View>
    );
  }

  return (
    <Modal visible={tampil} animationType="slide" onRequestClose={onTutup}>
      <SafeAreaView style={s.layar} edges={['top', 'bottom']}>
        <View style={s.kepala}>
          <Text style={s.judul}>{hasil ? 'Periksa Foto' : 'Foto Lot'}</Text>
          <Pressable onPress={onTutup} style={s.tombolTutup} accessibilityLabel="Tutup">
            <Ikon nama="silang" ukuran={24} warna={warna.putih} />
            <Text style={s.teksTutup}>Tutup</Text>
          </Pressable>
        </View>
        {tampil ? isi : null}
      </SafeAreaView>
    </Modal>
  );
}

const s = StyleSheet.create({
  layar: { flex: 1, backgroundColor: '#000000' },
  kepala: {
    flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingVertical: 8,
    backgroundColor: warna.utama,
  },
  judul: { flex: 1, color: warna.putih, fontSize: 17, fontWeight: '700' },
  tombolTutup: {
    flexDirection: 'row', alignItems: 'center', gap: 6, minHeight: 44, paddingHorizontal: 12,
    borderWidth: 1, borderColor: warna.putih, borderRadius: 4,
  },
  teksTutup: { color: warna.putih, fontWeight: '600', fontSize: 14 },
  tengah: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 16 },
  kartuIzin: { backgroundColor: warna.putih, borderRadius: 6, padding: 16, alignSelf: 'stretch', borderWidth: 1, borderColor: '#E4EBF1' },
  lingkaran: {
    width: 80, height: 80, borderRadius: 40, backgroundColor: warna.utamaMuda,
    alignItems: 'center', justifyContent: 'center', alignSelf: 'center', marginBottom: 12,
  },
  judulIzin: { fontSize: 17, fontWeight: '700', color: warna.teks, textAlign: 'center' },
  teksIzin: { fontSize: ukuran.hurufNormal, color: warna.teksRedup, textAlign: 'center', marginTop: 8, lineHeight: 20 },
  lapisan: { position: 'absolute', top: 16, left: 0, right: 0, alignItems: 'center' },
  petunjuk: {
    color: warna.putih, fontSize: 13, fontWeight: '600', textAlign: 'center',
    backgroundColor: 'rgba(0,0,0,0.6)', paddingHorizontal: 14, paddingVertical: 8, borderRadius: 4, marginHorizontal: 24,
  },
  galat: {
    position: 'absolute', bottom: 150, alignSelf: 'center', color: warna.putih, backgroundColor: warna.galat,
    paddingHorizontal: 12, paddingVertical: 6, borderRadius: 4, fontWeight: '600',
  },
  bawah: { position: 'absolute', bottom: 24, left: 0, right: 0, alignItems: 'center' },
  rana: {
    width: 76, height: 76, borderRadius: 38, borderWidth: 4, borderColor: warna.putih,
    alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(255,255,255,0.25)',
  },
  ranaDalam: { width: 56, height: 56, borderRadius: 28, backgroundColor: warna.putih },
  teksRana: { color: warna.putih, fontSize: 12, fontWeight: '600', marginTop: 6 },
  barisPeriksa: { flexDirection: 'row', gap: 10, padding: 16, backgroundColor: '#000000' },
  tombolPeriksa: { flex: 1 },
});

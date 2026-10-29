import { CameraView, useCameraPermissions } from 'expo-camera';
import { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Linking, Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Tombol } from '../../components/Dasar';
import Ikon from '../../components/Ikon';
import { ukuran, warna } from '../../tema';

/**
 * Jendela pemindai QR memakai kamera belakang (expo-camera).
 * onHasil(teks) dipanggil SEKALI saat kode QR terbaca.
 */
export default function PemindaiQR({ tampil, onHasil, onTutup }) {
  const [izin, mintaIzin] = useCameraPermissions();
  const [senter, setSenter] = useState(false);
  const sudahTerbaca = useRef(false);

  // Setiap kali jendela dibuka: siap membaca lagi
  useEffect(() => {
    if (tampil) {
      sudahTerbaca.current = false;
      setSenter(false);
    }
  }, [tampil]);

  function saatTerbaca({ data }) {
    if (sudahTerbaca.current || !data) return;
    sudahTerbaca.current = true;
    setSenter(false);
    onHasil(String(data).trim());
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
          <Text style={s.teksIzin}>
            Kamera dipakai untuk memindai kode QR kedatangan dan label lot. Foto tidak disimpan.
          </Text>
          {izin.canAskAgain ? (
            <Tombol judul="Izinkan Kamera" ikon="kamera" onPress={mintaIzin} gaya={{ marginTop: 18 }} />
          ) : (
            <Tombol judul="Buka Pengaturan" onPress={() => Linking.openSettings()} gaya={{ marginTop: 18 }} />
          )}
          <Tombol judul="Ketik Nomor Saja" jenis="teks" onPress={onTutup} gaya={{ marginTop: 8 }} />
        </View>
      </View>
    );
  } else {
    isi = (
      <View style={{ flex: 1 }}>
        <CameraView
          style={StyleSheet.absoluteFill}
          facing="back"
          enableTorch={senter}
          barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
          onBarcodeScanned={tampil ? saatTerbaca : undefined}
        />
        {/* Bingkai bidik */}
        <View style={s.lapisan} pointerEvents="none">
          <Text style={s.petunjuk}>Arahkan kamera ke kode QR kedatangan atau label lot</Text>
          <View style={s.bingkai}>
            <View style={[s.sudut, s.kiriAtas]} />
            <View style={[s.sudut, s.kananAtas]} />
            <View style={[s.sudut, s.kiriBawah]} />
            <View style={[s.sudut, s.kananBawah]} />
          </View>
        </View>
        <View style={s.bawah}>
          <Pressable
            onPress={() => setSenter((n) => !n)}
            style={({ pressed }) => [s.tombolBulat, senter && s.tombolBulatAktif, pressed && { opacity: 0.7 }]}
            accessibilityLabel="Senter"
          >
            <Ikon nama="senter" ukuran={26} warna={senter ? warna.utama : warna.putih} />
            <Text style={[s.teksBulat, senter && { color: warna.utama }]}>Senter</Text>
          </Pressable>
        </View>
      </View>
    );
  }

  return (
    <Modal visible={tampil} animationType="slide" onRequestClose={onTutup}>
      <SafeAreaView style={s.layar} edges={['top', 'bottom']}>
        <View style={s.kepala}>
          <Text style={s.judul}>Pindai Kode QR</Text>
          <Pressable onPress={onTutup} style={s.tombolTutup} accessibilityLabel="Tutup">
            <Ikon nama="silang" ukuran={24} warna={warna.putih} />
            <Text style={s.teksTutup}>Tutup</Text>
          </Pressable>
        </View>
        {isi}
      </SafeAreaView>
    </Modal>
  );
}

const SISI = 250;
const s = StyleSheet.create({
  layar: { flex: 1, backgroundColor: '#000000' },
  kepala: {
    flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingVertical: 8,
    backgroundColor: warna.utama,
  },
  judul: { flex: 1, color: warna.putih, fontSize: 20, fontWeight: '800' },
  tombolTutup: {
    flexDirection: 'row', alignItems: 'center', gap: 6, minHeight: 44, paddingHorizontal: 12,
    borderWidth: 1.5, borderColor: warna.putih, borderRadius: 8,
  },
  teksTutup: { color: warna.putih, fontWeight: '700', fontSize: 15 },
  tengah: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 20 },
  kartuIzin: { backgroundColor: warna.putih, borderRadius: 16, padding: 20, alignSelf: 'stretch' },
  lingkaran: {
    width: 80, height: 80, borderRadius: 40, backgroundColor: warna.utamaMuda,
    alignItems: 'center', justifyContent: 'center', alignSelf: 'center', marginBottom: 12,
  },
  judulIzin: { fontSize: 20, fontWeight: '800', color: warna.teks, textAlign: 'center' },
  teksIzin: { fontSize: ukuran.hurufNormal, color: warna.teksRedup, textAlign: 'center', marginTop: 8, lineHeight: 22 },
  lapisan: { ...StyleSheet.absoluteFillObject, alignItems: 'center', justifyContent: 'center' },
  petunjuk: {
    color: warna.putih, fontSize: ukuran.hurufNormal, fontWeight: '700', textAlign: 'center',
    backgroundColor: 'rgba(0,0,0,0.6)', paddingHorizontal: 14, paddingVertical: 8, borderRadius: 8,
    marginBottom: 20, marginHorizontal: 24,
  },
  bingkai: { width: SISI, height: SISI },
  sudut: { position: 'absolute', width: 44, height: 44, borderColor: warna.putih },
  kiriAtas: { top: 0, left: 0, borderTopWidth: 5, borderLeftWidth: 5, borderTopLeftRadius: 12 },
  kananAtas: { top: 0, right: 0, borderTopWidth: 5, borderRightWidth: 5, borderTopRightRadius: 12 },
  kiriBawah: { bottom: 0, left: 0, borderBottomWidth: 5, borderLeftWidth: 5, borderBottomLeftRadius: 12 },
  kananBawah: { bottom: 0, right: 0, borderBottomWidth: 5, borderRightWidth: 5, borderBottomRightRadius: 12 },
  bawah: { position: 'absolute', bottom: 28, left: 0, right: 0, alignItems: 'center' },
  tombolBulat: {
    width: 84, height: 84, borderRadius: 42, borderWidth: 2, borderColor: warna.putih,
    backgroundColor: 'rgba(0,0,0,0.5)', alignItems: 'center', justifyContent: 'center',
  },
  tombolBulatAktif: { backgroundColor: warna.putih },
  teksBulat: { color: warna.putih, fontSize: 13, fontWeight: '700', marginTop: 2 },
});

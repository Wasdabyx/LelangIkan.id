import { useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { kasir } from '../../api';
import { KotakPesan, Tombol } from '../../components/Dasar';
import FormGulir from '../../components/FormGulir';
import Ikon from '../../components/Ikon';
import RincianTagihan from '../../components/RincianTagihan';
import { Bagian } from '../../components/Tata';
import { warna } from '../../tema';
import PemindaiQR from '../penimbangan/PemindaiQR';

/** FR-31: periksa keaslian nota dengan memindai QR-nya (tanda tangan HMAC dicek di server). */
export default function PeriksaNota() {
  const [pindai, setPindai] = useState(false);
  const [hasil, setHasil] = useState(null);
  const [galat, setGalat] = useState(null);
  const [memuat, setMemuat] = useState(false);

  async function periksa(isi) {
    setPindai(false);
    setMemuat(true);
    setGalat(null);
    setHasil(null);
    try {
      const r = await kasir.verifikasiNota(isi);
      setHasil(r.data);
    } catch (e) {
      if (e.status !== 401) setGalat(e.message);
    } finally {
      setMemuat(false);
    }
  }

  return (
    <View style={{ flex: 1, backgroundColor: warna.latar }}>
      <FormGulir gayaIsi={s.isi}>
        <Bagian>
          <Text style={s.judul}>Periksa Keaslian Nota</Text>
          <Text style={s.teks}>Pindai QR pada nota (cetak atau PDF) untuk memastikan nota diterbitkan LelangIkan.id dan isinya tidak diubah.</Text>
          <Tombol judul="Pindai QR Nota" ikon="kamera" onPress={() => setPindai(true)} memuat={memuat} gaya={{ marginTop: 14 }} />
          <KotakPesan teks={galat} gaya={{ marginTop: 12 }} />
        </Bagian>

        {hasil ? (
          <Bagian judul="Hasil pemeriksaan">
            <View style={[s.status, { backgroundColor: hasil.asli ? warna.suksesLatar : warna.galatLatar, borderLeftColor: hasil.asli ? warna.sukses : warna.galat }]}>
              <Text style={[s.judulStatus, { color: hasil.asli ? warna.sukses : warna.galat }]}>
                {hasil.asli ? 'Nota asli' : 'Nota tidak dikenali'}
              </Text>
              <Text style={s.teks}>{hasil.pesan}</Text>
            </View>
            {hasil.asli ? (
              <View style={{ marginTop: 14 }}>
                <RincianTagihan t={hasil} tampilRetribusi={false} />
              </View>
            ) : null}
          </Bagian>
        ) : null}
      </FormGulir>
      <PemindaiQR
        tampil={pindai}
        petunjuk="Arahkan kamera ke QR nota pembayaran"
        alasanIzin="Kamera dipakai untuk memindai QR nota pembayaran. Foto tidak disimpan."
        onTutup={() => setPindai(false)}
        onHasil={periksa}
      />
    </View>
  );
}

const s = StyleSheet.create({
  isi: { paddingBottom: 32 },
  kartu: { backgroundColor: warna.kartu, borderRadius: 6, padding: 16, borderWidth: 1, borderColor: '#E4EBF1' },
  judul: { fontSize: 16, fontWeight: '700', color: warna.teks, marginBottom: 6 },
  teks: { fontSize: 13, color: warna.teksRedup, lineHeight: 18 },
  status: { borderRadius: 4, borderLeftWidth: 3, paddingVertical: 10, paddingHorizontal: 12, gap: 2 },
  judulStatus: { fontSize: 16, fontWeight: '700' },
});

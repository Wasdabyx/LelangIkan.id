import { useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { BilahAtas, DialogKonfirmasi, Lencana, Tombol } from '../../components/Dasar';
import Ikon from '../../components/Ikon';
import KodeQR from '../../components/KodeQR';
import { formatKg, tanggalPendek } from '../../format';
import { ukuran, warna } from '../../tema';
import DaftarKedatangan from './DaftarKedatangan';
import FormKedatangan from './FormKedatangan';

/**
 * Layar utama Petugas Pendataan (A1) - UC-01
 * mode: 'daftar' -> 'form' -> 'sukses'
 * idDraf: null = kedatangan baru, berisi id = melengkapi draf (alur A2)
 */
export default function KedatanganScreen({ pengguna, onKeluar }) {
  const [mode, setMode] = useState('daftar');
  const [hasil, setHasil] = useState(null);
  const [idDraf, setIdDraf] = useState(null);
  const [tanyaKeluar, setTanyaKeluar] = useState(false);

  // id = null: kedatangan baru, id berisi: lengkapi draf
  function bukaForm(id = null) {
    setIdDraf(id);
    setMode('form');
  }

  return (
    <SafeAreaView style={s.layar} edges={['top', 'bottom']}>
      <BilahAtas pengguna={pengguna} onKeluar={() => setTanyaKeluar(true)} />

      {mode === 'daftar' && (
        <DaftarKedatangan onTambah={() => bukaForm()} onLengkapi={(k) => bukaForm(k.id_kedatangan)} />
      )}

      {mode === 'form' && (
        <FormKedatangan
          key={idDraf || 'baru'}
          idDraf={idDraf}
          onBatal={() => setMode('daftar')}
          onTersimpan={(h) => {
            setHasil(h);
            setMode('sukses');
          }}
        />
      )}

      {mode === 'sukses' && hasil && (
        <LayarSukses
          hasil={hasil}
          onTambahLagi={() => bukaForm()}
          onLengkapi={() => bukaForm(hasil.data.id_kedatangan)}
          onKeDaftar={() => setMode('daftar')}
        />
      )}

      <DialogKonfirmasi
        tampil={tanyaKeluar}
        judul="Keluar dari aplikasi?"
        pesan={mode === 'form' ? 'Data pada formulir yang belum disimpan akan hilang.' : 'Anda perlu masuk kembali untuk memakai aplikasi.'}
        teksYa="Ya, Keluar"
        bahaya
        onYa={() => {
          setTanyaKeluar(false);
          onKeluar();
        }}
        onTidak={() => setTanyaKeluar(false)}
      />
    </SafeAreaView>
  );
}

/** Alur utama (10): tampilkan nomor kedatangan, QR untuk Petugas Timbang & ringkasan berat */
function LayarSukses({ hasil, onTambahLagi, onLengkapi, onKeDaftar }) {
  const d = hasil.data;
  const draf = d.status === 'DRAF';
  return (
    <ScrollView contentContainerStyle={s.isiSukses}>
      <View style={[s.strip, draf && { borderLeftColor: warna.teksRedup }]}>
        <Text style={s.judulSukses}>{hasil.pesan}</Text>
      </View>

      <View style={s.kartu}>
        <Text style={s.labelKecil}>Nomor kedatangan</Text>
        <Text style={s.nomor}>{d.nomor_kedatangan}</Text>
        <View style={{ flexDirection: 'row', gap: 8, marginBottom: 14 }}>
          <Lencana status={d.status} />
          <Lencana teks="Terkirim" latar={warna.suksesLatar} warnaTeks={warna.sukses} />
        </View>

        {draf ? (
          // Draf belum boleh ditimbang, jadi QR belum ditampilkan
          <View style={[s.kotakQR, { backgroundColor: warna.abu }]}>
            <Text style={[s.teksQR, { color: '#344054', marginTop: 0 }]}>
              Draf belum dapat ditimbang. Lengkapi datanya agar QR untuk Petugas Timbang muncul.
            </Text>
          </View>
        ) : (
          <View style={s.kotakQR}>
            <KodeQR nilai={d.nomor_kedatangan} ukuran={200} />
            <Text style={s.teksQR}>Tunjukkan QR ini ke Petugas Timbang untuk dipindai.</Text>
          </View>
        )}

        <Baris label="Kapal" nilai={`${d.nama_kapal} (${d.nomor_registrasi})`} />
        <Baris label="Waktu" nilai={`${tanggalPendek(d.tanggal)}, ${d.jam}`} />
        <Baris label="Kapten" nilai={d.nama_kapten} />
        <Baris label="Baris tangkapan" nilai={`${d.jumlah_baris} baris`} />
        <View style={s.garis} />
        <Text style={s.labelKecil}>Total berat estimasi</Text>
        <Text style={s.total}>{formatKg(d.total_berat_estimasi)}</Text>
      </View>

      {draf ? (
        <Tombol judul="Lengkapi Draf Sekarang" onPress={onLengkapi} gaya={{ marginBottom: 10 }} />
      ) : null}
      <Tombol judul="Tambah Kedatangan Lain" jenis={draf ? 'garis' : undefined} onPress={onTambahLagi} />
      <Tombol judul="Kembali ke Daftar" jenis="teks" onPress={onKeDaftar} gaya={{ marginTop: 10 }} />
    </ScrollView>
  );
}

function Baris({ label, nilai }) {
  return (
    <View style={s.barisInfo}>
      <Text style={s.labelInfo}>{label}</Text>
      <Text style={s.nilaiInfo}>{nilai}</Text>
    </View>
  );
}

const s = StyleSheet.create({
  layar: { flex: 1, backgroundColor: warna.latar },
  isiSukses: { padding: 16, paddingBottom: 40, alignItems: 'stretch' },
  strip: {
    backgroundColor: warna.putih, borderLeftWidth: 3, borderLeftColor: warna.sukses, borderRadius: 4,
    borderWidth: 1, borderColor: warna.garisHalus, paddingHorizontal: 14, paddingVertical: 10, marginBottom: 12,
  },
  judulSukses: { fontSize: 15, fontWeight: '700', color: warna.teks },
  kartu: {
    backgroundColor: warna.putih, borderRadius: 6, padding: 16, marginBottom: 14,
    borderWidth: 1, borderColor: warna.garisHalus,
  },
  labelKecil: { fontSize: 12, color: warna.teksRedup },
  nomor: { fontSize: 22, fontWeight: '700', color: warna.utama, marginVertical: 4, fontVariant: ['tabular-nums'] },
  kotakQR: {
    alignItems: 'center', borderWidth: 1, borderColor: warna.garisHalus, borderRadius: 4,
    paddingVertical: 14, paddingHorizontal: 12, marginBottom: 14,
  },
  teksQR: { fontSize: 13, color: warna.teksRedup, textAlign: 'center', marginTop: 10 },
  barisInfo: { flexDirection: 'row', paddingVertical: 7, borderBottomWidth: 1, borderBottomColor: warna.garisHalus },
  labelInfo: { width: 120, fontSize: 13, color: warna.teksRedup },
  nilaiInfo: { flex: 1, fontSize: 13, color: warna.teks, fontWeight: '500', textAlign: 'right' },
  garis: { height: 0, marginVertical: 6 },
  total: { fontSize: 24, fontWeight: '700', color: warna.utama, marginTop: 2, fontVariant: ['tabular-nums'] },
});

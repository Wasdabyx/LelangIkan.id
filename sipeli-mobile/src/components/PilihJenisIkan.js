import { useState } from 'react';
import { FlatList, Modal, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { ukuran, warna } from '../tema';
import Ikon from './Ikon';

/** Jendela pilihan jenis ikan dengan pencarian */
export default function PilihJenisIkan({ tampil, daftar, terpilih, onPilih, onTutup }) {
  const [cari, setCari] = useState('');
  const hasil = daftar.filter((j) => j.nama_jenis.toLowerCase().includes(cari.trim().toLowerCase()));

  function tutup() {
    setCari('');
    onTutup();
  }

  return (
    <Modal visible={tampil} animationType="slide" onRequestClose={tutup}>
      <View style={s.layar}>
        <View style={s.kepala}>
          <Text style={s.judul}>Pilih Jenis Ikan</Text>
          <Pressable onPress={tutup} style={s.tombolTutup} accessibilityLabel="Tutup">
            <Ikon nama="silang" ukuran={24} warna={warna.teks} />
          </Pressable>
        </View>

        <View style={s.kotakCari}>
          <Ikon nama="cari" ukuran={20} warna={warna.teksRedup} />
          <TextInput
            style={s.inputCari}
            value={cari}
            onChangeText={setCari}
            placeholder="Cari jenis ikan..."
            placeholderTextColor={warna.teksRedup}
            autoCorrect={false}
          />
        </View>

        <FlatList
          data={hasil}
          keyExtractor={(j) => String(j.id_jenis)}
          keyboardShouldPersistTaps="handled"
          contentContainerStyle={{ paddingBottom: 40 }}
          ListEmptyComponent={<Text style={s.kosong}>Jenis ikan tidak ditemukan.</Text>}
          renderItem={({ item }) => {
            const aktif = item.id_jenis === terpilih;
            return (
              <Pressable
                onPress={() => {
                  setCari('');
                  onPilih(item);
                }}
                style={({ pressed }) => [s.item, aktif && s.itemAktif, pressed && { backgroundColor: warna.utamaMuda }]}
              >
                <Ikon nama="ikan" ukuran={24} warna={warna.utama} />
                <View style={{ flex: 1 }}>
                  <Text style={s.namaItem}>{item.nama_jenis}</Text>
                  <Text style={s.kategori}>{(item.kategori || '').replace(/_/g, ' ').toLowerCase()}</Text>
                </View>
                {aktif ? <Ikon nama="centang" ukuran={22} warna={warna.sukses} tebal={2.6} /> : null}
              </Pressable>
            );
          }}
        />
      </View>
    </Modal>
  );
}

const s = StyleSheet.create({
  layar: { flex: 1, backgroundColor: warna.putih, paddingTop: 36 },
  kepala: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingBottom: 8 },
  judul: { flex: 1, fontSize: 20, fontWeight: '700', color: warna.teks },
  tombolTutup: { width: 48, height: 48, alignItems: 'center', justifyContent: 'center' },
  kotakCari: {
    flexDirection: 'row', alignItems: 'center', gap: 8, marginHorizontal: 16, marginBottom: 8,
    height: ukuran.tinggiInput, borderWidth: 1.5, borderColor: warna.garis, borderRadius: ukuran.radius, paddingHorizontal: 12,
  },
  inputCari: { flex: 1, fontSize: ukuran.hurufSedang, color: warna.teks, height: '100%' },
  item: {
    flexDirection: 'row', alignItems: 'center', gap: 14, minHeight: 60,
    paddingHorizontal: 16, borderBottomWidth: 1, borderBottomColor: warna.abu,
  },
  itemAktif: { backgroundColor: warna.suksesLatar },
  namaItem: { fontSize: ukuran.hurufSedang, color: warna.teks, fontWeight: '600' },
  kategori: { fontSize: 13, color: warna.teksRedup, marginTop: 2, textTransform: 'capitalize' },
  kosong: { textAlign: 'center', color: warna.teksRedup, marginTop: 24, fontSize: ukuran.hurufNormal },
});

import { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Platform, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { KotakPesan, Tombol } from '../../components/Dasar';
import { Bagian } from '../../components/Tata';
import { dukunganTimbangan } from '../../timbangan';
import { warna } from '../../tema';

/**
 * FR-07 — memilih sumber berat badan.
 *
 * Tiga pilihan yang ditampilkan hanya bila perangkat mendukungnya:
 *   - Bluetooth (BLE)      : ponsel/tablet, memindai timbangan di sekitar;
 *   - Kabel USB/Serial     : Chrome/Edge di laptop (Web Serial);
 *   - Simulasi             : selalu ada, diberi label jelas sebagai simulasi.
 *
 * Layar ini hanya mengumpulkan pilihan; pembacaan berat dilakukan `useTimbangan`.
 */
export default function PilihTimbangan({ perangkat, port, onPilihPerangkat, onPilihPort, onTutup }) {
  const dukung = dukunganTimbangan();
  const [memindai, setMemindai] = useState(false);
  const [temuan, setTemuan] = useState([]);
  const [galat, setGalat] = useState(null);
  const managerRef = useRef(null);

  const hentikanPindai = useCallback(() => {
    try {
      managerRef.current?.stopDeviceScan();
      managerRef.current?.destroy();
    } catch (e) {
      // sudah berhenti
    }
    managerRef.current = null;
    setMemindai(false);
  }, []);

  useEffect(() => () => hentikanPindai(), [hentikanPindai]);

  async function pindaiBle() {
    setGalat(null);
    setTemuan([]);
    setMemindai(true);
    try {
      const { BleManager } = require('react-native-ble-plx');
      const manager = new BleManager();
      managerRef.current = manager;
      manager.startDeviceScan(null, null, (e, alat) => {
        if (e) {
          setGalat('Pemindaian gagal: ' + e.message);
          hentikanPindai();
          return;
        }
        if (!alat?.id) return;
        setTemuan((lama) => {
          if (lama.some((x) => x.id === alat.id)) return lama;
          return [...lama, { id: alat.id, nama: alat.name || alat.localName || 'Tanpa nama' }];
        });
      });
      // berhenti memindai setelah 10 detik; hasil tetap ditampilkan
      setTimeout(hentikanPindai, 10000);
    } catch (e) {
      setGalat('Bluetooth tidak tersedia di perangkat ini: ' + (e?.message || ''));
      setMemindai(false);
    }
  }

  async function pilihSerial() {
    setGalat(null);
    try {
      const dipilih = await navigator.serial.requestPort();
      onPilihPort(dipilih);
      onTutup();
    } catch (e) {
      if (e?.name !== 'NotFoundError') setGalat('Port tidak dipilih: ' + (e?.message || ''));
    }
  }

  return (
    <ScrollView style={{ flex: 1, backgroundColor: warna.latar }} contentContainerStyle={s.isi}>
      {galat ? <KotakPesan teks={galat} gaya={{ marginBottom: 12 }} /> : null}

      <Bagian judul="Sumber berat">
        <Text style={s.ket}>
          Pilih timbangan yang dipakai. Bila timbangan tidak terbaca, gunakan Input Manual —
          alasannya wajib diisi dan tercatat (FR-07).
        </Text>

        <View style={s.baris}>
          <View style={{ flex: 1 }}>
            <Text style={s.judul}>Timbangan Bluetooth</Text>
            <Text style={s.sub}>
              {perangkat ? `Terpilih: ${perangkat.nama}` : dukung.ble ? 'Pindai timbangan di sekitar' : 'Hanya tersedia di aplikasi ponsel'}
            </Text>
          </View>
          <Tombol
            judul={memindai ? 'Memindai...' : 'Pindai'}
            jenis="garis"
            nonaktif={!dukung.ble || memindai}
            onPress={pindaiBle}
            gaya={{ minWidth: 100 }}
          />
        </View>

        {memindai ? (
          <View style={s.memindai}>
            <ActivityIndicator color={warna.utama} />
            <Text style={s.sub}>Mencari timbangan... ({temuan.length} ditemukan)</Text>
          </View>
        ) : null}

        {temuan.map((t) => (
          <Pressable
            key={t.id}
            onPress={() => { onPilihPerangkat(t); hentikanPindai(); onTutup(); }}
            style={s.hasil}
            accessibilityRole="button"
          >
            <Text style={s.judul}>{t.nama}</Text>
            <Text style={s.sub}>{t.id}</Text>
          </Pressable>
        ))}

        <View style={[s.baris, { marginTop: 16 }]}>
          <View style={{ flex: 1 }}>
            <Text style={s.judul}>Timbangan kabel (USB/Serial)</Text>
            <Text style={s.sub}>
              {port ? 'Port terpilih' : dukung.serialWeb ? 'Untuk Chrome/Edge di laptop' : 'Hanya tersedia di peramban Chrome/Edge'}
            </Text>
          </View>
          <Tombol
            judul="Pilih Port"
            jenis="garis"
            nonaktif={!dukung.serialWeb}
            onPress={pilihSerial}
            gaya={{ minWidth: 100 }}
          />
        </View>

        <View style={[s.baris, { marginTop: 16 }]}>
          <View style={{ flex: 1 }}>
            <Text style={s.judul}>Timbangan Simulasi</Text>
            <Text style={s.sub}>Tanpa perangkat keras. Angka tiruan, ditandai jelas di layar.</Text>
          </View>
          <Tombol
            judul="Pakai"
            jenis="garis"
            onPress={() => { onPilihPerangkat(null); onPilihPort(null); onTutup(); }}
            gaya={{ minWidth: 100 }}
          />
        </View>
      </Bagian>

      <Tombol judul="Tutup" jenis="teks" onPress={onTutup} gaya={{ marginTop: 16 }} />
    </ScrollView>
  );
}

const s = StyleSheet.create({
  isi: { padding: 16, paddingBottom: 40 },
  ket: { fontSize: 12, color: warna.teksRedup, lineHeight: 18, marginBottom: 12 },
  baris: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 10 },
  judul: { fontSize: 14, fontWeight: '600', color: warna.teks },
  sub: { fontSize: 12, color: warna.teksRedup, marginTop: 2 },
  memindai: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 10 },
  hasil: {
    borderWidth: 1, borderColor: warna.garis, borderRadius: 8, padding: 12, marginTop: 8,
    backgroundColor: Platform.OS === 'web' ? '#fff' : warna.putih,
  },
});

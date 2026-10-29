import DateTimePicker, { DateTimePickerAndroid } from '@react-native-community/datetimepicker';
import { Platform, Pressable, StyleSheet, Text, View } from 'react-native';
import { keJam, tanggalPanjang } from '../format';
import { ukuran, warna } from '../tema';
import Ikon from './Ikon';

/**
 * Isian tanggal dan jam (DPPL Tabel 3.2): bawaan waktu perangkat,
 * tidak boleh melebihi waktu sekarang.
 */
export default function PilihWaktu({ nilai, onUbah, galat }) {
  function gabung(pilihan, mode) {
    const baru = new Date(nilai);
    if (mode === 'date') baru.setFullYear(pilihan.getFullYear(), pilihan.getMonth(), pilihan.getDate());
    else baru.setHours(pilihan.getHours(), pilihan.getMinutes(), 0, 0);
    onUbah(baru);
  }

  function buka(mode) {
    DateTimePickerAndroid.open({
      value: nilai,
      mode,
      is24Hour: true,
      maximumDate: mode === 'date' ? new Date() : undefined,
      onValueChange: (e, pilihan) => {
        if (pilihan) gabung(pilihan, mode);
      },
    });
  }

  return (
    <View style={{ marginBottom: 14 }}>
      <View style={s.barisLabel}>
        <Text style={s.label}>Tanggal dan Jam Kedatangan</Text>
        <Pressable onPress={() => onUbah(new Date())} hitSlop={8} style={s.tombolSekarang}>
          <Text style={s.teksSekarang}>Sekarang</Text>
        </Pressable>
      </View>

      {Platform.OS === 'ios' ? (
        <View style={s.baris}>
          <DateTimePicker value={nilai} mode="date" display="compact" maximumDate={new Date()}
            onValueChange={(e, p) => p && gabung(p, 'date')} />
          <DateTimePicker value={nilai} mode="time" display="compact" is24Hour
            onValueChange={(e, p) => p && gabung(p, 'time')} />
        </View>
      ) : (
        <View style={s.baris}>
          <Pressable onPress={() => buka('date')} style={[s.kotak, { flex: 1.6 }, galat && s.kotakGalat]}>
            <Ikon nama="kalender" ukuran={20} warna={warna.utama} />
            <Text style={s.teks} numberOfLines={1}>{tanggalPanjang(nilai)}</Text>
          </Pressable>
          <Pressable onPress={() => buka('time')} style={[s.kotak, { flex: 1 }, galat && s.kotakGalat]}>
            <Ikon nama="jam" ukuran={20} warna={warna.utama} />
            <Text style={s.teks}>{keJam(nilai)}</Text>
          </Pressable>
        </View>
      )}
      {galat ? <Text style={s.teksGalat}>{galat}</Text> : null}
    </View>
  );
}

const s = StyleSheet.create({
  barisLabel: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 6 },
  label: { fontSize: ukuran.hurufNormal, fontWeight: '600', color: warna.teks },
  tombolSekarang: { minHeight: 32, justifyContent: 'center', paddingHorizontal: 4 },
  teksSekarang: { color: warna.utama, fontWeight: '700', fontSize: 15 },
  baris: { flexDirection: 'row', gap: 10 },
  kotak: {
    height: ukuran.tinggiInput, borderWidth: 1.5, borderColor: warna.garis, borderRadius: ukuran.radius,
    paddingHorizontal: 12, flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: warna.putih,
  },
  kotakGalat: { borderColor: warna.galat, borderWidth: 2 },
  teks: { fontSize: ukuran.hurufNormal, color: warna.teks, flexShrink: 1 },
  teksGalat: { color: warna.galat, fontSize: 14, marginTop: 6, fontWeight: '600' },
});

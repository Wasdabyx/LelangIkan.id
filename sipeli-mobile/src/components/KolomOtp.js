import { useEffect, useRef, useState } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { ukuran, warna } from '../tema';
import { hanyaAngka } from '../validasi';

/**
 * Tugas 2 - Kolom kode OTP 6 digit.
 * Tampil sebagai 6 kotak; di belakangnya ada satu TextInput angka (bisa ditempel / isi otomatis).
 * onLengkap(kode) dipanggil saat 6 digit sudah terisi.
 */
export function KolomOtp({ nilai, onUbah, onLengkap, galat, nonaktif, inputRef }) {
  const refDalam = useRef(null);
  const ref = inputRef || refDalam;
  const [fokus, setFokus] = useState(false);

  function ubah(t) {
    const kode = hanyaAngka(t).slice(0, 6); // RegEx: buang semua yang bukan angka
    onUbah(kode);
    if (kode.length === 6 && onLengkap) onLengkap(kode);
  }

  return (
    <View>
      <Pressable onPress={() => ref.current?.focus()} style={s.baris} accessibilityLabel="Kolom kode OTP 6 digit">
        {[0, 1, 2, 3, 4, 5].map((i) => {
          const aktif = fokus && !nonaktif && (i === nilai.length || (i === 5 && nilai.length === 6));
          return (
            <View key={i} style={[s.kotak, aktif && s.kotakAktif, galat && s.kotakGalat, nonaktif && s.kotakMati]}>
              <Text style={s.digit}>{nilai[i] || ''}</Text>
            </View>
          );
        })}
        <TextInput
          ref={ref}
          value={nilai}
          onChangeText={ubah}
          onFocus={() => setFokus(true)}
          onBlur={() => setFokus(false)}
          keyboardType="number-pad"
          maxLength={6}
          textContentType="oneTimeCode"
          autoComplete="one-time-code"
          importantForAutofill="yes"
          caretHidden
          editable={!nonaktif}
          accessibilityLabel="Kode OTP"
          testID="kode-otp"
          style={s.inputTersembunyi}
        />
      </Pressable>
      {galat ? <Text style={s.teksGalat}>{galat}</Text> : null}
    </View>
  );
}

/** Hitung mundur detik (berhenti di 0). mulai(detik) untuk memulai ulang. */
export function useHitungMundur(detikAwal = 0) {
  const [sisa, setSisa] = useState(detikAwal);
  const target = useRef(Date.now() + detikAwal * 1000);

  useEffect(() => {
    const t = setInterval(() => {
      setSisa(Math.max(0, Math.ceil((target.current - Date.now()) / 1000)));
    }, 500);
    return () => clearInterval(t);
  }, []);

  function mulai(detik) {
    target.current = Date.now() + detik * 1000;
    setSisa(detik);
  }

  return [sisa, mulai];
}

/** 600 -> "10:00" */
export const formatMenit = (detik) =>
  `${String(Math.floor(detik / 60)).padStart(2, '0')}:${String(detik % 60).padStart(2, '0')}`;

const s = StyleSheet.create({
  baris: { flexDirection: 'row', justifyContent: 'space-between', gap: 8 },
  kotak: {
    flex: 1, maxWidth: 52, height: 60, borderWidth: 1, borderColor: warna.garis,
    borderRadius: ukuran.radius, alignItems: 'center', justifyContent: 'center', backgroundColor: warna.putih,
  },
  kotakAktif: { borderColor: warna.utama, borderWidth: 2.5 },
  kotakGalat: { borderColor: warna.galat, borderWidth: 2 },
  kotakMati: { backgroundColor: warna.abu },
  digit: { fontSize: 22, fontWeight: '700', color: warna.teks },
  inputTersembunyi: {
    position: 'absolute', top: 0, left: 0, right: 0, bottom: 0,
    opacity: 0.011, color: 'transparent', fontSize: 1,
  },
  teksGalat: { color: warna.galat, fontSize: 13, marginTop: 8, fontWeight: '500', textAlign: 'center' },
});

import { useRef, useState } from 'react';
import { KeyboardAvoidingView, ScrollView, StyleSheet, Text, View } from 'react-native';
import { pembeli } from '../../api';
import { KotakPesan, Tombol } from '../../components/Dasar';
import Ikon from '../../components/Ikon';
import { formatMenit, KolomOtp, useHitungMundur } from '../../components/KolomOtp';
import { formatKg, formatRupiah } from '../../format';
import { ukuran, warna } from '../../tema';
import { periksaKodeOtp } from '../../validasi';

/**
 * Tugas 2 - Konfirmasi pemesanan lot dengan OTP 6 digit dari surel.
 * OTP berlaku 10 menit, hanya untuk lot ini, sekali pakai, maksimal 5 kali salah.
 */
export default function OtpPemesanan({ lot, info, onBerhasil, onBatal }) {
  const [kode, setKode] = useState('');
  const [galat, setGalat] = useState(null);
  const [pesan, setPesan] = useState(info?.pesan ? { jenis: 'sukses', teks: info.pesan } : null);
  const [surel, setSurel] = useState(info?.surel_tersamar || '');
  const [memuat, setMemuat] = useState(false);
  const [mengirim, setMengirim] = useState(false);
  const [sisaBerlaku, mulaiBerlaku] = useHitungMundur(info?.berlaku_detik ?? 0);
  const [sisaKirim, mulaiKirim] = useHitungMundur(info?.kirim_ulang_detik ?? 0);
  const refKode = useRef(null);

  async function konfirmasi(kodeDiisi = kode) {
    if (memuat) return;
    const g = periksaKodeOtp(kodeDiisi); // RegEx /^\d{6}$/
    setGalat(g);
    if (g) return;

    setMemuat(true);
    setPesan(null);
    try {
      const hasil = await pembeli.konfirmasi(lot.id_lot, kodeDiisi);
      onBerhasil(hasil.data);
    } catch (e) {
      setMemuat(false);
      setKode('');
      if (e.galatKolom?.kode_otp) setGalat(e.galatKolom.kode_otp);
      else setPesan({ jenis: 'galat', teks: e.message });
      setTimeout(() => refKode.current?.focus(), 100);
    }
  }

  async function kirimUlang() {
    if (mengirim) return;
    setMengirim(true);
    setGalat(null);
    try {
      const hasil = await pembeli.mintaOtp(lot.id_lot);
      setSurel(hasil.surel_tersamar);
      mulaiBerlaku(hasil.berlaku_detik);
      mulaiKirim(hasil.kirim_ulang_detik);
      setKode('');
      setPesan({ jenis: 'sukses', teks: hasil.pesan });
    } catch (e) {
      if (e.data?.tunggu_detik) mulaiKirim(e.data.tunggu_detik);
      setPesan({ jenis: 'galat', teks: e.message });
    } finally {
      setMengirim(false);
    }
  }

  const kedaluwarsa = sisaBerlaku === 0;

  return (
    <KeyboardAvoidingView style={s.wadah} behavior="padding">
      <ScrollView contentContainerStyle={s.isi} keyboardShouldPersistTaps="handled">
        <View style={s.kartu}>
          <Text style={s.judul}>Konfirmasi Pemesanan</Text>
          <Text style={s.teks}>
            Masukkan kode OTP yang dikirim ke <Text style={s.tebal}>{surel}</Text>
          </Text>

          <View style={s.lot}>
            <View style={s.barisLot}>
              <Text style={s.label}>Nomor lot</Text>
              <Text style={s.nilaiTebal}>{lot.kode_lot}</Text>
            </View>
            <View style={s.barisLot}>
              <Text style={s.label}>Jenis ikan</Text>
              <Text style={s.nilai}>{lot.nama_jenis} (Grade {lot.kode_grade})</Text>
            </View>
            <View style={s.barisLot}>
              <Text style={s.label}>Berat</Text>
              <Text style={s.nilai}>{formatKg(lot.berat_aktual)}</Text>
            </View>
            <View style={s.barisLot}>
              <Text style={s.label}>Harga pembukaan</Text>
              <Text style={s.nilai}>{formatRupiah(lot.harga_pembukaan)}/kg</Text>
            </View>
          </View>

          {pesan ? <KotakPesan jenis={pesan.jenis} teks={pesan.teks} gaya={{ marginTop: 16 }} /> : null}

          <View style={{ marginTop: 20 }}>
            <KolomOtp
              inputRef={refKode}
              nilai={kode}
              onUbah={(k) => {
                setKode(k);
                if (galat) setGalat(null);
              }}
              onLengkap={konfirmasi}
              galat={galat}
              nonaktif={memuat}
            />
          </View>

          <Text style={[s.waktu, kedaluwarsa && { color: warna.galat }]}>
            {mengirim
              ? 'Mengirim kode...'
              : kedaluwarsa
              ? 'Kode sudah kedaluwarsa. Tekan Kirim Ulang Kode.'
              : `Kode berlaku ${formatMenit(sisaBerlaku)} lagi`}
          </Text>

          <Tombol judul="Konfirmasi Pemesanan" onPress={() => konfirmasi()} memuat={memuat} gaya={{ marginTop: 16 }} />
          <Tombol
            judul={sisaKirim > 0 ? `Kirim Ulang Kode (${sisaKirim} dtk)` : 'Kirim Ulang Kode'}
            jenis="garis"
            ikon="muatUlang"
            onPress={kirimUlang}
            memuat={mengirim}
            nonaktif={sisaKirim > 0 || memuat}
            gaya={{ marginTop: 10 }}
          />
          <Tombol judul="Batal" jenis="teks" onPress={onBatal} nonaktif={memuat} gaya={{ marginTop: 6 }} />
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const s = StyleSheet.create({
  wadah: { flex: 1, backgroundColor: warna.latar },
  isi: { flexGrow: 1, paddingBottom: 32 },
  kartu: { backgroundColor: warna.kartu, padding: 16, borderBottomWidth: 1, borderColor: warna.garisHalus },
  lingkaran: {
    width: 68, height: 68, borderRadius: 34, backgroundColor: warna.utamaMuda,
    alignItems: 'center', justifyContent: 'center', alignSelf: 'center', marginBottom: 10,
  },
  judul: { fontSize: 18, fontWeight: '700', color: warna.teks },
  teks: { fontSize: 13, color: warna.teksRedup, marginTop: 4, lineHeight: 19 },
  tebal: { color: warna.teks, fontWeight: '600' },
  lot: { marginTop: 14, borderTopWidth: 1, borderBottomWidth: 1, borderColor: warna.garisHalus, paddingVertical: 8, gap: 6 },
  barisLot: { flexDirection: 'row', justifyContent: 'space-between', gap: 12 },
  label: { fontSize: 13, color: warna.teksRedup },
  nilai: { fontSize: 13, color: warna.teks, fontWeight: '500', flexShrink: 1, textAlign: 'right' },
  nilaiTebal: { fontSize: 14, color: warna.teks, fontWeight: '700' },
  waktu: { fontSize: 14, color: warna.teksRedup, textAlign: 'center', marginTop: 14, fontWeight: '500' },
});

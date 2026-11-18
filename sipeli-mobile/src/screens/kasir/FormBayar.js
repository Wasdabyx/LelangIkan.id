import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { kasir } from '../../api';
import { DialogKonfirmasi, KolomTeks, KotakPesan, Tombol } from '../../components/Dasar';
import FormGulir from '../../components/FormGulir';
import Ikon from '../../components/Ikon';
import KodeQR from '../../components/KodeQR';
import RincianTagihan from '../../components/RincianTagihan';
import { bagikanNotaPdf, cetakNota } from '../../cetakNota';
import { formatRupiah } from '../../format';
import { ukuran, warna } from '../../tema';
import { hanyaAngka } from '../../validasi';

const METODE = [
  { kode: 'TUNAI', judul: 'Tunai', ikon: 'dompet', ket: 'Uang tunai di loket' },
  { kode: 'TRANSFER', judul: 'Transfer', ikon: 'pensil', ket: 'Wajib nomor referensi' },
  { kode: 'QRIS', judul: 'QRIS', ikon: 'qr', ket: 'Simulasi' },
  { kode: 'SALDO', judul: 'Saldo', ikon: 'gembok', ket: 'Belum tersedia', mati: true },
];
const POLA_REFERENSI = /^[A-Za-z0-9-]{6,40}$/;

/**
 * UC-06 langkah 2-5: rincian tagihan (FR-28), pilih metode (FR-29), validasi nominal (FR-30),
 * catat pembayaran, lalu cetak / bagikan nota ber-QR (FR-31). Pembayaran bertahap diperbolehkan.
 */
export default function FormBayar({ awal, onKembali }) {
  const [t, setT] = useState(awal);
  const [metode, setMetode] = useState('TUNAI');
  const [nominal, setNominal] = useState(String(Math.round(awal.sisa_tagihan)));
  const [diterima, setDiterima] = useState('');
  const [referensi, setReferensi] = useState('');
  const [galat, setGalat] = useState({});
  const [pesan, setPesan] = useState(null);
  const [tanya, setTanya] = useState(false);
  const [memuat, setMemuat] = useState(false);
  const [mencetak, setMencetak] = useState(null);

  const sisa = Math.round(Number(t.sisa_tagihan));
  const lunas = t.status === 'LUNAS';
  const n = Number(nominal);
  const kembalian = metode === 'TUNAI' && diterima ? Number(diterima) - n : null;

  function periksa() {
    const g = {};
    if (!/^\d+$/.test(nominal) || n <= 0) g.nominal = 'Nominal pembayaran harus lebih dari Rp0.';
    else if (n > sisa) g.nominal = `Nominal melebihi sisa tagihan ${formatRupiah(sisa)}.`;
    if (metode === 'TRANSFER' && !POLA_REFERENSI.test(referensi)) {
      g.nomor_referensi = 'Nomor referensi 6-40 karakter: huruf, angka, atau tanda strip.';
    }
    if (metode === 'TUNAI' && diterima && Number(diterima) < n) g.diterima = 'Uang diterima kurang dari nominal yang dibayar.';
    setGalat(g);
    if (Object.keys(g).length === 0) setTanya(true);
  }

  async function simpan() {
    setTanya(false);
    setMemuat(true);
    setPesan(null);
    try {
      const hasil = await kasir.bayar(t.id_transaksi, metode, n, metode === 'TRANSFER' ? referensi : null);
      setT(hasil.data);
      setPesan({ jenis: 'sukses', teks: hasil.pesan });
      setNominal(String(Math.round(hasil.data.sisa_tagihan)));
      setReferensi('');
      setDiterima('');
    } catch (e) {
      if (e.status === 422 && Object.keys(e.galatKolom).length) setGalat(e.galatKolom);
      else setPesan({ jenis: 'galat', teks: e.message });
    } finally {
      setMemuat(false);
    }
  }

  async function nota(cara) {
    setMencetak(cara);
    try {
      if (cara === 'cetak') await cetakNota(t);
      else await bagikanNotaPdf(t);
    } catch (e) {
      setPesan({ jenis: 'galat', teks: e.message });
    } finally {
      setMencetak(null);
    }
  }

  const sudahBayar = t.pembayaran && t.pembayaran.length > 0;

  return (
    <View style={{ flex: 1, backgroundColor: warna.latar }}>
      <FormGulir gayaIsi={s.isi}>
        <View style={s.kartu}>
          <RincianTagihan t={t} />
          <KotakPesan jenis={pesan?.jenis} teks={pesan?.teks} gaya={{ marginTop: 12 }} />
        </View>

        {!lunas ? (
          <View style={s.kartu}>
            <Text style={s.judul}>Catat Pembayaran</Text>
            <Text style={s.label}>Metode pembayaran</Text>
            <View style={s.gridMetode}>
              {METODE.map((m) => {
                const pilih = metode === m.kode;
                return (
                  <Pressable
                    key={m.kode}
                    disabled={m.mati}
                    onPress={() => {
                      setMetode(m.kode);
                      setGalat({});
                    }}
                    style={[s.metode, pilih && s.metodeAktif, m.mati && s.metodeMati]}
                    accessibilityRole="radio"
                    accessibilityState={{ checked: pilih, disabled: !!m.mati }}
                  >
                    <Ikon nama={m.ikon} ukuran={24} warna={m.mati ? warna.garis : pilih ? warna.utama : warna.teksRedup} />
                    <Text style={[s.teksMetode, pilih && { color: warna.utama }, m.mati && { color: warna.teksRedup }]}>{m.judul}</Text>
                    <Text style={s.ketMetode}>{m.ket}</Text>
                  </Pressable>
                );
              })}
            </View>

            <KolomTeks
              label="Nominal dibayar (Rp)"
              value={nominal}
              onChangeText={(x) => {
                setNominal(hanyaAngka(x).slice(0, 11));
                setGalat({ ...galat, nominal: null });
              }}
              keyboardType="number-pad"
              galat={galat.nominal}
              accessibilityLabel="Nominal dibayar"
            />
            {n !== sisa ? (
              <Pressable onPress={() => setNominal(String(sisa))} style={s.tautan}>
                <Text style={s.teksTautan}>Bayar lunas {formatRupiah(sisa)}</Text>
              </Pressable>
            ) : (
              <Text style={s.petunjuk}>Kurangi nominal untuk pembayaran sebagian (bertahap).</Text>
            )}

            {metode === 'TUNAI' ? (
              <>
                <KolomTeks
                  label="Uang diterima (opsional, untuk kembalian)"
                  value={diterima}
                  onChangeText={(x) => {
                    setDiterima(hanyaAngka(x).slice(0, 11));
                    setGalat({ ...galat, diterima: null });
                  }}
                  keyboardType="number-pad"
                  galat={galat.diterima}
                  accessibilityLabel="Uang diterima"
                />
                {kembalian !== null && kembalian >= 0 ? (
                  <Text style={s.kembalian}>Kembalian: {formatRupiah(kembalian)}</Text>
                ) : null}
              </>
            ) : null}

            {metode === 'TRANSFER' ? (
              <KolomTeks
                label="Nomor referensi transfer"
                value={referensi}
                onChangeText={(x) => {
                  setReferensi(x.toUpperCase().replace(/[^A-Z0-9-]/g, '').slice(0, 40));
                  setGalat({ ...galat, nomor_referensi: null });
                }}
                placeholder="Contoh: BRI-20261118-778899"
                autoCapitalize="characters"
                galat={galat.nomor_referensi}
                accessibilityLabel="Nomor referensi"
              />
            ) : null}

            {metode === 'QRIS' && n > 0 && n <= sisa ? (
              <View style={s.qris}>
                <Text style={s.labelQris}>QRIS simulasi</Text>
                <KodeQR nilai={`QRIS-SIMULASI|${t.kode_transaksi}|${n}`} ukuran={170} />
                <Text style={s.nominalQris}>{formatRupiah(n)}</Text>
                <Text style={s.petunjuk}>
                  Minta pembeli memindai kode ini. Ini simulasi, tidak ada uang yang ditarik. Tekan Konfirmasi setelah pembeli menyatakan berhasil.
                </Text>
              </View>
            ) : null}

            <Tombol
              judul={metode === 'QRIS' ? 'Konfirmasi Pembayaran QRIS' : 'Simpan Pembayaran'}
              ikon="centang"
              onPress={periksa}
              memuat={memuat}
              gaya={{ marginTop: 14 }}
            />
          </View>
        ) : (
          <View style={[s.kartu, s.kartuLunas]}>
            <Ikon nama="centang" ukuran={40} warna={warna.sukses} />
            <Text style={s.judulLunas}>Transaksi lunas</Text>
            <Text style={s.petunjuk}>Cetak nota atau bagikan PDF ke pembeli.</Text>
          </View>
        )}

        {sudahBayar ? (
          <View style={s.barisTombol}>
            <Tombol judul="Cetak Nota" ikon="cetak" jenis="garis" onPress={() => nota('cetak')} memuat={mencetak === 'cetak'} nonaktif={!!mencetak} gaya={{ flex: 1 }} />
            <Tombol judul="Bagikan PDF" ikon="bagikan" jenis="garis" onPress={() => nota('pdf')} memuat={mencetak === 'pdf'} nonaktif={!!mencetak} gaya={{ flex: 1 }} />
          </View>
        ) : null}
        <Tombol judul="Kembali ke Daftar" jenis={lunas ? 'utama' : 'teks'} onPress={() => onKembali(lunas)} />
      </FormGulir>

      <DialogKonfirmasi
        tampil={tanya}
        judul="Simpan pembayaran?"
        pesan={`${t.kode_transaksi} - ${t.nama_pembeli}\n${METODE.find((m) => m.kode === metode).judul}${metode === 'TRANSFER' ? ` (${referensi})` : ''}: ${formatRupiah(n)}\n${n === sisa ? 'Transaksi akan LUNAS.' : `Sisa setelah ini ${formatRupiah(sisa - n)}.`}\n\nPembayaran yang sudah disimpan tidak dapat diubah.`}
        teksYa="Simpan"
        onYa={simpan}
        onTidak={() => setTanya(false)}
      />
    </View>
  );
}

const s = StyleSheet.create({
  isi: { padding: 16, paddingBottom: 32, gap: 12 },
  kartu: { backgroundColor: warna.kartu, borderRadius: 6, padding: 16, borderWidth: 1, borderColor: '#E4EBF1' },
  judul: { fontSize: 16, fontWeight: '700', color: warna.teks, marginBottom: 10 },
  label: { fontSize: ukuran.hurufNormal, fontWeight: '500', color: warna.teks, marginBottom: 8 },
  gridMetode: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 14 },
  metode: {
    width: '48%', flexGrow: 1, minHeight: 72, borderRadius: 4, borderWidth: 1, borderColor: warna.garis,
    alignItems: 'center', justifyContent: 'center', padding: 8, backgroundColor: warna.putih,
  },
  metodeAktif: { borderColor: warna.utama, borderWidth: 2.5, backgroundColor: warna.utamaMuda },
  metodeMati: { backgroundColor: warna.abu, borderStyle: 'dashed' },
  teksMetode: { fontSize: 15, fontWeight: '700', color: warna.teks, marginTop: 4 },
  ketMetode: { fontSize: 11, color: warna.teksRedup, marginTop: 1 },
  tautan: { alignSelf: 'flex-start', minHeight: 44, justifyContent: 'center', marginTop: -10, marginBottom: 4 },
  teksTautan: { fontSize: 14, fontWeight: '600', color: warna.aksen, textDecorationLine: 'underline' },
  petunjuk: { fontSize: 12, color: warna.teksRedup, lineHeight: 17, marginTop: -6, marginBottom: 8, textAlign: 'left' },
  kembalian: { fontSize: 15, fontWeight: '700', color: warna.sukses, marginTop: -6, marginBottom: 6 },
  qris: { alignItems: 'center', borderWidth: 1, borderColor: warna.garis, borderRadius: 4, padding: 14, gap: 8, borderStyle: 'dashed' },
  labelQris: { fontSize: 13, fontWeight: '700', color: warna.teksRedup },
  nominalQris: { fontSize: 18, fontWeight: '700', color: warna.teks },
  kartuLunas: { alignItems: 'center', gap: 8, backgroundColor: warna.suksesLatar, borderWidth: 1, borderColor: warna.sukses },
  judulLunas: { fontSize: 17, fontWeight: '700', color: warna.sukses },
  barisTombol: { flexDirection: 'row', gap: 10 },
});

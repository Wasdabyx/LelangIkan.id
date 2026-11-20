import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { kasir } from '../../api';
import { DialogKonfirmasi, KolomTeks, KotakPesan, Tombol } from '../../components/Dasar';
import FormGulir from '../../components/FormGulir';
import Ikon from '../../components/Ikon';
import KodeQR from '../../components/KodeQR';
import RincianTagihan from '../../components/RincianTagihan';
import { Bagian, Segmen } from '../../components/Tata';
import { bagikanNotaPdf, cetakNota } from '../../cetakNota';
import { formatRupiah } from '../../format';
import { ukuran, warna } from '../../tema';
import { hanyaAngka } from '../../validasi';

const METODE = [
  { kode: 'TUNAI', judul: 'Tunai', ikon: 'dompet', ket: 'Uang tunai di loket' },
  { kode: 'TRANSFER', judul: 'Transfer', ikon: 'pensil', ket: 'Wajib nomor referensi' },
  { kode: 'QRIS', judul: 'QRIS', ikon: 'qr', ket: 'Simulasi' },
  { kode: 'SALDO', judul: 'Saldo', ikon: 'dompet', ket: 'Potong dari saldo pembeli' },
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
        <Bagian>
          <RincianTagihan t={t} />
          <KotakPesan jenis={pesan?.jenis} teks={pesan?.teks} gaya={{ marginTop: 12 }} />
        </Bagian>

        {!lunas ? (
          <Bagian judul="Catat Pembayaran">
            <Text style={s.label}>Metode pembayaran</Text>
            <Segmen
              label="Metode pembayaran"
              nilai={metode}
              onGanti={(k) => {
                setMetode(k);
                setGalat({});
              }}
              pilihan={METODE.map((m) => ({ kunci: m.kode, judul: m.judul, nonaktif: m.mati }))}
            />
            <Text style={s.ketMetode}>
              {METODE.find((m) => m.kode === metode).ket}.
            </Text>

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
          </Bagian>
        ) : (
          <View style={s.lunas}>
            <Text style={s.judulLunas}>Transaksi lunas</Text>
            <Text style={s.teksLunas}>Cetak nota atau bagikan PDF ke pembeli.</Text>
          </View>
        )}

        {sudahBayar ? (
          <View style={[s.barisTombol, { paddingHorizontal: 16, marginBottom: 10 }]}>
            <Tombol judul="Cetak Nota" ikon="cetak" jenis="garis" onPress={() => nota('cetak')} memuat={mencetak === 'cetak'} nonaktif={!!mencetak} gaya={{ flex: 1 }} />
            <Tombol judul="Bagikan PDF" ikon="bagikan" jenis="garis" onPress={() => nota('pdf')} memuat={mencetak === 'pdf'} nonaktif={!!mencetak} gaya={{ flex: 1 }} />
          </View>
        ) : null}
        <View style={{ paddingHorizontal: 16 }}>
          <Tombol judul="Kembali ke Daftar" jenis={lunas ? 'utama' : 'teks'} onPress={() => onKembali(lunas)} />
        </View>
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
  isi: { paddingBottom: 32 },
  label: { fontSize: 13, fontWeight: '500', color: warna.teks, marginBottom: 6 },
  ketMetode: { fontSize: 12, color: warna.teksRedup, marginTop: 6, marginBottom: 14 },
  tautan: { alignSelf: 'flex-start', minHeight: 36, justifyContent: 'center', marginTop: -10, marginBottom: 4 },
  teksTautan: { fontSize: 13, fontWeight: '600', color: warna.aksen, textDecorationLine: 'underline' },
  petunjuk: { fontSize: 12, color: warna.teksRedup, lineHeight: 17, marginTop: -6, marginBottom: 8, textAlign: 'left' },
  kembalian: { fontSize: 15, fontWeight: '700', color: warna.sukses, marginTop: -6, marginBottom: 6 },
  qris: { alignItems: 'center', borderWidth: 1, borderColor: warna.garis, borderRadius: 4, padding: 14, gap: 8, borderStyle: 'dashed' },
  labelQris: { fontSize: 13, fontWeight: '600', color: warna.teksRedup },
  nominalQris: { fontSize: 18, fontWeight: '700', color: warna.teks },
  lunas: {
    backgroundColor: warna.suksesLatar, borderLeftWidth: 3, borderLeftColor: warna.sukses,
    marginHorizontal: 16, marginBottom: 10, borderRadius: 4, paddingVertical: 10, paddingHorizontal: 12,
  },
  judulLunas: { fontSize: 15, fontWeight: '700', color: warna.sukses },
  teksLunas: { fontSize: 12, color: warna.teks, marginTop: 2 },
  barisTombol: { flexDirection: 'row', gap: 10 },
});

import { useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { kasir } from '../../api';
import { formatRupiah } from '../../format';
import { warna } from '../../tema';

const PILIHAN_JENIS = [['SETOR', 'Top-up'], ['PENARIKAN', 'Penarikan'], ['PENYESUAIAN', 'Penyesuaian']];
const PILIHAN_METODE = [['TUNAI', 'Tunai'], ['TRANSFER', 'Transfer'], ['QRIS', 'QRIS']];

export default function KelolaSaldo() {
  const [cari, setCari] = useState('');
  const [hasilCari, setHasilCari] = useState([]);
  const [pembeli, setPembeli] = useState(null);
  const [jenis, setJenis] = useState('SETOR');
  const [arah, setArah] = useState('KREDIT');
  const [metode, setMetode] = useState('TUNAI');
  const [nominal, setNominal] = useState('');
  const [referensi, setReferensi] = useState('');
  const [keterangan, setKeterangan] = useState('');
  const [memuat, setMemuat] = useState(false);
  const [galat, setGalat] = useState(null);
  const [pesan, setPesan] = useState(null);

  async function cariPembeli() {
    setGalat(null);
    setPesan(null);
    if (cari.trim().length < 2) return setGalat('Masukkan minimal dua karakter untuk mencari pembeli.');
    setMemuat(true);
    try {
      const hasil = await kasir.cariPembeliSaldo(cari.trim());
      setHasilCari(hasil.data || []);
      if (!hasil.data?.length) setGalat('Pembeli tidak ditemukan.');
    } catch (e) {
      setGalat(e.message);
    } finally {
      setMemuat(false);
    }
  }

  async function pilihPembeli(item) {
    setMemuat(true);
    setGalat(null);
    try {
      const hasil = await kasir.saldoPembeli(item.id_pembeli);
      setPembeli(hasil.data);
      setHasilCari([]);
    } catch (e) {
      setGalat(e.message);
    } finally {
      setMemuat(false);
    }
  }

  async function simpan() {
    setGalat(null);
    setPesan(null);
    const nilai = Number(nominal);
    if (!pembeli || !Number.isFinite(nilai) || nilai <= 0) return setGalat('Masukkan nominal lebih dari Rp0.');
    if (jenis === 'PENYESUAIAN' && keterangan.trim().length < 5) return setGalat('Alasan penyesuaian minimal 5 karakter.');
    setMemuat(true);
    try {
      const data = {
        jenis,
        nominal: nilai,
        metode: jenis === 'PENYESUAIAN' ? null : metode,
        arah: jenis === 'PENYESUAIAN' ? arah : null,
        nomor_referensi: metode === 'TRANSFER' && jenis !== 'PENYESUAIAN' ? referensi.trim() : null,
        keterangan: keterangan.trim() || null,
      };
      const hasil = await kasir.mutasiSaldo(pembeli.pembeli.id_pembeli, data);
      setPesan(hasil.pesan);
      setNominal('');
      setReferensi('');
      if (jenis !== 'PENYESUAIAN') {
        const baru = await kasir.saldoPembeli(pembeli.pembeli.id_pembeli);
        setPembeli(baru.data);
      }
    } catch (e) {
      setGalat(e.galatKolom ? Object.values(e.galatKolom).flat().join(' ') : e.message);
    } finally {
      setMemuat(false);
    }
  }

  return (
    <ScrollView style={s.layar} contentContainerStyle={s.isi} keyboardShouldPersistTaps="handled">
      <Text style={s.judul}>Saldo Pembeli</Text>
      <View style={s.kartu}>
        <Text style={s.label}>Cari nama, nama pengguna, NIK, atau nomor telepon</Text>
        <View style={s.baris}>
          <TextInput value={cari} onChangeText={setCari} onSubmitEditing={cariPembeli} placeholder="Ketik untuk mencari" style={[s.input, { flex: 1 }]} />
          <Pressable onPress={cariPembeli} style={s.tombol}><Text style={s.teksTombol}>Cari</Text></Pressable>
        </View>
        {hasilCari.map((item) => (
          <Pressable key={item.id_pembeli} onPress={() => pilihPembeli(item)} style={s.hasil}>
            <Text style={s.nama}>{item.nama_lengkap}</Text>
            <Text style={s.redup}>{item.nama_pengguna} · NIK {item.nik_tersamar || '-'} · HP {item.no_hp_tersamar || '-'}</Text>
          </Pressable>
        ))}
      </View>

      {pembeli ? (
        <>
          <View style={s.kartu}>
            <View style={s.barisRingkasan}><Text style={s.redup}>Pembeli</Text><Text style={s.nama}>{pembeli.pembeli.nama_lengkap}</Text></View>
            <View style={s.barisRingkasan}><Text style={s.redup}>Saldo</Text><Text style={s.angka}>{formatRupiah(pembeli.saldo)}</Text></View>
            <View style={s.barisRingkasan}><Text style={s.redup}>Limit kredit</Text><Text style={s.nilai}>{formatRupiah(pembeli.limit_kredit)}</Text></View>
            <View style={s.barisRingkasan}><Text style={s.redup}>Tagihan terbuka</Text><Text style={s.nilai}>{formatRupiah(pembeli.tagihan_terbuka)}</Text></View>
            <View style={s.barisRingkasan}><Text style={s.redup}>Daya beli efektif</Text><Text style={s.nilai}>{formatRupiah(pembeli.daya_beli_efektif)}</Text></View>
          </View>
          <View style={s.kartu}>
            <Text style={s.judulKartu}>Catat Mutasi</Text>
            <Pilihan data={PILIHAN_JENIS} aktif={jenis} pilih={(v) => { setJenis(v); setPesan(null); }} />
            {jenis === 'PENYESUAIAN' ? <Pilihan data={ [['KREDIT', 'Tambah'], ['DEBIT', 'Kurangi']] } aktif={arah} pilih={setArah} /> : null}
            {jenis !== 'PENYESUAIAN' ? <Pilihan data={PILIHAN_METODE} aktif={metode} pilih={setMetode} /> : null}
            <Text style={s.label}>Nominal (Rupiah)</Text>
            <TextInput value={nominal} onChangeText={(v) => setNominal(v.replace(/[^0-9]/g, ''))} keyboardType="numeric" placeholder="Contoh: 100000" style={s.input} />
            {metode === 'TRANSFER' && jenis !== 'PENYESUAIAN' ? <>
              <Text style={s.label}>Nomor referensi transfer</Text>
              <TextInput value={referensi} onChangeText={setReferensi} autoCapitalize="characters" placeholder="Min. 6 karakter" style={s.input} />
            </> : null}
            <Text style={s.label}>{jenis === 'PENYESUAIAN' ? 'Alasan penyesuaian (wajib)' : 'Keterangan (opsional)'}</Text>
            <TextInput value={keterangan} onChangeText={setKeterangan} placeholder="Tulis keterangan" style={[s.input, s.multiline]} multiline />
            {jenis === 'PENYESUAIAN' ? <Text style={s.catatan}>Perubahan saldo baru berlaku setelah disetujui Pimpinan.</Text> : null}
            <Pressable disabled={memuat} onPress={simpan} style={[s.tombol, s.tombolSimpan, memuat && s.nonaktif]}>
              {memuat ? <ActivityIndicator color="#fff" /> : <Text style={s.teksTombol}>{jenis === 'PENYESUAIAN' ? 'Ajukan Persetujuan' : 'Simpan Mutasi'}</Text>}
            </Pressable>
            {pesan ? <Text style={s.sukses}>{pesan}</Text> : null}
            {galat ? <Text style={s.galat}>{galat}</Text> : null}
          </View>
          <View style={s.kartu}>
            <Text style={s.judulKartu}>10 Mutasi Terakhir</Text>
            {(pembeli.mutasi || []).length ? pembeli.mutasi.map((m) => (
              <View key={m.id_mutasi} style={s.mutasi}>
                <View style={{ flex: 1 }}>
                  <Text style={s.nama}>{m.jenis} · {m.status}</Text>
                  <Text style={s.redup}>{m.waktu} · {m.nama_kasir || 'Kasir'}{m.nama_penyetuju ? ` · Disetujui ${m.nama_penyetuju}` : ''}</Text>
                </View>
                <Text style={[s.nilai, m.arah === 'DEBIT' && { color: warna.galat }]}>{m.arah === 'DEBIT' ? '−' : '+'}{formatRupiah(m.nominal)}</Text>
              </View>
            )) : <Text style={s.redup}>Belum ada mutasi saldo.</Text>}
          </View>
        </>
      ) : null}
      {galat && !pembeli ? <Text style={s.galat}>{galat}</Text> : null}
    </ScrollView>
  );
}

function Pilihan({ data, aktif, pilih }) {
  return <View style={s.pilihan}>{data.map(([nilai, label]) => (
    <Pressable key={nilai} onPress={() => pilih(nilai)} style={[s.pilihanItem, aktif === nilai && s.pilihanAktif]}>
      <Text style={[s.pilihanTeks, aktif === nilai && s.pilihanTeksAktif]}>{label}</Text>
    </Pressable>
  ))}</View>;
}

const s = StyleSheet.create({
  layar: { flex: 1, backgroundColor: warna.latar },
  isi: { padding: 12, paddingBottom: 32, gap: 10 },
  judul: { color: warna.teks, fontWeight: '700', fontSize: 18 },
  judulKartu: { color: warna.teks, fontWeight: '700', fontSize: 15, marginBottom: 10 },
  kartu: { backgroundColor: warna.putih, borderWidth: 1, borderColor: warna.garisHalus, padding: 14, gap: 8 },
  label: { color: warna.teksRedup, fontSize: 12, fontWeight: '600' },
  input: { borderWidth: 1, borderColor: warna.garis, borderRadius: 4, minHeight: 44, paddingHorizontal: 10, color: warna.teks, backgroundColor: warna.putih },
  multiline: { minHeight: 70, paddingTop: 10, textAlignVertical: 'top' },
  baris: { flexDirection: 'row', gap: 8 },
  tombol: { backgroundColor: warna.utama, paddingHorizontal: 16, minHeight: 44, alignItems: 'center', justifyContent: 'center', borderRadius: 4 },
  tombolSimpan: { marginTop: 4 },
  teksTombol: { color: warna.putih, fontWeight: '700' },
  hasil: { borderTopWidth: 1, borderColor: warna.garisHalus, paddingVertical: 10 },
  nama: { color: warna.teks, fontWeight: '600', fontSize: 13 },
  redup: { color: warna.teksRedup, fontSize: 12 },
  angka: { color: warna.utama, fontSize: 19, fontWeight: '800' },
  nilai: { color: warna.teks, fontWeight: '700', fontSize: 13 },
  barisRingkasan: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10 },
  pilihan: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginBottom: 4 },
  pilihanItem: { borderWidth: 1, borderColor: warna.garis, paddingHorizontal: 10, minHeight: 38, justifyContent: 'center', borderRadius: 4 },
  pilihanAktif: { borderColor: warna.utama, backgroundColor: '#EAF2F7' },
  pilihanTeks: { color: warna.teksRedup, fontSize: 12, fontWeight: '600' },
  pilihanTeksAktif: { color: warna.utama },
  catatan: { color: warna.teksRedup, fontSize: 12 },
  sukses: { color: warna.sukses, fontSize: 13, fontWeight: '600' },
  galat: { color: warna.galat, fontSize: 13, marginVertical: 6 },
  mutasi: { flexDirection: 'row', alignItems: 'center', gap: 10, borderTopWidth: 1, borderColor: warna.garisHalus, paddingVertical: 9 },
  nonaktif: { opacity: 0.55 },
});

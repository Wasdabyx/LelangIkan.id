import { useEffect, useRef, useState } from 'react';
import { Platform, ScrollView, StyleSheet, Text, View } from 'react-native';
import { api, unggahFotoLot } from '../../api';
import { KameraFoto, pilihFileWeb } from '../../components/AmbilFoto';
import { bagikanLabelPdf, cetakLabel } from '../../cetakLabel';
import { KotakPesan, Tombol } from '../../components/Dasar';
import FotoIkan from '../../components/FotoIkan';
import Ikon from '../../components/Ikon';
import KodeQR from '../../components/KodeQR';
import { formatKg, formatRupiah, waktuPendek } from '../../format';
import { ukuran, warna } from '../../tema';
import LencanaLot from './LencanaLot';

/**
 * Alur utama UC-02 langkah (7)-(8): ringkasan lot + label QR, cetak & cetak ulang (alur A3)
 * baru = true  -> lot baru dibuat, dialog cetak langsung dibuka
 * Foto lot (opsional): dipotret sebelum lot dilelang, tampil untuk Operator dan Pembeli.
 */
export default function LabelLot({ lot, baru, namaTpi, teksKembali, onKembali, onFotoBerubah }) {
  const [jumlahCetak, setJumlahCetak] = useState(lot.jumlah_cetak || 0);
  const [mencetak, setMencetak] = useState(false);
  const [membagikan, setMembagikan] = useState(false);
  const [pesan, setPesan] = useState(null); // { jenis, teks }
  const sudahOtomatis = useRef(false);
  const [foto, setFoto] = useState(lot.foto || null);
  const [kamera, setKamera] = useState(false);
  const [mengunggah, setMengunggah] = useState(false);
  const [pesanFoto, setPesanFoto] = useState(null); // { jenis, teks }
  const bolehFoto = lot.status === 'DRAFT_HARGA' || lot.status === 'MENUNGGU_LELANG';

  async function kirimFoto(f) {
    setKamera(false);
    setMengunggah(true);
    setPesanFoto(null);
    try {
      const h = await unggahFotoLot(lot.id_lot, f);
      setFoto(h.data.foto);
      if (onFotoBerubah) onFotoBerubah();
      setPesanFoto({ jenis: 'sukses', teks: 'Foto lot disimpan dan tampil untuk Operator serta Pembeli.' });
    } catch (e) {
      setPesanFoto({ jenis: 'galat', teks: e.message });
    }
    setMengunggah(false);
  }

  useEffect(() => {
    if (baru && !sudahOtomatis.current) {
      sudahOtomatis.current = true;
      const t = setTimeout(cetak, 700);
      return () => clearTimeout(t);
    }
  }, []);

  async function cetak() {
    setMencetak(true);
    setPesan(null);
    // Catat setiap percobaan cetak di server (alur A3)
    try {
      const h = await api.post(`/lot/${lot.id_lot}/cetak`);
      setJumlahCetak(h.jumlah_cetak);
    } catch (e) {
      // pencatatan gagal tidak menghalangi pencetakan
    }
    try {
      await cetakLabel(lot, namaTpi);
      setPesan({
        jenis: 'info',
        teks: 'Dialog cetak dibuka. Pilih printer label, atau "Simpan sebagai PDF". Bila label tidak keluar, tekan Cetak Ulang.',
      });
    } catch (e) {
      setPesan({
        jenis: 'galat',
        teks: `Gagal membuka pencetakan (${e.message}). Periksa printer, lalu tekan Cetak Ulang.`,
      });
    }
    setMencetak(false);
  }

  async function bagikan() {
    setMembagikan(true);
    setPesan(null);
    try {
      await bagikanLabelPdf(lot, namaTpi);
    } catch (e) {
      setPesan({ jenis: 'galat', teks: `Gagal membuat PDF label: ${e.message}` });
    }
    setMembagikan(false);
  }

  const draf = lot.status === 'DRAFT_HARGA';

  return (
    <ScrollView contentContainerStyle={s.isi}>
      {baru ? (
        <View style={[s.kepalaSukses, draf && { borderLeftColor: warna.peringatan }]}>
          <Text style={s.judulSukses}>{draf ? 'Lot disimpan sebagai draf harga' : 'Lot berhasil dibuat'}</Text>
          {!draf ? <Text style={s.subSukses}>Lot siap masuk antrean lelang.</Text> : null}
        </View>
      ) : (
        <Text style={s.judul}>Label Lot</Text>
      )}

      {draf ? (
        <KotakPesan
          teks="Harga pembukaan belum diisi. Lot belum bisa dilelang sampai harga ditetapkan."
          gaya={{ marginBottom: 12 }}
        />
      ) : null}

      {/* Foto lot (tidak ikut dicetak pada label) */}
      <View style={s.kartuFoto}>
        <View style={s.barisFoto}>
          <FotoIkan lot={{ ...lot, foto }} lebar={132} tinggi={88} />
          <View style={{ flex: 1 }}>
            <Text style={s.judulFoto}>
              Foto Lot <Text style={s.opsional}>(opsional)</Text>
            </Text>
            <Text style={s.teksFoto}>
              {foto
                ? 'Foto lot sudah diambil.'
                : bolehFoto
                ? 'Belum ada foto. Pembeli melihat foto contoh jenis ikan.'
                : 'Lot tidak memiliki foto sendiri.'}
            </Text>
          </View>
        </View>
        {bolehFoto ? (
          <View style={s.barisTombolFoto}>
            <Tombol
              judul={foto ? 'Ganti Foto' : 'Ambil Foto'}
              ikon="kamera"
              jenis="garis"
              kecil
              memuat={mengunggah}
              onPress={() => setKamera(true)}
              gaya={{ flex: 1 }}
            />
            {Platform.OS === 'web' ? (
              <Tombol
                judul="Pilih File"
                jenis="garis"
                kecil
                nonaktif={mengunggah}
                onPress={() => pilihFileWeb(kirimFoto, (t) => setPesanFoto({ jenis: 'galat', teks: t }))}
                gaya={{ flex: 1 }}
              />
            ) : null}
          </View>
        ) : null}
        <KotakPesan jenis={pesanFoto?.jenis} teks={pesanFoto?.teks} gaya={{ marginTop: 10 }} />
      </View>
      <KameraFoto tampil={kamera} onPakai={kirimFoto} onTutup={() => setKamera(false)} />

      {/* Tampilan label */}
      <View style={s.label}>
        <View style={s.atasLabel}>
          <Text style={s.merek}>LelangIkan.id</Text>
          <Text style={s.tpi} numberOfLines={1}>{namaTpi || 'TPI'}</Text>
        </View>
        <View style={s.wadahQR}>
          <KodeQR nilai={lot.kode_lot} ukuran={200} />
        </View>
        <Text style={s.kode}>{lot.kode_lot}</Text>
        <Text style={s.jenis}>
          {lot.nama_jenis} | Grade {lot.kode_grade}
        </Text>
        <View style={{ flexDirection: 'row', justifyContent: 'center', marginTop: 6 }}>
          <LencanaLot status={lot.status} />
        </View>

        <View style={s.tabel}>
          <Baris label="Berat aktual" nilai={formatKg(lot.berat_aktual)} besar />
          <Baris label="Harga pembukaan" nilai={lot.harga_pembukaan ? `${formatRupiah(lot.harga_pembukaan)}/kg` : 'Belum diisi'} besar />
          {lot.nilai_lot ? <Baris label="Perkiraan nilai" nilai={formatRupiah(lot.nilai_lot)} /> : null}
          <Baris label="Estimasi awal" nilai={`${formatKg(lot.berat_estimasi)} (selisih ${String(lot.selisih_persen ?? 0).replace('.', ',')}%)`} />
          <Baris label="Kapal" nilai={lot.nama_kapal} />
          <Baris label="Kedatangan" nilai={lot.nomor_kedatangan} />
          <Baris label="Ditimbang" nilai={waktuPendek(lot.waktu_timbang)} />
          <Baris label="Petugas" nilai={lot.petugas_timbang} />
          <Baris label="Sumber berat" nilai={lot.perangkat_timbang || (lot.timbang_manual ? 'Input Manual' : '-')} />
          {lot.alasan ? <Baris label="Alasan" nilai={lot.alasan} /> : null}
        </View>

        {lot.perlu_verifikasi || lot.timbang_manual ? (
          <View style={s.barisTanda}>
            {lot.perlu_verifikasi ? <Text style={s.tanda}>Perlu verifikasi</Text> : null}
            {lot.timbang_manual ? <Text style={s.tanda}>Input manual</Text> : null}
          </View>
        ) : null}
      </View>

      <View style={s.statusCetak}>
        <Text style={s.teksStatusCetak}>
          {jumlahCetak ? `Label sudah dicetak ${jumlahCetak} kali` : 'Label belum pernah dicetak'}
        </Text>
      </View>

      {pesan ? <KotakPesan jenis={pesan.jenis} teks={pesan.teks} gaya={{ marginBottom: 12 }} /> : null}

      <Tombol
        judul={jumlahCetak ? 'Cetak Ulang' : 'Cetak Label'}
        onPress={cetak}
        memuat={mencetak}
      />
      <Tombol
        judul="Bagikan PDF"
        jenis="garis"
        onPress={bagikan}
        memuat={membagikan}
        gaya={{ marginTop: 10 }}
      />
      <Tombol judul={teksKembali} jenis="teks" onPress={onKembali} gaya={{ marginTop: 8 }} />
    </ScrollView>
  );
}

function Baris({ label, nilai, besar }) {
  return (
    <View style={s.baris}>
      <Text style={s.labelBaris}>{label}</Text>
      <Text style={[s.nilaiBaris, besar && s.nilaiBesar]}>{nilai || '-'}</Text>
    </View>
  );
}

const s = StyleSheet.create({
  isi: { padding: 16, paddingBottom: 40 },
  judul: { fontSize: 18, fontWeight: '700', color: warna.teks, marginBottom: 12 },
  kepalaSukses: {
    backgroundColor: warna.putih, borderLeftWidth: 3, borderLeftColor: warna.sukses,
    borderWidth: 1, borderColor: warna.garisHalus, borderRadius: 4, paddingHorizontal: 14, paddingVertical: 10, marginBottom: 12,
  },
  judulSukses: { fontSize: 16, fontWeight: '700', color: warna.teks },
  subSukses: { fontSize: 13, color: warna.teksRedup, marginTop: 2 },
  label: {
    backgroundColor: warna.putih, borderRadius: 4, padding: 16, marginBottom: 14,
    borderWidth: 2, borderColor: warna.teks, borderStyle: 'dashed',
  },
  atasLabel: {
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-end', gap: 8,
    borderBottomWidth: 2, borderBottomColor: warna.teks, paddingBottom: 6,
  },
  merek: { fontSize: 16, fontWeight: '700', color: warna.teks },
  tpi: { flexShrink: 1, fontSize: 12, color: warna.teksRedup },
  wadahQR: { alignItems: 'center', marginTop: 12 },
  kode: { fontSize: 20, fontWeight: '700', color: warna.teks, textAlign: 'center', marginTop: 6, fontVariant: ['tabular-nums'] },
  jenis: { fontSize: 16, fontWeight: '700', color: warna.utama, textAlign: 'center', marginTop: 4 },
  tabel: { marginTop: 12 },
  baris: { flexDirection: 'row', paddingVertical: 7, borderBottomWidth: 1, borderBottomColor: '#EEF2F6', gap: 10 },
  labelBaris: { width: 120, fontSize: 13, color: warna.teksRedup },
  nilaiBaris: { flex: 1, fontSize: 13, color: warna.teks, textAlign: 'right' },
  nilaiBesar: { fontSize: 15, fontWeight: '700' },
  barisTanda: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'center', gap: 8, marginTop: 12 },
  tanda: {
    borderWidth: 2, borderColor: warna.galat, color: warna.galat, fontWeight: '700', fontSize: 12,
    paddingHorizontal: 10, paddingVertical: 3, borderRadius: 4,
  },
  kartuFoto: {
    backgroundColor: warna.putih, borderRadius: 4, borderWidth: 1, borderColor: warna.garisHalus,
    padding: 12, marginBottom: 14,
  },
  barisFoto: { flexDirection: 'row', gap: 12, alignItems: 'center' },
  judulFoto: { fontSize: 15, fontWeight: '700', color: warna.teks },
  opsional: { fontSize: 12, fontWeight: '400', color: warna.teksRedup },
  teksFoto: { fontSize: 13, color: warna.teksRedup, marginTop: 4, lineHeight: 18 },
  barisTombolFoto: { flexDirection: 'row', gap: 8, marginTop: 10 },
  statusCetak: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 12, justifyContent: 'center' },
  teksStatusCetak: { fontSize: 13, color: warna.teksRedup },
});

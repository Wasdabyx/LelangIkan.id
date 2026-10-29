import { useEffect, useRef, useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { api } from '../../api';
import { bagikanLabelPdf, cetakLabel } from '../../cetakLabel';
import { KotakPesan, Tombol } from '../../components/Dasar';
import Ikon from '../../components/Ikon';
import KodeQR from '../../components/KodeQR';
import { formatKg, formatRupiah, waktuPendek } from '../../format';
import { ukuran, warna } from '../../tema';
import LencanaLot from './LencanaLot';

/**
 * Alur utama UC-02 langkah (7)-(8): ringkasan lot + label QR, cetak & cetak ulang (alur A3)
 * baru = true  -> lot baru dibuat, dialog cetak langsung dibuka
 */
export default function LabelLot({ lot, baru, namaTpi, teksKembali, onKembali }) {
  const [jumlahCetak, setJumlahCetak] = useState(lot.jumlah_cetak || 0);
  const [mencetak, setMencetak] = useState(false);
  const [membagikan, setMembagikan] = useState(false);
  const [pesan, setPesan] = useState(null); // { jenis, teks }
  const sudahOtomatis = useRef(false);

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
        <View style={s.kepalaSukses}>
          <View style={[s.lingkaran, draf && { backgroundColor: warna.peringatanLatar }]}>
            <Ikon nama={draf ? 'peringatan' : 'centang'} ukuran={40} warna={draf ? warna.peringatan : warna.sukses} tebal={3} />
          </View>
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
          {lot.nama_jenis} - Grade {lot.kode_grade}
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
            {lot.perlu_verifikasi ? <Text style={s.tanda}>PERLU VERIFIKASI</Text> : null}
            {lot.timbang_manual ? <Text style={s.tanda}>INPUT MANUAL</Text> : null}
          </View>
        ) : null}
      </View>

      <View style={s.statusCetak}>
        <Ikon nama="cetak" ukuran={20} warna={jumlahCetak ? warna.sukses : warna.teksRedup} />
        <Text style={s.teksStatusCetak}>
          {jumlahCetak ? `Label sudah dicetak ${jumlahCetak} kali` : 'Label belum pernah dicetak'}
        </Text>
      </View>

      {pesan ? <KotakPesan jenis={pesan.jenis} teks={pesan.teks} gaya={{ marginBottom: 12 }} /> : null}

      <Tombol
        judul={jumlahCetak ? 'Cetak Ulang' : 'Cetak Label'}
        ikon="cetak"
        onPress={cetak}
        memuat={mencetak}
      />
      <Tombol
        judul="Bagikan PDF"
        ikon="bagikan"
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
  judul: { fontSize: 24, fontWeight: '800', color: warna.teks, marginBottom: 12 },
  kepalaSukses: { alignItems: 'center', marginBottom: 14 },
  lingkaran: {
    width: 80, height: 80, borderRadius: 40, backgroundColor: warna.suksesLatar,
    alignItems: 'center', justifyContent: 'center',
  },
  judulSukses: { fontSize: 22, fontWeight: '800', color: warna.teks, marginTop: 10, textAlign: 'center' },
  subSukses: { fontSize: 15, color: warna.teksRedup, marginTop: 4, textAlign: 'center' },
  label: {
    backgroundColor: warna.putih, borderRadius: 12, padding: 16, marginBottom: 14,
    borderWidth: 2, borderColor: warna.teks, borderStyle: 'dashed',
  },
  atasLabel: {
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-end', gap: 8,
    borderBottomWidth: 2, borderBottomColor: warna.teks, paddingBottom: 6,
  },
  merek: { fontSize: 18, fontWeight: '800', color: warna.teks },
  tpi: { flexShrink: 1, fontSize: 13, color: warna.teksRedup },
  wadahQR: { alignItems: 'center', marginTop: 12 },
  kode: { fontSize: 26, fontWeight: '800', color: warna.teks, textAlign: 'center', letterSpacing: 1, marginTop: 6, fontVariant: ['tabular-nums'] },
  jenis: { fontSize: 19, fontWeight: '800', color: warna.utama, textAlign: 'center', marginTop: 4 },
  tabel: { marginTop: 12 },
  baris: { flexDirection: 'row', paddingVertical: 7, borderBottomWidth: 1, borderBottomColor: '#EEF2F6', gap: 10 },
  labelBaris: { width: 120, fontSize: 15, color: warna.teksRedup },
  nilaiBaris: { flex: 1, fontSize: 15, color: warna.teks, fontWeight: '600', textAlign: 'right' },
  nilaiBesar: { fontSize: 18, fontWeight: '800' },
  barisTanda: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'center', gap: 8, marginTop: 12 },
  tanda: {
    borderWidth: 2, borderColor: warna.galat, color: warna.galat, fontWeight: '800', fontSize: 13,
    paddingHorizontal: 10, paddingVertical: 3, borderRadius: 6,
  },
  statusCetak: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 12, justifyContent: 'center' },
  teksStatusCetak: { fontSize: ukuran.hurufNormal, color: warna.teks, fontWeight: '600' },
});

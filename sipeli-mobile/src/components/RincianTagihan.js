import { StyleSheet, Text, View } from 'react-native';
import { formatKg, formatRupiah, waktuPendek } from '../format';
import { ukuran, warna } from '../tema';
import { Lencana } from './Dasar';
import { BarisRincian, WARNA_TRANSAKSI } from './Lelang';

const METODE = { TUNAI: 'Tunai', TRANSFER: 'Transfer bank', QRIS: 'QRIS', SALDO: 'Saldo' };

/** FR-28: rincian tagihan (berat, harga akhir, retribusi, biaya layanan, total) + riwayat pembayaran. */
export default function RincianTagihan({ t, tampilRetribusi = true }) {
  const w = WARNA_TRANSAKSI[t.status] || WARNA_TRANSAKSI.BELUM_DIBAYAR;
  return (
    <View>
      <View style={s.barisJudul}>
        <Text style={s.kode} selectable>{t.kode_transaksi}</Text>
        <Lencana teks={w.label} latar={w.latar} warnaTeks={w.teks} />
      </View>
      <Text style={s.lot}>
        {t.kode_lot} | {t.nama_jenis} <Text style={{ color: warna.aksen }}>Grade {t.kode_grade}</Text>
      </Text>
      <Text style={s.redup}>
        Pembeli {t.nama_pembeli} | Kapal {t.nama_kapal} | {waktuPendek(t.waktu_transaksi)}
      </Text>

      <View style={s.kotak}>
        <BarisRincian label={`${formatKg(t.berat_final)} x ${formatRupiah(t.harga_akhir_per_kg)}`} nilai={formatRupiah(t.subtotal)} />
        <BarisRincian label="Biaya layanan" nilai={formatRupiah(t.biaya_layanan)} />
        <View style={s.garis} />
        <BarisRincian label="Total tagihan" nilai={formatRupiah(t.total_tagihan)} besar />
        {Number(t.total_dibayar) > 0 ? <BarisRincian label="Sudah dibayar" nilai={formatRupiah(t.total_dibayar)} warnaNilai={warna.sukses} /> : null}
        <BarisRincian
          label="Sisa tagihan"
          nilai={formatRupiah(t.sisa_tagihan)}
          tebal
          warnaNilai={Number(t.sisa_tagihan) > 0 ? warna.galat : warna.sukses}
        />
      </View>
      {tampilRetribusi ? (
        <Text style={s.catatan}>
          Retribusi {Number(t.persen_retribusi).toLocaleString('id-ID')}% ({formatRupiah(t.nilai_retribusi)}) dipotong dari hasil nelayan, tidak ditambahkan ke tagihan pembeli.
        </Text>
      ) : null}

      {t.pembayaran && t.pembayaran.length > 0 ? (
        <View style={{ marginTop: 14 }}>
          <Text style={s.subjudul}>Riwayat Pembayaran</Text>
          {t.pembayaran.map((p, i) => (
            <View key={i} style={s.barisBayar}>
              <View style={{ flex: 1 }}>
                <Text style={s.metode}>{METODE[p.metode] || p.metode}</Text>
                <Text style={s.redupKecil}>
                  {waktuPendek(p.waktu_bayar)}
                  {p.nomor_referensi ? ` | ${p.nomor_referensi}` : ''} | {p.nama_kasir}
                </Text>
              </View>
              <Text style={s.nominal}>{formatRupiah(p.nominal)}</Text>
            </View>
          ))}
        </View>
      ) : null}
    </View>
  );
}

const s = StyleSheet.create({
  barisJudul: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 },
  kode: { fontSize: 20, fontWeight: '800', color: warna.teks, letterSpacing: 0.3 },
  lot: { fontSize: 15, fontWeight: '700', color: warna.teks, marginTop: 6 },
  redup: { fontSize: 13, color: warna.teksRedup, marginTop: 2, lineHeight: 19 },
  kotak: { backgroundColor: warna.latar, borderRadius: ukuran.radius, padding: 12, marginTop: 12, gap: 8 },
  garis: { height: 1, backgroundColor: warna.garis },
  catatan: { fontSize: 12, color: warna.teksRedup, marginTop: 8, lineHeight: 18 },
  subjudul: { fontSize: 16, fontWeight: '800', color: warna.teks, marginBottom: 4 },
  barisBayar: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: '#E4EBF1' },
  metode: { fontSize: 15, fontWeight: '700', color: warna.teks },
  redupKecil: { fontSize: 12, color: warna.teksRedup, marginTop: 1 },
  nominal: { fontSize: 15, fontWeight: '800', color: warna.sukses },
});

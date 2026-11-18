import { StyleSheet, Text, View } from 'react-native';
import { formatKg, formatRupiah, waktuPendek } from '../format';
import { warna } from '../tema';
import { Lencana } from './Dasar';
import { WARNA_TRANSAKSI } from './Lelang';
import { Info, Tabel } from './Tata';

const METODE = { TUNAI: 'Tunai', TRANSFER: 'Transfer', QRIS: 'QRIS', SALDO: 'Saldo' };

/** FR-28: rincian tagihan (berat, harga akhir, retribusi, biaya layanan, total) + riwayat pembayaran. */
export default function RincianTagihan({ t, tampilRetribusi = true }) {
  const w = WARNA_TRANSAKSI[t.status] || WARNA_TRANSAKSI.BELUM_DIBAYAR;
  const sisa = Number(t.sisa_tagihan);
  return (
    <View>
      <View style={s.barisJudul}>
        <Text style={s.kode} selectable>{t.kode_transaksi}</Text>
        <Lencana teks={w.label} latar={w.latar} warnaTeks={w.teks} />
      </View>
      <Text style={s.lot}>
        {t.kode_lot} | {t.nama_jenis} Grade {t.kode_grade}
      </Text>
      <Text style={s.redup}>
        {t.nama_pembeli} | KM {String(t.nama_kapal).replace(/^KM\s+/i, '')} | {waktuPendek(t.waktu_transaksi)}
      </Text>

      <View style={s.rincian}>
        <Info label={`${formatKg(t.berat_final)} x ${formatRupiah(t.harga_akhir_per_kg)}`} nilai={formatRupiah(t.subtotal)} />
        <Info label="Biaya layanan" nilai={formatRupiah(t.biaya_layanan)} />
        <Info label="Total tagihan" nilai={formatRupiah(t.total_tagihan)} tebal garis />
        {Number(t.total_dibayar) > 0 ? <Info label="Sudah dibayar" nilai={formatRupiah(t.total_dibayar)} /> : null}
      </View>
      <View style={[s.sisa, sisa > 0 ? s.sisaAda : s.sisaNol]}>
        <Text style={s.labelSisa}>Sisa tagihan</Text>
        <Text style={[s.nilaiSisa, { color: sisa > 0 ? warna.galat : warna.sukses }]}>{formatRupiah(t.sisa_tagihan)}</Text>
      </View>
      {tampilRetribusi ? (
        <Text style={s.catatan}>
          Retribusi {Number(t.persen_retribusi).toLocaleString('id-ID')}% ({formatRupiah(t.nilai_retribusi)}) dipotong dari hasil nelayan, tidak ditambahkan ke tagihan pembeli.
        </Text>
      ) : null}

      {t.pembayaran && t.pembayaran.length > 0 ? (
        <View style={{ marginTop: 16 }}>
          <Text style={s.subjudul}>Riwayat pembayaran</Text>
          <Tabel
            label="Riwayat pembayaran"
            kolom={[
              { judul: 'Waktu', flex: 1.1, redup: true, render: (p) => waktuPendek(p.waktu_bayar).replace(/^\d+ \w+ \d+, /, '') },
              {
                judul: 'Metode', flex: 1.6,
                render: (p) => (
                  <View>
                    <Text style={s.metode}>{METODE[p.metode] || p.metode}</Text>
                    <Text style={s.redupKecil} numberOfLines={1}>{p.nomor_referensi || p.nama_kasir}</Text>
                  </View>
                ),
              },
              { judul: 'Nominal', flex: 1.4, kanan: true, tebal: true, render: (p) => formatRupiah(p.nominal) },
            ]}
            data={t.pembayaran}
          />
        </View>
      ) : null}
    </View>
  );
}

const s = StyleSheet.create({
  barisJudul: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 },
  kode: { fontSize: 18, fontWeight: '700', color: warna.teks, fontVariant: ['tabular-nums'] },
  lot: { fontSize: 14, fontWeight: '500', color: warna.teks, marginTop: 4 },
  redup: { fontSize: 12, color: warna.teksRedup, marginTop: 2, lineHeight: 17 },
  rincian: { marginTop: 12 },
  sisa: {
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
    marginTop: 8, paddingVertical: 10, paddingHorizontal: 12, borderRadius: 4, borderLeftWidth: 3,
  },
  sisaAda: { backgroundColor: warna.galatLatar, borderLeftColor: warna.galat },
  sisaNol: { backgroundColor: warna.suksesLatar, borderLeftColor: warna.sukses },
  labelSisa: { fontSize: 14, fontWeight: '600', color: warna.teks },
  nilaiSisa: { fontSize: 18, fontWeight: '700', fontVariant: ['tabular-nums'] },
  catatan: { fontSize: 11, color: warna.teksRedup, marginTop: 8, lineHeight: 16 },
  subjudul: { fontSize: 13, fontWeight: '600', color: warna.teksRedup, marginBottom: 2 },
  metode: { fontSize: 13, fontWeight: '600', color: warna.teks },
  redupKecil: { fontSize: 11, color: warna.teksRedup, marginTop: 1 },
});

import { File, Paths } from 'expo-file-system';
import * as Print from 'expo-print';
import * as Sharing from 'expo-sharing';
import { svgQR } from './components/KodeQR';
import { formatKg, formatRupiah, waktuPendek } from './format';

/**
 * UC-06 FR-31: nota pembayaran ber-QR verifikasi (ukuran A6), dicetak lewat expo-print.
 * Isi QR = LELANGIKAN|TRX-000001|tanda-tangan; dapat diperiksa keasliannya oleh kasir.
 */
const LEBAR = 298;
const TINGGI = 420;
const METODE = { TUNAI: 'Tunai', TRANSFER: 'Transfer', QRIS: 'QRIS', SALDO: 'Saldo' };
const STATUS = { BELUM_DIBAYAR: 'BELUM DIBAYAR', DIBAYAR_SEBAGIAN: 'DIBAYAR SEBAGIAN', LUNAS: 'LUNAS', DIBATALKAN: 'DIBATALKAN' };

const aman = (t) =>
  String(t ?? '-').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

export function htmlNota(t) {
  const bayar = (t.pembayaran || [])
    .map(
      (p) => `<tr><td class="k">${aman(waktuPendek(p.waktu_bayar))} ${aman(METODE[p.metode] || p.metode)}${
        p.nomor_referensi ? `<br/><span class="ref">${aman(p.nomor_referensi)}</span>` : ''
      }</td><td class="v">${aman(formatRupiah(p.nominal))}</td></tr>`
    )
    .join('');

  return `<!DOCTYPE html>
<html><head><meta charset="utf-8" />
<style>
  @page { size: 105mm 148mm; margin: 0; }
  * { box-sizing: border-box; }
  body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #000; }
  .nota { width: 105mm; min-height: 148mm; padding: 5mm 6mm; }
  .atas { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #000; padding-bottom: 1.5mm; }
  .merek { font-size: 13pt; font-weight: bold; }
  .judul { font-size: 9pt; text-align: right; }
  .kode { font-family: 'Courier New', monospace; font-size: 14pt; font-weight: bold; margin-top: 2mm; }
  table { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin-top: 1.5mm; }
  td { padding: 0.8mm 0; vertical-align: top; }
  td.k { color: #222; }
  td.v { text-align: right; font-weight: bold; }
  tr.garis td { border-top: 1px solid #000; }
  tr.total td { font-size: 11pt; border-top: 2px solid #000; padding-top: 1.2mm; }
  .ref { font-size: 7.5pt; color: #444; }
  .sub { font-size: 8pt; font-weight: bold; margin-top: 2mm; border-bottom: 1px solid #000; }
  .bawah { display: flex; gap: 3mm; align-items: center; margin-top: 2.5mm; }
  .bawah svg { width: 26mm; height: 26mm; display: block; }
  .status { border: 2px solid #000; font-weight: bold; font-size: 11pt; padding: 1mm 2.5mm; display: inline-block; }
  .catatan { font-size: 7pt; color: #333; margin-top: 1.5mm; }
</style></head>
<body><div class="nota">
  <div class="atas"><div class="merek">LelangIkan.id</div><div class="judul">NOTA PEMBAYARAN<br/>${aman(t.nama_tpi)}</div></div>
  <div class="kode">${aman(t.kode_transaksi)}</div>
  <table>
    <tr><td class="k">Pembeli</td><td class="v">${aman(t.nama_pembeli)}</td></tr>
    <tr><td class="k">Lot</td><td class="v">${aman(t.kode_lot)}</td></tr>
    <tr><td class="k">Jenis ikan</td><td class="v">${aman(t.nama_jenis)} (Grade ${aman(t.kode_grade)})</td></tr>
    <tr><td class="k">Kapal</td><td class="v">${aman(t.nama_kapal)}</td></tr>
    <tr><td class="k">Waktu lelang</td><td class="v">${aman(waktuPendek(t.waktu_transaksi))}</td></tr>
    <tr class="garis"><td class="k">${aman(formatKg(t.berat_final))} x ${aman(formatRupiah(t.harga_akhir_per_kg))}</td><td class="v">${aman(formatRupiah(t.subtotal))}</td></tr>
    <tr><td class="k">Biaya layanan</td><td class="v">${aman(formatRupiah(t.biaya_layanan))}</td></tr>
    <tr class="total"><td class="k"><b>TOTAL</b></td><td class="v">${aman(formatRupiah(t.total_tagihan))}</td></tr>
  </table>
  <div class="sub">Pembayaran</div>
  <table>${bayar || '<tr><td class="k">Belum ada pembayaran</td><td></td></tr>'}
    <tr class="garis"><td class="k">Sisa tagihan</td><td class="v">${aman(formatRupiah(t.sisa_tagihan))}</td></tr>
  </table>
  <div class="bawah">
    <div>${svgQR(t.qr_nota, 120)}</div>
    <div><div class="status">${aman(STATUS[t.status] || t.status)}</div>
      <div class="catatan">Pindai QR ini di aplikasi LelangIkan.id (menu Kasir, Periksa Nota) untuk memeriksa keaslian nota.</div>
      <div class="catatan">Retribusi ${aman(formatRupiah(t.nilai_retribusi))} dipotong dari hasil nelayan.</div></div>
  </div>
</div></body></html>`;
}

export async function cetakNota(t) {
  await Print.printAsync({ html: htmlNota(t), width: LEBAR, height: TINGGI });
}

/** PDF nota lalu menu Bagikan (cara yang sama dengan label lot agar jalan di Expo Go). */
export async function bagikanNotaPdf(t) {
  if (!(await Sharing.isAvailableAsync())) {
    throw new Error('Fitur bagikan tidak tersedia di perangkat ini.');
  }
  const { base64 } = await Print.printToFileAsync({ html: htmlNota(t), width: LEBAR, height: TINGGI, base64: true });
  if (!base64) throw new Error('Data PDF kosong.');
  const berkas = new File(Paths.cache, `Nota-${t.kode_transaksi}.pdf`);
  if (berkas.exists) berkas.delete();
  berkas.create();
  berkas.write(base64, { encoding: 'base64' });
  await Sharing.shareAsync(berkas.uri, { mimeType: 'application/pdf', dialogTitle: `Nota ${t.kode_transaksi}`, UTI: 'com.adobe.pdf' });
}

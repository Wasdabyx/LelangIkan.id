import { File, Paths } from 'expo-file-system';
import * as Print from 'expo-print';
import * as Sharing from 'expo-sharing';
import { svgQR } from './components/KodeQR';
import { formatKg, formatRupiah, waktuPendek } from './format';

/**
 * Label lot untuk dicetak (ukuran A6: 105 x 148 mm = 298 x 420 poin).
 * Isi: kode QR + nomor lot + jenis/grade + berat + harga pembukaan (FR-08, FR-11)
 */
const LEBAR = 298;
const TINGGI = 420;

const aman = (t) =>
  String(t ?? '-').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

export function htmlLabel(lot, namaTpi) {
  const harga = lot.harga_pembukaan ? `${formatRupiah(lot.harga_pembukaan)} / kg` : 'BELUM ADA (DRAF)';
  const tanda = [
    lot.status === 'DRAFT_HARGA' ? 'DRAF HARGA' : null,
    lot.perlu_verifikasi ? 'PERLU VERIFIKASI' : null,
    lot.timbang_manual ? 'INPUT MANUAL' : null,
  ].filter(Boolean);

  return `<!DOCTYPE html>
<html><head><meta charset="utf-8" />
<style>
  @page { size: 105mm 148mm; margin: 0; }
  * { box-sizing: border-box; }
  body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #000; }
  .label { width: 105mm; height: 148mm; padding: 6mm; display: flex; flex-direction: column; align-items: center; }
  .atas { width: 100%; display: flex; justify-content: space-between; align-items: flex-end;
          border-bottom: 2px solid #000; padding-bottom: 2mm; }
  .merek { font-size: 14pt; font-weight: bold; }
  .tpi { font-size: 9pt; }
  .qr { margin-top: 3mm; }
  .qr svg { width: 50mm; height: 50mm; display: block; }
  .kode { font-family: 'Courier New', monospace; font-size: 19pt; font-weight: bold; letter-spacing: 1px; margin-top: 1mm; }
  .jenis { font-size: 15pt; font-weight: bold; margin-top: 2mm; text-align: center; }
  table { width: 100%; border-collapse: collapse; font-size: 10pt; margin-top: 3mm; }
  td { padding: 1.2mm 0; border-bottom: 1px solid #bbb; vertical-align: top; }
  td.k { width: 36%; color: #333; }
  td.v { font-weight: bold; text-align: right; }
  .besar td.v { font-size: 13pt; }
  .tanda { margin-top: 2mm; display: flex; flex-wrap: wrap; justify-content: center; gap: 2mm; }
  .tanda span { border: 2px solid #000; padding: 0.8mm 2.5mm; font-size: 9pt; font-weight: bold; }
</style></head>
<body><div class="label">
  <div class="atas"><div class="merek">LelangIkan.id</div><div class="tpi">${aman(namaTpi || 'TPI')}</div></div>
  <div class="qr">${svgQR(lot.kode_lot, 189)}</div>
  <div class="kode">${aman(lot.kode_lot)}</div>
  <div class="jenis">${aman(lot.nama_jenis)} - Grade ${aman(lot.kode_grade)}</div>
  <table>
    <tr class="besar"><td class="k">Berat aktual</td><td class="v">${aman(formatKg(lot.berat_aktual))}</td></tr>
    <tr class="besar"><td class="k">Harga buka</td><td class="v">${aman(harga)}</td></tr>
    <tr><td class="k">Kapal</td><td class="v">${aman(lot.nama_kapal)}</td></tr>
    <tr><td class="k">Kedatangan</td><td class="v">${aman(lot.nomor_kedatangan)}</td></tr>
    <tr><td class="k">Ditimbang</td><td class="v">${aman(waktuPendek(lot.waktu_timbang))}</td></tr>
    <tr><td class="k">Petugas</td><td class="v">${aman(lot.petugas_timbang)}</td></tr>
  </table>
  ${tanda.length ? `<div class="tanda">${tanda.map((t) => `<span>${t}</span>`).join('')}</div>` : ''}
</div></body></html>`;
}

/** Buka dialog cetak Android (pilih printer atau "Simpan sebagai PDF") */
export async function cetakLabel(lot, namaTpi) {
  await Print.printAsync({ html: htmlLabel(lot, namaTpi), width: LEBAR, height: TINGGI });
}

/**
 * Buat berkas PDF label lalu buka menu Bagikan (WhatsApp, Drive, dll.)
 * Di Expo Go (Android), berkas hasil expo-print tidak boleh dibaca expo-sharing
 * ("Not allowed to read file under given URL"). Karena itu PDF diminta dalam bentuk
 * base64 lalu ditulis ulang ke folder cache milik aplikasi sebelum dibagikan.
 */
export async function bagikanLabelPdf(lot, namaTpi) {
  if (!(await Sharing.isAvailableAsync())) {
    throw new Error('Fitur bagikan tidak tersedia di perangkat ini.');
  }
  const { base64 } = await Print.printToFileAsync({
    html: htmlLabel(lot, namaTpi),
    width: LEBAR,
    height: TINGGI,
    base64: true,
  });
  if (!base64) throw new Error('Data PDF kosong.');

  const berkas = new File(Paths.cache, `Label-${lot.kode_lot}.pdf`);
  if (berkas.exists) berkas.delete();
  berkas.create();
  berkas.write(base64, { encoding: 'base64' });

  await Sharing.shareAsync(berkas.uri, {
    mimeType: 'application/pdf',
    dialogTitle: `Label ${lot.kode_lot}`,
    UTI: 'com.adobe.pdf',
  });
}

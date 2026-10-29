import QRCode from 'qrcode';
import Svg, { Path, Rect } from 'react-native-svg';

/**
 * Kode QR LelangIkan.id (tanpa internet, dibuat langsung di HP).
 * - <KodeQR nilai="KDT-00001" ukuran={200} />  : tampil di layar
 * - svgQR("PLR-261026-0001", 180)              : teks SVG untuk label cetak (HTML)
 */
const TEPI = 4; // zona kosong di sekeliling QR (standar: 4 modul)

function jalurQR(nilai) {
  const qr = QRCode.create(String(nilai), { errorCorrectionLevel: 'M' });
  const n = qr.modules.size;
  let d = '';
  for (let baris = 0; baris < n; baris++) {
    for (let kolom = 0; kolom < n; kolom++) {
      if (qr.modules.get(baris, kolom)) {
        d += `M${kolom + TEPI} ${baris + TEPI}h1v1h-1z`;
      }
    }
  }
  return { d, sisi: n + TEPI * 2 };
}

export default function KodeQR({ nilai, ukuran = 200 }) {
  const { d, sisi } = jalurQR(nilai);
  return (
    <Svg width={ukuran} height={ukuran} viewBox={`0 0 ${sisi} ${sisi}`} accessibilityLabel={`Kode QR ${nilai}`}>
      <Rect x="0" y="0" width={sisi} height={sisi} fill="#FFFFFF" />
      <Path d={d} fill="#000000" />
    </Svg>
  );
}

export function svgQR(nilai, ukuran = 180) {
  const { d, sisi } = jalurQR(nilai);
  return (
    `<svg xmlns="http://www.w3.org/2000/svg" width="${ukuran}" height="${ukuran}" ` +
    `viewBox="0 0 ${sisi} ${sisi}" shape-rendering="crispEdges">` +
    `<rect width="${sisi}" height="${sisi}" fill="#fff"/><path d="${d}" fill="#000"/></svg>`
  );
}

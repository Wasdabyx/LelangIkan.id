import Svg, { Circle, Path, Rect } from 'react-native-svg';

/**
 * Kumpulan ikon gambar (SVG) LelangIkan.id - pengganti emoji.
 * Pemakaian: <Ikon nama="cari" ukuran={22} warna="#0B4F6C" />
 */
const GARIS = {
  cari: 'M11 4a7 7 0 1 1 0 14a7 7 0 1 1 0-14z M16.2 16.2L21 21',
  tambah: 'M12 5v14 M5 12h14',
  hapus: 'M4 7h16 M9 7V4h6v3 M6 7l1 13h10l1-13 M10 11v6 M14 11v6',
  centang: 'M5 12.5l4.5 4.5L19 7.5',
  silang: 'M6 6l12 12 M18 6L6 18',
  kembali: 'M15 5l-7 7l7 7',
  bawah: 'M6 9l6 6l6-6',
  kanan: 'M9 6l6 6l-6 6',
  muatUlang: 'M20 12a8 8 0 1 1-2.4-5.7 M20 4v5h-5',
  jam: 'M12 3a9 9 0 1 1 0 18a9 9 0 1 1 0-18z M12 7v5l3.5 2',
  kapal: 'M3 15h18l-2.6 5H5.6z M6 15v-4h9l3 4 M9 11V7h4v4 M11 7V4',
  pengguna: 'M12 4a4 4 0 1 1 0 8a4 4 0 1 1 0-8z M4 21c0-4 3.6-6 8-6s8 2 8 6',
  keluar: 'M10 4H5v16h5 M15 8l4 4l-4 4 M19 12H9',
  peringatan: 'M12 3.5L22 20.5H2z M12 10v5 M12 17.6v.1',
  awan: 'M7 18h10.5a4 4 0 0 0 .4-8A6 6 0 0 0 6.3 11.6A3.3 3.3 0 0 0 7 18z',
  telepon: 'M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1z',
  dompet: 'M3 7h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z M3 7l12-3v3 M16 13.5h2',
  ikan: 'M2.5 12c2.5-4 6-6 9.5-6c3 0 5.5 1.5 7.2 3.5L22 7v10l-2.8-2.5C17.5 16.5 15 18 12 18c-3.5 0-7-2-9.5-6z M7.5 11.2v.1',
  qr: 'M4 4h6v6H4z M14 4h6v6h-6z M4 14h6v6H4z M14 14h2v2h-2z M18 14h2 M14 18h2v2 M18 18h2v2h-2z',
  timbangan: 'M4 21h16 M6.5 21l2-11h7l2 11 M12 10V6 M5 6h14 M12 3v3 M10.5 15h3',
  cetak: 'M7 9V3h10v6 M7 17H4v-6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v6h-3 M7 14h10v7H7z',
  bagikan: 'M12 3v12 M7 8l5-5l5 5 M5 13v7h14v-7',
  senter: 'M8 3h8v4l-2 3v11h-4V10L8 7z M12 13v2',
  kamera: 'M4 8h3l2-3h6l2 3h3v12H4z M12 10.5a3.5 3.5 0 1 1 0 7a3.5 3.5 0 1 1 0-7z',
  pensil: 'M4 20h4L19 9l-4-4L4 16z M13.5 6.5l4 4',
};

export default function Ikon({ nama, ukuran = 24, warna = '#102A43', tebal = 2 }) {
  if (nama === 'kalender') {
    return (
      <Svg width={ukuran} height={ukuran} viewBox="0 0 24 24" fill="none" stroke={warna} strokeWidth={tebal} strokeLinecap="round" strokeLinejoin="round">
        <Rect x="3" y="5" width="18" height="16" rx="2" />
        <Path d="M3 10h18 M8 3v4 M16 3v4" />
      </Svg>
    );
  }
  return (
    <Svg width={ukuran} height={ukuran} viewBox="0 0 24 24" fill="none" stroke={warna} strokeWidth={tebal} strokeLinecap="round" strokeLinejoin="round">
      <Path d={GARIS[nama] || GARIS.peringatan} />
    </Svg>
  );
}

/** Logo LelangIkan.id: ikan di atas gelombang dalam lingkaran putih */
export function Logo({ ukuran = 72 }) {
  return (
    <Svg width={ukuran} height={ukuran} viewBox="0 0 64 64">
      <Circle cx="32" cy="32" r="32" fill="#FFFFFF" />
      <Path
        d="M9 29c5-8 13-12 22-12c7 0 12 3 16 7l7-6v22l-7-6c-4 4-9 7-16 7c-9 0-17-4-22-12z"
        fill="#0B4F6C"
      />
      <Circle cx="19" cy="27" r="2.6" fill="#FFFFFF" />
      <Path d="M26 22c2 4 2 10 0 14" stroke="#FFFFFF" strokeWidth="2" fill="none" strokeLinecap="round" />
      <Path
        d="M11 47c3.5 0 3.5 3 7 3s3.5-3 7-3s3.5 3 7 3s3.5-3 7-3s3.5 3 7 3s3.5-3 7-3"
        stroke="#2E8BC0" strokeWidth="3" fill="none" strokeLinecap="round"
      />
    </Svg>
  );
}

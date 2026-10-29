// Fungsi bantu format tanggal, jam, dan berat (Bahasa Indonesia)
const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const BULAN = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

const dua = (n) => String(n).padStart(2, '0');

// Date -> "2026-10-26" (format yang diminta server)
export const keTanggalServer = (d) => `${d.getFullYear()}-${dua(d.getMonth() + 1)}-${dua(d.getDate())}`;

// Date -> "05:47"
export const keJam = (d) => `${dua(d.getHours())}:${dua(d.getMinutes())}`;

// Date -> "Senin, 26 Oktober 2026"
export const tanggalPanjang = (d) =>
  `${HARI[d.getDay()]}, ${d.getDate()} ${BULAN[d.getMonth()]} ${d.getFullYear()}`;

// "2026-10-26" -> "26 Okt 2026"
export function tanggalPendek(teks) {
  if (!teks) return '-';
  const [t, b, h] = teks.split('-').map(Number);
  return `${h} ${BULAN[b - 1].slice(0, 3)} ${t}`;
}

// Teks isian angka -> Number (menerima koma atau titik sebagai desimal)
export function keAngka(teks) {
  if (teks === null || teks === undefined) return NaN;
  const bersih = String(teks).trim().replace(',', '.');
  if (bersih === '' || !/^\d+(\.\d+)?$/.test(bersih)) return NaN;
  return Number(bersih);
}

// 1250.5 -> "1.250,5 kg"
export function formatKg(n) {
  const angka = Number(n) || 0;
  const [bulat, pecahan] = (Math.round(angka * 100) / 100).toString().split('.');
  const ribuan = bulat.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  return `${ribuan}${pecahan ? ',' + pecahan : ''} kg`;
}

// 28000 -> "Rp28.000"
export function formatRupiah(n) {
  if (n === null || n === undefined || n === '' || isNaN(Number(n))) return '-';
  const bulat = Math.round(Number(n)).toString();
  return 'Rp' + bulat.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

// 30.5 -> "30,50" (jumlah desimal tetap, untuk angka timbangan)
export function formatAngka(n, desimal = 2) {
  const [bulat, pecahan] = (Number(n) || 0).toFixed(desimal).split('.');
  const ribuan = bulat.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  return pecahan ? `${ribuan},${pecahan}` : ribuan;
}

// "2026-10-26 08:03" -> "26 Okt 2026, 08:03"
export function waktuPendek(teks) {
  if (!teks) return '-';
  const [tgl, jam] = teks.split(' ');
  return `${tanggalPendek(tgl)}${jam ? ', ' + jam.slice(0, 5) : ''}`;
}

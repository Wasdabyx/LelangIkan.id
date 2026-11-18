/**
 * Validasi isian dengan Regular Expression (RegEx) - Tugas Week 9
 * Pola di file ini SAMA dengan backend app/Support/PolaInput.php,
 * sehingga isian yang lolos di HP juga lolos di server.
 *
 * Cara pakai RegEx di JavaScript:
 *   POLA.nik.test('3202125508900002')   -> true / false   (cek cocok)
 *   teks.replace(/\D/g, '')             -> buang semua karakter bukan angka
 */

// ------------------------------------------------------------------
// 1. Daftar pola
// ------------------------------------------------------------------
export const POLA = {
  // Nama orang: diawali huruf, lalu huruf/spasi/titik/koma/petik/strip, total 3-100 karakter
  namaOrang: /^[A-Za-z][A-Za-z .',-]{2,99}$/,

  // NIK 16 digit:
  //   (1[1-9]|[2-9]\d)                          kode provinsi 11-99
  //   \d{4}                                     kode kabupaten/kota + kecamatan
  //   (0[1-9]|[12]\d|3[01]|4[1-9]|[56]\d|7[01]) tanggal lahir 01-31 (perempuan +40 = 41-71)
  //   (0[1-9]|1[0-2])                           bulan lahir 01-12
  //   \d{2}                                     tahun lahir (2 digit)
  //   (?!0000)\d{4}                             nomor urut, tidak boleh 0000
  nik: /^(1[1-9]|[2-9]\d)\d{4}(0[1-9]|[12]\d|3[01]|4[1-9]|[56]\d|7[01])(0[1-9]|1[0-2])\d{2}(?!0000)\d{4}$/,

  // Nomor HP Indonesia: 08 / 628 / +628 lalu 8-11 digit
  noHp: /^(\+62|62|0)8[1-9]\d{7,10}$/,

  // Surel: bagian-lokal@domain.tld
  surel: /^[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}$/,

  // Nama pengguna saat daftar: huruf kecil di awal, lalu huruf kecil/angka/titik/garis bawah, 4-30 karakter
  namaPengguna: /^[a-z][a-z0-9._]{3,29}$/,

  // Nama pengguna saat masuk: pola sama, tetapi flag "i" = tidak membedakan huruf besar/kecil
  namaPenggunaMasuk: /^[a-z][a-z0-9._]{3,29}$/i,

  // Kata sandi kuat (lookahead "(?=...)" = syarat yang wajib ada di mana saja):
  //   huruf kecil, huruf besar, angka, simbol; tanpa spasi; 8-64 karakter
  sandiKuat: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9\s])\S{8,64}$/,

  // Nomor surat izin berlayar: SPB atau SIB / kode pelabuhan / TTBBHH / urut, contoh SPB/PLR/261014/01
  nomorSurat: /^(SPB|SIB)\/[A-Z]{2,5}\/\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\/\d{2}$/,

  // Tugas 2: kode aktivasi / OTP tepat 6 digit angka
  kodeOtp: /^\d{6}$/,
};

// Syarat kata sandi satu per satu (untuk daftar centang di layar Registrasi)
export const SYARAT_SANDI = [
  { pola: /^.{8,64}$/, teks: '8 sampai 64 karakter' },
  { pola: /[A-Z]/, teks: 'Ada huruf besar (A-Z)' },
  { pola: /[a-z]/, teks: 'Ada huruf kecil (a-z)' },
  { pola: /\d/, teks: 'Ada angka (0-9)' },
  { pola: /[^A-Za-z0-9\s]/, teks: 'Ada simbol, contoh ! @ # ?' },
  { pola: /^\S+$/, teks: 'Tanpa spasi' },
];

// ------------------------------------------------------------------
// 2. Perapi isian (memakai replace + RegEx)
// ------------------------------------------------------------------
export const rapikanSpasi = (t) => t.trim().replace(/\s+/g, ' ');   // "  Siti   N " -> "Siti N"
export const hanyaAngka = (t) => t.replace(/\D/g, '');               // "3202-12" -> "320212"
export const filterHp = (t) => t.replace(/[^\d+]/g, '');             // sisakan angka dan "+"
export const rapikanHp = (t) => t.replace(/[\s-]/g, '').replace(/^(\+62|62)(?=8)/, '0'); // "+62812..." -> "0812..."
export const filterNomorSurat = (t) => t.toUpperCase().replace(/[^A-Z0-9/]/g, '');      // huruf besar, angka, "/"

// ------------------------------------------------------------------
// 3. Pemeriksa per kolom: mengembalikan pesan galat, atau null bila valid
// ------------------------------------------------------------------
export function periksaNamaOrang(nilai, label = 'Nama lengkap') {
  const v = rapikanSpasi(nilai);
  if (!v) return `${label} wajib diisi.`;
  if (/\d/.test(v)) return `${label} tidak boleh mengandung angka.`;
  if (!POLA.namaOrang.test(v)) {
    return `${label} hanya boleh huruf, spasi, titik, koma, petik, dan strip (minimal 3 karakter).`;
  }
  return null;
}

export function periksaNik(nilai) {
  const v = nilai.trim();
  if (!v) return 'NIK wajib diisi.';
  if (!/^\d+$/.test(v)) return 'NIK hanya boleh berisi angka.';
  if (!/^\d{16}$/.test(v)) return `NIK harus tepat 16 digit (sekarang ${v.length} digit).`;
  if (!POLA.nik.test(v)) return 'NIK tidak valid: periksa kode wilayah, tanggal lahir, dan nomor urut.';
  return null;
}

export function periksaNoHp(nilai) {
  const v = nilai.replace(/[\s-]/g, '');
  if (!v) return 'Nomor HP wajib diisi.';
  if (!/^(\+62|62|0)8/.test(v)) return 'Nomor HP harus diawali 08, 628, atau +628.';
  if (!POLA.noHp.test(v)) return 'Panjang nomor HP harus 10-13 digit (contoh: 081234567890).';
  return null;
}

export function periksaSurel(nilai) {
  const v = nilai.trim();
  if (!v) return 'Surel wajib diisi.';
  if (!POLA.surel.test(v)) return 'Format surel tidak valid (contoh: nama@contoh.com).';
  return null;
}

export function periksaNamaPengguna(nilai) {
  const v = nilai.trim();
  if (!v) return 'Nama pengguna wajib diisi.';
  if (!/^[a-z]/.test(v)) return 'Nama pengguna harus diawali huruf kecil.';
  if (/[^a-z0-9._]/.test(v)) return 'Hanya boleh huruf kecil, angka, titik (.), dan garis bawah (_).';
  if (!POLA.namaPengguna.test(v)) return 'Nama pengguna harus 4-30 karakter.';
  return null;
}

export function periksaSandi(nilai) {
  if (!nilai) return 'Kata sandi wajib diisi.';
  if (!POLA.sandiKuat.test(nilai)) return 'Kata sandi belum memenuhi semua syarat di bawah.';
  return null;
}

export function periksaKonfirmasi(sandi, konfirmasi) {
  if (!konfirmasi) return 'Konfirmasi kata sandi wajib diisi.';
  if (sandi !== konfirmasi) return 'Konfirmasi kata sandi tidak sama.';
  return null;
}

export function periksaNomorSurat(nilai) {
  const v = nilai.trim();
  if (!v) return 'Nomor surat wajib diisi.';
  if (!POLA.nomorSurat.test(v)) return 'Format nomor surat harus SPB/KODE/TTBBHH/NN, contoh: SPB/PLR/261014/01.';
  return null;
}

// ------------------------------------------------------------------
// 4. Validasi satu formulir sekaligus
// ------------------------------------------------------------------
export function validasiRegistrasi(d) {
  const g = {
    nama_lengkap: periksaNamaOrang(d.nama_lengkap),
    nik: periksaNik(d.nik),
    no_hp: periksaNoHp(d.no_hp),
    surel: periksaSurel(d.surel),
    nama_pengguna: periksaNamaPengguna(d.nama_pengguna),
    kata_sandi: periksaSandi(d.kata_sandi),
    konfirmasi_sandi: periksaKonfirmasi(d.kata_sandi, d.konfirmasi_sandi),
  };
  // buang kolom yang valid (null) agar hanya galat yang tersisa
  Object.keys(g).forEach((k) => g[k] === null && delete g[k]);
  return g;
}

export function validasiMasuk(namaPengguna, kataSandi) {
  const g = {};
  const n = namaPengguna.trim();
  if (!n) g.nama_pengguna = 'Nama pengguna wajib diisi.';
  else if (!POLA.namaPenggunaMasuk.test(n)) {
    g.nama_pengguna = 'Nama pengguna 4-30 karakter: huruf, angka, titik, atau garis bawah, diawali huruf.';
  }
  if (!kataSandi) g.kata_sandi = 'Kata sandi wajib diisi.';
  return g;
}

// Tugas 2: kode aktivasi / OTP
export function periksaKodeOtp(nilai) {
  if (!nilai) return 'Kode wajib diisi.';
  if (!POLA.kodeOtp.test(nilai)) return 'Kode harus 6 digit angka.';
  return null;
}

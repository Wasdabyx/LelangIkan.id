import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

/**
 * Antrean lokal untuk data kedatangan yang gagal terkirim (SKPL NFR-09).
 *
 * NFR-09: "Data kedatangan yang gagal terkirim tersimpan lokal dan tidak hilang ketika
 * aplikasi ditutup atau perangkat dinyalakan ulang." Cara verifikasi: uji mode pesawat
 * dan pembunuhan proses aplikasi.
 *
 * Cara kerja:
 * - Saat POST /api/kedatangan gagal KARENA JARINGAN (status 0: timeout/terputus), muatan
 *   formulir disimpan ke penyimpanan perangkat (SecureStore di HP, localStorage di web)
 *   dengan kunci idempotensi agar pengiriman ulang tidak membuat data ganda
 *   (server menolak nomor surat kembar lewat 422, jadi kiriman ulang aman).
 * - Antrean dibaca kembali saat aplikasi dibuka (termasuk setelah proses dibunuh), lalu
 *   dikirim ulang otomatis begitu jaringan tersedia.
 * - Galat 422 (isian salah) TIDAK diantrekan: itu bukan kegagalan jaringan dan harus
 *   diperbaiki pengguna.
 *
 * Isi antrean: { id, waktu, jenis: 'kedatangan'|'draf', jalur, metode, muatan, percobaan }
 */

const KUNCI = 'sipeli_antrean';
const BATAS_PERCOBAAN = 5;

async function baca() {
  try {
    const mentah = Platform.OS === 'web'
      ? localStorage.getItem(KUNCI)
      : await SecureStore.getItemAsync(KUNCI);
    const isi = mentah ? JSON.parse(mentah) : [];
    return Array.isArray(isi) ? isi : [];
  } catch (e) {
    return [];
  }
}

async function tulis(daftar) {
  const nilai = JSON.stringify(daftar);
  if (Platform.OS === 'web') localStorage.setItem(KUNCI, nilai);
  else await SecureStore.setItemAsync(KUNCI, nilai);
  return daftar;
}

/** Tambahkan satu kiriman yang gagal karena jaringan. Mengembalikan panjang antrean. */
export async function antrekan(item) {
  const daftar = await baca();
  // Muatan formulir tidak disimpan berulang bila pengguna menekan Simpan berkali-kali.
  const sidik = JSON.stringify(item.muatan);
  const ada = daftar.find((x) => JSON.stringify(x.muatan) === sidik);
  if (ada) return daftar.length;
  daftar.push({
    id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    waktu: new Date().toISOString(),
    percobaan: 0,
    ...item,
  });
  await tulis(daftar);
  return daftar.length;
}

/** Daftar antrean yang masih menunggu (urut waktu). */
export async function daftarAntrean() {
  return baca();
}

export async function jumlahAntrean() {
  return (await baca()).length;
}

export async function hapusAntrean(id) {
  const daftar = await baca();
  await tulis(daftar.filter((x) => x.id !== id));
}

export async function kosongkanAntrean() {
  await tulis([]);
}

/**
 * Kirim ulang seluruh antrean memakai fungsi pengirim yang diberikan.
 * `kirim(item)` harus mengembalikan Promise; lempar `GalatApi` untuk kegagalan.
 * Bila gagal karena jaringan, item tetap di antrean dengan `percobaan` bertambah;
 * setelah BATAS_PERCOBAAN item DIBIARKAN (tidak dibuang) agar data tidak hilang, hanya
 * diberi tanda `perluPerhatian` supaya petugas dapat melaporkannya.
 */
export async function kirimUlangAntrean(kirim) {
  const daftar = await baca();
  if (!daftar.length) return { terkirim: 0, sisa: 0, hasil: [] };

  const sisa = [];
  const hasil = [];
  for (const item of daftar) {
    try {
      const r = await kirim(item);
      hasil.push({ id: item.id, ok: true, hasil: r });
    } catch (e) {
      const jaringan = !e || e.status === 0;
      if (jaringan) {
        sisa.push({
          ...item,
          percobaan: (item.percobaan || 0) + 1,
          perluPerhatian: (item.percobaan || 0) + 1 >= BATAS_PERCOBAAN,
        });
        hasil.push({ id: item.id, ok: false, galat: e?.message || 'jaringan' });
        // Jaringan masih putus: hentikan putaran, jangan buang waktu untuk sisanya.
        break;
      }
      // Galat bukan jaringan (mis. 422 karena data sudah ada di server) -> kiriman ini
      // selesai ditangani; simpan jejaknya di hasil agar bisa ditampilkan.
      hasil.push({ id: item.id, ok: false, galat: e?.message || 'ditolak server', status: e?.status });
    }
  }
  await tulis(sisa);
  return { terkirim: hasil.filter((h) => h.ok).length, sisa: sisa.length, hasil };
}

/** Penanda agar pemanggil dapat membedakan kegagalan jaringan dari penolakan server. */
export function gagalJaringan(e) {
  return !e || e.status === 0;
}

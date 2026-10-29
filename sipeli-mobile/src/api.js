import { Platform } from 'react-native';
import { API_URL, BATAS_WAKTU } from './config';

// Token disimpan di memori selama aplikasi berjalan
let tokenAktif = null;
export function aturToken(token) {
  tokenAktif = token;
}

// Dipanggil bila server membalas 401 (token kedaluwarsa / dicabut)
let saatSesiHabis = null;
export function aturSaatSesiHabis(fungsi) {
  saatSesiHabis = fungsi;
}

// Kesalahan dari API: berisi pesan, kode status, dan galat per kolom
export class GalatApi extends Error {
  constructor(pesan, status = 0, galatKolom = {}) {
    super(pesan);
    this.status = status;
    this.galatKolom = galatKolom;
  }
}

async function permintaan(metode, jalur, data) {
  const pengendali = new AbortController();
  const batas = setTimeout(() => pengendali.abort(), BATAS_WAKTU);

  let respons;
  try {
    respons = await fetch(API_URL + jalur, {
      method: metode,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(tokenAktif ? { Authorization: `Bearer ${tokenAktif}` } : {}),
      },
      body: data ? JSON.stringify(data) : undefined,
      signal: pengendali.signal,
    });
  } catch (e) {
    throw new GalatApi(
      'Tidak dapat terhubung ke server. Periksa Wi-Fi dan pastikan server LelangIkan.id menyala.'
    );
  } finally {
    clearTimeout(batas);
  }

  let isi = null;
  try {
    isi = await respons.json();
  } catch (e) {
    // respons bukan JSON
  }

  if (!respons.ok) {
    if (respons.status === 401 && tokenAktif && jalur !== '/masuk' && saatSesiHabis) {
      saatSesiHabis();
    }
    // Laravel mengirim galat validasi dalam bentuk { errors: { kolom: ["pesan"] } }
    const galatKolom = {};
    if (isi && isi.errors) {
      for (const [kolom, daftar] of Object.entries(isi.errors)) {
        galatKolom[kolom] = Array.isArray(daftar) ? daftar[0] : String(daftar);
      }
    }
    const pesan =
      respons.status === 401
        ? 'Sesi berakhir. Silakan masuk kembali.'
        : Object.values(galatKolom)[0] ||
          (isi && (isi.pesan || isi.message)) ||
          `Terjadi kesalahan pada server (kode ${respons.status}).`;
    throw new GalatApi(pesan, respons.status, galatKolom);
  }

  return isi;
}

// ---- Titik akhir API (SKPL Tabel IV.6) ----
export const masuk = (namaPengguna, kataSandi) =>
  permintaan('POST', '/masuk', {
    nama_pengguna: namaPengguna,
    kata_sandi: kataSandi,
    perangkat: `expo-${Platform.OS}`,
  });

export const ambilSaya = () => permintaan('GET', '/saya');
export const keluar = () => permintaan('POST', '/keluar');

// Dipakai tampilan berikutnya (kedatangan, penimbangan)
export const api = {
  get: (jalur) => permintaan('GET', jalur),
  post: (jalur, data) => permintaan('POST', jalur, data),
  put: (jalur, data) => permintaan('PUT', jalur, data),
};

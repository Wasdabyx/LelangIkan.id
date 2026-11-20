import { Platform } from 'react-native';
import { API_URL, BATAS_WAKTU } from './config';

// Token disimpan di memori selama aplikasi berjalan
let tokenAktif = null;
export function aturToken(token) {
  tokenAktif = token;
}
export function ambilToken() {
  return tokenAktif;
}

// Dipanggil bila server membalas 401 (token kedaluwarsa / dicabut)
let saatSesiHabis = null;
export function aturSaatSesiHabis(fungsi) {
  saatSesiHabis = fungsi;
}

// Kesalahan dari API: berisi pesan, kode status, dan galat per kolom
export class GalatApi extends Error {
  constructor(pesan, status = 0, galatKolom = {}, data = null) {
    super(pesan);
    this.status = status;
    this.galatKolom = galatKolom;
    this.data = data; // isi lengkap respons (contoh: kode BELUM_AKTIVASI, tunggu_detik)
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
      respons.status === 429 && !galatKolom.nama_pengguna && !(isi && isi.tunggu_detik)
        ? 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.'
        : respons.status === 401
        ? 'Sesi berakhir. Silakan masuk kembali.'
        : Object.values(galatKolom)[0] ||
          (isi && (isi.pesan || isi.message)) ||
          `Terjadi kesalahan pada server (kode ${respons.status}).`;
    throw new GalatApi(pesan, respons.status, galatKolom, isi);
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

// Tugas Week 9: Registrasi Pembeli (tanpa token)
export const daftar = (data) => permintaan('POST', '/registrasi', data);

// Tugas 2: aktivasi akun dengan kode 6 digit dari surel (tanpa token)
export const aktivasi = (namaPengguna, kataSandi, kodeOtp) =>
  permintaan('POST', '/aktivasi', {
    nama_pengguna: namaPengguna,
    kata_sandi: kataSandi,
    kode_otp: kodeOtp,
    perangkat: `expo-${Platform.OS}`,
  });
export const kirimUlangAktivasi = (namaPengguna, kataSandi) =>
  permintaan('POST', '/aktivasi/kirim-ulang', { nama_pengguna: namaPengguna, kata_sandi: kataSandi });

// Tugas 2: Pembeli - lot siap lelang dan pemesanan dengan OTP
export const pembeli = {
  daftarLot: () => permintaan('GET', '/pembeli/lot'),
  pemesananSaya: () => permintaan('GET', '/pembeli/pemesanan'),
  mintaOtp: (idLot) => permintaan('POST', '/pembeli/pemesanan/otp', { id_lot: idLot }),
  konfirmasi: (idLot, kodeOtp) => permintaan('POST', '/pembeli/pemesanan', { id_lot: idLot, kode_otp: kodeOtp }),
};

export const ambilSaya = () => permintaan('GET', '/saya');
export const keluar = () => permintaan('POST', '/keluar');

// Kunci idempotensi penawaran (UUID v4) - FR-20
export const buatKunci = () =>
  'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
  });

// UC-03 dan UC-05: Operator Lelang
export const operator = {
  antrean: () => permintaan('GET', '/operator/antrean'),
  daftarSesi: () => permintaan('GET', '/operator/sesi'),
  buka: (idLot, durasiDetik, kenaikanMinimum, turun = {}) =>
    permintaan('POST', '/operator/sesi', { id_lot: idLot, durasi_detik: durasiDetik, kenaikan_minimum: kenaikanMinimum, ...turun }),
  status: (idSesi) => permintaan('GET', `/operator/sesi/${idSesi}`),
  tutup: (idSesi, alasan) => permintaan('POST', `/operator/sesi/${idSesi}/tutup`, { alasan }),
};

// UC-04: Pembeli menawar (status diminta tiap 1 detik) + tagihan
export const lelang = {
  daftar: () => permintaan('GET', '/pembeli/lelang'),
  status: (idSesi) => permintaan('GET', `/pembeli/lelang/${idSesi}`),
  tawar: (idSesi, hargaPerKg, kunci) =>
    permintaan('POST', `/pembeli/lelang/${idSesi}/tawar`, { harga_per_kg: hargaPerKg, kunci_idempotensi: kunci }),
  ambil: (idSesi, kunci) => permintaan('POST', `/pembeli/lelang/${idSesi}/ambil`, { kunci_idempotensi: kunci }),
  tagihan: () => permintaan('GET', '/pembeli/tagihan'),
  detailTagihan: (id) => permintaan('GET', `/pembeli/tagihan/${id}`),
};

// UC-06: Kasir TPI
export const kasir = {
  daftar: () => permintaan('GET', '/kasir/tagihan'),
  cari: (kode) => permintaan('GET', `/kasir/tagihan/${encodeURIComponent(kode)}`),
  bayar: (id, metode, nominal, nomorReferensi) =>
    permintaan('POST', `/kasir/tagihan/${id}/bayar`, { metode, nominal, nomor_referensi: nomorReferensi || null }),
  verifikasiNota: (isiQr) => permintaan('GET', `/nota/verifikasi?kode=${encodeURIComponent(isiQr)}`),
  cariPembeliSaldo: (cari) => permintaan('GET', `/kasir/saldo/pembeli?cari=${encodeURIComponent(cari)}`),
  saldoPembeli: (id) => permintaan('GET', `/kasir/saldo/pembeli/${id}`),
  mutasiSaldo: (id, data) => permintaan('POST', `/kasir/saldo/pembeli/${id}/mutasi`, data),
};

export const pimpinan = {
  penyesuaianSaldo: () => permintaan('GET', '/pimpinan/saldo/penyesuaian'),
  putuskanPenyesuaianSaldo: (id, keputusan, catatan) =>
    permintaan('POST', `/pimpinan/saldo/penyesuaian/${id}/keputusan`, { keputusan, catatan }),
};

// Foto lot oleh Petugas Timbang (multipart). foto = { uri, nama, tipe, blob? } dari components/AmbilFoto.js
export async function unggahFotoLot(idLot, foto) {
  const isian = new FormData();
  if (foto.blob) {
    isian.append('foto', foto.blob, foto.nama); // Chrome
  } else {
    isian.append('foto', { uri: foto.uri, name: foto.nama, type: foto.tipe }); // HP (Expo Go)
  }
  const pengendali = new AbortController();
  const batas = setTimeout(() => pengendali.abort(), BATAS_WAKTU * 4);
  let respons;
  try {
    respons = await fetch(`${API_URL}/lot/${idLot}/foto`, {
      method: 'POST',
      headers: { Accept: 'application/json', ...(tokenAktif ? { Authorization: `Bearer ${tokenAktif}` } : {}) },
      body: isian,
      signal: pengendali.signal,
    });
  } catch (e) {
    throw new GalatApi('Foto gagal dikirim. Periksa Wi-Fi dan pastikan server LelangIkan.id menyala.');
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
    if (respons.status === 401 && saatSesiHabis) saatSesiHabis();
    const galatFoto = isi && isi.errors && isi.errors.foto ? isi.errors.foto[0] : null;
    throw new GalatApi(
      respons.status === 413
        ? 'Ukuran foto terlalu besar untuk server.'
        : galatFoto || (isi && (isi.pesan || isi.message)) || `Foto gagal disimpan (kode ${respons.status}).`,
      respons.status
    );
  }
  return isi;
}

// Dipakai tampilan berikutnya (kedatangan, penimbangan)
export const api = {
  get: (jalur) => permintaan('GET', jalur),
  post: (jalur, data) => permintaan('POST', jalur, data),
  put: (jalur, data) => permintaan('PUT', jalur, data),
};

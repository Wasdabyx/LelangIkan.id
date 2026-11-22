import { useEffect, useRef, useState } from 'react';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { ambilToken } from './api';
import { API_URL } from './config';

/**
 * NFR-10: kanal real-time dengan **status yang dilaporkan ke layar**.
 *
 * SKPL NFR-10: "Bila kanal real-time terputus, aplikasi berpindah ke mode pengambilan
 * berkala setiap 2 detik sampai kanal pulih."
 *
 * Hook ini mengembalikan `tersambung` (true/false). Selama `tersambung === false`,
 * layar memakai `usePolling(muat, 2000)` sebagai pengganti; begitu kanal pulih,
 * polling berhenti dan data kembali lewat peristiwa. Dengan begitu tidak ada
 * pengambilan berkala yang berjalan bersamaan dengan kanal yang sehat, dan saat kanal
 * putus penggantinya tepat 2 detik seperti bunyi kebutuhan.
 *
 * Status diambil dari koneksi Pusher/Reverb (`state_change`):
 * connected -> tersambung, connecting/unavailable/failed/disconnected -> tidak.
 */

const apiUrl = (typeof API_URL === 'string' && API_URL)
  || process.env.EXPO_PUBLIC_API_URL
  || 'http://localhost:8000/api';
const appKey = process.env.EXPO_PUBLIC_REVERB_APP_KEY || 'lelangikan-local-key';
const scheme = process.env.EXPO_PUBLIC_REVERB_SCHEME || 'http';
const port = Number(process.env.EXPO_PUBLIC_REVERB_PORT || (scheme === 'https' ? 443 : 8080));
const hostApi = apiUrl.replace(/^https?:\/\//, '').split('/')[0].split(':')[0];
const host = process.env.EXPO_PUBLIC_REVERB_HOST || hostApi;

/** Jeda pengganti saat kanal terputus (NFR-10: 2 detik). */
export const JEDA_PENGGANTI = 2000;

/**
 * Batas umur data sebelum jam sisa waktu mulai berdetak sendiri (ms).
 *
 * SKPL NFR-10 hanya mewajibkan pembaruan tiap 2 detik saat kanal putus, sehingga
 * angka `sisa_detik` bisa tertinggal sampai 2 detik. Supaya pengguna melihat waktu
 * bergerak, layar menghitung mundur dari angka terakhir + waktu terima, lalu
 * mencocokkannya lagi setiap kali data baru datang atau peristiwa kanal diterima.
 */
export const AMAN_DIAM = 1000;

/** Jeda sebelum sambungan real-time dibangun ulang bila kanal tidak pulih sendiri. */
export const JEDA_SAMBUNG_ULANG = 5000;

function buatEcho(token) {
  return new Echo({
    broadcaster: 'reverb',
    Pusher,
    key: appKey,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: scheme === 'https' ? ['wss'] : ['ws', 'wss'],
    channelAuthorization: {
      endpoint: `${apiUrl.replace(/\/$/, '')}/broadcasting/auth`,
      transport: 'ajax',
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    },
  });
}

/**
 * Berlangganan satu kanal privat untuk **satu atau beberapa peristiwa**; laporkan status
 * sambungan. Mengembalikan `tersambung`. Bila token/nama belum siap, status false
 * (polling aktif).
 *
 * `peristiwa` boleh berupa satu nama (`'PENAWARAN_BARU'`) atau larik
 * (`['PENAWARAN_BARU', 'SESI_DIPERPANJANG', 'SESI_DITUTUP']`). `saatPeristiwa` menerima
 * dua argumen: `(data, namaPeristiwa)` sehingga pemanggil dapat membedakan peristiwa.
 */
export function useKanalRealtime(namaKanal, peristiwa, saatPeristiwa) {
  const callback = useRef(saatPeristiwa);
  callback.current = saatPeristiwa;
  const [tersambung, setTersambung] = useState(false);
  const daftarPeristiwa = (Array.isArray(peristiwa) ? peristiwa : [peristiwa]).join(',');
  // Percobaan ke-berapa; dinaikkan oleh pewaktu sambung ulang di bawah.
  const [percobaan, setPercobaan] = useState(0);

  useEffect(() => {
    const token = ambilToken();
    if (!namaKanal || !token) {
      setTersambung(false);
      return undefined;
    }
    const echo = buatEcho(token);

    // NFR-10: bila kanal tidak pulih sendiri (Pusher menyerah setelah beberapa
    // percobaan), bangun ulang sambungan setelah JEDA_SAMBUNG_ULANG supaya polling
    // pengganti tidak berjalan selamanya.
    let pewaktuUlang = null;
    const pantau = (perubahan) => {
      const keadaan = perubahan?.current;
      if (keadaan === 'connected') {
        setTersambung(true);
        if (pewaktuUlang) {
          clearTimeout(pewaktuUlang);
          pewaktuUlang = null;
        }
      } else if (keadaan) {
        setTersambung(false);
        if ((keadaan === 'failed' || keadaan === 'unavailable') && !pewaktuUlang) {
          pewaktuUlang = setTimeout(() => {
            pewaktuUlang = null;
            setPercobaan((n) => n + 1);   // memicu efek ini dijalankan ulang
          }, JEDA_SAMBUNG_ULANG);
        }
      }
    };
    if (echo.connector?.pusher?.connection) {
      echo.connector.pusher.connection.bind('state_change', pantau);
      setTersambung(echo.connector.pusher.connection.state === 'connected');
    }

    // Terima satu nama peristiwa atau larik; didengar pada kanal yang sama.
    const daftar = daftarPeristiwa.split(',').filter(Boolean);
    const saluran = echo.private(namaKanal);
    for (const nama of daftar) {
      saluran.listen(`.${nama}`, (data) => callback.current?.(data, nama));
    }

    return () => {
      if (pewaktuUlang) clearTimeout(pewaktuUlang);
      try {
        if (echo.connector?.pusher?.connection) {
          echo.connector.pusher.connection.unbind('state_change', pantau);
        }
      } catch (e) {
        // koneksi sudah ditutup; tidak perlu tindakan
      }
      echo.leave(namaKanal);
      echo.disconnect();
      setTersambung(false);
    };
    // `daftarPeristiwa` berupa teks supaya efek tidak dijalankan ulang hanya karena
    // pemanggil membuat larik baru pada setiap render.
  }, [namaKanal, daftarPeristiwa, percobaan]);

  return tersambung;
}

/** Nama peristiwa yang didengar Ruang Lelang pada kanal TPI. */
export const PERISTIWA_RUANG_LELANG = ['PENAWARAN_BARU', 'SESI_DIPERPANJANG', 'HASIL_LELANG', 'SESI_DITUTUP'];

/** Nama peristiwa yang didengar layar daftar lelang pembeli pada kanal TPI. */
export const PERISTIWA_DAFTAR_LELANG = ['SESI_DIMULAI', 'SESI_DITUTUP', 'HASIL_LELANG'];

/**
 * Kanal TPI untuk layar daftar lelang pembeli: sesi dimulai, ditutup, dan hasil lelang
 * (pemenang) — supaya daftar dan status tagihan berubah tanpa menunggu polling.
 * Mengembalikan `tersambung`; `saatPeristiwa(data, namaPeristiwa)`.
 */
export function useKanalTpiDaftar(idTpi, saatPeristiwa) {
  return useKanalRealtime(idTpi ? `tpi.${idTpi}` : null, PERISTIWA_DAFTAR_LELANG, saatPeristiwa);
}

/** Kompatibilitas: notifikasi sesi baru pada kanal TPI (dipakai layar daftar lelang). */
export function useSesiDimulai(idTpi, saatDimulai) {
  return useKanalRealtime(idTpi ? `tpi.${idTpi}` : null, 'SESI_DIMULAI', saatDimulai);
}

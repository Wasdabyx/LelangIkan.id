import { useEffect, useRef } from 'react';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { ambilToken } from './api';
import { API_URL } from './config';

const apiUrl = (typeof API_URL === 'string' && API_URL)
  || process.env.EXPO_PUBLIC_API_URL
  || 'http://localhost:8000/api';
const appKey = process.env.EXPO_PUBLIC_REVERB_APP_KEY || 'lelangikan-local-key';
const scheme = process.env.EXPO_PUBLIC_REVERB_SCHEME || 'http';
const port = Number(process.env.EXPO_PUBLIC_REVERB_PORT || (scheme === 'https' ? 443 : 8080));
const hostApi = apiUrl.replace(/^https?:\/\//, '').split('/')[0].split(':')[0];
const host = process.env.EXPO_PUBLIC_REVERB_HOST || hostApi;

/** Subscribe ke notifikasi sesi baru pada kanal private TPI; polling tetap menjadi fallback. */
export function useSesiDimulai(idTpi, saatDimulai) {
  const callback = useRef(saatDimulai);
  callback.current = saatDimulai;

  useEffect(() => {
    const token = ambilToken();
    if (!idTpi || !token) return undefined;

    const echo = new Echo({
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

    const namaKanal = `tpi.${idTpi}`;
    echo.private(namaKanal).listen('.SESI_DIMULAI', (data) => callback.current?.(data));

    return () => {
      echo.leave(namaKanal);
      echo.disconnect();
    };
  }, [idTpi]);
}

import { useEffect, useRef } from 'react';

/**
 * Real-time dengan polling: panggil fungsi setiap `jeda` milidetik selama `aktif`.
 * Permintaan berikutnya baru dikirim setelah yang sebelumnya selesai (tidak menumpuk
 * walaupun Wi-Fi lambat). Fungsi selalu memakai versi terbaru (tanpa perlu dependensi).
 */
export function usePolling(fungsi, jeda = 1000, aktif = true) {
  const ref = useRef(fungsi);
  ref.current = fungsi;

  useEffect(() => {
    if (!aktif) return undefined;
    let berhenti = false;
    let pewaktu = null;

    async function putaran() {
      try {
        await ref.current();
      } catch (e) {
        // galat ditangani di dalam fungsi; polling tetap berjalan
      }
      if (!berhenti) pewaktu = setTimeout(putaran, jeda);
    }
    putaran();

    return () => {
      berhenti = true;
      clearTimeout(pewaktu);
    };
  }, [jeda, aktif]);
}

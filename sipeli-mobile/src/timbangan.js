/**
 * FR-07 — adaptor timbangan digital + input manual cadangan.
 *
 * Satu antarmuka (`useTimbangan`) dengan tiga sumber, dipilih otomatis menurut
 * kemampuan perangkat:
 *
 *   1. `BLE`        — timbangan Bluetooth Low Energy sungguhan (ponsel/tablet).
 *                     Memakai layanan Nordic UART (NUS) atau layanan khusus timbangan
 *                     yang menyiarkan berat sebagai teks ASCII, format yang paling banyak
 *                     dipakai timbangan meja ber-BLE.
 *   2. `SERIAL_WEB` — Web Serial API (Chrome/Edge di laptop) untuk timbangan kabel USB-RS232.
 *                     Dipakai petugas timbang yang bekerja di meja dengan laptop.
 *   3. `SIMULASI`   — dipakai hanya bila tidak ada perangkat keras terbaca, mis. saat
 *                     demonstrasi atau ponsel tanpa izin Bluetooth. Selalu diberi label
 *                     "Simulasi" pada layar agar tidak pernah dikira bacaan sungguhan.
 *
 * Perilaku "stabil" (DPPL UC-02): berat dianggap stabil bila tidak berubah selama
 * LAMA_STABIL milidetik. Aturan ini sama untuk ketiga sumber, sehingga alur kerja
 * petugas tidak berubah saat berpindah sumber.
 */
import { useEffect, useRef, useState } from 'react';
import { Platform } from 'react-native';

export const LAMA_STABIL = 1500; // ms tanpa perubahan -> berat stabil
const KETELITIAN = 0.05;         // kg, sesuai ketelitian timbangan TPI
const JEDA_SIMULASI = 200;       // ms antara dua pembacaan tiruan

/** Nama singkat sumber untuk ditampilkan di layar. */
export const LABEL_SUMBER = {
  BLE: 'Timbangan Bluetooth',
  SERIAL_WEB: 'Timbangan kabel (USB/Serial)',
  SIMULASI: 'Timbangan Simulasi',
};

/**
 * Pisahkan angka berat dari satu pesan timbangan.
 * Contoh pesan yang diterima: "ST,GS,+ 30.55kg", "  30.55 kg\r\n", "W=30.55".
 * Angka pertama yang masuk akal diambil sebagai berat dalam kg.
 * Mengembalikan null bila pesan tidak berisi angka (mis. "ST,GS" saja).
 */
export function bacaBeratDariPesan(pesan) {
  if (typeof pesan !== 'string') return null;
  // Buang satuan dan pemisah ribuan bergaya "1,234.5" -> "1234.5"
  const bersih = pesan.replace(/(\d),(\d{3})(?=[.,\d])/g, '$1$2');
  const cocok = bersih.match(/-?\d+(?:[.,]\d+)?/g);
  if (!cocok) return null;
  for (const teks of cocok) {
    const nilai = Number(teks.replace(',', '.'));
    if (Number.isFinite(nilai) && Math.abs(nilai) <= 99999) {
      return Math.round(nilai / KETELITIAN) * KETELITIAN;
    }
  }
  return null;
}

/**
 * Pelacak kestabilan: menerima deretan berat, memberi tahu kapan dianggap stabil.
 * Dipakai bersama oleh ketiga sumber agar aturannya benar-benar satu.
 */
function usePelacakStabil() {
  const [berat, setBerat] = useState(0);
  const [stabil, setStabil] = useState(false);
  const [adaBeban, setAdaBeban] = useState(false);
  const terakhir = useRef({ nilai: 0, berubah: Date.now() });

  function terima(nilai) {
    const bulat = Math.round(Math.max(0, Number(nilai) || 0) / KETELITIAN) * KETELITIAN;
    if (bulat !== terakhir.current.nilai) {
      terakhir.current = { nilai: bulat, berubah: Date.now() };
    }
    setBerat(bulat);
    setAdaBeban(bulat > 0.05);
    setStabil(bulat > 0.05 && Date.now() - terakhir.current.berubah >= LAMA_STABIL);
  }

  function kosongkan() {
    terakhir.current = { nilai: 0, berubah: Date.now() };
    setBerat(0);
    setStabil(false);
    setAdaBeban(false);
  }

  return { berat, stabil, adaBeban, terima, kosongkan };
}

/**
 * Timbangan tiruan — hanya dipakai bila tidak ada perangkat keras.
 *
 * `aktif` wajib diperhatikan: bila petugas sudah terhubung ke timbangan BLE atau kabel,
 * simulasi HARUS berhenti mengirim angka. Tanpa penjagaan ini interval simulasi terus
 * menulis 0 kg setiap 200 ms dan menimpa bacaan timbangan sungguhan, sehingga layar
 * tampak "macet di 0 kg" walaupun perangkatnya sehat.
 */
function useTimbanganSimulasi(pelacak, aktif = true) {
  const target = useRef(0);
  const langkah = useRef(0);
  const aktifRef = useRef(aktif);
  aktifRef.current = aktif;

  useEffect(() => {
    const id = setInterval(() => {
      if (!aktifRef.current) return;   // sumber lain yang sedang dipakai
      let nilai = 0;
      if (target.current > 0) {
        langkah.current += 1;
        const n = langkah.current;
        if (n === 1) nilai = target.current * 0.35;
        else if (n === 2) nilai = target.current * 0.8;
        else if (n <= 7) nilai = target.current + (Math.random() - 0.5) * target.current * 0.06 * ((8 - n) / 5);
        else nilai = target.current;
      }
      pelacak.terima(nilai);
    }, JEDA_SIMULASI);
    return () => clearInterval(id);
  }, []);

  return {
    letakkan(kg) {
      target.current = Math.round(kg / KETELITIAN) * KETELITIAN;
      langkah.current = 0;
    },
    angkat() {
      target.current = 0;
      pelacak.kosongkan();
    },
  };
}

/**
 * Timbangan BLE sungguhan. Perangkat harus sudah dipasangkan lewat layar
 * "Hubungkan timbangan" (memakai react-native-ble-plx).
 *
 * Pemakaian: `useTimbanganBle(perangkat)` menerima objek { id, nama } hasil pemilihan
 * perangkat, lalu berlangganan notifikasi dan meneruskan tiap bacaan ke pelacak stabil.
 */
function useTimbanganBle(perangkat, pelacak, saatGalat) {
  useEffect(() => {
    if (!perangkat?.id) return undefined;

    let aktif = true;
    let ble;
    let langganan = null;

    (async () => {
      try {
        const { BleManager } = require('react-native-ble-plx');
        ble = new BleManager();
        const terhubung = await ble.connectToDevice(perangkat.id, { timeout: 10000 });
        await terhubung.discoverAllServicesAndCharacteristics();

        const layanan = await terhubung.services();
        const karakteristik = (await Promise.all(layanan.map((l) => l.characteristics())))
          .flat()
          .filter((k) => k.isNotifiable || k.isIndicatable);

        if (karakteristik.length === 0) {
          saatGalat?.('Timbangan terhubung tetapi tidak mengirim data. Periksa mode keluaran timbangan.');
          return;
        }
        const target = karakteristik[0];
        langganan = terhubung.monitorCharacteristicForService(
          target.serviceUUID,
          target.uuid,
          (galat, nilai) => {
            if (galat) {
              if (aktif) saatGalat?.('Bacaan timbangan terputus: ' + galat.message);
              return;
            }
            if (!nilai?.value) return;
            const teks = Buffer.from(nilai.value, 'base64').toString('ascii');
            const berat = bacaBeratDariPesan(teks);
            if (berat !== null && aktif) pelacak.terima(berat);
          },
        );
      } catch (e) {
        if (aktif) {
          saatGalat?.('Tidak dapat terhubung ke timbangan: ' + (e?.message || 'kesalahan tidak dikenal')
            + '. Anda dapat memakai input manual dengan alasan.');
        }
      }
    })();

    return () => {
      aktif = false;
      try { langganan?.remove(); } catch (e) { /* sudah lepas */ }
      try { ble?.destroy(); } catch (e) { /* sudah mati */ }
    };
  }, [perangkat?.id]);

  return {
    letakkan() {}, // timbangan sungguhan digerakkan oleh beban fisik, bukan tombol
    angkat() { pelacak.kosongkan(); },
  };
}

/** Timbangan kabel lewat Web Serial (Chrome/Edge di laptop). */
function useTimbanganSerial(port, pelacak, saatGalat) {
  useEffect(() => {
    if (!port) return undefined;
    let aktif = true;
    let pembaca = null;

    (async () => {
      try {
        await port.open({ baudRate: 9600, dataBits: 8, stopBits: 1, parity: 'none' });
        const teks = port.readable.getReader();
        pembaca = teks;
        let sisa = '';
        while (aktif) {
          const { value, done } = await teks.read();
          if (done) break;
          sisa += new TextDecoder().decode(value);
          const baris = sisa.split(/\r?\n/);
          sisa = baris.pop() ?? '';
          for (const b of baris) {
            const berat = bacaBeratDariPesan(b);
            if (berat !== null && aktif) pelacak.terima(berat);
          }
        }
      } catch (e) {
        if (aktif) saatGalat?.('Port serial terputus: ' + (e?.message || 'kesalahan tidak dikenal'));
      }
    })();

    return () => {
      aktif = false;
      try { pembaca?.cancel(); } catch (e) { /* sudah lepas */ }
      try { port.close(); } catch (e) { /* sudah tertutup */ }
    };
  }, [port]);

  return {
    letakkan() {},
    angkat() { pelacak.kosongkan(); },
  };
}

/**
 * Hook utama yang dipakai layar penimbangan.
 * `perangkat` = { id, nama } bila petugas sudah memilih timbangan BLE.
 * `port`      = objek SerialPort bila memakai timbangan kabel.
 */
export function useTimbangan({ perangkat = null, port = null, paksaSimulasi = false } = {}) {
  const pelacak = usePelacakStabil();
  const [galat, setGalat] = useState(null);

  // Tentukan sumber lebih dulu supaya simulasi bisa dimatikan saat perangkat keras dipakai.
  let sumber = 'SIMULASI';
  if (!paksaSimulasi) {
    if (perangkat?.id) sumber = 'BLE';
    else if (port) sumber = 'SERIAL_WEB';
  }

  const simulasi = useTimbanganSimulasi(pelacak, sumber === 'SIMULASI');
  const ble = useTimbanganBle(perangkat, pelacak, setGalat);
  const serial = useTimbanganSerial(port, pelacak, setGalat);

  const kendali = sumber === 'BLE' ? ble : sumber === 'SERIAL_WEB' ? serial : simulasi;

  return {
    ...pelacak,
    sumber,
    label: LABEL_SUMBER[sumber],
    galat,
    bersihkanGalat: () => setGalat(null),
    letakkan: kendali.letakkan,
    angkat: kendali.angkat,
    /** Perangkat ini punya Web Serial? (Chrome/Edge desktop) */
    bisaSerial: Platform.OS === 'web' && typeof navigator !== 'undefined' && 'serial' in navigator,
  };
}

/** Ringkasan dukungan perangkat, dipakai layar untuk memilih sumber. */
export function dukunganTimbangan() {
  const webSerial = Platform.OS === 'web' && typeof navigator !== 'undefined' && 'serial' in navigator;
  return {
    ble: Platform.OS !== 'web',   // BLE hanya di ponsel/tablet (native)
    serialWeb: webSerial,
    simulasi: true,
  };
}

# Audit Isi Dokumen v1.1 terhadap Kode Terpasang

**Tanggal:** 21 November 2026 · **Dokumen:** `SKPL-LelangIkan-v1.1.pdf`, `DPPL-LelangIkan-v1.1.pdf`
**Kode acuan:** `LelangIkan.id` commit `8c537e8` (backend Laravel, `php artisan route:list`)

Cara audit: setiap angka dan nama pada dokumen dicocokkan ke sumber yang dapat diperiksa —
`route:list` (85 rute API, 128 pasangan metode–URI), migrasi basis data, enum PHP, dan kelas layanan.
Hasil di bawah hanya memuat butir yang **perlu keputusan**; sisanya cocok.

## 1. Ringkasan

| Butir | Dokumen | Kode | Status |
|---|---|---|---|
| Titik akhir Tabel IV.6 | 30 baris | semuanya ada padanannya | **cocok** |
| Rute API keseluruhan | 30 baris (dokumen menyebut "ringkasan") | 85 rute | wajar (dokumen memang ringkasan) |
| Tabel basis data | 27 tabel domain | 27 tabel domain (33 termasuk 6 tabel infrastruktur) | **cocok** |
| Alias `GET /api/laporan/harga` & `/retribusi` | ada di Tabel IV.6 | tidak ada rute khusus; ditangani `/api/laporan/{jenis}` dengan `jenis=harga\|retribusi` | **perlu klarifikasi teks** |
| Enumerasi diagram kelas | 20 (narasi & judul) | 20 kotak «enumeration» pada `class-diagram-lelangikan.drawio` | **cocok** |
| Jumlah entitas ERD | 27 | 27 (setelah `pencairan_nelayan` ditambahkan) | **cocok** |

## 2. Empat butir yang perlu keputusan

### C-1. Alias rute laporan di Tabel IV.6 (kecil, teks)

Tabel IV.6 memuat tiga baris laporan:

| Baris | Isi | Padanan di kode |
|---|---|---|
| `GET /api/laporan/harga` | Laporan harga dan volume per jenis ikan | rute `{jenis}` → `jenis=harga` |
| `GET /api/laporan/retribusi` | Rekap retribusi harian | rute `{jenis}` → `jenis=retribusi` |
| `GET /api/laporan/{jenis}` | Laporan harga, retribusi, tren, atau kinerja | ada |

Karena `{jenis}` adalah parameter rute, permintaan ke `/api/laporan/harga` **tetap dilayani** oleh rute yang sama
(diuji: 19/19 pada `uji_rute_resmi.py`, termasuk laporan). Jadi tidak ada rute yang hilang; yang kurang hanya
penjelasan bahwa tiga baris itu menunjuk rute yang sama.

**Usulan (pilih salah satu):**
- **(a) tanpa menyentuh dokumen** — biarkan, karena perilakunya benar; atau
- **(b) satu kalimat tambahan** pada catatan Tabel IV.6: "`/api/laporan/harga` dan `/api/laporan/retribusi`
  dilayani oleh `/api/laporan/{jenis}` dengan nilai `jenis` yang sama."

### C-2. FR-05, FR-06, NFR-09 — antrean sinkronisasi luring (sedang)

Kebutuhan menyebut penyimpanan lokal + pengiriman ulang idempotent + indikator status sinkronisasi.
Kode: tidak ada di `sipeli-mobile/src`; `@react-native-community/netinfo` terpasang tetapi tidak dipakai.

**Usulan:** nyatakan tegas di SKPL sebagai **di luar cakupan versi 1.1** (satu kalimat pada Tabel III.1 atau
ruang lingkup), atau bangun antrean lokal. Tanpa keputusan, statusnya tetap "belum ada".

### C-3. FR-07 timbangan digital (sedang)

Antarmuka pembacaan ada dan dipakai (`FormTimbang.js`: `useTimbanganSimulasi`, stabil 1,5 detik, ketelitian
0,05 kg), input manual cadangan lengkap, tetapi sumber berat adalah simulasi di dalam aplikasi — bukan adaptor
serial/Bluetooth. SKPL Tabel IV.4 sudah menyebut "disimulasikan".

**Usulan:** tambahkan satu kalimat pada deskripsi FR-07 bahwa pada versi 1.1 pembacaan berasal dari
**timbangan simulasi**, dan adaptor perangkat nyata masuk rencana versi berikutnya.

### C-4. Pelanggan peristiwa real-time di klien (sedang)

Server menyiarkan 9 peristiwa Tabel IV.7 (uji_ws 9/9 diterima). Klien mobile hanya berlangganan `SESI_DIMULAI`
pada kanal `tpi.{id}`; `OUTBID`, `LOT_DIPANTAU_DIBUKA`, dan `SETTLEMENT_DIBAYAR` belum dilanggan aplikasi
(kanal `pembeli.{id}` dan `nelayan.{id}` sudah tersedia di server).

**Usulan:** ini **pekerjaan kode**, bukan dokumen: perluas `src/realtime.js` agar berlangganan tiga kanal itu.
Dokumen tidak perlu diubah karena yang dijanjikan adalah peristiwa tersiar, dan itu sudah terpenuhi.

## 3. Yang sudah saya periksa dan cocok (tidak perlu tindakan)

- Tabel IV.6: 30 titik akhir, semuanya punya padanan rute nyata (termasuk `/api/master/{entitas}` dengan
  sembilan nilai entitas yang persis sama dengan `whereIn` pada `routes/api.php`).
- 27 tabel: `pengguna, peran, tpi, konfigurasi, pemilik_kapal, kapal, kedatangan_kapal, jenis_ikan, grade_mutu,
  tangkapan, lot_lelang, foto_lot, sesi_lelang, penawaran, daftar_pantauan, kode_otp, transaksi_penjualan,
  pembayaran, saldo_pembeli, mutasi_saldo, tarif_retribusi, pembayaran_nelayan, pencairan_nelayan, notifikasi,
  audit_log, tautan_akses_nelayan, kartu_nelayan`.
- Enumerasi: `StatusLot` (termasuk `DIPERPANJANG`, `SELESAI`), `StatusBayarNelayan` (4 nilai),
  `JenisMutasi`, `TujuanOtp`, `KanalPenawaran` — semua ada di `app/Enums/`.
- Jumlah 20 kelas enumerasi pada diagram = 20 pada narasi dan judul (diperbaiki pada putaran ini).
- FR-21 (daya beli = saldo + limit kredit) benar-benar diterapkan pada tawaran naik **dan** Ambil pada lelang
  turun (`LelangService::ajukanTawaran`, `ambilHargaTurun`).

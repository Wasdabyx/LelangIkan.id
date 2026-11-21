# Antarmuka Web LELANGIKAN.ID

Antarmuka web adalah bagian dari sistem yang sama dengan aplikasi mobile. Pembagian platform
mengikuti **SKPL v1.1 Bagian I.B**:

| Pengguna | Platform | Alasan |
|---|---|---|
| Petugas pendataan, petugas timbang, pembeli | aplikasi mobile | bekerja di lantai TPI, membawa perangkat |
| **Operator lelang, kasir, pimpinan/dinas, administrator sistem** | **antarmuka web (folder ini)** | bekerja di meja, memakai layar lebar |
| Nelayan/pemilik kapal | portal baca-saja (tautan akses) | tanpa pemasangan aplikasi |
| Ruang pelelangan | papan lelang layar besar | baca-saja, tanpa masuk |

## Cara menjalankan

```bash
cd sipeli-backend
composer install
npm install
cp .env.example .env && php artisan key:generate      # sekali saja
php artisan migrate:fresh --seed                      # 126 lot contoh, 6 kedatangan
npm run build                                         # aset Tailwind untuk tampilan web
php artisan serve --host=0.0.0.0 --port=8000
```

Lalu buka `http://127.0.0.1:8000`.

Akun contoh hasil seeder (kata sandi semuanya `sipeli12345`):

| Nama pengguna | Peran | Halaman pertama setelah masuk |
|---|---|---|
| `operator` | Operator Lelang | `/dasbor/operator/antrean` |
| `kasir` | Kasir TPI | `/dasbor/kasir/tagihan` |
| `pimpinan` | Pimpinan | `/dasbor/pimpinan/ringkasan` |
| `admin` | Administrator Sistem | `/dasbor/admin/master` |

Papan lelang dapat dibuka tanpa masuk: `http://127.0.0.1:8000/papan-lelang`.

## Peta halaman

**Operator Lelang (UC-03, UC-05)** — `/dasbor/operator`
- `antrean` — lot `MENUNGGU_LELANG` beserta usulan kenaikan minimum (ALG-05); formulir buka sesi (naik/turun, durasi, kenaikan minimum).
- `sesi` — sesi aktif dan sesi hari ini.
- `sesi/{id}` — layar pemantauan: harga berjalan, sisa waktu, penawar tertinggi, sepuluh penawaran terakhir, penutupan manual beralasan. Diperbarui tiap 1 detik dari `sesi/{id}/keadaan`; setelah sesi ditutup, kotak hasil menampilkan pemenang, harga akhir, total tagihan, dan batas bayar.

**Kasir TPI (UC-06, UC-07, UC-08)** — `/dasbor/kasir`
- `tagihan` — tagihan terbuka + pelunasan (TUNAI/TRANSFER/QRIS) dan daftar lunas hari ini.
- `settlement` — kedatangan dengan lot lunas; hitung bruto, retribusi, netto, potongan lain; bukti settlement; pencairan TUNAI/TRANSFER/KARTU_NELAYAN.
- `saldo` — pencarian pembeli (min. 3 karakter), daya beli efektif, sepuluh mutasi terakhir, setor/penarikan/penyesuaian.

**Pimpinan/dinas (UC-10, UC-12)** — `/dasbor/pimpinan`
- `ringkasan` — sesi aktif/ditutup, lot terjual/tidak, nilai penjualan, pembayaran diterima, tagihan belum lunas.
- `laporan` — harga & volume per jenis/grade, rekap retribusi harian, tren harga, kinerja lelang; unduh CSV.
- `penyesuaian` — persetujuan/penolakan penyesuaian saldo yang diajukan kasir.

**Administrator Sistem (UC-11)** — `/dasbor/admin`
- `master` — jenis ikan, grade mutu, tarif retribusi, kapal, pemilik kapal, TPI (tambah baris).
- `pengguna` — daftar akun, aktivasi/penonaktifan (FR-51), pembuatan akun baru dengan peran dan TPI.
- `konfigurasi` — parameter `ambang_selisih_berat`, `durasi_lelang_bawaan`, dan kunci lain (FR-50).
- `audit` — jejak audit dengan saringan entitas/aksi/tanggal dan ekspor CSV (FR-52..FR-55).

## Rancangan teknis

- **Satu sumber aturan.** Controller web memanggil Service yang sama dengan rute `/api`
  (`LelangService`, `PembayaranService`, `SettlementService`, `DepositService`,
  `LaporanService`, `RingkasanPimpinanService`, `PapanLelangService`, `AuditService`).
  Validasi nominal, transisi status, dan pencatatan audit tidak diduplikasi.
- **Autentikasi.** Masuk web memakai `AutentikasiService::masuk()` (termasuk batas 5 percobaan
  dan blokir 60 detik) lalu menyimpan pengguna pada guard sesi Laravel. Token API yang
  diterbitkan sementara langsung dicabut karena peramban tidak memakainya.
- **Hak akses.** Middleware `web.peran` (`App\Http\Middleware\WebCekPeran`) membalas halaman 403,
  bukan JSON, sehingga pengguna web mendapat penjelasan. Peran yang tidak dilayani web
  (pendataan, timbang, pembeli, nelayan) ditolak saat masuk dengan pesan yang mengarahkan
  ke aplikasi mobile.
- **Pembaruan berkala.** Pemantauan sesi dan papan lelang menarik JSON tiap 1 detik
  (`fetch` + `credentials: same-origin`). Kanal WebSocket (Reverb) tetap berjalan untuk
  aplikasi mobile; papan memakai polling sebagai jalur cadangan (NFR-10).
- **Tampilan.** Blade + Tailwind (Vite). `npm run build` wajib dijalankan sekali agar
  `public/build/manifest.json` tersedia; tanpa itu halaman tampil tanpa gaya.

## Uji

| Perkakas | Cakupan | Hasil |
|---|---|---|
| `php artisan test` | 25 uji PHPUnit (termasuk `AntarmukaWebTest`) | 25 lulus / 86 asersi |
| `dokumen/skrip/uji_web.py` | 42 pemeriksaan antarmuka web: pembatasan peran, 12 halaman dasbor, 4 matriks silang peran, papan lelang, pembayaran, settlement + pencairan, setor & penyesuaian saldo, persetujuan pimpinan, CSV, buka/pantau/tutup sesi, jejak audit, keluar | 42/42 |
| `uji_rute_resmi.py` | 19 rute resmi SKPL Tabel IV.6 | 19/19 |
| `e2e2.py`, `uji_fitur.py`, `uji_settlement.py` | alur API lengkap | 81/81, 43/43, 29/29 |

`uji_web.py` menjalankan `migrate:fresh --seed` lebih dulu agar dapat diulang dari keadaan bersih.

## Tangkapan layar

`dokumen/tangkapan-web/` memuat tangkapan Chromium 1440x900 dari: halaman masuk, antrean lelang,
pemantauan sesi (sesi tertutup), tagihan pembeli, settlement nelayan, ringkasan harian,
laporan & analitik, pengguna & peran, jejak audit, dan papan lelang.

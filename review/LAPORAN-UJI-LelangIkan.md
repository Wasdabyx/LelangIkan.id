# Laporan Uji & Kesesuaian LelangIkan.id dengan SKPL/DPPL

**Repo:** https://github.com/Wasdabyx/LelangIkan.id — uji kode pada commit `5e11aa8` ("Tambah dashboard pimpinan dan seeder pengembangan", 21 Nov 2026)
**Dokumen acuan:** SKPL/DPPL **v1.1** (commit `1d0c6fe`)
**Tanggal uji:** 21 November 2026 · **Penguji:** Arena.ai Agent Mode (lingkungan terpisah dari laptop penulis)
**Status putaran ini:** bagian 5 sudah diperbarui setelah revisi dokumen v1.1 dan pembaruan matriks; angka pada bagian 1 adalah hasil putaran pertama (sebelum audit log, settlement, portal nelayan, data master, papan lelang, dan lelang jarak jauh masuk ke kode) — lihat §5 untuk kondisi terkini.

---

## 1. Jawaban singkat

**Sudah saya uji sendiri di lingkungan bersih dan aplikasinya jalan.** Backend Laravel terpasang, migrasi + seeder sukses, dan seluruh alur inti yang sudah dibangun (UC-01 s.d. UC-07 + lelang turun) berfungsi.

**Tapi progres belum sesuai dokumen apa adanya.** Dari **67 kebutuhan fungsional** di SKPL:

| Status | Jumlah | Keterangan |
|---|---|---|
| ✅ Selesai & teruji jalan | **33** | Inti operasional TPI |
| 🟡 Sebagian | **7** | Ada, tetapi belum memenuhi seluruh kalimat kebutuhan |
| ❌ Belum ada (endpoint/tabel/UI tidak ditemukan) | **26** | UC-08, UC-09 (papan), UC-10, UC-11, UC-12, UC-13 + beberapa FR kecil |
| ⚠️ Dihapus tanpa pembaruan dokumen | **1** | FR-21 (validasi daya beli) |

**Cara membacanya ada dua, dan keduanya harus dipilih:**

- Kalau **dokumen dianggap acuan final** (versi 1.1, 13 use case), maka progres sekarang ≈ **50–55%**: inti POS + lelang sudah benar dan teruji, tetapi audit, portal nelayan, laporan/analitik, data master, dan papan lelang belum ada.
- Kalau **tahap implementasi dianggap lebih dulu** (wajar untuk tubes IMPAL), maka **dokumen yang harus ditambahkan/dikoreksi**, bukan aplikasi — minimal 16 butir (bagian 5). Yang paling mendesak: **audit log (FR-52..55, NFR-14)**, **papan lelang & WebSocket**, **kartu nelayan**, **portal nelayan**, **antrean luring FR-05/FR-06**, dan **FR-21 yang dihapus**.

Ringkasnya: **bukan sekadar "tambah dokumen"** — ada 5–6 hal yang justru lebih aman dibangun cepat (audit log paling murah dan paling sering dinilai), sisanya dokumentasi yang disinkronkan.

---

## 2. Yang saya lakukan untuk menguji

| Langkah | Perintah / alat | Hasil |
|---|---|---|
| Clone repo | `git clone` | 28 MB, `sipeli-backend` (Laravel 11) + `sipeli-mobile` (Expo SDK 57) |
| Baca dokumen | ekstraksi teks SKPL (55 hlm) & DPPL (88 hlm) | 13 use case, 67 FR, 15 NFR, matriks kerunutan TC-01..TC-67 |
| Siapkan lingkungan | PHP 8.4, Composer 2.11, MariaDB 11.8 | di luar repo |
| Backend | `composer install` → `php artisan key:generate` → `migrate --seed` → `serve` | **sukses tanpa ubah satu baris kode**; 16 migrasi; 126 lot riwayat 30 hari + 6 kedatangan hari ini + skenario lelang (1 aktif, 2 terjual, 1 tidak terjual) |
| Tes otomatis repo | `php artisan test` | **6 lulus** (4 unit `LelangService`, 2 contoh) |
| Uji API | skrip Python (e2e) menembak **46 endpoint** sesuai peran & skenario SKPL | 63 pemeriksaan fungsional + 10 pemeriksaan ketersediaan endpoint; lihat bagian 3–5 |
| Uji mobile | `npx expo start --web`, login otomatis 6 peran + tangkapan layar | semua peran tampil benar (folder `tangkapan-layar/`) |
| Uji khusus | lelang turun, anti-sniping, lelang tidak terjual, saldo, nota, idempotensi | lihat bagian 4 |

Skrip uji lengkap ada di `skrip-uji/`, matriks per-FR di `Matriks-Kesesuaian-SKPL-DPPL-LelangIkan.xlsx`.

> Catatan: sandi pengguna di-hash (bcrypt), dan kode OTP juga di-hash sehingga tidak bisa dibaca dari log. Untuk menguji alur pemesanan saya menerbitkan OTP lewat `artisan tinker` — bukan celah aplikasi, tapi konsekuensi wajar OTP yang disimpan aman.

---

## 3. Hasil uji (yang jalan)

### 3.1 Backend: 46 rute API, semua peran

| Area | Yang terbukti jalan |
|---|---|
| Autentikasi | Login 7 akun demo, token Sanctum, `GET /saya`, logout, 401 JSON tanpa redirect. **Rate limit 5 gagal/menit → 429** (NFR-06 per akun) |
| UC-01 Kedatangan | Simpan TERDAFTAR & DRAF, cari kapal + pemilik (min. 3 huruf), referensi 8 jenis ikan & 3 grade, validasi berat 0 / nama kapten / nomor surat, nomor surat duplikat ditolak, detail & daftar hari ini |
| UC-02 Penimbangan | Data penimbangan + **usulan harga ALG-06** (median 7 hari × faktor grade, bulat 500), buat lot (kode `PLR-261121-0007`), selisih berat & `perlu_verifikasi`, manual tanpa alasan ditolak, lihat lot via QR, catat cetak, **foto lot** (JPG/PNG/WEBP ≤5 MB, dapat diakses tanpa token) |
| UC-03 Sesi | Antrean lot, buka sesi NAIK 30–600 detik, **tolak sesi ganda**, pemilih mode NAIK/TURUN, pantau sesi |
| UC-04 Penawaran | Tolak tawaran < harga pembukaan, tolak kenaikan < minimum, **idempotensi kunci (duplikat=true)**, tolak penawar tertinggi menawar lagi, 3 tawaran 2 pembeli konsisten (lock baris), riwayat & penyamaran "Peserta 1/2" |
| UC-05 Penutupan | Tutup manual, **tutup otomatis saat waktu habis**, pemenang, transaksi `TRX-000005` (berat × harga), lot tidak terjual bila tanpa tawaran |
| **Lelang turun (FR-61/62)** | Harga turun otomatis 50.000 → 48.000 → 46.000 tiap 2 detik, berhenti di batas bawah, **Ambil → pemenang**, tagihan 46.000/kg, lot TERJUAL, Ambil kedua ditolak |
| **Anti-sniping (FR-23)** | Tawar di detik ke-9 dari 30 → sisa kembali 30 detik, perpanjangan 0→1 (maks 5) |
| UC-06 Pembayaran | Tolak nominal 0 & melebihi total, pembayaran sebagian, tunai/saldo, **verifikasi nota QR**: isi asli `LELANGIKAN\|TRX-000001\|A0AB1DA844CA` → `asli:true`; diubah 3 karakter → `asli:false`; bayar dari saldo; jadwal pengambilan |
| UC-07 Saldo | Saldo/limit/mutasi, SETOR menambah saldo, tarik melebihi saldo ditolak, **penyesuaian wajib persetujuan pimpinan** → disetujui |
| Ringkasan pimpinan | Sesi aktif/ditutup, terjual/tidak terjual, nilai penjualan, tagihan belum lunas, pembayaran diterima, filter tanggal |
| Hak akses | 401 tanpa token, 403 untuk peran salah (4 kombinasi diuji) |

### 3.2 Aplikasi mobile (Expo web)

Login 6 peran berhasil dan diarahkan otomatis sesuai peran: Petugas Pendataan (daftar kedatangan + QR), Petugas Timbang (pindai QR/ketik nomor, statistik "10 kedatangan / 14 belum ditimbang / 3 selesai"), Operator (antrean + sesi hari ini), Pembeli (tab Lot/Pesanan/Lelang/Tagihan), Kasir (Tagihan/Lunas/Saldo/Periksa Nota), Pimpinan (ringkasan + persetujuan saldo). Tidak ada galat JS di konsol. Tampilan rapi, berbahasa Indonesia, kontras baik.

Contoh tangkapan layar: `tangkapan-layar/peran-timbang.png`, `peran-kasir.png`, `peran-pimpinan.png`, dst.

---

## 4. Temuan yang perlu diperbaiki (bukan soal dokumen)

| # | Temuan | Dampak | Saran |
|---|---|---|---|
| 1 | **Pembeli tidak bisa ikut lelang setelah sesi dibuka.** Pendaftaran peserta hanya bisa saat lot `MENUNGGU_LELANG`; begitu sesi AKTIF muncul "Lot ... tidak lagi menunggu lelang" (422) | Pembeli yang datang terlambat/membuka aplikasi setelah operator menekan "Buka" tidak bisa masuk ruang lelang → risiko demo gagal kalau urutannya keliru | Izinkan pendaftaran selama sesi AKTIF (atau tombol "Ikut sekarang" saat sesi berjalan) |
| 2 | **Klaim lelang turun ikut aturan kenaikan.** Pada sesi TURUN, `hargaMinimumBerikut()` masih "harga tertinggi + kenaikan minimum", padahal mode turun tidak memakai kenaikan | Tawaran pada lelang turun berperilaku aneh (jalur resminya memang tombol Ambil, tapi validasinya menyesatkan) | Khusus `mode=TURUN`: minimum = harga berjalan, dan tolak tawaran nominal dengan pesan yang memakai harga berjalan |
| 3 | **Penyiaran real-time gagal** — log: `Gagal menyiarkan peristiwa SESI_DIMULAI` karena Reverb tidak dijalankan; README tidak menyebut `php artisan reverb:start` | FR-15 (siaran < 1 detik) tidak terpenuhi walau event-nya sudah ditulis; 6 dari 7 event Tabel IV.7 belum ada | Jalankan/dokumentasikan Reverb **atau** nyatakan resmi "polling 1 detik" di dokumen |
| 4 | **Stop kontak event:** penutupan otomatis hanya terjadi ketika status dipolling atau lewat `lelang:tutup-otomatis` (butuh `schedule:work`) | Sesi berakhir tanpa ada yang membuka aplikasi bisa "menggantung" | Dokumentasikan `schedule:work`, atau jadikan perintah terjadwal wajib di README |
| 5 | **Hak akses / TPI belum menyaring semua listing** (mis. lot yang dibuat di luar TPI pengguna masih muncul di antrean operator; sebagian endpoint hanya memeriksa peran) | NFR-05 "hanya fungsi sesuai perannya" baru sebagian | Tambahkan filter `id_tpi` pada antrean/sesi/tagihan seperti pada ringkasan pimpinan |
| 6 | **Tidak ada pembatalan transaksi manual oleh supervisor (FR-32)**; yang ada hanya pembatalan otomatis kedaluwarsa 2 jam | FR-32 berstatus Tinggi | Tambah endpoint + alasan wajib + audit |
| 7 | **Nama mutasi saldo**: `SETOR` (bukan TOPUP), `PENYESUAIAN` wajib `arah` KREDIT/DEBIT — tidak terlihat di UI/dokumentasi API | Integrator (dan saya saat uji) mudah salah kirim | Lengkapi tabel API di README |
| 8 | Rate limit login hanya per akun, belum per alamat IP (NFR-06) | Brute force lintas akun dari satu IP tidak dibatasi | Tambah limiter per IP |
| 9 | Seeder idempoten ✔ (dijalankan 2×: jumlah lot/kedatangan/pengguna tidak bertambah). Tidak ada masalah. | — | — |

---

## 5. Dokumen: apa yang harus ditambahkan / dikoreksi (putaran 21 Nov 2026, dokumen v1.1)

Perincian lengkap ada di sheet **"Dokumen vs Kode"** dan sheet baru **"Status Batang Tubuh v1.1"**. Ringkasnya,
setelah revisi dokumen v1.1 dan pembaruan kode, sebagian besar butir pada putaran pertama sudah **tidak berlaku lagi**.

**A. Sudah selesai — butir lama tidak berlaku:**

1. ~~Audit log~~ → **selesai**: `AuditService`, `AuditController`, model `AuditLog`, migrasi `audit_log`; jejak diuji (MS-09). FR-52..FR-55 dan NFR-14 terpenuhi.
2. ~~Papan lelang & dasbor real-time~~ → **selesai**: `PapanLelangService`, dasbor pimpinan (FR-41..FR-43).
3. ~~Laporan & analitik~~ → **selesai**: `GET /api/laporan/{jenis}` beserta ekspor CSV/PDF (FR-44..FR-47), diuji 19/19 pada `uji_rute_resmi.py`.
4. ~~Data master & pengguna~~ → **selesai**: `MasterController` (jenis ikan, grade, tarif, kapal, pemilik kapal, TPI, peran, pengguna, konfigurasi) termasuk kartu nelayan (FR-48..FR-50, FR-66).
5. ~~Portal nelayan~~ → **selesai**: `PortalNelayanService`, `tautan_akses_nelayan`, `pembayaran_nelayan`, `pencairan_nelayan`; `uji_settlement.py` 29/29.
6. ~~Retribusi & pembayaran nelayan~~ → **selesai**: settlement per kedatangan bruto → retribusi → netto → pencairan bertahap (FR-37, FR-39, FR-40, FR-67).
7. ~~Notifikasi~~ → **selesai**: tabel `notifikasi` + `LOT_DIPANTAU_DIBUKA`, `OUTBID`, `SETTLEMENT_DIBAYAR`.
8. ~~FR-21 dihapus tanpa pembaruan dokumen~~ → **selesai**: validasi daya beli (saldo + limit kredit) ada di `LelangService` dan kini ditegaskan pada SKPL **FR-21** (berlaku untuk lelang naik maupun Ambil pada lelang turun).
9. ~~Tabel IV.6 beda dari implementasi~~ → **selesai dua arah**: `RuteResmiController` menyediakan rute resmi persis seperti dokumen dan SKPL ditambah tiga titik akhir; `uji_rute_resmi.py` 19/19.
10. ~~ERD 26 tabel vs 24 tabel domain~~ → **selesai**: basis data 27 tabel domain dan ERD v1.1 diganti sehingga memuat 27 entitas (termasuk `pencairan_nelayan`, commit `70de974`).
11. ~~FR-63 kanal penawaran hanya kolom~~ → **sebagian, disengaja**: kanal kini terisi pada setiap tawaran, tetapi belum ditentukan dari lokasi/terminal; dinyatakan pada SKPL Tabel III.13 (prioritas Sedang).

**B. Masih terbuka:**

12. **Antrean sinkronisasi luring** (FR-05, FR-06, NFR-09, kelas `AntreanSinkronisasi`) — belum ada di `sipeli-mobile/src`; paket `netinfo` sudah terpasang tetapi belum dipakai. Perlu diputuskan: dibangun atau dinyatakan di luar cakupan v1.1.
13. **Timbangan digital** (FR-07, Tabel IV.4) — antarmuka pembacaan ada tetapi sumbernya simulasi di dalam aplikasi (`useTimbanganSimulasi`), bukan adaptor serial/Bluetooth; input manual cadangan berjalan penuh. Sudah dinyatakan sebagai "disimulasikan" di SKPL Tabel IV.4.
14. **Klien real-time** — server menyiarkan 9 peristiwa (uji_ws 9/9 diterima), tetapi aplikasi mobile hanya berlangganan `SESI_DIMULAI` pada kanal `tpi.{id}`; `OUTBID`, `LOT_DIPANTAU_DIBUKA`, dan `SETTLEMENT_DIBAYAR` belum dilanggan klien mana pun (kanal `pembeli.{id}`/`nelayan.{id}` tersedia di server).
15. **Ruang lingkup "antarmuka web terpisah"** — semua peran dilayani satu aplikasi Expo (mobile + web). Pernyataan ruang lingkup perlu disesuaikan atau aplikasi web terpisah dibangun.
16. **Hardware printer ESC/POS** — cetak label/nota memakai `expo-print` (PDF), bukan ESC/POS.
17. **Bukti pengujian yang belum ada:** skenario uji TC-01..TC-67 (Tabel 4.1 DPPL) belum berwujud dokumen; laporan cakupan tes (NFR-13 target ≥70%); uji ketergunaan 5 pengguna (NFR-07); pengukuran kontras (NFR-08); prosedur backup/pemulihan (NFR-15).

**C. Dokumen yang layak ditambahkan ke repo (biasanya dinilai di IMPAL):**

- Dokumen **Skenario Pengujian** (TC per FR) — bahan mentahnya sudah ada di `Matriks-...xlsx`
- **Laporan Hasil Uji** (laporan ini bisa dipakai/diringkas)
- **Manual pengguna** singkat per peran (6 peran)
- **Panduan operasi**: `reverb:start`, `schedule:work`, backup, akun demo
- **README**: tambahkan 46 endpoint nyata (sekarang hanya ±16 tercantum), bagian Reverb & penjadwalan, dan pernyataan cakupan tahap.

---

## 6. Saran urutan kerja

1. **Dokumen (hampir selesai):** v1.1 sudah memuat FR-21, FR-64, Tabel IV.6/IV.7, kamus 27 entitas, enumerasi, ALG-01, serta diagram use case/ERD/kelas terbaru (vektor). Tinggal memutuskan butir 12–17 di atas: mana yang dibangun, mana yang dinyatakan di luar cakupan.
2. **Kode:** perluas langganan klien ke `OUTBID`, `LOT_DIPANTAU_DIBUKA`, dan `SETTLEMENT_DIBAYAR` (butir 14) — satu perubahan kecil di `src/realtime.js` yang menutup tiga FR sekaligus.
3. **Kode:** antrean luring FR-05/FR-06 (butir 12) bila tetap masuk cakupan.
4. **Bukti:** dokumen Skenario Pengujian (TC per FR) dan laporan cakupan tes, memakai bahan `Matriks-Kesesuaian-SKPL-DPPL-LelangIkan.xlsx`.

---

## 7. Berkas pendamping

| Berkas | Isi |
|---|---|
| `Matriks-Kesesuaian-SKPL-DPPL-LelangIkan.xlsx` | Sheet **Ringkasan**, **Matriks FR** (67 baris: status + bukti uji + catatan), **Matriks NFR** (15), **Dokumen vs Kode** (16 butir) |
| `tangkapan-layar/` | 7 tangkapan layar: layar masuk + 6 peran |
| `skrip-uji/` | `e2e2.py` (63 pemeriksaan fungsional + 10 cek endpoint), `fokus.py` (foto/2 pembeli/saldo/nota/idempotensi), `lelang_khusus.py` (turun, anti-sniping, auto-close), `ambil.py` (klaim lelang turun), `ui10.py` (login 6 peran) |

Skrip uji memakai `php artisan tinker` untuk menerbitkan OTP (karena OTP disimpan sebagai hash) dan melakukan `migrate:fresh --seed` sebelum skenario; aman dijalankan di database pengembangan, **jangan** di database berisi data asli.

---

## 7. Hasil uji ulang 21 November 2026 (putaran D)

Dijalankan dari basis data bersih (`php artisan migrate:fresh --seed`), API di `127.0.0.1:8000`, dan
Reverb di `127.0.0.1:8080`. Skrip uji ada di `uji/` (tidak masuk repo).

| Uji | Perintah | Hasil |
|---|---|---|
| PHPUnit (regresi fitur) | `php artisan test` | **20 lulus / 61 asersi** |
| Rute resmi Tabel IV.6 | `python3 uji_rute_resmi.py` | **19/19** |
| End-to-end 13 use case | `python3 e2e2.py` | **81/81** |
| Fitur UC-09..UC-12 + FR-64 | `python3 uji_fitur.py` | **43/43** |
| UC-08, UC-13, FR-66/67 | `python3 uji_settlement.py` | **29/29** |
| WebSocket kelompok 1 (naik) | `python3 uji_ws.py` | 5 peristiwa diterima (LELANG_DIMULAI, PENAWARAN_BARU, OUTBID, HASIL_LELANG, SESI_DITUTUP) |
| WebSocket kelompok 2 (turun/perpanjangan) | `python3 uji_ws2.py` | HARGA_TURUN + SESI_DIPERPANJANG diterima |
| WebSocket kelompok 3 (pantauan/settlement) | `python3 uji_ws3.py` | LOT_DIPANTAU_DIBUKA + SETTLEMENT_DIBAYAR diterima |
| Tabel domain | `information_schema` | **27** (sesuai dokumen) |
| Rute API terdaftar | `php artisan route:list --path=api` | 85 rute |

**Total: 192 pemeriksaan lulus** (20 + 19 + 81 + 43 + 29) ditambah 9 peristiwa real-time yang teramati klien.

Catatan teknis:

1. `uji_ws2.py` dan `uji_ws3.py` mengubah keadaan basis data (membuat sesi dan settlement), sehingga keduanya
   harus dijalankan **masing-masing dari basis data baru**. Bila dijalankan setelah uji lain, keduanya berhenti
   dengan `Call to a member function getIdLot() on null` — itu keterbatasan skrip, bukan kegagalan aplikasi.
2. Uji beban NFR-01/NFR-02 yang sesungguhnya **belum** dilakukan: `uji_akhir.py` hanya menunjukkan latensi
   ±13 ms per tawaran secara berurutan pada data bersih, dan penolakan 422 pada uji itu terjadi karena
   pembeli uji belum memiliki saldo — bukan karena kinerja.

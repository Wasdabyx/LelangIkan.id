# Spesifikasi Revisi Dokumen v1.0 → v1.1

Dokumen: **SKPL LELANGIKAN.ID** dan **DPPL LELANGIKAN.ID**
Tujuan revisi: menyelaraskan dokumen dengan aplikasi yang sudah dibangun dan diuji
(20 tes PHPUnit, 81 uji e2e, 43 uji fitur, uji WebSocket untuk seluruh peristiwa real-time).

Cara pakai berkas ini: setiap butir menyebut **lokasi** (bab/tabel/halaman pada PDF asli),
**teks lama** (kutipan), dan **teks baru**. Setelah DOCX asli diterima, butir-butir ini
diterapkan langsung pada DOCX sehingga tata letak asli tetap utuh, lalu diekspor ke PDF v1.1.

---

## A. Perubahan pada halaman sampul & daftar isi (kedua dokumen)

| Lokasi | Lama | Baru |
|---|---|---|
| Halaman sampul, baris kode dokumen | `Kode Dokumen: LELANGIKAN.ID-SKPL-1.0 • Versi 1.0` | `Kode Dokumen: LELANGIKAN.ID-SKPL-1.1 • Versi 1.1` |
| Halaman sampul, baris kode dokumen DPPL | `LELANGIKAN.ID-DPPL-1.0 • Versi 1.0` | `LELANGIKAN.ID-DPPL-1.1 • Versi 1.1` |
| Kaki halaman semua halaman | `LELANGIKAN.ID-SKPL-1.0` | `LELANGIKAN.ID-SKPL-1.1` |
| Daftar isi | nomor halaman v1.0 | diperbarui otomatis oleh Word (Ctrl+A, F9) |
| Setelah daftar isi | — | **tabel baru "Riwayat Revisi"** (lihat Lampiran A berkas ini) |

---

## B. SKPL — butir yang berubah

### B1. FR-21 ditegaskan kembali (Bab III.A.1, Tabel III.4, hal. 15)

**Lama**
> FR-21 Sistem menolak penawaran apabila saldo atau limit kredit pembeli tidak mencukupi nilai penawaran. **Tinggi**

**Baru**
> FR-21 Sistem menolak penawaran apabila saldo ditambah limit kredit pembeli (daya beli) tidak mencukupi nilai penawaran, yaitu harga tawaran dikalikan berat aktual lot, dan menerapkan aturan yang sama pada pengambilan harga pada lelang turun. Pesan penolakan menyebutkan nilai daya beli dan jalan keluar berupa top up melalui Kasir TPI. **Tinggi**

Alasan: keputusan pemilik dokumen pada 21 November 2026 — kebutuhan tetap wajib (Tinggi), aplikasi yang disesuaikan.

### B2. FR-15 dan Tabel IV.7 diperluas menjadi sembilan peristiwa (Bab IV.C, hal. 53)

**Lama**: Tabel IV.7 memuat 7 peristiwa (LELANG_DIMULAI … SESI_DITUTUP).
**Baru**: Tabel IV.7 memuat **9 peristiwa**; dua baris terakhir:

| Peristiwa | Arah | Muatan Utama | Penerima |
|---|---|---|---|
| SETTLEMENT_DIBAYAR | peladen → klien | id_kedatangan, id_bayar_nelayan, total_netto, total_dibayar, status, waktu_bayar | Portal nelayan terkait (kanal privat `nelayan.{id_pemilik}`) |
| LOT_DIPANTAU_DIBUKA | peladen → klien | id_lot, kode_lot, id_sesi, mode | Pembeli yang memantau lot tersebut (kanal privat `pembeli.{id_pembeli}`) |

Tambahan kalimat setelah tabel:
> Kanal `tpi.{id_tpi}` memuat seluruh klien TPI. Kanal `nelayan.{id_pemilik}` dan `pembeli.{id_pembeli}` bersifat privat: hanya pengguna yang berhak (pemilik kapal terkait, pembeli pemantau) yang dapat berlangganan. Bila kanal WebSocket terputus, klien memakai cadangan polling 1 detik melalui `GET /api/papan-lelang` dan `GET /api/operator/sesi/{id}` (NFR-10).

### B3. FR-64 diperjelas: bergabung selama sesi berjalan (Bab III.A.1, hal. 16)

**Lama**
> FR-64 Sistem mengizinkan pembeli menambahkan lot ke daftar pantauan, menetapkan batas harga pribadi, dan menerima notifikasi saat sesi lot tersebut dibuka. **Sedang**

**Baru**
> FR-64 Sistem mengizinkan pembeli menambahkan lot ke daftar pantauan, menetapkan batas harga pribadi, dan menerima notifikasi saat sesi lot tersebut dibuka (peristiwa LOT_DIPANTAU_DIBUKA). Pembeli juga dapat bergabung mengikuti lelang selama sesi masih berjalan, yaitu pada status lot MENUNGGU_LELANG, DILELANG, atau DIPERPANJANG, dengan konfirmasi kode OTP pemesanan; setelah sesi ditutup pembeli tidak dapat lagi bergabung. **Sedang**

Alasan: pada v1.0 batas waktu bergabung tidak dinyatakan sehingga implementasi awal menutup pintu saat sesi mulai; revisi ini menyatakan aturannya secara eksplisit.

### B4. Catatan ALG-01: tawaran pertama boleh sama dengan harga pembukaan (Bab III.C, hal. 24)

Sisipkan setelah uraian ALG-01:
> Pada lelang naik, tawaran pertama sah bila nilainya sama dengan atau lebih tinggi dari harga pembukaan lot; tawaran berikutnya wajib lebih tinggi minimal sebesar kenaikan minimum. Tawaran yang lebih dari sepuluh kali harga pembukaan ditolak sebagai salah ketik.

### B5. Jumlah entitas data (Bab III.D, hal. 40 dan 44)

**Lama**: "Model data konseptual terdiri atas dua puluh lima entitas." dan judul Gambar III.15 "(25 entitas)".
**Baru**: "Model data konseptual terdiri atas **dua puluh tujuh entitas**." dan judul gambar menjadi "(27 entitas)".

Angka 27 adalah jumlah tabel nyata pada basis data terpasang (`SHOW TABLES`, tanpa tabel infrastruktur
`migrations`, `sessions`, `cache`, `jobs`, `personal_access_tokens`):

```
audit_log, daftar_pantauan, foto_lot, grade_mutu, jenis_ikan, kapal, kartu_nelayan,
kedatangan_kapal, kode_otp, konfigurasi, lot_lelang, mutasi_saldo, notifikasi, pembayaran,
pembayaran_nelayan, pemilik_kapal, penawaran, pencairan_nelayan, pengguna, peran, saldo_pembeli,
sesi_lelang, tangkapan, tarif_retribusi, tautan_akses_nelayan, tpi, transaksi_penjualan
```

Tujuh entitas yang ditambahkan setelah v1.0: `audit_log`, `pembayaran_nelayan`, `pencairan_nelayan`,
`kartu_nelayan`, `tautan_akses_nelayan`, `notifikasi`, `kode_otp`; ditambah kolom baru
`tpi.kode_tpi`, `transaksi_penjualan.id_pembatal/alasan_batal/waktu_batal/tanggal_pengambilan`,
`pengguna.preferensi`, dan `lot_lelang` status `DIPERPANJANG`.

### B6. Tabel IV.6 — rute REST (hal. 52–54)

Tabel IV.6 pada DOCX ternyata **sudah memakai penamaan rute resmi** (`/api/sesi`, `/api/sesi/{id}/penawaran`,
`/api/pembayaran`, `/api/saldo/mutasi`, `/api/master/{entitas}`, `/api/settlement/{idKedatangan}`), sedangkan
aplikasi sebelumnya hanya menyediakan rute per peran. Penyelesaiannya dipilih dari dua arah sekaligus:

1. **Aplikasi menyesuaikan dokumen** — rute resmi diaktifkan melalui `RuteResmiController`
   (`app/Http/Controllers/Api/RuteResmiController.php`) yang meneruskan ke pengendali per peran, sehingga
   validasi dan aturan bisnis tetap satu sumber. Rute per peran tetap ada untuk klien mobile.
2. **Dokumen dilengkapi** — tiga baris baru dan empat keterangan yang diperjelas.

| Metode | Titik Akhir | Aktor | Fungsi | Token |
|---|---|---|---|---|
| GET | /api/laporan/{jenis} | A6 | Laporan harga, retribusi, tren, atau kinerja; ?format=csv\|pdf | Ya |
| GET | /api/kasir/settlement | A5 | Daftar kedatangan yang siap diselesaikan | Ya |
| GET | /api/audit?format=csv | A7 | Ekspor jejak audit untuk pemeriksaan | Ya |

Perjelas baris yang sudah ada:
- `POST /api/master/{entitas}` → tambahkan "entitas: jenis-ikan, grade-mutu, tarif-retribusi, kapal, pemilik-kapal, tpi, peran, pengguna, konfigurasi".
- `POST /api/settlement/{idKedatangan}` → "Perhitungan settlement nelayan; pencairan melalui `POST /api/pembayaran-nelayan/{id}/cairkan`".
- `POST /api/sesi/{id}/penawaran` → "Pengajuan penawaran (mode naik, muatan nominal) atau Ambil (mode turun), dengan kanal di tempat atau jarak jauh".
- `GET /api/nelayan/rekap?token=` → "memerlukan token + kode verifikasi 4 digit".

Bukti: `uji_rute_resmi.py` **19/19 lulus** (membuka sesi, menawar, menutup, membayar lewat `kode_tagihan`,
mutasi saldo, settlement, pencairan, kartu, tautan, master, serta batas wewenang per peran).

### B7. Ruang lingkup versi 1.1 (Bab I.B, hal. 6)

Tambahkan butir:
> Pada versi 1.1, seluruh tiga belas use case dilengkapi antarmuka REST dan peristiwa real-time, termasuk papan lelang, pelaporan/analitik, pengelolaan data master, jejak audit, dan portal nelayan berbasis tautan akses.

---

## C. DPPL — butir yang berubah

### C1. Jumlah tabel basis data (Bab 2, hal. 17 dan 625)

**Lama**: "Basis data terdiri atas dua puluh lima tabel dengan lima kelompok fungsional."
**Baru**: "Basis data terdiri atas **dua puluh tujuh tabel** dengan lima kelompok fungsional."
Gambar 2.2 dan judulnya menjadi "… (**27 tabel**)".

### C2. Kamus data — tabel baru (Bab 2)

Sisipkan kartu kamus data berikut (format sama dengan kartu tabel lain):

**pembayaran_nelayan**
```
PK id_bayar_nelayan bigint unsigned
FK id_kedatangan bigint unsigned  (unik)
total_bruto decimal(12,2)
potongan_retribusi decimal(12,2)
potongan_lain decimal(12,2)
keterangan_potongan varchar(200)
total_netto decimal(12,2)
status enum(BELUM_DIBAYAR, DIBAYAR_SEBAGIAN, LUNAS, DITAHAN)
total_dibayar decimal(12,2)
cara_pencairan enum(TUNAI, TRANSFER_REKENING, KARTU_NELAYAN)
nomor_referensi_bank varchar(40)
waktu_bayar datetime
FK id_kasir bigint unsigned
waktu_dibuat datetime
```

**pencairan_nelayan**
```
PK id_pencairan bigint unsigned
FK id_bayar_nelayan bigint unsigned
nominal decimal(12,2)
cara_pencairan enum(TUNAI, TRANSFER_REKENING, KARTU_NELAYAN)
nomor_referensi varchar(40)
waktu datetime
FK id_kasir bigint unsigned
keterangan varchar(200)
```

**kartu_nelayan**
```
PK id_kartu bigint unsigned
FK id_pemilik bigint unsigned
UQ nomor_kartu varchar(20)
UQ token_qr char(64)
status enum(AKTIF, DIBLOKIR, KEDALUWARSA)
berlaku_sampai date
waktu_terbit datetime
alasan_blokir varchar(200)
```

**tautan_akses_nelayan**
```
PK id_tautan bigint unsigned
FK id_pemilik bigint unsigned
UQ token char(64)
kode_verifikasi char(60)
kedaluwarsa datetime
jumlah_akses tinyint unsigned
waktu_dibuat datetime
FK id_penerbit bigint unsigned
```

**notifikasi**
```
PK id_notifikasi bigint unsigned
FK id_pengguna bigint unsigned
tipe varchar(40)
judul varchar(120)
isi text
status_baca tinyint(1)
waktu datetime
```

**audit_log**
```
PK id_audit bigint unsigned
FK id_pengguna bigint unsigned (null)
aksi varchar(40)
entitas varchar(40)
id_entitas varchar(40)
data_sebelum json
data_sesudah json
alamat_ip varchar(45)
waktu datetime(3)
```
Catatan pada kartu ini: "Hanya sisip dan baca. Perubahan dan penghapusan ditolak pada lapisan API (FR-54); kata sandi dan token disamarkan sebelum disimpan."

**Kolom tambahan pada tabel yang sudah ada**

| Tabel | Kolom baru | Keterangan |
|---|---|---|
| tpi | kode_tpi varchar(5) | kode pada nomor lot `[kode]-[YYMMDD]-[urut]` (FR-08) |
| transaksi_penjualan | id_pembatal, alasan_batal varchar(200), waktu_batal datetime | pembatalan oleh supervisor (FR-32) |
| pengguna | id_pemilik bigint unsigned (FK), preferensi json | tautan akun A8 ke pemilik kapal (FR-59); parameter laporan terakhir (UC-10) |

### C3. Daftar enumerasi (Bab 3.4)

Tambahkan tiga enumerasi:

| Enumerasi | Nilai | Kolom |
|---|---|---|
| StatusBayarNelayan | BELUM_DIBAYAR, DIBAYAR_SEBAGIAN, LUNAS, DITAHAN | pembayaran_nelayan.status |
| CaraPencairan | TUNAI, TRANSFER_REKENING, KARTU_NELAYAN | pembayaran_nelayan.cara_pencairan, pencairan_nelayan.cara_pencairan |
| StatusKartuNelayan | AKTIF, DIBLOKIR, KEDALUWARSA | kartu_nelayan.status |

### C4. Kelas kendali baru pada diagram kelas dan tabel detail kelas

Tambahkan lima kelas kendali (Bab 3.3.1) beserta tabel detailnya:

| Kelas | Tanggung jawab | Use case |
|---|---|---|
| SettlementService | hitung bruto/retribusi/netto per kedatangan (ALG-05, ALG-06), catat pencairan tunai/transfer/kartu, simulasikan nomor referensi | UC-08 |
| PapanLelangService | keadaan papan: harga tertinggi, sisa waktu, inisial penawar, ringkasan harian | UC-09 |
| LaporanService | agregasi volume/nilai/harga rata-rata, tren + pembanding periode, rekap retribusi harian/bulanan, kinerja lelang, bentuk CSV | UC-10 |
| MasterService (MasterController) | CRUD data master, pengguna/peran/penugasan TPI, konfigurasi lelang, nonaktifkan pengguna | UC-11 |
| PortalNelayanService | terbitkan/buka tautan akses (token + 4 digit, maks 72 jam), rekap per kapal, rincian per lot, status bayar | UC-13 |

### C5. Sequence diagram UC-08 sudah sesuai; tambahkan pesan pencairan

Pada Tabel 3.32 (Daftar pesan sequence diagram UC-08), pesan no. 9–11 disesuaikan:

| No | Pengirim | Penerima | Pesan | Sifat |
|---|---|---|---|---|
| 9 | SettlementService | PencairanNelayan | simpanCairan(nominal, cara, nomorReferensi) | sinkron |
| 10 | SettlementService | PenyiaranLelangService | siarkan('SETTLEMENT_DIBAYAR', ringkasan) | asinkron |
| 11 | SettlementService | Pencetak | cetakBukti(idBayarNelayan) | asinkron |

### C6. Rancangan antarmuka papan lelang (UC-09)

Gambar 3.17 tetap. Tambahkan pada Tabel 3.34 (deskripsi objek UI UC-09) satu baris:

| Objek UI | Jenis | Perilaku dan Validasi | Kebutuhan |
|---|---|---|---|
| Penanda sambungan | Lencana status | Menampilkan "Langsung (WebSocket)" atau "Cadangan polling 1 detik" bila kanal terputus lebih dari 5 detik | FR-41, NFR-10 |

### C7. Tautan akses portal nelayan (UC-13, Tabel 3.29/3.26)

Tegaskan pada uraian UC-13:
> Tautan akses berupa token 64 karakter dengan masa berlaku maksimum 72 jam dan wajib disertai kode verifikasi 4 digit yang disampaikan kasir secara terpisah. Setiap pembukaan tautan dicatat pada audit (TERBITKAN_TAUTAN_NELAYAN, BUKA_PORTAL_NELAYAN).

---

## D. Yang **tidak** diubah (sengaja dipertahankan)

1. Seluruh 13 use case dan 67 FR tetap ada; tidak ada penurunan prioritas.
2. NFR-01 … NFR-15 tidak berubah.
3. Semua activity diagram dan sequence diagram (18 di SKPL, 27 di DPPL) dipertahankan apa adanya.
   **Pengecualian (revisi lanjutan):** tiga diagram analisis diganti dengan versi terbaru dari berkas
   `LelangIkan.id/diagrams/*.drawio` — Gambar III.14 (use case), Gambar III.15 / Gambar 2.2 (ERD), dan
   Gambar III.17 / Gambar 3.27 (diagram kelas). Rinciannya pada butir G.
4. Struktur bab, penomoran tabel, dan gaya sitasi IEEE 830 tetap.

---

## E. Lampiran A — Riwayat Revisi (**dibatalkan**)

> **Keputusan akhir:** halaman/tabel RIWAYAT REVISI DOKUMEN **tidak dipakai** pada v1.1 dan sudah dihapus
> dari kedua dokumen (SKPL 55 halaman, DPPL 86 halaman). Bagian di bawah dipertahankan hanya sebagai
> catatan usulan yang tidak jadi diterapkan; jangan disisipkan lagi.

| Versi | Tanggal | Penyusun | Ringkasan perubahan |
|---|---|---|---|
| 1.0 | 2026 | Tim Pengembang | Terbitan awal: 13 use case, 67 FR, 15 NFR |
| 1.1 | 21 November 2026 | Tim Pengembang | Penyelarasan dengan aplikasi hasil pengujian: (a) FR-21 ditegaskan (daya beli = saldo + limit kredit, berlaku untuk lelang naik dan turun); (b) FR-64 diperjelas (pembeli boleh bergabung selama sesi berjalan); (c) Tabel IV.7 diperluas dari 7 menjadi 9 peristiwa (SETTLEMENT_DIBAYAR, LOT_DIPANTAU_DIBUKA); (d) rute resmi Tabel IV.6 diaktifkan pada aplikasi dan dilengkapi tiga titik akhir; (e) kamus data bertambah tujuh entitas (audit_log, pembayaran_nelayan, pencairan_nelayan, kartu_nelayan, tautan_akses_nelayan, notifikasi, kode_otp) dan enam kolom baru; (f) lima kelas kendali baru didokumentasikan; (g) catatan ALG-01 tentang tawaran pertama pada harga pembukaan |

---

## F. Bukti uji yang mendasari revisi

| Berkas | Isi | Hasil terakhir |
|---|---|---|
| `sipeli-backend/tests/Feature/PerbaikanNovemberTest.php` | 20 tes regresi (daya beli, TURUN, audit, papan lelang, laporan, settlement, pantauan, real-time) | 20 lulus (61 asersi) |
| `/home/user/uji/e2e2.py` | alur end-to-end 13 use case melalui REST | 81/81 lulus |
| `/home/user/uji/uji_settlement.py` | UC-08, UC-13, FR-66 | 29/29 lulus |
| `/home/user/uji/uji_fitur.py` | UC-09, UC-10, UC-11, FR-64 | 43/43 lulus |
| `/home/user/uji/uji_ws.py`, `uji_ws2.py`, `uji_ws3.py` | sembilan peristiwa Tabel IV.7 lewat WebSocket Reverb | 9/9 diterima klien |
| `/home/user/uji/uji_rute_resmi.py` | rute resmi Tabel IV.6 persis seperti tertulis di dokumen | 19/19 lulus |

---

## G. Diagram yang diganti (revisi lanjutan, kedua dokumen)

Gambar v1.0 berasal dari sketsa awal dan tidak lagi sama dengan berkas diagram di repositori. Ketiga
diagram diganti dengan hasil render `LelangIkan.id/diagrams/*.drawio`:

| Gambar SKPL | Gambar DPPL | Berkas sumber | Isi |
|---|---|---|---|
| Gambar III.14 | — | `usecase-lelangikan.drawio` | 8 aktor, 13 use case, batas sistem "LelangIkan.id", relasi «include» dan «extend» |
| Gambar III.15 | Gambar 2.2 | `erd-lelangikan.drawio` | ERD bentuk tabel baku draw.io, **27 entitas** |
| Gambar III.17 | Gambar 3.27 | `class-diagram-lelangikan.drawio` | **37 kelas**, 3 interface, 20 kelas enumerasi |

Catatan penyelarasan:

1. **ERD 27 entitas.** Berkas ERD dari repositori baru memuat 26 tabel; `pencairan_nelayan` ditambahkan
   pada berkas `.drawio` (posisi 1200,905; kolom mengikuti migrasi
   `2026_11_21_000004_create_pembayaran_nelayan_table.php`) sehingga jumlahnya cocok dengan basis data
   terpasang dan dengan Tabel III.16 / Tabel 2.4. Perubahan ini ikut dikomit ke repositori.
2. **Diagram kelas 37 kelas.** Versi baru memuat tiga kelas kendali hasil revisi (LelangNaik, LelangTurun,
   PencairanTunai/TransferBank/KartuNelayan beserta antarmuka `MetodePencairan`) sehingga narasi
   "Diagram memuat 35 kelas" pada SKPL hal. 48 dan DPPL hal. 63 diperbarui menjadi 37 kelas; judul gambar
   pada daftar gambar ikut berubah.
3. **Enumerasi pada diagram kelas.** Diagram memuat 20 kelas enumerasi (19 pada v1.0 ditambah
   `TujuanOtp`). Narasi dan judul gambar pada SKPL (hal. 46, 47) serta DPPL (hal. 62, 64) sudah
   diselaraskan menjadi **20 enumerasi**, cocok dengan Tabel 3.92 yang memuat `TujuanOtp`.
4. Ukuran gambar dihitung dari rasio asli lalu dibatasi lebar area teks, sehingga pemenggalan halaman
   tidak berubah: SKPL tetap 56 halaman, DPPL tetap 87 halaman. Kanvas kosong pada ekspor drawio dipangkas
   lebih dahulu agar diagram tampil sebesar mungkin (ERD 24,4 cm; diagram kelas 17,2 cm pada halaman
   landscape; use case 14,4 cm pada halaman portrait).
5. **Diagram di PDF berupa vektor, bukan gambar raster.** DOCX memuat PNG (aman untuk Word), tetapi PDF
   final memakai hasil ekspor PDF drawio sehingga teks dan garis tetap tajam pada perbesaran berapa pun
   dan teks diagram dapat dicari. Rantainya:
   `render_diagram.sh` (drawio-desktop + xvfb → PDF vektor) → `pangkas_diagram.py` (pangkas kanvas, buat
   PNG untuk DOCX) → `sunting_skpl.py` / `sunting_dppl.py` (+ `ganti_gambar.py`) → LibreOffice →
   `sisip_vektor.py` (ganti gambar raster pada kotak yang sama dengan vektor).

---

## H. Kejelasan tambahan hasil audit (kedua dokumen)

Tiga kalimat ditambahkan setelah audit isi dokumen terhadap kode (lihat `review/AUDIT-DOKUMEN-vs-KODE.md`):

1. **C-1 — catatan di bawah Tabel IV.6:** baris `/api/laporan/harga` dan `/api/laporan/retribusi` dilayani oleh
   satu rute `/api/laporan/{jenis}` dengan nilai `{jenis}` = `harga`/`retribusi`, dan keduanya menerima
   `?format=csv` atau `?format=pdf`. Tidak ada rute yang hilang; ini hanya penjelasan.
2. **C-2 — FR-05 dan FR-06 (SKPL Tabel III.1):** ditambahkan catatan miring *"Pada versi 1.1 antrean luring
   belum diimplementasikan; ketentuan ini berlaku untuk versi berikutnya."* (FR-05) dan kalimat serupa untuk
   FR-06 (status sinkronisasi). Keduanya tetap berprioritas "Sedang".
3. **C-3 — FR-07 (SKPL Tabel III.2):** ditambahkan catatan miring bahwa pada versi 1.1 pembacaan berat berasal
   dari **timbangan simulasi** di dalam aplikasi, dan adaptor perangkat nyata (Tabel IV.4) masuk rencana versi
   berikutnya.

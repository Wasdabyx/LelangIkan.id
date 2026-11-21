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

**Lama**: "Model data relasional terdiri atas dua puluh lima entitas." dan judul Gambar III.15 "… (26 tabel)".
**Baru**: "Model data relasional terdiri atas **tiga puluh dua** entitas." dan judul gambar menjadi "… (**32 tabel**)".

Tabel yang ditambahkan pada v1.1 (7 tabel): `audit_log`, `pembayaran_nelayan`, `pencairan_nelayan`,
`kartu_nelayan`, `tautan_akses_nelayan`, `notifikasi`, serta kolom baru `tpi.kode_tpi`,
`transaksi_penjualan.id_pembatal/alasan_batal/waktu_batal`, `pengguna.id_pemilik/preferensi`.

### B6. Tabel IV.6 — rute REST diselaraskan dengan aplikasi (hal. 52–53)

Tambahkan tiga baris pada Tabel IV.6:

| Metode | Titik Akhir | Aktor | Fungsi | Token |
|---|---|---|---|---|
| GET | /api/laporan/{jenis} | A6 | Laporan harga, retribusi, tren, atau kinerja (format=csv/pdf) | Ya |
| GET | /api/kasir/settlement | A5 | Daftar kedatangan yang siap diselesaikan | Ya |
| GET | /api/papan-lelang | A3, A5, A6 | Keadaan papan lelang (cadangan polling) | Ya |

Perjelas baris yang sudah ada:
- `POST /api/master/{entitas}` → tambahkan "entitas: jenis-ikan, grade-mutu, tarif-retribusi, kapal, pemilik-kapal, tpi, peran, pengguna, konfigurasi".
- `POST /api/settlement/{idKedatangan}` → "Perhitungan settlement; pencairan melalui `POST /api/pembayaran-nelayan/{id}/cairkan`".
- `GET /api/nelayan/rekap?token=` → "memerlukan token + kode verifikasi 4 digit".

### B7. Ruang lingkup versi 1.1 (Bab I.B, hal. 6)

Tambahkan butir:
> Pada versi 1.1, seluruh tiga belas use case dilengkapi antarmuka REST dan peristiwa real-time, termasuk papan lelang, pelaporan/analitik, pengelolaan data master, jejak audit, dan portal nelayan berbasis tautan akses.

---

## C. DPPL — butir yang berubah

### C1. Jumlah tabel basis data (Bab 2, hal. 17 dan 625)

**Lama**: "Basis data terdiri atas dua puluh enam tabel dengan lima kelompok fungsional."
**Baru**: "Basis data terdiri atas **tiga puluh tiga tabel** dengan lima kelompok fungsional."
Gambar 2.2 dan judulnya menjadi "… (**33 tabel**)".

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
3. Semua diagram (18 di SKPL, 27 di DPPL) dipertahankan apa adanya; hanya judul yang menyebut
   jumlah tabel yang berubah (Gambar III.15 dan Gambar 2.2) — gambarnya sendiri tidak digambar ulang.
4. Struktur bab, penomoran tabel, dan gaya sitasi IEEE 830 tetap.

---

## E. Lampiran A — Riwayat Revisi (tabel baru untuk kedua dokumen)

| Versi | Tanggal | Penyusun | Ringkasan perubahan |
|---|---|---|---|
| 1.0 | 2026 | Tim Pengembang | Terbitan awal: 13 use case, 67 FR, 15 NFR |
| 1.1 | 21 November 2026 | Tim Pengembang | Penyelarasan dengan aplikasi hasil pengujian: (a) FR-21 ditegaskan (daya beli = saldo + limit kredit, berlaku untuk lelang naik dan turun); (b) FR-64 diperjelas (pembeli boleh bergabung selama sesi berjalan); (c) Tabel IV.7 diperluas dari 7 menjadi 9 peristiwa (SETTLEMENT_DIBAYAR, LOT_DIPANTAU_DIBUKA); (d) Tabel IV.6 diselaraskan dengan 60+ rute REST terpasang; (e) kamus data bertambah 7 tabel (audit_log, pembayaran_nelayan, pencairan_nelayan, kartu_nelayan, tautan_akses_nelayan, notifikasi) dan 6 kolom baru; (f) lima kelas kendali baru didokumentasikan; (g) catatan ALG-01 tentang tawaran pertama pada harga pembukaan |

---

## F. Bukti uji yang mendasari revisi

| Berkas | Isi | Hasil terakhir |
|---|---|---|
| `sipeli-backend/tests/Feature/PerbaikanNovemberTest.php` | 20 tes regresi (daya beli, TURUN, audit, papan lelang, laporan, settlement, pantauan, real-time) | 20 lulus (61 asersi) |
| `/home/user/uji/e2e2.py` | alur end-to-end 13 use case melalui REST | 81/81 lulus |
| `/home/user/uji/uji_settlement.py` | UC-08, UC-13, FR-66 | 29/29 lulus |
| `/home/user/uji/uji_fitur.py` | UC-09, UC-10, UC-11, FR-64 | 43/43 lulus |
| `/home/user/uji/uji_ws.py`, `uji_ws2.py`, `uji_ws3.py` | sembilan peristiwa Tabel IV.7 lewat WebSocket Reverb | 9/9 diterima klien |

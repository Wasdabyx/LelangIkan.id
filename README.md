# LelangIkan.id

LelangIkan.id adalah sistem informasi pelelangan ikan untuk Tempat Pelelangan Ikan (TPI) yang berfungsi sebagai titik layanan (Point of Sale) dengan kemampuan lelang harga secara real-time.

Proyek ini dibuat untuk Tugas Besar mata kuliah Implementasi dan Pengujian Perangkat Lunak (IMPAL), Program Studi Teknologi Informasi, Telkom University.

**Kelompok:** Afin, Hunafa, Farrel, Gabriello

## Dokumen

| Dokumen | Isi |
|---|---|
| [SKPL LelangIkan.id.pdf](SKPL%20LelangIkan.id.pdf) | Spesifikasi Kebutuhan Perangkat Lunak: kebutuhan fungsional, use case, aktor |
| [DPPL LelangIkan.id.pdf](DPPL%20LelangIkan.id.pdf) | Deskripsi Perancangan Perangkat Lunak: class diagram, ERD, rancangan antarmuka, algoritma |

## Tampilan yang sudah dibuat

Tahap pertama mencakup tiga tampilan pada aplikasi mobile. Setelah login, pengguna diarahkan otomatis ke tampilan sesuai perannya.

| No | Tampilan | Use case | Pengguna | Ringkasan |
|---|---|---|---|---|
| 1 | Login (FormMasukUI) | Autentikasi | Semua peran | Masuk dengan nama pengguna dan kata sandi, token Sanctum, batas 5 kali percobaan gagal per 60 detik |
| 2 | Form Kedatangan & Tangkapan | UC-01 | Petugas Pendataan | Mencari kapal, mencatat data pelayaran dan perkiraan tangkapan per jenis ikan dan grade, simpan sebagai draf atau langsung terdaftar, menghasilkan nomor kedatangan beserta QR |
| 3 | Form Penimbangan & Label Lot | UC-02 | Petugas Timbang | Memilih kedatangan (pindai QR, ketik nomor, atau dari daftar), menimbang per keranjang, memeriksa selisih terhadap estimasi, menetapkan harga pembukaan, membuat lot lelang, dan mencetak label A6 berisi QR |

Catatan: timbangan digital pada tahap ini masih berupa simulasi di aplikasi, dan tersedia juga input berat manual (wajib disertai alasan).

## Foto lot dan foto contoh jenis ikan

Setiap lot dapat memiliki foto agar Operator Lelang dan Pembeli bisa melihat kondisi ikan.

- **Foto lot (opsional).** Petugas Timbang memotret ikan dari layar Label Lot dengan tombol **Ambil Foto**. Di HP tombol ini membuka kamera belakang. Di Chrome tombol ini membuka webcam, dan tersedia juga tombol **Pilih File**. Foto hanya dapat diambil atau diganti sebelum lot dilelang (status DRAFT_HARGA atau MENUNGGU_LELANG). Foto disimpan di `sipeli-backend/storage/app/private/foto-lot/` dengan nama acak, lalu ditampilkan melalui `GET /api/foto-lot/{nama}`.
- **Foto contoh jenis ikan.** Lot yang belum difoto menampilkan foto contoh sesuai jenis ikannya, dengan tanda "Contoh" agar tidak dikira foto lot asli. Foto contoh tersimpan di `sipeli-mobile/assets/ikan/`.
- **Lokasi tampil:**
  - Petugas Timbang: daftar baris kedatangan dan Label Lot.
  - Operator Lelang: Antrean Lot, Buka Sesi Lelang, dan pemantauan sesi.
  - Pembeli: daftar lot, tab Pesanan, tab Lelang, dan Ruang Lelang.
- Ketuk foto untuk melihatnya dalam ukuran besar.

### Sumber foto contoh

Semua foto contoh berasal dari [Wikimedia Commons](https://commons.wikimedia.org). Foto telah diperkecil dan diberi latar buram menjadi 480 x 320 piksel. Hasil ubahan tersebut berlisensi sama dengan foto aslinya.

| Berkas | Jenis ikan | Pembuat | Lisensi | Sumber |
|---|---|---|---|---|
| `cakalang.jpg` | Cakalang (Katsuwonus pelamis) | Sahat Ratmuangkhwang | [CC BY 3.0](https://creativecommons.org/licenses/by/3.0/) | [Katsuwonus pelamis Thailand.jpg](https://commons.wikimedia.org/wiki/File:Katsuwonus_pelamis_Thailand.jpg) |
| `tongkol.jpg` | Tongkol (Euthynnus affinis) | Mohammad Moazzam Khan | [CC BY 3.0](https://creativecommons.org/licenses/by/3.0/) | [Euthynnus affinis.jpg](https://commons.wikimedia.org/wiki/File:Euthynnus_affinis.jpg) |
| `tuna.jpg` | Tuna Sirip Kuning (Thunnus albacares) | Hugues Evano (IFREMER) | [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/) | [Thon albacore (Thunnus albacares) (Ifremer 00764-87616).jpg](https://commons.wikimedia.org/wiki/File:Thon_albacore_%28Thunnus_albacares%29_%28Ifremer_00764-87616%29.jpg) |
| `kembung.jpg` | Kembung (Rastrelliger kanagurta) | Sahat Ratmuangkhwang | [CC BY 3.0](https://creativecommons.org/licenses/by/3.0/) | [Rastrelliger kanagurta Thailand.jpg](https://commons.wikimedia.org/wiki/File:Rastrelliger_kanagurta_Thailand.jpg) |
| `layur.jpg` | Layur (Trichiurus lepturus) | D. Ross Robertson | Domain publik | [Trichiurus lepturus SI.jpg](https://commons.wikimedia.org/wiki/File:Trichiurus_lepturus_SI.jpg) |
| `kakap.jpg` | Kakap Merah (Lutjanus malabaricus) | U.S. Food and Drug Administration | Domain publik | [Lutjanus malabaricus (FDA 164).jpg](https://commons.wikimedia.org/wiki/File:Lutjanus_malabaricus_%28FDA_164%29.jpg) |
| `udang.jpg` | Udang Jerbung (Penaeus merguiensis) | RubyGoes | [CC BY 2.0](https://creativecommons.org/licenses/by/2.0/) | [Penaeus merguiensis.jpg](https://commons.wikimedia.org/wiki/File:Penaeus_merguiensis.jpg) |
| `cumi.jpg` | Cumi-cumi (contoh: Loligo vulgaris) | Hans Hillewaert | [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/) | [Loligo vulgaris.jpg](https://commons.wikimedia.org/wiki/File:Loligo_vulgaris.jpg) |

## Teknologi

| Bagian | Teknologi |
|---|---|
| Backend (API) | PHP 8.3, Laravel 11, Laravel Sanctum |
| Database | MariaDB 10.4 (XAMPP) |
| Aplikasi mobile | React Native dengan Expo SDK 57, dijalankan lewat Expo Go (Android 9 ke atas) |
| Pustaka mobile | expo-camera (pindai QR), expo-print dan expo-sharing (cetak dan bagikan label), expo-secure-store (penyimpanan token), qrcode, react-native-svg |

## Struktur repositori

```
LelangIkan.id/
├── SKPL LelangIkan.id.pdf
├── DPPL LelangIkan.id.pdf
├── sipeli-backend/                 API Laravel
│   ├── app/Enums/                  status dan kode peran
│   ├── app/Http/Controllers/Api/   AutentikasiController, KedatanganController, PenimbanganController
│   ├── app/Http/Middleware/        CekPeran (hak akses per peran)
│   ├── app/Http/Requests/          validasi isian
│   ├── app/Models/                 entitas sesuai class diagram DPPL
│   ├── app/Services/               logika use case (Autentikasi, RegistrasiKedatangan, Penimbangan, Lot)
│   ├── database/migrations/        struktur tabel sesuai ERD DPPL
│   ├── database/seeders/           data awal dan data demo
│   └── routes/api.php              daftar alamat API
└── sipeli-mobile/                  aplikasi Expo
    ├── App.js                      pemulihan sesi dan pemilihan layar sesuai peran
    └── src/
        ├── config.js               alamat server (IP laptop)
        ├── api.js                  penghubung ke API
        ├── components/             komponen bersama (kolom, tombol, QR, pilih waktu)
        └── screens/
            ├── LoginScreen.js
            ├── kedatangan/         tampilan UC-01
            └── penimbangan/        tampilan UC-02
```

## Cara menjalankan

### Prasyarat

- PHP 8.3 dan Composer
- XAMPP (MariaDB/MySQL)
- Node.js 22.13 atau lebih baru
- Aplikasi Expo Go di HP Android
- Laptop dan HP tersambung ke jaringan Wi-Fi yang sama

### 1. Backend

1. Nyalakan MySQL dari XAMPP Control Panel, lalu buat database kosong bernama `sipeli` (lewat phpMyAdmin).
2. Jalankan perintah berikut di folder `sipeli-backend`:

   ```
   composer install
   copy .env.example .env
   php artisan key:generate
   php artisan migrate:fresh --seed
   php artisan serve --host=0.0.0.0 --port=8000
   ```

   Bila memakai Git Bash, ganti `copy` dengan `cp`. Sesuaikan `DB_USERNAME` dan `DB_PASSWORD` di `.env` bila berbeda dari bawaan XAMPP (`root` tanpa kata sandi).

3. Buka `http://127.0.0.1:8000/up` di browser. Halaman "Application up" menandakan server berjalan.

### 2. Aplikasi mobile

1. Jalankan `npm install` di folder `sipeli-mobile`.
2. Cari IP laptop dengan `ipconfig` (baris IPv4 Address pada adaptor Wi-Fi), lalu ubah `src/config.js`:

   ```js
   export const API_URL = 'http://IP_LAPTOP:8000/api';
   ```

3. Jalankan `npx expo start --clear`, lalu pindai QR yang muncul dengan aplikasi Expo Go.

Bila HP tidak bisa membuka `http://IP_LAPTOP:8000/up`, izinkan `php.exe` pada Windows Firewall untuk jaringan Private.

## Akun demo

Kata sandi semua akun: `sipeli12345`

| Nama pengguna | Peran | Tampilan setelah login |
|---|---|---|
| `pendata` | Petugas Pendataan | Form Kedatangan & Tangkapan |
| `timbang` | Petugas Timbang | Form Penimbangan & Label Lot |
| `operator`, `pembeli`, `kasir`, `pimpinan`, `admin` | Peran lain | Beranda sementara (belum dikembangkan) |

`php artisan migrate:fresh --seed` juga mengisi data demo: riwayat lelang 30 hari terakhir dan 6 kedatangan hari ini.

| Nomor | Status | Kegunaan saat demo |
|---|---|---|
| KDT-00041, KDT-00042 | DITIMBANG | Contoh kedatangan yang semua barisnya sudah menjadi lot |
| KDT-00043 sampai KDT-00045 | TERDAFTAR | Siap ditimbang oleh Petugas Timbang |
| KDT-00046 | DRAF | Contoh draf yang dapat dilengkapi oleh Petugas Pendataan |

## Daftar API

Semua alamat berawalan `/api`. Selain `/masuk` dan `/foto-lot/{nama}`, setiap permintaan wajib menyertakan header `Authorization: Bearer <token>`.

| Metode | Alamat | Peran | Fungsi |
|---|---|---|---|
| POST | `/masuk` | Semua | Login, menghasilkan token |
| GET | `/saya` | Semua | Data pengguna yang sedang login |
| POST | `/keluar` | Semua | Logout, menghapus token perangkat ini |
| GET | `/kapal?cari=` | Petugas Pendataan, Petugas Timbang | Mencari kapal (minimal 3 huruf) |
| GET | `/referensi/tangkapan` | Petugas Pendataan, Petugas Timbang | Daftar jenis ikan dan grade mutu |
| GET | `/kedatangan` | Petugas Pendataan, Petugas Timbang | Daftar kedatangan hari ini |
| POST | `/kedatangan` | Petugas Pendataan | Menyimpan kedatangan baru (UC-01) |
| GET | `/kedatangan/{id}` | Petugas Pendataan | Detail kedatangan (untuk melengkapi draf) |
| PUT | `/kedatangan/{id}` | Petugas Pendataan | Memperbarui draf kedatangan |
| GET | `/penimbangan/{kode}` | Petugas Timbang | Data kedatangan untuk ditimbang, beserta usulan harga |
| POST | `/lot` | Petugas Timbang | Menyimpan hasil timbang dan membuat lot lelang (UC-02) |
| GET | `/lot/{kode}` | Petugas Pendataan, Petugas Timbang, Operator Lelang | Data lot (dipakai untuk cetak ulang label) |
| POST | `/lot/{id}/cetak` | Petugas Timbang | Mencatat pencetakan label |
| POST | `/lot/{id}/foto` | Petugas Timbang | Mengunggah atau mengganti foto lot (JPG/PNG/WEBP, maks. 5 MB, sebelum lot dilelang) |
| GET | `/foto-lot/{nama}` | Tanpa token | Menampilkan foto lot (nama berkas acak) |

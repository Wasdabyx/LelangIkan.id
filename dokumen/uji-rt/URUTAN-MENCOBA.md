# Urutan Mencoba Fitur Real-Time (dari nol sampai terbukti)

Dokumen ini **berbeda** dari `dokumen/uji-rt/UJI-REAL-TIME-MOBILE.md`:

- Berkas itu = **pengujian** (membuktikan kode benar, termasuk tes otomatis dan skrip pendengar kanal).
- Berkas ini = **mencoba sendiri** seperti pengguna sungguhan, lewat layar, tanpa `curl` dan
  tanpa berkas uji. Semua langkah di bawah bisa dilakukan dengan ponsel + peramban saja.

Jalankan **berkas ini dulu** untuk memastikan fiturnya terasa benar, baru berkas uji di atas
bila Anda ingin bukti teknis tambahan.

Perkiraan waktu: **15–20 menit** (jalur cepat). Bahan: **1 ponsel** (Expo Go) + **1 laptop**.
Langkah 5 butuh perangkat kedua, dan boleh dilewati.

---

## 0. Data yang sudah tersedia setelah `migrate:fresh --seed`

Angka ini saya periksa langsung dari basis data hasil seeder, bukan perkiraan:

| Hal | Nilai |
|---|---|
| Lot siap lelang (MENUNGGU_LELANG + ada harga) | **1 lot**: `PLR-261122-0006`, harga pembukaan Rp34.000/kg |
| Sesi yang dibuat skenario | 4: `PLR-261122-0001` **aktif**, `0002` lunas, `0003` dibayar sebagian, `0004` tidak terjual |
| Pembeli yang sudah memantau | akun `pembeli` memantau 4 lot: `0001`–`0004` |
| Sesi `0001` | lot `PLR-261122-0001`, berat **152,35 kg**, harga pembukaan **Rp28.500/kg**, kenaikan minimum **Rp1.000/kg** → tawaran pertama **Rp28.500/kg** ≈ **Rp4.341.975** |
| Saldo akun `pembeli` | **belum ada** (0) — lihat peringatan di bawah |

> ### ⚠️ Peringatan yang menghemat 10 menit Anda
>
> Seeder **tidak** memberi saldo kepada akun `pembeli`. Aturan FR-21 menolak tawaran yang
> nilainya melebihi saldo + limit kredit, jadi **tawaran pertama pasti ditolak** dengan pesan
> *"Daya beli tidak cukup"* bila Anda melompat langsung ke langkah 5. Itu bukan kerusakan;
> itu aturan yang bekerja.
>
> **Karena itu, lakukan langkah 2b di bawah sebelum mencoba menawar.**

**Artinya:** begitu seed selesai, akun `pembeli` **langsung bisa masuk Ruang Lelang** sesi
`PLR-261122-0001` tanpa memesan apa pun lagi. Lot `PLR-261122-0006` disiapkan khusus sebagai
**lot segar** untuk skenario membuka sesi.

> Sesi `0001` aktif dengan durasi 600 detik **dihitung dari saat seeder dijalankan**. Bila Anda
> lebih dari 10 menit baru mencoba, sesi itu sudah ditutup otomatis oleh penjadwal. Solusinya:
> jalankan ulang `php artisan migrate:fresh --seed`, lalu langsung mulai.

---

## 1. Nyalakan tiga hal (urutan ini penting)

| # | Di mana | Perintah / tindakan |
|---|---|---|
| 1 | Terminal A | `cd sipeli-backend` → `php artisan serve --host=0.0.0.0 --port=8000` |
| 2 | Terminal B | `php artisan reverb:start` |
| 3 | Laptop | `php artisan migrate:fresh --seed` (boleh sebelum 1 dan 2) |

Terminal B **wajib**. Tanpa Reverb, aplikasi tetap jalan tetapi semua perubahan datang
terlambat ±2 detik lewat polling — dan Anda tidak akan bisa membedakan fitur baru bekerja
atau tidak.

Terminal C opsional: `php artisan schedule:work` (menutup sesi otomatis saat waktu habis).

### Nyalakan aplikasi mobile

```bash
cd sipeli-mobile
npx expo start --clear
```

Pastikan `.env` mobile memakai **IP laptop**, bukan `localhost`:

```
EXPO_PUBLIC_API_URL=http://192.168.x.x:8000/api
EXPO_PUBLIC_REVERB_HOST=192.168.x.x
EXPO_PUBLIC_REVERB_PORT=8080
```

Salah satu yang paling sering terjadi: `EXPO_PUBLIC_REVERB_HOST` lupa diubah → aplikasi
"hidup" tapi selalu terlambat 2 detik. Tanda pastinya ada di langkah 6 nanti.

---

## 2. Masuk sebagai pembeli di ponsel

1. Buka Expo Go → aplikasi LelangIkan.
2. Masuk: `pembeli` / `sipeli12345`.
3. Anda mendarat di tab **Lot**. Pindah ke tab **Lelang**.
4. Di tab Lelang, **sesi `PLR-261122-0001` sudah muncul** dan berstatus berjalan.
   Ketuk untuk masuk **Ruang Lelang**.

Yang harus terlihat: kode lot, harga tertinggi, sisa waktu yang **berdetak setiap detik**,
dan status **"Anda belum menawar"**.

> Kalau tab Lelang kosong: berarti sesi `0001` sudah ditutup (lewat 10 menit). Ulangi
> `migrate:fresh --seed` lalu masuki lagi.

---

## 2b. Isi saldo pembeli lewat Kasir (wajib sebelum menawar)

Lewati langkah ini hanya bila Anda tidak akan mencoba menawar.

1. Di laptop, buka jendela **penyamaran** (`Ctrl+Shift+N`). Ini penting: langkah 3 akan memakai
   jendela biasa untuk akun operator, dan satu jendela hanya bisa memuat satu sesi masuk.
2. Di jendela penyamaran, masuk: `kasir` / `sipeli12345` → menu **Saldo Pembeli**
   (`/dasbor/kasir/saldo`).
3. Isi kotak **"Cari pembeli"** dengan `pembeli` → tekan **Cari**.
4. Pada hasil pencarian, klik nama **Pembeli Demo** → panel rincian terbuka di bawah.
5. Pada kotak **"Mutasi saldo baru"**:
   - **Jenis**: `Setor (tambah saldo)`
   - **Nominal (Rp)**: `5000000`
   - **Metode**: `TUNAI`
   - **Keterangan**: `Uji coba URUTAN-MENCOBA`
   - Tekan **Simpan mutasi**.
6. **Harapan:** muncul pesan sukses, saldo pembeli menjadi **Rp5.000.000**.
   *Setor langsung berlaku; hanya PENYESUAIAN yang menunggu persetujuan pimpinan.*

> Sudah saya jalankan sendiri terhadap basis data hasil seeder: SETOR Rp5.000.000 langsung
> berstatus `BERHASIL` dan saldo menjadi Rp5.000.000, lalu tawaran Rp28.500/kg diterima.

## 3. Masuk sebagai operator di laptop

1. Buka `http://127.0.0.1:8000` → halaman masuk.
2. Masuk: `operator` / `sipeli12345`.
3. Anda ada di **Antrean Lelang**. Halaman ini memuat daftar lot siap lelang —
   seharusnya **hanya satu baris**: `PLR-261122-0006`.

Perhatikan baris itu punya: pilihan mode **Naik/Turun**, kolom **durasi** (bawaan **120 detik**, dari konfigurasi `durasi_lelang_bawaan`), kolom **kenaikan minimum** (usulan ALG-05), dan tombol **Buka**.

---

## 4. Coba: sesi baru muncul di ponsel tanpa menyentuh layar

**Yang diuji:** peristiwa `SESI_DIMULAI` pada kanal **`private-tpi.1`**. Ini yang membuat tab
**Lelang** bertambah sendiri tanpa ditarik manual.

1. Di ponsel, buka tab **Lelang**. Catat isinya (setelah seed: **1 sesi** — `PLR-261122-0001`).
2. Di laptop, pada baris `PLR-261122-0006`: mode **Naik**, **durasi `30` detik**, kenaikan
   minimum biarkan usulan ALG-05, lalu tekan **Buka**.

   > **Kenapa 30 detik?** Satu lot hanya bisa dibuka sekali. Dengan durasi 30 detik, Anda bisa
   > langsung melanjutkan ke langkah 6 (perpanjangan) tanpa menunggu 10 menit. Kalau Anda lebih
   > suka mengamati sesi panjang, isi `120` — tetapi langkah 6 harus memakai sesi
   > `PLR-261122-0001` dan menunggu sampai sisa waktunya ≤ 15 detik.
3. **Lihat ponsel tanpa menyentuhnya.**

| Hasil | Arti |
|---|---|
| Dalam ±1 detik tab Lelang menampilkan sesi baru `PLR-261122-0006` | ✅ `SESI_DIMULAI` diterima lewat kanal `private-tpi.1` |
| Berubah, tetapi setelah ±2 detik | Kanal putus, aplikasi memakai polling pengganti (2 detik). Periksa Reverb & IP |
| Tidak berubah sampai layar ditarik manual | Layar tidak berlangganan, atau aplikasi masih versi lama → `npx expo start --clear` |

### Bonus di langkah yang sama: `LOT_DIPANTAU_DIBUKA`

Lot `PLR-261122-0001` **sudah dipantau** akun `pembeli`. Karena itu, bila yang Anda buka
adalah lot yang dipantau, server juga mengirim `LOT_DIPANTAU_DIBUKA` ke kanal
**`private-pembeli.{id}`**. Untuk melihatnya, ulangi langkah 4 dengan lot yang dipantau —
tetapi keempat lot skenario sudah terpakai, jadi **lewati bonus ini** dan cukup catat bahwa
jalur pantauan sudah diuji terpisah (lihat berkas uji `UJI-REAL-TIME-MOBILE.md`, Tier 3).

## 5. Coba: tawaran lawan terlihat seketika (opsional)

**Yang diuji:** `PENAWARAN_BARU` — sudah ada sebelum perubahan ini, disertakan sebagai
pembanding supaya Anda bisa membedakan "seketika" dan "terlambat".

**Menawar hanya bisa dari aplikasi mobile** — antarmuka web tidak punya tombol tawar.
Jadi Anda butuh **dua perangkat** untuk melihat tawaran orang lain:

- **Perangkat A** (ponsel): akun `pembeli`, sudah di Ruang Lelang.
- **Perangkat B**: akun pembeli lain. Buka lewat Expo web (`npx expo start` lalu tekan `w`
  di terminal, buka alamat yang muncul) atau ponsel kedua.

Di Perangkat B, masuk ke sesi yang sama dan ajukan tawaran. Perangkat A harus berubah dalam
±1 detik (label tertinggi + "Anda tertinggal" pada penawar lama).

**Punya satu perangkat saja?** Lewati langkah ini. `PENAWARAN_BARU` bukan bagian perubahan
ini dan sudah terbukti pada versi sebelumnya.

---

## 6. Coba: sisa waktu melonjak saat sesi diperpanjang ★

**Ini inti fitur baru.** Sebelum perubahan ini, angka sisa waktu **tidak** ikut berubah saat
sesi diperpanjang; layar tetap menghitung mundur ke waktu tutup yang lama.

### Kenapa Anda harus menunggu, bukan sekadar menawar

Perpanjangan **hanya** terpicu bila tawaran masuk saat sisa waktu **di bawah 15 detik**
(`ambang_perpanjangan_detik`). Sesi `PLR-261122-0001` dibuka dengan **600 detik**, jadi Anda
memang perlu menunggu beberapa menit. Ini bawaan aturan ALG-03, bukan pengaturan uji.

Ada tiga cara:

| Cara | Langkah | Cocok untuk |
|---|---|---|
| **Paling cepat** (disarankan) | Buka sesi `PLR-261122-0006` dengan durasi **30 detik** (durasi terpendek yang diizinkan) pada langkah 4 | Selesai di bawah 1 menit |
| **Cepat, tanpa buka sesi baru** | Pakai sesi `0001`, tetapi majukan jamnya dulu dari terminal (lihat perintah di bawah) | Sudah terlanjur memakai `0001` |
| **Lambat** | Pakai sesi `0001` apa adanya: 600 detik, tunggu sampai sisa ≤ 15 detik | Ingin melihat alur asli tanpa menyentuh data |

**Memajukan jam sesi dari terminal** (hanya mengubah jam tutup, bukan aturan):

```bash
cd sipeli-backend
php artisan tinker --execute="$s=App\Models\SesiLelang::find(1); $s->waktu_selesai_rencana=now()->addSeconds(10); $s->save();"
```

Setelah itu sisa waktu di layar langsung melorot ke `00:10` — jadi Anda tidak perlu menunggu.

### Langkah mencoba

1. Di laptop, pilih sesi yang sesuai lalu **tekan "Buka"** (untuk `0006`, isi durasi `30`).
   Ini saat mulai berdetak — jangan menunggu sesi sudah hampir habis.
2. Di ponsel, segera masuk **Ruang Lelang** sesi tersebut. Sisa waktu berdetak di layar dan
   turun sampai **≤ 15 detik** (atau langsung `00:10` bila Anda memakai perintah `tinker` di atas).
3. Ketik harga tawarannya **selagi menunggu** — jangan mengisi formulir setelah waktunya habis
   (sesi sudah tertutup dan tawaran akan ditolak). Angka yang dipakai:
   - sesi `PLR-261122-0001` → **Rp28.500/kg** (harga pembukaan, belum ada penawar);
   - sesi `PLR-261122-0006` → **Rp34.000/kg** (harga pembukaannya).
   Angka yang sama juga tertulis sebagai "tawaran minimal" di layar.
4. **Tekan ajukan tawaran tepat saat sisa waktu di bawah 15 detik.**
5. **Perhatikan angka sisa waktu, jangan ke rincian lain.**

| Hasil | Arti |
|---|---|
| Sisa waktu **melonjak ke ±29 detik** dalam ±1 detik | ✅ `SESI_DIPERPANJANG` diterima langsung. **Ini bukti terkuat fitur ini** |
| Melonjak, tetapi terlihat setelah ±2 detik | Kanal putus; aplikasi memakai polling 2 detik. Periksa Reverb & IP |
| Tetap menghitung mundur ke 0 lalu sesi tertutup | Perpanjangan tidak terpicu: tawaran masuk terlalu awal, **atau** kuota 5 kali sudah habis, **atau** peristiwa tidak diterima |

> **Angka acuan yang sudah saya ukur** pada basis data hasil seeder: sisa waktu 9 detik →
> ajukan tawaran Rp29.500/kg → **sisa waktu 29 detik, perpanjangan ke-1**. Persis segitu yang
> harus Anda lihat (boleh berselisih 1–2 detik karena waktu berjalan).

### Membuktikan bahwa lonjakan itu dari kanal, bukan kebetulan

Matikan Terminal B (Ctrl+C) lalu ulangi percobaan. Kali ini lonjakan **baru terlihat setelah
±2 detik** karena datang dari polling. Bila dengan Reverb hidup lonjakannya ±1 detik dan tanpa
Reverb ±2 detik, berarti peristiwa kanal benar-benar bekerja.

## 7. Coba: pemenang muncul saat sesi ditutup ★

**Yang diuji:** `HASIL_LELANG` dan `SESI_DITUTUP` pada kanal `private-tpi.1`.

Prasyarat: Anda memegang tawaran tertinggi di sesi itu (langkah 6 sudah membuat Anda
penawar tertinggi).

1. Di ponsel, tetap di **Ruang Lelang** sesi tersebut.
2. Di laptop, buka menu **Sesi Lelang** (`/dasbor/operator/sesi`) → pilih sesi yang sama →
   halaman **Pantau Sesi** (`/dasbor/operator/sesi/{id}`).
3. Gulir ke kotak **"Tutup sesi lebih awal"** → isi **Alasan** (minimal 5 karakter, mis.
   `Uji coba penutupan`) → tekan **Tutup sesi** → konfirmasi.
4. **Lihat ponsel tanpa menyentuhnya.**

| Hasil | Arti |
|---|---|
| Dalam ±1 detik layar menampilkan **pemenang dan harga akhir** | ✅ `HASIL_LELANG` diterima |
| Layar kembali ke daftar, sesi bertanda selesai | ✅ `SESI_DITUTUP` diterima |
| Tidak ada perubahan sampai layar ditarik manual | Periksa koneksi Reverb, `.env` mobile, dan cache Metro |

> **Catat apa yang Anda lihat.** Bila HASIL muncul tetapi tagihan tidak bertambah di tab
> **Tagihan**, itu masalah berbeda (bukan real-time) dan perlu saya periksa terpisah.

## 8. Coba: tetap berjalan saat kanal mati (NFR-10)

1. Saat sesi masih aktif dan layar di Ruang Lelang, **hentikan Terminal B** (Ctrl+C).
2. **Harapan:** sisa waktu tetap berdetak, sekarang tiap ±2 detik (aplikasi berpindah ke
   polling pengganti).
3. Jalankan lagi `php artisan reverb:start`.
4. **Harapan:** dalam ±5 detik pembaruan kembali seketika (±1 detik).

Ini memastikan fitur baru **tidak merusak** jaminan lama: kanal putus tidak membuat layar beku.

---

## 9. Coba: perilaku saat aplikasi ditutup (antrean luring, NFR-09)

Ini bukan bagian fitur real-time, tetapi cepat dicek dan sering ditanya penguji:

1. Masuk sebagai `pendata` / `sipeli12345` di ponsel.
2. **Matikan Wi-Fi ponsel** (mode pesawat).
3. Isi **Form Kedatangan** lalu simpan.
4. **Harapan:** muncul pesan data tersimpan di perangkat, bukan galat.
5. **Tutup paksa aplikasi**, buka lagi, nyalakan Wi-Fi.
6. **Harapan:** panel di layar Kedatangan menyebut "N kedatangan tersimpan di perangkat,
   belum terkirim", lalu terkirim otomatis. Tidak ada data ganda.

---

## Ringkasan: apa yang membuktikan fitur ini berhasil

| Langkah | Peristiwa | Wajib dicoba? |
|---|---|---|
| 4 | `LELANG_DIMULAI` → daftar bertambah | Ya |
| 6 | `SESI_DIPERPANJANG` → sisa waktu melonjak | **Ya, ini intinya** |
| 7 | `HASIL_LELANG` + `SESI_DITUTUP` → pemenang muncul | Ya |
| 8 | Kanal mati → tetap jalan via polling | Ya (menjaga NFR-10) |
| 5 | `PENAWARAN_BARU` | Hanya bila ada 2 perangkat |
| 9 | Antrean luring | Opsional |

## Bila ada yang tidak sesuai

Kirimkan ke saya: langkah ke berapa, yang terlihat di layar, dan **isi Terminal B**
(Reverb). Bila Reverb mencetak baris pengiriman peristiwa, berarti masalahnya di klien;
bila tidak mencetak apa pun, masalahnya di server atau konfigurasi.

## Perbaikan sesudah percobaan pertama Anda

Dua hal yang Anda laporkan pada percobaan pertama sudah diperbaiki dan diuji ulang:

| Laporan | Sebab | Perbaikan |
|---|---|---|
| "Please enter a valid value. The two nearest valid values are 4999901 and 5000001" saat mengisi saldo | Atribut `min="1"` dengan `step="100"` membuat peramban hanya menerima 1, 101, 201, … sehingga angka bulat seperti 5000000 ditolak sebelum sampai ke server | `min` disetel `0` (server tetap menolak Rp0) di Saldo Pembeli, Tagihan, dan dua formulir Settlement |
| Sisa waktu lelang tidak bergerak | Angka `sisa_detik` hanya diperbarui saat data baru tiba (tiap 2 detik saat kanal putus, atau hanya saat ada peristiwa saat kanal sehat) | Jam kini berdetak sendiri tiap detik dari angka terakhir + waktu terima, dan dicocokkan ulang tiap detik. Saat kanal sehat, peristiwa tetap terasa seketika |

## Yang belum bisa dicoba lewat cara ini

- **Notifikasi "tersalip" (`OUTBID`) secara real-time.** Server mengirimnya, tetapi aplikasi
  belum mendengarkannya. Saat ini tanda "Anda tertinggal" tetap muncul — tetapi berasal dari
  data status, bukan dari peristiwa. Ini kandidat pengembangan berikutnya.
- **`SETTLEMENT_DIBAYAR`**: menyasar kanal nelayan, dan aplikasi mobile belum punya layar
  nelayan.

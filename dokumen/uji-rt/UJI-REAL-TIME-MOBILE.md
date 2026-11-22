# Alur Pengujian — Peristiwa Real-Time di Aplikasi Mobile

**Untuk commit** `Lengkapi peristiwa real-time pada aplikasi mobile dan uji kontraknya`
**Tanggal penulisan:** 22 November 2026 · **Perkiraan waktu:** 25–35 menit

Dokumen ini berisi urutan pengujian yang bisa dijalankan sendiri: dari yang paling murah
(otomatis) sampai yang membuktikan perilaku di layar ponsel (manual).

> **Batas kejujuran.** Saya (agen) tidak dapat menjalankan aplikasi mobile maupun membuka
> kanal WebSocket dari lingkungan kerja saya. Yang sudah saya buktikan hanya: (a) sintaks
> seluruh 47 berkas JS mobile valid, (b) tes kontrak server lulus 5/5, dan (c) seluruh 45
> tes backend lulus. **Langkah 3 dan 4 di bawah adalah yang membuktikan perilaku di layar
> dan belum pernah dijalankan** — itu tugas Anda.

---

## Ringkasan tingkatan

| Tingkat | Yang dibuktikan | Butuh apa | Waktu |
|---|---|---|---|
| 1 | Nama peristiwa & kanal di sisi server tetap sesuai yang didengar klien | PHP + MariaDB | 1 menit |
| 2 | Tidak ada regresi pada seluruh backend | sama | 1 menit |
| 3 | Peristiwa benar-benar terkirim lewat Reverb (di luar PHPUnit) | 2 terminal | 5 menit |
| 4 | Perilaku di layar ponsel: sisa waktu, pemenang, daftar menyegar | HP + Expo Go | 20 menit |

---

## Persiapan (sekali saja)

### A. Backend + basis data

```bash
cd /c/LelangIkan.id/sipeli-backend
composer install
cp .env.example .env          # lewati bila .env sudah ada
php artisan key:generate
php artisan migrate:fresh --seed
```

`migrate:fresh` **menghapus seluruh isi database** dan mengisi ulang data demo. Itu justru
diinginkan di sini: skenario di bawah butuh lot yang belum pernah dilelang.

Proyek ini hanya punya **satu seeder** (`DatabaseSeeder`): data awal (peran, TPI, akun contoh,
konfigurasi, tarif retribusi, jenis ikan, grade, pemilik kapal, kapal) sekaligus data demo
(riwayat 30 hari, enam kedatangan hari ini, dan skenario lelang hari ini).

Pastikan `.env` memuat pengaturan siaran (sudah ada di `.env.example`):

```
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=lelangikan-local
REVERB_APP_KEY=lelangikan-local-key
REVERB_APP_SECRET=lelangikan-local-secret-key-2026
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
```

### B. Tiga proses yang harus hidup bersamaan

Buka **tiga terminal** di `sipeli-backend`:

| Terminal | Perintah | Kegunaan |
|---|---|---|
| 1 | `php artisan serve --host=0.0.0.0 --port=8000` | API |
| 2 | `php artisan reverb:start` | kanal WebSocket (wajib; tanpa ini real-time mati) |
| 3 | `php artisan schedule:work` | menutup sesi otomatis saat waktu habis |

> Tanpa terminal 2, aplikasi **tetap berjalan** tetapi jatuh ke polling 2 detik (NFR-10).
> Jadi bila langkah 4 "terasa lambat 2 detik", periksa dulu apakah Reverb hidup.

### C. Aplikasi mobile

```bash
cd /c/LelangIkan.id/sipeli-mobile
npm install
cp .env.example .env
```

Isi `.env` dengan alamat IP laptop Anda (bukan `localhost`):

```
EXPO_PUBLIC_API_URL=http://192.168.x.x:8000/api
EXPO_PUBLIC_REVERB_HOST=192.168.x.x
EXPO_PUBLIC_REVERB_PORT=8080
EXPO_PUBLIC_REVERB_SCHEME=http
```

Lalu `npx expo start --clear` dan pindai QR dengan Expo Go.

**Yang sering gagal dan sebabnya:**

| Gejala | Sebab |
|---|---|
| Aplikasi bisa memuat data tapi tidak pernah "hidup" | `EXPO_PUBLIC_REVERB_HOST` masih `YOUR_COMPUTER_IP` atau `localhost` — HP tidak bisa menjangkau itu |
| Berubah hanya tiap ~2 detik | Reverb mati, atau port 8080 diblokir Windows Firewall. Izinkan `php.exe` pada jaringan Private |
| Gagal masuk sama sekali | Laptop dan HP tidak di jaringan Wi-Fi yang sama |

---

## Tingkat 1 — Tes kontrak real-time (otomatis, 5 tes)

> **Tingkat 1 dan 2 hanya bisa dijalankan bila salinan Anda memuat folder `tests/`.**
> Berkas pengujian tidak disertakan di repositori GitHub; bila Anda baru meng-clone repo,
> folder itu tidak ada dan `php artisan test` akan menjawab "No tests found". Bila demikian,
> **lewati Tingkat 1 dan 2** dan langsung ke Tingkat 3 — keduanya hanya menjaga kode, bukan
> membuktikan fitur.

```bash
cd /c/LelangIkan.id/sipeli-backend
php artisan test --filter=RealTimeKontrakTest
```

**Harus keluar:** `Tests: 5 passed (15 assertions)`.

Yang dijaga oleh tiap tes:

| Nama tes | Menjaga |
|---|---|
| `membuka sesi menyiarkan lelang dimulai pada kanal tpi` | `LELANG_DIMULAI` terkirim ke `tpi.{id}` |
| `perpanjangan waktu disiarkan dengan nama yang didengar klien` | `PENAWARAN_BARU` **dan** `SESI_DIPERPANJANG` saat sisa waktu melewati ambang |
| `penawar yang tersalip menerima outbid` | `OUTBID` terkirim saat ada penawar baru (FR-22) |
| `menutup sesi menyiarkan hasil dan penutupan` | `HASIL_LELANG` dan `SESI_DITUTUP` |
| `kanal pribadi pembeli dan nelayan bernama sesuai yang didengar klien` | nama kanal `pembeli.{id}` dan `nelayan.{id}` |

**Kalau gagal, artinya:** nama peristiwa atau nama kanal di server berubah, dan aplikasi
mobile akan berhenti bereaksi **tanpa pesan galat apa pun**. Jangan diabaikan.

---

## Tingkat 2 — Tidak ada regresi (otomatis, 45 tes)

```bash
php artisan test
```

**Harus keluar:** `Tests: 45 passed (326 assertions)`.

Angka pembanding: sebelum perubahan ini **40 tes / 311 asersi**. Selisih 5 tes / 15 asersi
tepat berasal dari `RealTimeKontrakTest`.

---

## Tingkat 3 — Bukti kanal benar-benar terkirim (manual, 2 terminal)

PHPUnit mematikan penyiaran (`Event::fake`), jadi tingkat 1 **tidak** membuktikan data
sampai ke klien. Langkah ini membuktikannya tanpa perlu HP.

### Langkah 3.1 — Pasang pendengar kanal

Buat berkas `dengar.js` **di luar** folder repo (mis. `C:\Users\Wasdabyx\dengar.js`):

```js
// Mendengarkan kanal privat tpi.{id} lewat Reverb, lalu mencetak setiap peristiwa.
const Pusher = require('pusher-js');

const KEY    = 'lelangikan-local-key';
const HOST   = process.env.HOST || '127.0.0.1';
const TOKEN  = process.env.TOKEN;      // token Sanctum akun operator
const ID_TPI = Number(process.env.ID_TPI || 1);

const pusher = new Pusher(KEY, {
  wsHost: HOST, wsPort: 8080, wssPort: 8080,
  forceTLS: false, enabledTransports: ['ws'],
  cluster: 'mt1',
  authorizer: (kanal) => ({
    authorize: (socketId, cb) => {
      fetch(`http://${HOST}:8000/api/broadcasting/auth`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${TOKEN}`, 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ socket_id: socketId, channel_name: kanal.name }),
      })
        .then((r) => r.json())
        .then((d) => cb(null, d))
        .catch((e) => cb(e, null));
    },
  }),
});

pusher.connection.bind('connected', () => {
  console.log('tersambung ke Reverb');
  pusher.subscribe(`private-tpi.${ID_TPI}`).bind_global((nama, data) => {
    console.log(new Date().toISOString().slice(11, 23), '<=', nama, JSON.stringify(data));
  });
});
pusher.connection.bind('error', (e) => console.log('galat:', e));
```

Siapkan di terminal baru:

```bash
cd /c/Users/Wasdabyx
npm init -y
npm install pusher-js
```

### Langkah 3.2 — Ambil token operator dan id TPI

```bash
curl -s -X POST http://127.0.0.1:8000/api/masuk \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"nama_pengguna":"operator","kata_sandi":"sipeli12345","perangkat":"uji-rt"}'
```

Salin `data.token` dan `data.pengguna.id_tpi` dari jawabannya.

### Langkah 3.3 — Jalankan pendengar

```bash
TOKEN="<token>" ID_TPI=1 node dengar.js
```

Harus mencetak `tersambung ke Reverb`. Endpoint `POST /api/broadcasting/auth` sudah
terverifikasi terdaftar (`GET|POST api/broadcasting/auth`, didaftarkan lewat
`withBroadcasting()` di `bootstrap/app.php`). Bila tidak tersambung: Reverb belum jalan,
atau endpoint itu menolak token (401) — periksa `storage/logs/laravel.log`.

### Langkah 3.4 — Picu peristiwa dari terminal lain

Buka sesi pada salah satu lot yang belum dilelang:

```bash
# Ambil satu lot siap lelang (perhatikan "id_lot" dan "harga_pembukaan")
curl -s http://127.0.0.1:8000/api/lot/siap-lelang \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" | head -c 600

# Buka sesi 60 detik
curl -s -X POST http://127.0.0.1:8000/api/sesi \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"id_lot": <ID_LOT>, "durasi_detik": 60, "mode": "NAIK", "kenaikan_minimum": 500}'
```

**Yang harus muncul di `dengar.js`** (dalam urutan ini):

```
… <= LELANG_DIMULAI  {"id_sesi":…,"kode_lot":"…","harga_pembukaan":…,"waktu_selesai":"…"}
… <= LOT_DIPANTAU_DIBUKA {"id_lot":…,"kode_lot":"…","id_sesi":…,"mode":"NAIK"}
```

`LOT_DIPANTAU_DIBUKA` **hanya** muncul bila ada pembeli yang memantau lot itu. Untuk melihatnya,
tambahkan pemantauan lebih dulu (pakai token akun `pembeli`):

```bash
curl -s -X POST http://127.0.0.1:8000/api/pantauan \
  -H "Authorization: Bearer $TOKEN_PEMBELI" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"id_lot": <ID_LOT>, "notifikasi_aktif": true}'
```

Lalu ajukan tawaran (token akun `pembeli`) — harus muncul `PENAWARAN_BARU`:

```bash
curl -s -X POST http://127.0.0.1:8000/api/sesi/<ID_SESI>/penawaran \
  -H "Authorization: Bearer $TOKEN_PEMBELI" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"harga_per_kg": 25000, "kunci_idempotensi": "11111111-1111-4111-8111-111111111111"}'
```

> Field tawaran pada rute resmi adalah `harga_per_kg` (atau `nominal`), bukan `nilai`;
> lihat `RuteResmiController::penawaran`. Angka `harga_per_kg` harus minimal
> `harga_minimum_berikut` — nilainya terlihat pada jawaban `GET /api/sesi/{id}`.

Terakhir, tutup sesi (token akun `operator`) — harus muncul `HASIL_LELANG` lalu `SESI_DITUTUP`:

```bash
curl -s -X POST http://127.0.0.1:8000/api/sesi/<ID_SESI>/tutup \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"alasan_penutupan":"selesai"}'
```

**Kriteria lulus tingkat 3:** kelima nama peristiwa di atas tercetak oleh `dengar.js`.
Bila tes Tingkat 1 lulus tetapi langkah ini tidak, masalahnya ada di **konfigurasi Reverb**,
bukan di kode.

---

## Tingkat 4 — Perilaku di layar ponsel (manual, ini yang paling menentukan)

Siapkan **dua perangkat masuk bersamaan**:

| Perangkat | Akun | Layar |
|---|---|---|
| HP (Expo Go) | `pembeli` / `sipeli12345` | tab **Lelang** → pilih sesi → Ruang Lelang |
| Peramban di laptop | `operator` / `sipeli12345` | `http://127.0.0.1:8000` → Antrean lot |

Pastikan minimal satu lot berstatus siap lelang. Bila belum ada, lakukan alur UC-01 → UC-02
lebih dulu, atau jalankan ulang `php artisan migrate:fresh --seed`.

### Skenario 1 — Sesi dibuka muncul di daftar (≈2 detik)

1. Di HP, buka tab **Lelang**. Catat jumlah "N sesi berjalan".
2. Di peramban, operator membuka sesi pada satu lot, durasi **60 detik**.
3. **Harapan:** dalam ±1 detik daftar di HP bertambah, tanpa menarik layar.
   **Bukti baru:** sebelumnya daftar hanya berubah lewat polling; sekarang peristiwa
   `LELANG_DIMULAI` dan `LOT_DIPANTAU_DIBUKA` yang memicunya.

### Skenario 2 — Tawaran lawan terlihat seketika (≈1 detik)

1. Di HP, masuk ke Ruang Lelang sesi tersebut.
2. Dari terminal, ajukan tawaran dengan akun `pembeli` lain (lihat perintah di 3.4).
3. **Harapan:** harga tertinggi di layar naik dalam ±1 detik, dan bila Anda tadi penawar
   tertinggi, muncul tanda **"Anda tertinggal"** + getar.

### Skenario 3 — Sisa waktu bertambah saat diperpanjang (inti perubahan ini)

1. Sesi berdurasi 60 detik. Tunggu sampai sisa waktu **di bawah 15 detik** (ambang
   perpanjangan).
2. Ajukan satu tawaran.
3. **Harapan:** sisa waktu **melonjak kembali** ke sekitar 30 detik di layar, dalam ±1 detik,
   tanpa menarik layar. Angka `jumlah_perpanjangan` bertambah.
   **Ini yang sebelumnya tidak mungkin:** layar hanya mendengar `PENAWARAN_BARU`, sehingga
   sisa waktu baru berubah pada polling berikutnya (bisa sampai 2 detik) — dan bila polling
   dimatikan karena kanal dianggap sehat, perubahannya **tidak muncul sama sekali** sampai
   pengguna berpindah layar. Sekarang `SESI_DIPERPANJANG` didengar langsung.

### Skenario 4 — Pemenang muncul saat sesi ditutup

1. Di peramban, operator menekan tombol tutup sesi.
2. **Harapan:** di HP, layar berganti menampilkan hasil (pemenang dan harga akhir) dalam
   ±1 detik, tanpa menarik layar. Peristiwa `HASIL_LELANG` dan `SESI_DITUTUP` yang memicu.

### Skenario 5 — NFR-10 tetap terjaga saat kanal putus

1. Matikan Wi-Fi laptop (atau hentikan `php artisan reverb:start` dengan Ctrl+C) saat sesi aktif.
2. **Harapan:** layar **tetap** memperbarui sisa waktu tiap ±2 detik — aplikasi berpindah ke
   polling pengganti.
3. Nyalakan kembali Wi-Fi / jalankan lagi Reverb.
4. **Harapan:** dalam ±5 detik pembaruan kembali seketika (±1 detik), polling berhenti.
   **Bukti tambahan:** sekarang polling hanya berjalan bila **kedua** kanal (TPI dan
   `pembeli.{id}`) putus, bukan satu saja.

---

## Cara membaca hasilnya

| Hasil | Arti | Tindakan |
|---|---|---|
| Tingkat 1 & 2 lulus, Tingkat 3 lulus, Tingkat 4 sesuai harapan | Fitur berjalan | Selesai; catat sebagai bukti |
| Tingkat 1 & 2 lulus, Tingkat 3 gagal | Konfigurasi Reverb/firewall | Periksa port 8080 dan `REVERB_*` di `.env` |
| Tingkat 1 & 2 lulus, Tingkat 3 lulus, Tingkat 4 **tidak** berubah | Kemungkinan `EXPO_PUBLIC_REVERB_HOST` salah, atau aplikasi masih versi lama (cache Metro) | `npx expo start --clear`, pastikan `.env` mobile memakai IP laptop |
| Skenario 3 tidak melonjak, tetapi Skenario 2 berubah | `SESI_DIPERPANJANG` tidak sampai | Cek `dengar.js`: apakah peristiwa itu tercetak? Bila ya, masalah di klien; bila tidak, di server |
| Semua berubah tapi selalu terlambat ~2 detik | Kanal putus, aplikasi memakai polling | Reverb mati atau token kedaluwarsa |

---

## Yang belum dicakup alur ini

- **`OUTBID` belum dipakai layar.** Server mengirimnya (dengan `id_pembeli` penerima), tetapi
  aplikasi mobile belum mendengarkannya dan belum menyaring berdasarkan `id_pembeli`. Saat ini
  "Anda tertinggal" muncul dari perubahan `posisi` pada data status, bukan dari peristiwa itu.
  Bila Anda ingin notifikasi tersalip benar-benar real-time, itu pekerjaan berikutnya
  (perlu menyaring kanal TPI agar tidak membocorkan identitas pembeli lain).
- **`SETTLEMENT_DIBAYAR`** menyasar kanal `nelayan.{id}`; aplikasi mobile belum punya layar
  nelayan, jadi belum ada yang perlu diuji.
- Uji beban kanal (banyak klien bersamaan) belum dilakukan; lihat NFR-01 pada laporan uji.

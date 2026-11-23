<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// UC-05: tutup otomatis sesi lelang yang waktunya habis.
// Sesi juga ditutup otomatis saat aplikasi meminta statusnya (polling), jadi perintah ini opsional:
//   php artisan lelang:tutup-otomatis      (sekali jalan)
//   php artisan schedule:work              (berjalan terus, memeriksa setiap detik)
Artisan::command('lelang:tutup-otomatis', function (\App\Services\LelangService $lelang) {
    $jumlah = $lelang->tutupSemuaKedaluwarsa();
    $this->info("Sesi ditutup: {$jumlah}");
})->purpose('Tutup sesi lelang yang waktunya sudah habis (ALG-04)');

\Illuminate\Support\Facades\Schedule::command('lelang:tutup-otomatis')->everySecond()->withoutOverlapping();

// NFR-15: pencadangan harian otomatis. Skrip dokumen/skrip/cadangkan.sh membuat satu
// arsip .tar.gz (dump MySQL + berkas unggahan + MANIFEST berisi SHA-256 dan COUNT(*)
// per tabel) di SIPELI_CADANGAN, lalu memangkas arsip yang lebih tua dari 14 hari.
// Dijadwalkan 02.00 waktu setempat, di luar jam operasi TPI 04.00-12.00.
// Pemulihan: dokumen/skrip/pulihkan.sh - membandingkan COUNT(*) tiap tabel dengan MANIFEST.
// Hasil uji di lingkungan pengembangan (data demo 126 lot): cadang 1,0 s (20 KB, 35 tabel),
// pulih 0,9 s, 35/35 tabel cocok (uji/uji-cadangan-db.py). Batas NFR-15: pulih < 60 menit.
Artisan::command('cadangan:harian', function () {
    $skrip = env('SIPELI_SKRIP_CADANGAN', base_path('../dokumen/skrip/cadangkan.sh'));
    $tujuan = env('SIPELI_CADANGAN', storage_path('cadangan'));
    if (! is_file($skrip)) {
        $this->error("Skrip cadangan tidak ditemukan: {$skrip}");
        return 1;
    }
    $this->info("Mencadangkan ke {$tujuan}");
    passthru('bash ' . escapeshellarg($skrip) . ' ' . escapeshellarg($tujuan), $kode);
    return $kode;
})->purpose('Cadangkan basis data dan berkas unggahan (NFR-15), simpan 14 hari terakhir');

\Illuminate\Support\Facades\Schedule::command('cadangan:harian')->dailyAt('02:00')->withoutOverlapping();

// NFR-01, NFR-02, NFR-11: perintah bantu untuk uji beban. Membuat pembeli uji (peran 4, saldo
// besar, surel terverifikasi) lalu mencetak token Sanctum tiap pembeli sebagai satu baris JSON.
// Dipakai oleh uji/uji-beban-lelang.py supaya 100 klien penawaran bisa berjalan bersamaan tanpa
// menembus batas 5 percobaan masuk per menit (NFR-06). Ditolak di lingkungan produksi.
Artisan::command('beban:siapkan {--jumlah=100} {--saldo=500000000}', function () {
    if (app()->environment('production')) {
        $this->error('Perintah ini hanya untuk pengujian beban, tidak dijalankan di produksi.');
        return 1;
    }

    $jumlah = max(1, (int) $this->option('jumlah'));
    $saldo = (float) $this->option('saldo');
    $idTpi = (int) \App\Models\Konfigurasi::nilai('id_tpi_aktif', 1);
    $daftar = [];

    for ($i = 1; $i <= $jumlah; $i++) {
        $nama = sprintf('beban%03d', $i);
        $pembeli = \App\Models\Pengguna::firstOrCreate(
            ['nama_pengguna' => $nama],
            [
                'nama_lengkap'            => 'Pembeli Beban ' . $i,
                'surel'                   => $nama . '@uji.local',
                'kata_sandi'              => 'sipeli12345',
                'id_peran'                => 4,
                'id_tpi'                  => $idTpi,
                'status_aktif'            => 1,
                'surel_terverifikasi_pada' => now(),
            ],
        );
        $pembeli->tokens()->delete(); // token lama dicabut agar daftar tidak menumpuk
        \App\Models\SaldoPembeli::updateOrCreate(
            ['id_pembeli' => $pembeli->getIdPengguna()],
            ['saldo' => $saldo, 'limit_kredit' => 0, 'status' => 'AKTIF'],
        );
        $daftar[] = [
            'id_pengguna'   => $pembeli->getIdPengguna(),
            'nama_pengguna' => $nama,
            'token'         => $pembeli->createToken('beban-uji')->plainTextToken,
        ];
    }

    $this->line(json_encode($daftar));
})->purpose('Siapkan pembeli uji + token untuk uji beban lelang (NFR-01/02/11)');

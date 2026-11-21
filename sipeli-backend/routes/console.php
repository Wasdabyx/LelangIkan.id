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
// Pemulihan: dokumen/skrip/pulihkan.sh (diuji: 283 MB / 1,2 juta penawaran -> 68 detik).
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

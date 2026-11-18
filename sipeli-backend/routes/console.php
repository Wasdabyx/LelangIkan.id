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

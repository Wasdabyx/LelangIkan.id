<?php

use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\AutentikasiWebController;
use App\Http\Controllers\Web\KasirController;
use App\Http\Controllers\Web\OperatorController;
use App\Http\Controllers\Web\PapanController;
use App\Http\Controllers\Web\PimpinanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Antarmuka web LELANGIKAN.ID
|--------------------------------------------------------------------------
| SKPL v1.1 Bagian I.B menetapkan pembagian platform:
|   - aplikasi mobile  : petugas pendataan, petugas timbang, pembeli;
|   - antarmuka web    : operator lelang, kasir, pimpinan/dinas, administrator sistem;
|   - papan lelang     : layar baca-saja di ruang pelelangan (tanpa masuk).
|
| Dasbor memakai guard sesi Laravel, tetapi seluruh aturan bisnis, validasi nominal,
| dan pencatatan audit dijalankan oleh Service yang sama dengan rute /api (satu sumber aturan).
*/

Route::get('/', fn () => redirect()->route(auth()->check() ? 'web.beranda' : 'web.masuk'));

// ---- masuk / keluar ------------------------------------------------------
Route::get('/masuk', [AutentikasiWebController::class, 'formulir'])->name('web.masuk');
Route::post('/masuk', [AutentikasiWebController::class, 'masuk'])->name('web.masuk.kirim');
Route::post('/keluar', [AutentikasiWebController::class, 'keluar'])->name('web.keluar');
Route::get('/beranda', fn () => redirect()->route(app(AutentikasiWebController::class)->beranda()))->name('web.beranda');

// ---- papan lelang (baca-saja, tanpa masuk) ------------------------------
Route::get('/papan-lelang', [PapanController::class, 'papan'])->name('web.papan');
Route::get('/papan-lelang/keadaan', [PapanController::class, 'keadaan'])->name('papan.keadaan');

// ---- operator lelang (A3) ----------------------------------------------
Route::middleware(['auth', 'web.peran:OPERATOR_LELANG'])->prefix('dasbor/operator')->group(function () {
    Route::get('/antrean', [OperatorController::class, 'antrean'])->name('operator.antrean');
    Route::get('/sesi', [OperatorController::class, 'sesi'])->name('operator.sesi');
    Route::post('/sesi', [OperatorController::class, 'buka'])->name('operator.sesi.buka');
    Route::get('/sesi/{id}', [OperatorController::class, 'pantau'])->name('operator.sesi.pantau');
    Route::get('/sesi/{id}/keadaan', [OperatorController::class, 'keadaan'])->name('operator.sesi.keadaan');
    Route::post('/sesi/{id}/tutup', [OperatorController::class, 'tutup'])->name('operator.sesi.tutup');
});

// ---- kasir TPI (A5) ----------------------------------------------------
Route::middleware(['auth', 'web.peran:KASIR'])->prefix('dasbor/kasir')->group(function () {
    Route::get('/tagihan', [KasirController::class, 'tagihan'])->name('kasir.tagihan');
    Route::post('/tagihan/{id}/bayar', [KasirController::class, 'bayar'])->name('kasir.tagihan.bayar');

    Route::get('/settlement', [KasirController::class, 'settlement'])->name('kasir.settlement');
    Route::post('/settlement/{idKedatangan}', [KasirController::class, 'hitungSettlement'])->name('kasir.settlement.hitung');
    Route::get('/settlement/rincian/{id}', [KasirController::class, 'rincianSettlement'])->name('kasir.settlement.rincian');
    Route::post('/settlement/{id}/cairkan', [KasirController::class, 'cairkan'])->name('kasir.settlement.cairkan');

    Route::get('/saldo', [KasirController::class, 'saldo'])->name('kasir.saldo');
    Route::post('/saldo/{id}/mutasi', [KasirController::class, 'mutasi'])->name('kasir.saldo.mutasi');
});

// ---- pimpinan / dinas (A6) --------------------------------------------
Route::middleware(['auth', 'web.peran:PIMPINAN'])->prefix('dasbor/pimpinan')->group(function () {
    Route::get('/ringkasan', [PimpinanController::class, 'ringkasan'])->name('pimpinan.ringkasan');
    Route::get('/laporan', [PimpinanController::class, 'laporan'])->name('pimpinan.laporan');
    Route::get('/laporan/{jenis}/csv', [PimpinanController::class, 'unduhCsv'])->name('pimpinan.laporan.csv');
    Route::get('/penyesuaian', [PimpinanController::class, 'penyesuaian'])->name('pimpinan.penyesuaian');
    Route::post('/penyesuaian/{id}/keputusan', [PimpinanController::class, 'putuskan'])->name('pimpinan.penyesuaian.putuskan');
});

// ---- administrator sistem (A7) ----------------------------------------
Route::middleware(['auth', 'web.peran:ADMINISTRATOR'])->prefix('dasbor/admin')->group(function () {
    Route::get('/master', [AdminController::class, 'master'])->name('admin.master');
    Route::post('/master/{entitas}', [AdminController::class, 'simpanMaster'])->name('admin.master.simpan');

    Route::get('/pengguna', [AdminController::class, 'pengguna'])->name('admin.pengguna');
    Route::post('/pengguna', [AdminController::class, 'simpanPengguna'])->name('admin.pengguna.simpan');
    Route::post('/pengguna/{id}/status', [AdminController::class, 'statusPengguna'])->name('admin.pengguna.status');

    Route::get('/konfigurasi', [AdminController::class, 'konfigurasi'])->name('admin.konfigurasi');
    Route::post('/konfigurasi', [AdminController::class, 'ubahKonfigurasi'])->name('admin.konfigurasi.simpan');

    Route::get('/audit', [AdminController::class, 'audit'])->name('admin.audit');
});

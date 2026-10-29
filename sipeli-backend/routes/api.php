<?php

use App\Http\Controllers\Api\AutentikasiController;
use App\Http\Controllers\Api\KedatanganController;
use App\Http\Controllers\Api\PenimbanganController;
use Illuminate\Support\Facades\Route;

/*
| Titik akhir API SIPELI (SKPL Tabel IV.6)
| Semua alamat di file ini otomatis diawali /api
| id_peran: 1 = Petugas Pendataan (A1), 2 = Petugas Timbang (A2)
*/

// Tanpa token
Route::post('/masuk', [AutentikasiController::class, 'masuk']);

// Wajib token (header: Authorization: Bearer <token>)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/saya', [AutentikasiController::class, 'saya']);
    Route::post('/keluar', [AutentikasiController::class, 'keluar']);

    // UC-01 Registrasi Kedatangan Kapal (A1); pencarian & daftar juga untuk A2
    Route::get('/kapal', [KedatanganController::class, 'cariKapal'])->middleware('peran:1,2');
    Route::get('/referensi/tangkapan', [KedatanganController::class, 'referensi'])->middleware('peran:1,2');
    Route::get('/kedatangan', [KedatanganController::class, 'index'])->middleware('peran:1,2');
    Route::post('/kedatangan', [KedatanganController::class, 'store'])->middleware('peran:1');
    Route::get('/kedatangan/{id}', [KedatanganController::class, 'detail'])->whereNumber('id')->middleware('peran:1');
    Route::put('/kedatangan/{id}', [KedatanganController::class, 'perbarui'])->whereNumber('id')->middleware('peran:1');

    // UC-02 Penimbangan dan Pembuatan Lot Lelang (A2); lihat lot juga untuk A1 & A3
    Route::get('/penimbangan/{kode}', [PenimbanganController::class, 'kedatangan'])->middleware('peran:2');
    Route::post('/lot', [PenimbanganController::class, 'simpan'])->middleware('peran:2');
    Route::get('/lot/{kode}', [PenimbanganController::class, 'lihatLot'])->middleware('peran:1,2,3');
    Route::post('/lot/{id}/cetak', [PenimbanganController::class, 'catatCetak'])->whereNumber('id')->middleware('peran:2');
});

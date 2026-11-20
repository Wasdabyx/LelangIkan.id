<?php

use App\Http\Controllers\Api\AutentikasiController;
use App\Http\Controllers\Api\DepositController;
use App\Http\Controllers\Api\KasirController;
use App\Http\Controllers\Api\KedatanganController;
use App\Http\Controllers\Api\LelangPembeliController;
use App\Http\Controllers\Api\OperatorLelangController;
use App\Http\Controllers\Api\PembeliController;
use App\Http\Controllers\Api\PenimbanganController;
use Illuminate\Support\Facades\Route;

/*
| Titik akhir API SIPELI (SKPL Tabel IV.6)
| Semua alamat di file ini otomatis diawali /api
| id_peran: 1 = Petugas Pendataan (A1), 2 = Petugas Timbang (A2)
*/

// Tanpa token
Route::post('/masuk', [AutentikasiController::class, 'masuk']);
Route::post('/registrasi', [AutentikasiController::class, 'daftar']); // Week 9: Registrasi Pembeli (UC-11)
Route::post('/aktivasi', [AutentikasiController::class, 'aktivasi']);                   // Tugas 2
Route::post('/aktivasi/kirim-ulang', [AutentikasiController::class, 'kirimUlangAktivasi']); // Tugas 2
Route::get('/foto-lot/{nama}', [PenimbanganController::class, 'tampilFoto'])
    ->where('nama', '[0-9a-f-]{36}\.(jpg|png|webp)');                                         // foto lot (opsional)

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
    Route::post('/lot/{id}/foto', [PenimbanganController::class, 'unggahFoto'])->whereNumber('id')->middleware('peran:2');

    // Tugas 2: Pembeli (A4) memesan / ikut lelang lot dengan konfirmasi OTP surel
    Route::middleware('peran:4')->prefix('pembeli')->group(function () {
        Route::get('/lot', [PembeliController::class, 'lot']);
        Route::get('/pemesanan', [PembeliController::class, 'pemesanan']);
        Route::post('/pemesanan/otp', [PembeliController::class, 'kirimOtp']);
        Route::post('/pemesanan', [PembeliController::class, 'konfirmasi']);

        // UC-04 penawaran real-time (polling 1 detik) dan tagihan
        Route::get('/lelang', [LelangPembeliController::class, 'daftar']);
        Route::get('/lelang/{id}', [LelangPembeliController::class, 'status'])->whereNumber('id');
        Route::post('/lelang/{id}/tawar', [LelangPembeliController::class, 'tawar'])->whereNumber('id');
        Route::post('/lelang/{id}/ambil', [LelangPembeliController::class, 'ambil'])->whereNumber('id');
        Route::get('/tagihan', [LelangPembeliController::class, 'tagihan']);
        Route::get('/tagihan/{id}', [LelangPembeliController::class, 'detailTagihan'])->whereNumber('id');
    });

    // UC-03 dan UC-05: Operator Lelang (A3)
    Route::middleware('peran:3')->prefix('operator')->group(function () {
        Route::get('/antrean', [OperatorLelangController::class, 'antrean']);
        Route::get('/sesi', [OperatorLelangController::class, 'daftarSesi']);
        Route::post('/sesi', [OperatorLelangController::class, 'buka']);
        Route::get('/sesi/{id}', [OperatorLelangController::class, 'status'])->whereNumber('id');
        Route::post('/sesi/{id}/tutup', [OperatorLelangController::class, 'tutup'])->whereNumber('id');
    });

    // UC-06: Kasir TPI (A5)
    Route::middleware('peran:5')->prefix('kasir')->group(function () {
        Route::get('/tagihan', [KasirController::class, 'daftar']);
        Route::get('/tagihan/{kode}', [KasirController::class, 'cari']);
        Route::post('/tagihan/{id}/bayar', [KasirController::class, 'bayar'])->whereNumber('id');
        Route::get('/saldo/pembeli', [DepositController::class, 'cariPembeli']);
        Route::get('/saldo/pembeli/{id}', [DepositController::class, 'rincian'])->whereNumber('id');
        Route::post('/saldo/pembeli/{id}/mutasi', [DepositController::class, 'mutasi'])->whereNumber('id');
    });

    Route::middleware('peran:6')->prefix('pimpinan')->group(function () {
        Route::get('/saldo/penyesuaian', [DepositController::class, 'menungguPersetujuan']);
        Route::post('/saldo/penyesuaian/{id}/keputusan', [DepositController::class, 'putuskan'])->whereNumber('id');
    });

    // FR-31: periksa keaslian nota (Kasir, Pimpinan, Operator)
    Route::get('/nota/verifikasi', [KasirController::class, 'verifikasiNota'])->middleware('peran:3,5,6');
});

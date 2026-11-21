<?php

use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AutentikasiController;
use App\Http\Controllers\Api\DepositController;
use App\Http\Controllers\Api\KasirController;
use App\Http\Controllers\Api\KedatanganController;
use App\Http\Controllers\Api\LelangPembeliController;
use App\Http\Controllers\Api\OperatorLelangController;
use App\Http\Controllers\Api\PembeliController;
use App\Http\Controllers\Api\PimpinanController;
use App\Http\Controllers\Api\PenimbanganController;
use App\Http\Controllers\Api\PortalNelayanController;
use App\Http\Controllers\Api\SettlementController;
use App\Http\Controllers\Api\KartuNelayanController;
use App\Http\Controllers\Api\LaporanController;
use App\Http\Controllers\Api\MasterController;
use App\Http\Controllers\Api\PantauanController;
use App\Http\Controllers\Api\PapanLelangController;
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

// UC-13: portal nelayan baca-saja memakai token khusus + verifikasi 4 digit (FR-59), bukan token Sanctum
Route::get('/nelayan/rekap', [PortalNelayanController::class, 'rekap']);
Route::get('/nelayan/bukti/{id}.pdf', [PortalNelayanController::class, 'buktiPdf'])->whereNumber('id');

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
        Route::post('/tagihan/{id}/bayar-saldo', [LelangPembeliController::class, 'bayarSaldo'])->whereNumber('id');
        Route::post('/tagihan/{id}/pengambilan', [LelangPembeliController::class, 'jadwalkanPengambilan'])->whereNumber('id');

    });

    // FR-64: daftar pantauan pembeli (jalur utama sesuai SKPL Tabel IV.6)
    Route::middleware('peran:4')->group(function () {
        Route::get('/pantauan', [PantauanController::class, 'index']);
        Route::post('/pantauan', [PantauanController::class, 'simpan']);
        Route::delete('/pantauan/{id}', [PantauanController::class, 'hapus'])->whereNumber('id');
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

        // UC-08: perhitungan retribusi dan pembayaran kepada nelayan
        Route::get('/settlement', [SettlementController::class, 'daftar']);
        Route::post('/settlement/{idKedatangan}', [SettlementController::class, 'hitung'])->whereNumber('idKedatangan');
        Route::get('/settlement/rincian/{id}', [SettlementController::class, 'rincian'])->whereNumber('id');
        Route::post('/pembayaran-nelayan/{id}/cairkan', [SettlementController::class, 'cairkan'])->whereNumber('id');
        Route::get('/pembayaran-nelayan/{id}/bukti.pdf', [SettlementController::class, 'buktiPdf'])->whereNumber('id');

        // UC-13: penerbitan tautan akses portal nelayan (FR-59)
        Route::post('/nelayan/tautan', [PortalNelayanController::class, 'terbitkanTautan']);

        // UC-11 FR-66: penerbitan dan pemblokiran kartu nelayan
        Route::get('/kartu-nelayan', [KartuNelayanController::class, 'daftar']);
        Route::post('/kartu-nelayan', [KartuNelayanController::class, 'terbitkan']);
        Route::post('/kartu-nelayan/{id}/blokir', [KartuNelayanController::class, 'blokir'])->whereNumber('id');
        Route::post('/kartu-nelayan/{id}/aktifkan', [KartuNelayanController::class, 'aktifkan'])->whereNumber('id');
        Route::get('/kartu-nelayan/pindai', [KartuNelayanController::class, 'pindai']);
    });

    Route::middleware('peran:6')->prefix('pimpinan')->group(function () {
        Route::get('/ringkasan', [PimpinanController::class, 'ringkasan']);
        Route::get('/saldo/penyesuaian', [DepositController::class, 'menungguPersetujuan']);
        Route::post('/saldo/penyesuaian/{id}/keputusan', [DepositController::class, 'putuskan'])->whereNumber('id');
    });

    // FR-31: periksa keaslian nota (Kasir, Pimpinan, Operator)
    Route::get('/nota/verifikasi', [KasirController::class, 'verifikasiNota'])->middleware('peran:3,5,6');

    // FR-32: pembatalan transaksi hanya oleh supervisor (Pimpinan), alasan wajib
    Route::post('/supervisor/tagihan/{id}/batal', [KasirController::class, 'batalkan'])
        ->whereNumber('id')->middleware('peran:6');

    // UC-09: papan lelang (FR-41, FR-42, FR-43) - cadangan polling selain kanal WebSocket
    Route::get('/papan-lelang', [PapanLelangController::class, 'index'])->middleware('peran:3,6,7,5,1,2');

    // UC-10: laporan dan analitik harga ikan (Pimpinan/Admin Dinas)
    Route::middleware('peran:6,7')->prefix('laporan')->group(function () {
        Route::get('/{jenis?}', [LaporanController::class, 'index']);
    });

    // UC-11: pengelolaan data master dan pengguna (Administrator Sistem)
    Route::middleware('peran:7')->prefix('master')->group(function () {
        Route::get('/{entitas}', [MasterController::class, 'index']);
        Route::post('/{entitas}', [MasterController::class, 'simpan']);
        Route::put('/{entitas}/{id}', [MasterController::class, 'perbarui'])->whereNumber('id');
        Route::patch('/{entitas}/{id}', [MasterController::class, 'perbarui'])->whereNumber('id');
        Route::post('/pengguna/{id}/nonaktifkan', [MasterController::class, 'nonaktifkan'])->whereNumber('id');
        Route::post('/pengguna/{id}/aktifkan', [MasterController::class, 'aktifkan'])->whereNumber('id');
    });

    // UC-12 Audit dan Penelusuran Aktivitas (Administrator, Pimpinan boleh membaca)
    Route::middleware('peran:6,7')->group(function () {
        Route::get('/audit', [AuditController::class, 'index']);
        Route::match(['put', 'patch', 'delete'], '/audit/{id?}', [AuditController::class, 'tolakUbah'])->whereNumber('id');
    });
});

<?php

namespace App\Services;

use App\Enums\KodePeran;
use App\Models\Konfigurasi;
use App\Models\Pengguna;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kelas kendali AutentikasiService (DPPL Tabel 3.55)
 * masuk(), keluar(), dataPengguna(), catatPercobaanGagal()
 * daftarPembeli() ditambahkan untuk Tugas Week 9 (Registrasi Pembeli, UC-11 / AS-03)
 * Pemeriksaan kata sandi, status aktif, dan data pengguna memakai metode kelas Pengguna.
 */
class AutentikasiService
{
    // DPPL 2.1.4: maksimal 5 percobaan masuk per menit per akun
    private const BATAS_PERCOBAAN = 5;
    private const DURASI_BLOKIR_DETIK = 60;

    public function masuk(string $namaPengguna, string $kataSandi, string $perangkat): array
    {
        $kunci = 'masuk:' . Str::lower($namaPengguna);

        // 1. Tolak bila sudah terlalu banyak percobaan gagal
        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_PERCOBAAN)) {
            $detik = RateLimiter::availableIn($kunci);
            throw ValidationException::withMessages([
                'nama_pengguna' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$detik} detik.",
            ])->status(429);
        }

        // 2. Cari pengguna dan periksa kata sandi
        $pengguna = Pengguna::cariNamaPengguna($namaPengguna);

        if (! $pengguna || ! $pengguna->autentikasi($kataSandi)) {
            $this->catatPercobaanGagal($kunci);
            throw ValidationException::withMessages([
                'kata_sandi' => 'Nama pengguna atau kata sandi salah.',
            ]);
        }

        // 3. Akun nonaktif tidak boleh masuk
        if (! $pengguna->isStatusAktif()) {
            throw ValidationException::withMessages([
                'nama_pengguna' => 'Akun Anda belum aktif atau sedang dinonaktifkan. Hubungi petugas TPI.',
            ])->status(403);
        }

        // 4. Berhasil: hapus hitungan gagal dan terbitkan token
        RateLimiter::clear($kunci);
        $token = $pengguna->createToken($perangkat)->plainTextToken;

        return [
            'token'    => $token,
            'pengguna' => $this->dataPengguna($pengguna),
        ];
    }

    /**
     * Registrasi Pembeli (peran A4). Data sudah lolos validasi RegEx di RegistrasiPembeliRequest.
     * Sesuai AS-03 SKPL, akun dibuat NONAKTIF sampai identitas (NIK) pembeli diverifikasi.
     */
    public function daftarPembeli(array $data): Pengguna
    {
        return Pengguna::create([
            'nama_lengkap'  => $data['nama_lengkap'],
            'nama_pengguna' => $data['nama_pengguna'],
            'surel'         => $data['surel'],
            'nik'           => $data['nik'],
            'no_hp'         => $data['no_hp'],
            'kata_sandi'    => $data['kata_sandi'],   // di-hash otomatis oleh model
            'id_peran'      => KodePeran::PEMBELI->value,
            'id_tpi'        => (int) Konfigurasi::nilai('id_tpi_aktif', 1),
            'status_aktif'  => false,
        ]);
    }

    public function keluar(Pengguna $pengguna): void
    {
        // Cabut hanya token yang sedang dipakai (perangkat ini)
        $pengguna->currentAccessToken()->delete();
    }

    public function dataPengguna(Pengguna $pengguna): array
    {
        return $pengguna->dataPublik();
    }

    private function catatPercobaanGagal(string $kunci): void
    {
        RateLimiter::hit($kunci, self::DURASI_BLOKIR_DETIK);
    }
}

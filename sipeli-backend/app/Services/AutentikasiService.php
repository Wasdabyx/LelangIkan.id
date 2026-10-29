<?php

namespace App\Services;

use App\Models\Pengguna;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kelas kendali AutentikasiService (DPPL Tabel 3.55)
 * masuk(), keluar(), dataPengguna(), catatPercobaanGagal()
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
                'nama_pengguna' => 'Akun Anda dinonaktifkan. Hubungi administrator.',
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

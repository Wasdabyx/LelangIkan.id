<?php

namespace App\Services;

use App\Enums\KodePeran;
use App\Enums\TujuanOtp;
use App\Models\Konfigurasi;
use App\Models\Pengguna;
use App\Models\SaldoPembeli;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kelas kendali AutentikasiService (DPPL Tabel 3.55)
 * masuk(), keluar(), dataPengguna(), catatPercobaanGagal()
 * daftarPembeli() ditambahkan untuk Tugas Week 9 (Registrasi Pembeli, UC-11 / AS-03)
 * aktivasi() dan kirimUlangAktivasi() ditambahkan untuk Tugas 2 (aktivasi akun lewat kode OTP di surel)
 */
class AutentikasiService
{
    // DPPL 2.1.4: maksimal 5 percobaan masuk per menit per akun
    private const BATAS_PERCOBAAN = 5;
    private const DURASI_BLOKIR_DETIK = 60;

    public function __construct(private OtpService $otp)
    {
    }

    public function masuk(string $namaPengguna, string $kataSandi, string $perangkat): array
    {
        // 1-2. Batas percobaan, cari pengguna, dan periksa kata sandi
        $pengguna = $this->periksaKredensial($namaPengguna, $kataSandi);

        // 3. Akun nonaktif tidak boleh masuk
        if (! $pengguna->isStatusAktif()) {
            // Tugas 2: pembeli yang belum memasukkan kode aktivasi diarahkan ke layar Aktivasi
            if ($pengguna->isPembeli() && ! $pengguna->isSurelTerverifikasi()) {
                $pesan = 'Akun belum diaktivasi. Masukkan kode aktivasi yang dikirim ke surel Anda.';
                throw new HttpResponseException(response()->json([
                    'message'        => $pesan,
                    'kode'           => 'BELUM_AKTIVASI',
                    'surel_tersamar' => $pengguna->getSurelTersamar(),
                    'errors'         => ['nama_pengguna' => [$pesan]],
                ], 403));
            }
            throw ValidationException::withMessages([
                'nama_pengguna' => 'Akun Anda sedang dinonaktifkan. Hubungi petugas TPI.',
            ])->status(403);
        }

        // 4. Berhasil: hapus hitungan gagal dan terbitkan token
        RateLimiter::clear('masuk:' . Str::lower($namaPengguna));

        return $this->terbitkanToken($pengguna, $perangkat);
    }

    /**
     * Registrasi Pembeli (peran A4). Data sudah lolos validasi RegEx di RegistrasiPembeliRequest.
     * Akun dibuat NONAKTIF, lalu kode aktivasi dikirim ke surel (Tugas 2).
     * @return array{0: Pengguna, 1: array} pengguna baru dan info pengiriman kode aktivasi
     */
    public function daftarPembeli(array $data): array
    {
        $pengguna = Pengguna::create([
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
        SaldoPembeli::firstOrCreate(
            ['id_pembeli' => $pengguna->getIdPengguna()],
            ['saldo' => 0, 'limit_kredit' => 0, 'status' => 'AKTIF'],
        );

        try {
            $info = $this->otp->kirim($pengguna, TujuanOtp::AKTIVASI) + ['surel_terkirim' => true];
        } catch (HttpResponseException $e) {
            // Akun tetap tersimpan; pembeli dapat menekan Kirim Ulang di layar Aktivasi
            $info = [
                'surel_terkirim'    => false,
                'surel_tersamar'    => $pengguna->getSurelTersamar(),
                'berlaku_detik'     => 0,
                'kirim_ulang_detik' => 0,
            ];
        }

        return [$pengguna, $info];
    }

    /** Tugas 2: aktivasi akun dengan kode 6 digit dari surel. Berhasil = akun aktif dan langsung masuk. */
    public function aktivasi(string $namaPengguna, string $kataSandi, string $kode, string $perangkat): array
    {
        $pengguna = $this->penggunaBelumAktivasi($namaPengguna, $kataSandi);

        $this->otp->verifikasi($pengguna, TujuanOtp::AKTIVASI, $kode);
        $pengguna->tandaiSurelTerverifikasi();   // status_aktif = true

        RateLimiter::clear('masuk:' . Str::lower($namaPengguna));

        return $this->terbitkanToken($pengguna, $perangkat);
    }

    /** Tugas 2: kirim ulang kode aktivasi (jeda minimal 60 detik). */
    public function kirimUlangAktivasi(string $namaPengguna, string $kataSandi): array
    {
        $pengguna = $this->penggunaBelumAktivasi($namaPengguna, $kataSandi);

        return $this->otp->kirim($pengguna, TujuanOtp::AKTIVASI);
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

    /** Batas percobaan + cari pengguna + cocokkan kata sandi (dipakai masuk dan aktivasi). */
    private function periksaKredensial(string $namaPengguna, string $kataSandi): Pengguna
    {
        $kunci = 'masuk:' . Str::lower($namaPengguna);

        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_PERCOBAAN)) {
            $detik = RateLimiter::availableIn($kunci);
            throw ValidationException::withMessages([
                'nama_pengguna' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$detik} detik.",
            ])->status(429);
        }

        $pengguna = Pengguna::cariNamaPengguna($namaPengguna);

        if (! $pengguna || ! $pengguna->autentikasi($kataSandi)) {
            $this->catatPercobaanGagal($kunci);
            throw ValidationException::withMessages([
                'kata_sandi' => 'Nama pengguna atau kata sandi salah.',
            ]);
        }

        return $pengguna;
    }

    private function penggunaBelumAktivasi(string $namaPengguna, string $kataSandi): Pengguna
    {
        $pengguna = $this->periksaKredensial($namaPengguna, $kataSandi);

        if (! $pengguna->isPembeli() || $pengguna->isSurelTerverifikasi()) {
            throw ValidationException::withMessages([
                'kode_otp' => 'Akun ini sudah aktif. Silakan masuk.',
            ]);
        }

        return $pengguna;
    }

    private function terbitkanToken(Pengguna $pengguna, string $perangkat): array
    {
        return [
            'token'    => $pengguna->createToken($perangkat)->plainTextToken,
            'pengguna' => $this->dataPengguna($pengguna),
        ];
    }

    private function catatPercobaanGagal(string $kunci): void
    {
        RateLimiter::hit($kunci, self::DURASI_BLOKIR_DETIK);
    }
}

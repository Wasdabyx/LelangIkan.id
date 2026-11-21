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
    // DPPL 2.1.4 + SKPL NFR-06: maksimal 5 percobaan masuk per menit per akun DAN per alamat IP
    private const BATAS_PERCOBAAN = 5;
    private const DURASI_BLOKIR_DETIK = 60;

    /** Alamat IP permintaan yang sedang diproses (diisi oleh masuk()), untuk kunci pembatas. */
    private ?string $alamatIp = null;

    public function __construct(private OtpService $otp)
    {
    }

    public function masuk(string $namaPengguna, string $kataSandi, string $perangkat, ?string $alamatIp = null): array
    {
        $this->alamatIp = $alamatIp;

        // 1-2. Batas percobaan (per akun dan per alamat IP), cari pengguna, cocokkan kata sandi
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

        // 4. Berhasil: hapus hitungan gagal (akun dan IP) dan terbitkan token
        RateLimiter::clear('masuk:' . Str::lower($namaPengguna));
        if ($this->alamatIp) {
            RateLimiter::clear($this->kunciIp());
        }

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
    public function aktivasi(string $namaPengguna, string $kataSandi, string $kode, string $perangkat, ?string $alamatIp = null): array
    {
        $this->alamatIp = $alamatIp;
        $pengguna = $this->penggunaBelumAktivasi($namaPengguna, $kataSandi);

        $this->otp->verifikasi($pengguna, TujuanOtp::AKTIVASI, $kode);
        $pengguna->tandaiSurelTerverifikasi();   // status_aktif = true

        RateLimiter::clear('masuk:' . Str::lower($namaPengguna));

        return $this->terbitkanToken($pengguna, $perangkat);
    }

    /** Tugas 2: kirim ulang kode aktivasi (jeda minimal 60 detik). */
    public function kirimUlangAktivasi(string $namaPengguna, string $kataSandi, ?string $alamatIp = null): array
    {
        $this->alamatIp = $alamatIp;
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
        $kunciIp = $this->alamatIp ? $this->kunciIp() : null;

        // NFR-06: batas per akun dan per alamat IP. Kunci IP menahan percobaan yang menyebar
        // ke banyak nama pengguna (password spraying) dari satu terminal.
        foreach ([$kunci, $kunciIp] as $k) {
            if ($k && RateLimiter::tooManyAttempts($k, self::BATAS_PERCOBAAN)) {
                $detik = RateLimiter::availableIn($k);
                $pesan = $k === $kunciIp
                    ? "Terlalu banyak percobaan masuk dari alamat ini. Coba lagi dalam {$detik} detik."
                    : "Terlalu banyak percobaan masuk. Coba lagi dalam {$detik} detik.";

                app(AuditService::class)->catat(null, 'MASUK_DIBATASI', 'pengguna', $namaPengguna,
                    null, ['kunci' => $k === $kunciIp ? 'ip' : 'akun', 'sisa_detik' => $detik],
                    $this->alamatIp);

                throw ValidationException::withMessages(['nama_pengguna' => $pesan])->status(429);
            }
        }

        $pengguna = Pengguna::cariNamaPengguna($namaPengguna);

        if (! $pengguna || ! $pengguna->autentikasi($kataSandi)) {
            $this->catatPercobaanGagal($kunci);
            if ($kunciIp) {
                $this->catatPercobaanGagal($kunciIp);
            }
            throw ValidationException::withMessages([
                'kata_sandi' => 'Nama pengguna atau kata sandi salah.',
            ]);
        }

        return $pengguna;
    }

    /** Kunci pembatas per alamat IP (NFR-06). */
    private function kunciIp(): string
    {
        return 'masuk-ip:' . $this->alamatIp;
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

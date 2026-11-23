<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Kelas entitas TautanAksesNelayan - UC-13 (FR-59).
 * Tautan sekali pakai berkode token, berlaku maksimum 72 jam, dan hanya dapat dibuka
 * dengan verifikasi tambahan berupa 4 digit yang disampaikan terpisah oleh kasir.
 */
class TautanAksesNelayan extends Model
{
    protected $table = 'tautan_akses_nelayan';
    protected $primaryKey = 'id_tautan';
    public $timestamps = false;

    /** FR-59: batas umur tautan (jam). */
    public const UMUR_MAKS_JAM = 72;

    /** Umur sesi portal setelah tautan dibuka sekali (menit). */
    public const MENIT_SESI = 30;

    protected $fillable = [
        'id_pemilik', 'token', 'kode_verifikasi', 'kedaluwarsa', 'jumlah_akses', 'waktu_dibuat', 'id_penerbit',
        'dipakai_pada', 'sesi_token', 'sesi_kedaluwarsa',
    ];

    protected $casts = [
        'kedaluwarsa' => 'datetime', 'waktu_dibuat' => 'datetime',
        'dipakai_pada' => 'datetime', 'sesi_kedaluwarsa' => 'datetime',
    ];
    protected $hidden = ['kode_verifikasi'];

    public function getIdTautan(): int { return (int) $this->id_tautan; }
    public function getToken(): string { return $this->token; }
    public function getIdPemilik(): int { return (int) $this->id_pemilik; }

    public function pemilik()
    {
        return $this->belongsTo(PemilikKapal::class, 'id_pemilik', 'id_pemilik');
    }

    /** Tautan masih dapat dipakai: belum kedaluwarsa dan belum melewati batas pemakaian. */
    public function masihBerlaku(): bool
    {
        return $this->kedaluwarsa->isFuture();
    }

    /** Verifikasi tambahan: 4 digit yang hanya diketahui pemilik kapal. */
    public function verifikasiEmpatDigit(string $kode): bool
    {
        return Hash::check(trim($kode), $this->kode_verifikasi);
    }

    public function tambahPemakaian(): void
    {
        $this->jumlah_akses = (int) $this->jumlah_akses + 1;
        $this->save();
    }

    /** FR-59: tautan hanya boleh dibuka satu kali. */
    public function sudahDipakai(): bool
    {
        return $this->dipakai_pada !== null;
    }

    /** Tandai tautan terpakai pada pembukaan pertama (sekaligus menaikkan penghitung akses). */
    public function tandaiDipakai(): void
    {
        $this->dipakai_pada = now();
        $this->jumlah_akses = (int) $this->jumlah_akses + 1;
        $this->save();
    }

    /**
     * Terbitkan sesi portal setelah tautan dibuka: halaman rekap dan unduhan bukti memakai
     * sesi ini, sehingga tautannya sendiri tidak perlu (dan tidak bisa) dibuka dua kali.
     */
    public function buatSesi(): void
    {
        $this->sesi_token = hash('sha256', Str::random(40) . microtime(true));
        $this->sesi_kedaluwarsa = now()->addMinutes(self::MENIT_SESI);
        $this->save();
    }

    public function sesiMasihBerlaku(): bool
    {
        return $this->sesi_token !== null
            && $this->sesi_kedaluwarsa !== null
            && $this->sesi_kedaluwarsa->isFuture();
    }

    /**
     * Terbitkan tautan baru. Mengembalikan [tautan, kodeVerifikasi] karena kode hanya
     * ditampilkan sekali kepada kasir yang menerbitkan.
     *
     * @return array{0: self, 1: string}
     */
    public static function terbitkan(PemilikKapal $pemilik, ?Pengguna $penerbit, ?int $jam = null): array
    {
        $jam = max(1, min(self::UMUR_MAKS_JAM, $jam ?? self::UMUR_MAKS_JAM));
        $kode = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        $tautan = static::create([
            'id_pemilik'      => $pemilik->getIdPemilik(),
            'token'           => hash('sha256', Str::random(48) . microtime(true)),
            'kode_verifikasi' => Hash::make($kode),
            'kedaluwarsa'     => now()->addHours($jam),
            'jumlah_akses'    => 0,
            'waktu_dibuat'    => now(),
            'id_penerbit'     => $penerbit?->getIdPengguna(),
        ]);

        return [$tautan, $kode];
    }

    public static function cariDariToken(string $token): ?self
    {
        return static::where('token', $token)->first();
    }

    /** Cari tautan dari sesi portal yang masih berlaku (FR-59). */
    public static function cariDariSesi(string $sesi): ?self
    {
        $sesi = trim($sesi);
        if ($sesi === '') {
            return null;
        }

        return static::where('sesi_token', $sesi)->first();
    }
}

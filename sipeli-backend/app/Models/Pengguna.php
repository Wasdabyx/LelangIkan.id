<?php

namespace App\Models;

use App\Enums\KodePeran;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\HasApiTokens;

/**
 * Kelas entitas Pengguna (diagram kelas, paket Kelembagaan dan pengguna).
 * Atribut disimpan oleh Eloquent (setara private); akses dari luar lewat get/set di bawah.
 */
class Pengguna extends Authenticatable
{
    use HasApiTokens;   // token login API (Laravel Sanctum)

    protected $table = 'pengguna';
    protected $primaryKey = 'id_pengguna';

    // Hanya ada kolom waktu_dibuat (tidak ada updated_at) sesuai ERD
    const CREATED_AT = 'waktu_dibuat';
    const UPDATED_AT = null;

    // Kolom kata sandi bernama "kata_sandi", bukan "password"
    protected $authPasswordName = 'kata_sandi';
    // Tabel pengguna tidak memiliki kolom remember_token
    protected $rememberTokenName = '';

    protected $fillable = [
        'nama_lengkap', 'nama_pengguna', 'surel', 'kata_sandi',
        'id_peran', 'id_tpi', 'status_aktif',
    ];

    protected $hidden = ['kata_sandi'];

    protected function casts(): array
    {
        return [
            'kata_sandi'   => 'hashed',   // otomatis di-hash bcrypt
            'status_aktif' => 'boolean',
            'waktu_dibuat' => 'datetime',
        ];
    }

    // ---------------- get / set ----------------

    public function getIdPengguna(): int
    {
        return (int) $this->id_pengguna;
    }

    public function getNamaLengkap(): string
    {
        return $this->nama_lengkap;
    }

    public function setNamaLengkap(string $nama): void
    {
        $this->nama_lengkap = $nama;
    }

    public function getNamaPengguna(): string
    {
        return $this->nama_pengguna;
    }

    public function setNamaPengguna(string $nama): void
    {
        $this->nama_pengguna = $nama;
    }

    public function getSurel(): string
    {
        return $this->surel;
    }

    public function setSurel(string $surel): void
    {
        $this->surel = $surel;
    }

    /** Tidak ada getKataSandi(): hash kata sandi tidak boleh dibaca dari luar kelas. */
    public function setKataSandi(string $sandiBaru): void
    {
        $this->kata_sandi = $sandiBaru;   // di-hash otomatis oleh cast 'hashed'
    }

    public function getIdPeran(): int
    {
        return (int) $this->id_peran;
    }

    public function setIdPeran(int $idPeran): void
    {
        $this->id_peran = $idPeran;
    }

    public function getIdTpi(): ?int
    {
        return $this->id_tpi === null ? null : (int) $this->id_tpi;
    }

    public function setIdTpi(?int $idTpi): void
    {
        $this->id_tpi = $idTpi;
    }

    public function isStatusAktif(): bool
    {
        return (bool) $this->status_aktif;
    }

    public function getWaktuDibuat(): ?\DateTimeInterface
    {
        return $this->waktu_dibuat;
    }

    // ---------------- relasi ----------------

    public function peran()
    {
        return $this->belongsTo(Peran::class, 'id_peran');
    }

    public function tpi()
    {
        return $this->belongsTo(Tpi::class, 'id_tpi');
    }

    // ---------------- metode domain ----------------

    /** Cocokkan kata sandi yang diketik dengan hash bcrypt yang tersimpan. */
    public function autentikasi(string $kataSandi): bool
    {
        return Hash::check($kataSandi, $this->kata_sandi);
    }

    /**
     * Apakah pengguna memegang salah satu peran yang diizinkan.
     * @param KodePeran[] $daftarPeran
     */
    public function periksaKewenangan(array $daftarPeran): bool
    {
        $peranSaya = KodePeran::tryFrom($this->getIdPeran());

        return $peranSaya !== null && in_array($peranSaya, $daftarPeran, true);
    }

    public function ubahKataSandi(string $sandiLama, string $sandiBaru): void
    {
        if (! $this->autentikasi($sandiLama)) {
            throw ValidationException::withMessages(['sandi_lama' => 'Kata sandi lama salah.']);
        }
        if (mb_strlen($sandiBaru) < 8) {
            throw ValidationException::withMessages(['sandi_baru' => 'Kata sandi baru minimal 8 karakter.']);
        }
        $this->setKataSandi($sandiBaru);
        $this->simpan();
    }

    /** Nonaktifkan akun sekaligus mencabut semua token yang masih berlaku. */
    public function nonaktifkan(): void
    {
        $this->status_aktif = false;
        $this->simpan();
        $this->tokens()->delete();
    }

    public function aktifkan(): void
    {
        $this->status_aktif = true;
        $this->simpan();
    }

    /** Administrator yang tidak terikat pada satu TPI. */
    public function isAdminPusat(): bool
    {
        return $this->getIdPeran() === KodePeran::ADMINISTRATOR->value && $this->getIdTpi() === null;
    }

    /** Data yang aman dikirim ke aplikasi (tanpa kata sandi). */
    public function dataPublik(): array
    {
        $this->loadMissing(['peran', 'tpi']);

        return [
            'id_pengguna'   => $this->getIdPengguna(),
            'nama_lengkap'  => $this->getNamaLengkap(),
            'nama_pengguna' => $this->getNamaPengguna(),
            'id_peran'      => $this->getIdPeran(),
            'nama_peran'    => $this->peran->getNamaPeran(),
            'id_tpi'        => $this->getIdTpi(),
            'nama_tpi'      => $this->tpi?->getNamaTpi(),
        ];
    }

    public function simpan(): void
    {
        $this->save();
    }

    public static function cari(int $id): ?self
    {
        return static::find($id);
    }

    public static function cariNamaPengguna(string $nama): ?self
    {
        return static::where('nama_pengguna', $nama)->first();
    }
}

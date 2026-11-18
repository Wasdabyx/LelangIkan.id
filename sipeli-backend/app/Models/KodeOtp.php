<?php

namespace App\Models;

use App\Enums\TujuanOtp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * Kelas entitas KodeOtp (Tugas 2).
 * Kode 6 digit disimpan sebagai hash bcrypt, berlaku terbatas, dan dibatasi jumlah percobaannya.
 */
class KodeOtp extends Model
{
    public const MAKS_PERCOBAAN = 5;

    protected $table = 'kode_otp';
    protected $primaryKey = 'id_otp';

    const CREATED_AT = 'waktu_dibuat';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_pengguna', 'tujuan', 'id_lot', 'kode_hash', 'kedaluwarsa_pada', 'jumlah_percobaan', 'terpakai_pada',
    ];

    protected $hidden = ['kode_hash'];

    protected function casts(): array
    {
        return [
            'kedaluwarsa_pada' => 'datetime',
            'terpakai_pada'    => 'datetime',
            'waktu_dibuat'     => 'datetime',
            'jumlah_percobaan' => 'integer',
        ];
    }

    // ---------------- get ----------------

    public function getIdOtp(): int
    {
        return (int) $this->id_otp;
    }

    public function getTujuan(): TujuanOtp
    {
        return TujuanOtp::from($this->tujuan);
    }

    public function getKedaluwarsaPada(): \DateTimeInterface
    {
        return $this->kedaluwarsa_pada;
    }

    public function getWaktuDibuat(): \DateTimeInterface
    {
        return $this->waktu_dibuat;
    }

    // ---------------- metode domain ----------------

    /** Buat kode baru. Kode asli dikembalikan sekali untuk dikirim lewat surel, lalu hanya hash-nya yang disimpan. */
    public static function terbitkan(Pengguna $pengguna, TujuanOtp $tujuan, ?int $idLot, int $berlakuMenit): array
    {
        $kode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $otp = static::create([
            'id_pengguna'      => $pengguna->getIdPengguna(),
            'tujuan'           => $tujuan->value,
            'id_lot'           => $idLot,
            'kode_hash'        => Hash::make($kode),
            'kedaluwarsa_pada' => now()->addMinutes($berlakuMenit),
        ]);

        return [$otp, $kode];
    }

    public function isKedaluwarsa(): bool
    {
        return now()->greaterThan($this->kedaluwarsa_pada);
    }

    public function isTerpakai(): bool
    {
        return $this->terpakai_pada !== null;
    }

    public function sisaPercobaan(): int
    {
        return max(0, self::MAKS_PERCOBAAN - (int) $this->jumlah_percobaan);
    }

    /** Cocokkan kode yang diketik. Bila salah, jumlah percobaan bertambah. */
    public function cocokkan(string $kode): bool
    {
        if (Hash::check($kode, $this->kode_hash)) {
            return true;
        }
        $this->jumlah_percobaan = (int) $this->jumlah_percobaan + 1;
        $this->save();

        return false;
    }

    public function tandaiTerpakai(): void
    {
        $this->terpakai_pada = now();
        $this->save();
    }

    /** Kode terakhir yang belum terpakai untuk satu pengguna, tujuan, dan lot. */
    public static function aktifTerakhir(int $idPengguna, TujuanOtp $tujuan, ?int $idLot, bool $kunci = false): ?self
    {
        $q = static::where('id_pengguna', $idPengguna)
            ->where('tujuan', $tujuan->value)
            ->where('id_lot', $idLot)
            ->whereNull('terpakai_pada')
            ->orderByDesc('id_otp');

        return ($kunci ? $q->lockForUpdate() : $q)->first();
    }

    /** Kode terakhir yang pernah dibuat (terpakai atau belum), untuk menghitung jeda kirim ulang. */
    public static function terakhirDibuat(int $idPengguna, TujuanOtp $tujuan, ?int $idLot): ?self
    {
        return static::where('id_pengguna', $idPengguna)
            ->where('tujuan', $tujuan->value)
            ->where('id_lot', $idLot)
            ->orderByDesc('id_otp')
            ->first();
    }

    /** Hapus kode lama yang belum terpakai agar hanya kode terbaru yang berlaku. */
    public static function batalkanYangLama(int $idPengguna, TujuanOtp $tujuan, ?int $idLot): void
    {
        static::where('id_pengguna', $idPengguna)
            ->where('tujuan', $tujuan->value)
            ->where('id_lot', $idLot)
            ->whereNull('terpakai_pada')
            ->delete();
    }
}

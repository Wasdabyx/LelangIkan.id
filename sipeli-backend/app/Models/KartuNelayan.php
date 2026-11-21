<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Kelas entitas KartuNelayan - UC-11 FR-66: kartu ber-QR untuk pendataan kedatangan. */
class KartuNelayan extends Model
{
    protected $table = 'kartu_nelayan';
    protected $primaryKey = 'id_kartu';
    public $timestamps = false;

    public const AKTIF = 'AKTIF';
    public const DIBLOKIR = 'DIBLOKIR';
    public const KEDALUWARSA = 'KEDALUWARSA';

    protected $fillable = ['id_pemilik', 'nomor_kartu', 'token_qr', 'status', 'berlaku_sampai', 'waktu_terbit', 'alasan_blokir'];

    protected $casts = ['berlaku_sampai' => 'date', 'waktu_terbit' => 'datetime'];

    public function getIdKartu(): int { return (int) $this->id_kartu; }
    public function getNomorKartu(): string { return $this->nomor_kartu; }
    public function getTokenQr(): string { return $this->token_qr; }
    public function getStatus(): string { return $this->status; }

    public function pemilik()
    {
        return $this->belongsTo(PemilikKapal::class, 'id_pemilik', 'id_pemilik');
    }

    public function masihBerlaku(): bool
    {
        return $this->status === self::AKTIF && $this->berlaku_sampai->isFuture();
    }

    public function blokir(string $alasan): void
    {
        $this->status = self::DIBLOKIR;
        $this->alasan_blokir = $alasan;
        $this->save();
    }

    public function aktifkan(): void
    {
        $this->status = self::AKTIF;
        $this->alasan_blokir = null;
        $this->save();
    }

    /** Terbitkan kartu baru; nomor kartu berurutan per tahun terbit. */
    public static function terbitkan(PemilikKapal $pemilik, int $berlakuTahun = 3): self
    {
        $urut = static::whereYear('waktu_terbit', now()->year)->count() + 1;

        return static::create([
            'id_pemilik'     => $pemilik->getIdPemilik(),
            'nomor_kartu'    => 'NLY-' . now()->format('y') . '-' . str_pad((string) $urut, 4, '0', STR_PAD_LEFT),
            'token_qr'       => hash('sha256', $pemilik->getIdPemilik() . '|' . Str::random(40)),
            'status'         => self::AKTIF,
            'berlaku_sampai' => now()->addYears($berlakuTahun)->toDateString(),
            'waktu_terbit'   => now(),
        ]);
    }

    public static function cariDariQr(string $tokenQr): ?self
    {
        return static::where('token_qr', trim($tokenQr))->first();
    }

    public function ringkas(): array
    {
        return [
            'id_kartu'       => $this->getIdKartu(),
            'nomor_kartu'    => $this->getNomorKartu(),
            'nama_pemilik'   => $this->pemilik?->getNama(),
            'status'         => $this->getStatus(),
            'berlaku_sampai' => $this->berlaku_sampai->format('Y-m-d'),
            'masih_berlaku'  => $this->masihBerlaku(),
            'alasan_blokir'  => $this->alasan_blokir,
        ];
    }
}

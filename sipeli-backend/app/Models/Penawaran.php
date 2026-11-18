<?php

namespace App\Models;

use App\Enums\KanalPenawaran;
use App\Enums\StatusPenawaran;
use Illuminate\Database\Eloquent\Model;

/** Kelas entitas Penawaran (paket Lelang) - UC-04. */
class Penawaran extends Model
{
    protected $table = 'penawaran';
    protected $primaryKey = 'id_penawaran';
    public $timestamps = false;

    protected $fillable = [
        'id_sesi', 'id_pembeli', 'nominal', 'harga_per_kg', 'waktu_penawaran', 'status',
        'kanal', 'kunci_idempotensi', 'alamat_terminal',
    ];

    protected $casts = [
        'nominal'         => 'decimal:2',
        'harga_per_kg'    => 'decimal:2',
        'waktu_penawaran' => 'datetime',
    ];

    public function getIdPenawaran(): int { return $this->id_penawaran; }
    public function getIdSesi(): int { return $this->id_sesi; }
    public function getIdPembeli(): int { return $this->id_pembeli; }
    public function getNominal(): string { return $this->nominal; }
    public function getHargaPerKg(): string { return $this->harga_per_kg; }
    public function getWaktuPenawaran(): \DateTimeInterface { return $this->waktu_penawaran; }
    public function getStatus(): StatusPenawaran { return StatusPenawaran::from($this->status); }
    public function getKanal(): KanalPenawaran { return KanalPenawaran::from($this->kanal); }
    public function getKunciIdempotensi(): string { return $this->kunci_idempotensi; }

    public function sesi()
    {
        return $this->belongsTo(SesiLelang::class, 'id_sesi', 'id_sesi');
    }

    public function pembeli()
    {
        return $this->belongsTo(Pengguna::class, 'id_pembeli', 'id_pengguna');
    }

    public function tandaiTertinggi(): void
    {
        $this->status = StatusPenawaran::TERTINGGI->value;
        $this->save();
    }

    public function tandaiTertinggal(): void
    {
        $this->status = StatusPenawaran::TERTINGGAL->value;
        $this->save();
    }

    public static function tertinggiPadaSesi(int $idSesi): ?self
    {
        return static::where('id_sesi', $idSesi)
            ->where('status', StatusPenawaran::TERTINGGI->value)
            ->orderByDesc('harga_per_kg')
            ->orderBy('id_penawaran')
            ->first();
    }

    public static function cariKunci(string $kunci): ?self
    {
        return static::where('kunci_idempotensi', $kunci)->first();
    }
}

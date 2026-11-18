<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Kelas entitas TarifRetribusi (paket Keuangan) - ALG-05. */
class TarifRetribusi extends Model
{
    protected $table = 'tarif_retribusi';
    protected $primaryKey = 'id_tarif';
    public $timestamps = false;

    protected $fillable = ['nama', 'persentase', 'nominal_minimum', 'berlaku_mulai', 'berlaku_sampai', 'status'];

    protected $casts = [
        'persentase'      => 'decimal:2',
        'nominal_minimum' => 'decimal:2',
        'berlaku_mulai'   => 'date',
        'berlaku_sampai'  => 'date',
        'status'          => 'boolean',
    ];

    public function getIdTarif(): int { return $this->id_tarif; }
    public function getNama(): string { return $this->nama; }
    public function getPersentase(): string { return $this->persentase; }
    public function getNominalMinimum(): string { return $this->nominal_minimum; }
    public function isAktif(): bool { return (bool) $this->status; }

    /** ALG-05 langkah 2: retribusi = max(nominal_minimum, persentase x subtotal), dibulatkan ke bawah. */
    public function hitung(float $subtotal): float
    {
        return floor(max((float) $this->nominal_minimum, (float) $this->persentase / 100 * $subtotal));
    }

    /** ALG-05 langkah 1: tarif aktif dengan berlaku_mulai <= tanggal <= berlaku_sampai. */
    public static function cariBerlaku(\DateTimeInterface $tanggal): ?self
    {
        $t = $tanggal->format('Y-m-d');

        return static::where('status', true)
            ->where('berlaku_mulai', '<=', $t)
            ->where(fn ($q) => $q->whereNull('berlaku_sampai')->orWhere('berlaku_sampai', '>=', $t))
            ->orderByDesc('berlaku_mulai')
            ->first();
    }
}

<?php

namespace App\Models;

use App\Enums\StatusTangkapan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Kelas entitas Tangkapan (diagram kelas, paket Kedatangan dan tangkapan).
 * Satu baris tangkapan = satu jenis ikan + satu grade dalam satu kedatangan.
 */
class Tangkapan extends Model
{
    protected $table = 'tangkapan';
    protected $primaryKey = 'id_tangkapan';
    public $timestamps = false;

    protected $fillable = [
        'id_kedatangan', 'id_jenis', 'id_grade',
        'berat_estimasi', 'jumlah_keranjang', 'status',
    ];

    // Baris baru selalu berstatus BELUM_DITIMBANG
    protected $attributes = ['status' => 'BELUM_DITIMBANG'];

    protected $casts = ['berat_estimasi' => 'decimal:2'];

    private const BERAT_MAKSIMUM = 999999.99;

    // ---------------- get / set ----------------

    public function getIdTangkapan(): int
    {
        return (int) $this->id_tangkapan;
    }

    public function getIdKedatangan(): int
    {
        return (int) $this->id_kedatangan;
    }

    public function getIdJenis(): int
    {
        return (int) $this->id_jenis;
    }

    public function setIdJenis(int $idJenis): void
    {
        $this->id_jenis = $idJenis;
    }

    public function getIdGrade(): int
    {
        return (int) $this->id_grade;
    }

    public function setIdGrade(int $idGrade): void
    {
        $this->id_grade = $idGrade;
    }

    public function getBeratEstimasi(): string
    {
        return $this->berat_estimasi;
    }

    public function setBeratEstimasi(string|float $berat): void
    {
        $this->berat_estimasi = (string) $berat;
    }

    public function getJumlahKeranjang(): int
    {
        return (int) $this->jumlah_keranjang;
    }

    public function setJumlahKeranjang(int $jumlah): void
    {
        $this->jumlah_keranjang = $jumlah;
    }

    /** Tidak ada setStatus(): status berubah lewat tandaiDitimbang(). */
    public function getStatus(): StatusTangkapan
    {
        return StatusTangkapan::from($this->status);
    }

    // ---------------- relasi ----------------

    public function kedatangan()
    {
        return $this->belongsTo(KedatanganKapal::class, 'id_kedatangan');
    }

    public function jenisIkan()
    {
        return $this->belongsTo(JenisIkan::class, 'id_jenis');
    }

    public function grade()
    {
        return $this->belongsTo(GradeMutu::class, 'id_grade');
    }

    /** Satu baris tangkapan menghasilkan paling banyak satu lot (multiplisitas 0..1). */
    public function lot()
    {
        return $this->hasOne(LotLelang::class, 'id_tangkapan');
    }

    // ---------------- metode domain ----------------

    public function validasiBerat(float $berat): bool
    {
        return $berat > 0 && $berat <= self::BERAT_MAKSIMUM;
    }

    public function isSudahDitimbang(): bool
    {
        return $this->getStatus() === StatusTangkapan::DITIMBANG;
    }

    /** BELUM_DITIMBANG -> DITIMBANG. Ditolak bila sudah pernah ditimbang. */
    public function tandaiDitimbang(): void
    {
        if ($this->isSudahDitimbang()) {
            throw ValidationException::withMessages([
                'id_tangkapan' => 'Baris tangkapan ini sudah ditimbang.',
            ]);
        }
        $this->status = StatusTangkapan::DITIMBANG->value;
        $this->save();
    }

    public static function cari(int $id): ?self
    {
        return static::find($id);
    }

    /** Ambil dan kunci baris (SELECT ... FOR UPDATE) agar tidak ditimbang dua kali bersamaan. */
    public static function kunciUntukTimbang(int $id): self
    {
        return static::lockForUpdate()->findOrFail($id);
    }
}

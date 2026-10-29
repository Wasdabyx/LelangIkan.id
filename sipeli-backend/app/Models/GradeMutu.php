<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Kelas entitas GradeMutu (diagram kelas, paket Kedatangan dan tangkapan).
 */
class GradeMutu extends Model
{
    protected $table = 'grade_mutu';
    protected $primaryKey = 'id_grade';
    public $timestamps = false;

    protected $fillable = ['kode_grade', 'deskripsi', 'faktor_harga'];

    protected $casts = ['faktor_harga' => 'decimal:4'];

    // ---------------- get / set ----------------

    public function getIdGrade(): int
    {
        return (int) $this->id_grade;
    }

    public function getKodeGrade(): string
    {
        return $this->kode_grade;
    }

    public function setKodeGrade(string $kode): void
    {
        $this->kode_grade = strtoupper($kode);
    }

    public function getDeskripsi(): ?string
    {
        return $this->deskripsi;
    }

    public function setDeskripsi(?string $deskripsi): void
    {
        $this->deskripsi = $deskripsi;
    }

    public function getFaktorHarga(): string
    {
        return $this->faktor_harga;
    }

    public function setFaktorHarga(string|float $faktor): void
    {
        $this->faktor_harga = (string) $faktor;
    }

    // ---------------- metode domain ----------------

    public function simpan(): void
    {
        $this->save();
    }

    /** @return Collection<int, GradeMutu> */
    public static function cariSemua(): Collection
    {
        return static::orderBy('kode_grade')->get();
    }
}

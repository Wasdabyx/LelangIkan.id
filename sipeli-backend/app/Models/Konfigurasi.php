<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kelas entitas Konfigurasi (diagram kelas, paket Kelembagaan dan pengguna).
 * Kunci dan nilai hanya bisa diubah lewat ubahNilai() agar pengubahnya tercatat.
 */
class Konfigurasi extends Model
{
    protected $table = 'konfigurasi';
    protected $primaryKey = 'id_konfigurasi';

    const CREATED_AT = null;
    const UPDATED_AT = 'waktu_ubah';

    protected $fillable = ['kunci', 'nilai', 'deskripsi', 'diubah_oleh'];

    // ---------------- get / set ----------------

    public function getIdKonfigurasi(): int
    {
        return (int) $this->id_konfigurasi;
    }

    public function getKunci(): string
    {
        return $this->kunci;
    }

    public function getNilai(): string
    {
        return (string) $this->getAttribute('nilai');
    }

    public function getDeskripsi(): ?string
    {
        return $this->deskripsi;
    }

    public function setDeskripsi(?string $deskripsi): void
    {
        $this->deskripsi = $deskripsi;
    }

    public function getDiubahOleh(): ?int
    {
        return $this->diubah_oleh === null ? null : (int) $this->diubah_oleh;
    }

    public function getWaktuUbah(): ?\DateTimeInterface
    {
        return $this->waktu_ubah;
    }

    // ---------------- metode domain ----------------

    public function ubahNilai(string $nilaiBaru, int $olehPengguna): void
    {
        $this->setAttribute('nilai', $nilaiBaru);
        $this->diubah_oleh = $olehPengguna;
        $this->save();   // waktu_ubah terisi otomatis
    }

    public function nilaiAngka(): float
    {
        return (float) $this->getNilai();
    }

    /** Ambil nilai konfigurasi berdasarkan kunci, contoh: Konfigurasi::nilai('kode_tpi') */
    public static function nilai(string $kunci, mixed $bawaan = null): mixed
    {
        return static::where('kunci', $kunci)->value('nilai') ?? $bawaan;
    }

    public static function cariKunci(string $kunci): ?self
    {
        return static::where('kunci', $kunci)->first();
    }
}

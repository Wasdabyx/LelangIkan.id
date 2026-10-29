<?php

namespace App\Models;

use App\Enums\KodePeran;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Kelas entitas Peran (diagram kelas, paket Kelembagaan dan pengguna).
 */
class Peran extends Model
{
    protected $table = 'peran';
    protected $primaryKey = 'id_peran';
    public $timestamps = false;

    protected $fillable = ['nama_peran', 'deskripsi'];

    // ---------------- get / set ----------------

    public function getIdPeran(): int
    {
        return (int) $this->id_peran;
    }

    public function getNamaPeran(): string
    {
        return $this->nama_peran;
    }

    public function setNamaPeran(string $nama): void
    {
        $this->nama_peran = $nama;
    }

    public function getDeskripsi(): ?string
    {
        return $this->deskripsi;
    }

    public function setDeskripsi(?string $deskripsi): void
    {
        $this->deskripsi = $deskripsi;
    }

    // ---------------- relasi ----------------

    public function pengguna()
    {
        return $this->hasMany(Pengguna::class, 'id_peran');
    }

    // ---------------- metode domain ----------------

    /** Peran dalam bentuk enumerasi, misalnya KodePeran::PETUGAS_TIMBANG. */
    public function kode(): KodePeran
    {
        return KodePeran::from($this->getIdPeran());
    }

    /** @return Collection<int, Pengguna> */
    public function daftarPengguna(): Collection
    {
        return $this->pengguna()->get();
    }

    public function simpan(): void
    {
        $this->save();
    }

    public static function cari(int $id): ?self
    {
        return static::find($id);
    }
}

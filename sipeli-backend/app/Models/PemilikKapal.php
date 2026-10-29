<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Kelas entitas PemilikKapal (diagram kelas, paket Nelayan dan kapal).
 * Metode kartuAktif(), terbitkanTautan(), dan rekapHasilLelang() milik fitur F3
 * (kartu nelayan) dan baru dibuat bila tabelnya sudah ada.
 */
class PemilikKapal extends Model
{
    protected $table = 'pemilik_kapal';
    protected $primaryKey = 'id_pemilik';
    public $timestamps = false;

    protected $fillable = ['nama', 'nik', 'no_telepon', 'alamat', 'no_rekening', 'nama_bank'];

    // ---------------- get / set ----------------

    public function getIdPemilik(): int
    {
        return (int) $this->id_pemilik;
    }

    public function getNama(): string
    {
        return $this->nama;
    }

    public function setNama(string $nama): void
    {
        $this->nama = $nama;
    }

    public function getNik(): string
    {
        return $this->nik;
    }

    public function setNik(string $nik): void
    {
        $this->nik = $nik;
    }

    public function getNoTelepon(): ?string
    {
        return $this->no_telepon;
    }

    public function setNoTelepon(?string $nomor): void
    {
        $this->no_telepon = $nomor;
    }

    public function getAlamat(): ?string
    {
        return $this->alamat;
    }

    public function setAlamat(?string $alamat): void
    {
        $this->alamat = $alamat;
    }

    public function getNoRekening(): ?string
    {
        return $this->no_rekening;
    }

    public function setNoRekening(?string $nomor): void
    {
        $this->no_rekening = $nomor;
    }

    public function getNamaBank(): ?string
    {
        return $this->nama_bank;
    }

    public function setNamaBank(?string $bank): void
    {
        $this->nama_bank = $bank;
    }

    // ---------------- relasi ----------------

    public function kapal()
    {
        return $this->hasMany(Kapal::class, 'id_pemilik');
    }

    // ---------------- metode domain ----------------

    /** @return Collection<int, Kapal> */
    public function daftarKapal(): Collection
    {
        return $this->kapal()->orderBy('nama_kapal')->get();
    }

    /** Nomor rekening yang aman ditampilkan, contoh: ******3344 */
    public function samarkanRekening(): string
    {
        $rekening = (string) $this->getNoRekening();
        if (strlen($rekening) <= 4) {
            return $rekening;
        }

        return str_repeat('*', strlen($rekening) - 4) . substr($rekening, -4);
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

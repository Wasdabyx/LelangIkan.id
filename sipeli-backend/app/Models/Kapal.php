<?php

namespace App\Models;

use App\Enums\StatusKapal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Kelas entitas Kapal (diagram kelas, paket Nelayan dan kapal).
 * Nilai decimal dikembalikan sebagai string agar presisinya tidak hilang.
 */
class Kapal extends Model
{
    protected $table = 'kapal';
    protected $primaryKey = 'id_kapal';
    public $timestamps = false;

    protected $fillable = ['nama_kapal', 'nomor_registrasi', 'gt', 'id_pemilik', 'status'];

    protected $casts = ['gt' => 'decimal:2'];

    // ---------------- get / set ----------------

    public function getIdKapal(): int
    {
        return (int) $this->id_kapal;
    }

    public function getNamaKapal(): string
    {
        return $this->nama_kapal;
    }

    public function setNamaKapal(string $nama): void
    {
        $this->nama_kapal = $nama;
    }

    public function getNomorRegistrasi(): string
    {
        return $this->nomor_registrasi;
    }

    public function setNomorRegistrasi(string $nomor): void
    {
        $this->nomor_registrasi = $nomor;
    }

    public function getGt(): ?string
    {
        return $this->gt;
    }

    public function setGt(string|float|null $gt): void
    {
        $this->gt = is_float($gt) ? (string) $gt : $gt;
    }

    public function getIdPemilik(): int
    {
        return (int) $this->id_pemilik;
    }

    public function setIdPemilik(int $idPemilik): void
    {
        $this->id_pemilik = $idPemilik;
    }

    public function getStatus(): StatusKapal
    {
        return StatusKapal::from($this->status);
    }

    // ---------------- relasi ----------------

    public function pemilik()
    {
        return $this->belongsTo(PemilikKapal::class, 'id_pemilik');
    }

    public function kedatangan()
    {
        return $this->hasMany(KedatanganKapal::class, 'id_kapal');
    }

    // ---------------- metode domain ----------------

    /** @return Collection<int, KedatanganKapal> kedatangan antara dua tanggal, terbaru dulu */
    public function riwayatKedatangan(string $awal, string $akhir): Collection
    {
        return $this->kedatangan()
            ->whereBetween('tanggal', [$awal, $akhir])
            ->orderByDesc('tanggal')->orderByDesc('jam')
            ->get();
    }

    public function isAktif(): bool
    {
        return $this->getStatus() === StatusKapal::AKTIF;
    }

    public function nonaktifkan(): void
    {
        $this->status = StatusKapal::NONAKTIF->value;
        $this->simpan();
    }

    public function aktifkan(): void
    {
        $this->status = StatusKapal::AKTIF->value;
        $this->simpan();
    }

    public function simpan(): void
    {
        $this->save();
    }

    /**
     * FR-02: cari kapal AKTIF berdasarkan nama atau nomor registrasi.
     * @return Collection<int, Kapal>
     */
    public static function cari(string $kataKunci, int $batas = 10): Collection
    {
        return static::with('pemilik')
            ->where('status', StatusKapal::AKTIF->value)
            ->where(function ($q) use ($kataKunci) {
                $q->where('nama_kapal', 'like', "%{$kataKunci}%")
                  ->orWhere('nomor_registrasi', 'like', "%{$kataKunci}%");
            })
            ->orderBy('nama_kapal')
            ->limit($batas)
            ->get();
    }
}

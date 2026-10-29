<?php

namespace App\Models;

use App\Enums\KategoriIkan;
use App\Enums\SatuanIkan;
use App\Services\LotService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Kelas entitas JenisIkan (diagram kelas, paket Kedatangan dan tangkapan).
 */
class JenisIkan extends Model
{
    protected $table = 'jenis_ikan';
    protected $primaryKey = 'id_jenis';
    public $timestamps = false;

    protected $fillable = ['nama_jenis', 'nama_ilmiah', 'kategori', 'satuan', 'status'];

    protected $casts = ['status' => 'boolean'];

    // ---------------- get / set ----------------

    public function getIdJenis(): int
    {
        return (int) $this->id_jenis;
    }

    public function getNamaJenis(): string
    {
        return $this->nama_jenis;
    }

    public function setNamaJenis(string $nama): void
    {
        $this->nama_jenis = $nama;
    }

    public function getNamaIlmiah(): ?string
    {
        return $this->nama_ilmiah;
    }

    public function setNamaIlmiah(?string $nama): void
    {
        $this->nama_ilmiah = $nama;
    }

    public function getKategori(): KategoriIkan
    {
        return KategoriIkan::from($this->kategori);
    }

    public function setKategori(KategoriIkan $kategori): void
    {
        $this->kategori = $kategori->value;
    }

    public function getSatuan(): SatuanIkan
    {
        return SatuanIkan::from($this->satuan);
    }

    public function setSatuan(SatuanIkan $satuan): void
    {
        $this->satuan = $satuan->value;
    }

    // ---------------- metode domain ----------------

    public function isAktif(): bool
    {
        return (bool) $this->status;
    }

    public function nonaktifkan(): void
    {
        $this->status = false;
        $this->simpan();
    }

    /** Usulan harga pembukaan per kg untuk grade tertentu (ALG-06), null bila belum ada riwayat. */
    public function usulanHarga(GradeMutu $grade): ?float
    {
        $usulan = app(LotService::class)->usulanHarga($this->getIdJenis(), $grade->getIdGrade());

        return $usulan['harga'] === null ? null : (float) $usulan['harga'];
    }

    public function simpan(): void
    {
        $this->save();
    }

    /** @return Collection<int, JenisIkan> */
    public static function cariSemuaAktif(): Collection
    {
        return static::where('status', true)->orderBy('nama_jenis')->get();
    }
}

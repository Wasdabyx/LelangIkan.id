<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Kelas entitas FotoLot (class diagram revisi).
 * Foto ikan pada sebuah lot, dipotret Petugas Timbang sebelum lot dilelang.
 * LotLelang 1 *-- 0..* FotoLot (komposisi).
 */
class FotoLot extends Model
{
    protected $table = 'foto_lot';
    protected $primaryKey = 'id_foto';

    const CREATED_AT = 'waktu_unggah';
    const UPDATED_AT = null;

    protected $fillable = ['id_lot', 'path_berkas', 'keterangan', 'diunggah_oleh'];

    protected function casts(): array
    {
        return ['waktu_unggah' => 'datetime'];
    }

    // ---------------- get / set ----------------

    public function getIdFoto(): int
    {
        return (int) $this->id_foto;
    }

    public function getIdLot(): int
    {
        return (int) $this->id_lot;
    }

    public function getPathBerkas(): string
    {
        return $this->path_berkas;
    }

    public function getKeterangan(): ?string
    {
        return $this->keterangan;
    }

    public function setKeterangan(?string $keterangan): void
    {
        $this->keterangan = $keterangan;
    }

    public function getDiunggahOleh(): int
    {
        return (int) $this->diunggah_oleh;
    }

    public function getWaktuUnggah(): ?\DateTimeInterface
    {
        return $this->waktu_unggah;
    }

    // ---------------- perilaku ----------------

    /**
     * Alamat foto relatif terhadap alamat API, contoh "foto-lot/xxxx.jpg"
     * (aplikasi mobile menggabungkannya dengan API_URL, ditayangkan oleh GET /api/foto-lot/{nama}).
     */
    public function urlPublik(): string
    {
        return 'foto-lot/' . basename($this->path_berkas);
    }

    /** Hapus berkas foto dari penyimpanan beserta datanya. */
    public function hapus(): void
    {
        Storage::disk('local')->delete($this->path_berkas);
        $this->delete();
    }

    /** Catat foto baru untuk lot. $berkas = path berkas yang sudah tersimpan di disk local. */
    public static function unggah(LotLelang $lot, string $berkas, Pengguna $pengunggah): self
    {
        return self::create([
            'id_lot'        => $lot->getIdLot(),
            'path_berkas'   => $berkas,
            'diunggah_oleh' => $pengunggah->getIdPengguna(),
        ]);
    }

    // ---------------- relasi ----------------

    public function lot()
    {
        return $this->belongsTo(LotLelang::class, 'id_lot');
    }

    public function pengunggah()
    {
        return $this->belongsTo(Pengguna::class, 'diunggah_oleh');
    }
}

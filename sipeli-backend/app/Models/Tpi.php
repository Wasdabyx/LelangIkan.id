<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Kelas entitas Tpi (diagram kelas, paket Kelembagaan dan pengguna).
 */
class Tpi extends Model
{
    protected $table = 'tpi';
    protected $primaryKey = 'id_tpi';
    public $timestamps = false;

    protected $fillable = ['nama_tpi', 'alamat', 'kabupaten', 'kapasitas_ton', 'jam_operasi'];

    protected function casts(): array
    {
        return ['kapasitas_ton' => 'decimal:2'];
    }

    // ---------------- get / set ----------------

    public function getIdTpi(): int
    {
        return (int) $this->id_tpi;
    }

    public function getNamaTpi(): string
    {
        return $this->nama_tpi;
    }

    public function setNamaTpi(string $nama): void
    {
        $this->nama_tpi = $nama;
    }

    public function getAlamat(): ?string
    {
        return $this->alamat;
    }

    public function setAlamat(?string $alamat): void
    {
        $this->alamat = $alamat;
    }

    public function getKabupaten(): ?string
    {
        return $this->kabupaten;
    }

    public function setKabupaten(?string $kabupaten): void
    {
        $this->kabupaten = $kabupaten;
    }

    public function getKapasitasTon(): ?float
    {
        return $this->kapasitas_ton === null ? null : (float) $this->kapasitas_ton;
    }

    public function setKapasitasTon(?float $ton): void
    {
        $this->kapasitas_ton = $ton;
    }

    public function getJamOperasi(): ?string
    {
        return $this->jam_operasi;
    }

    public function setJamOperasi(?string $jam): void
    {
        $this->jam_operasi = $jam;
    }

    // ---------------- relasi ----------------

    public function pengguna()
    {
        return $this->hasMany(Pengguna::class, 'id_tpi');
    }

    // ---------------- metode domain ----------------

    /** @return Collection<int, Pengguna> */
    public function daftarPengguna(): Collection
    {
        return $this->pengguna()->get();
    }

    /** Total berat lot yang ditimbang di TPI ini pada satu tanggal, dalam ton. */
    public function kapasitasTerpakai(string $tanggal): float
    {
        $kg = DB::table('lot_lelang as l')
            ->join('tangkapan as t', 't.id_tangkapan', '=', 'l.id_tangkapan')
            ->join('kedatangan_kapal as k', 'k.id_kedatangan', '=', 't.id_kedatangan')
            ->where('k.id_tpi', $this->getIdTpi())
            ->whereDate('l.waktu_timbang', $tanggal)
            ->sum('l.berat_aktual');

        return round((float) $kg / 1000, 3);
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

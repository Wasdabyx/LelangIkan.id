<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Kelas entitas DaftarPantauan (class diagram DPPL).
 * Tugas 2: pembeli yang mendaftar ikut lelang sebuah lot setelah konfirmasi OTP,
 * ditandai dengan kode_pemesanan PSN-YYMMDD-####.
 */
class DaftarPantauan extends Model
{
    protected $table = 'daftar_pantauan';
    protected $primaryKey = 'id_pantauan';

    const CREATED_AT = 'waktu_ditambah';
    const UPDATED_AT = null;

    protected $fillable = ['kode_pemesanan', 'id_pembeli', 'id_lot', 'harga_maksimum', 'notifikasi_aktif'];

    protected function casts(): array
    {
        return [
            'notifikasi_aktif' => 'boolean',
            'waktu_ditambah'   => 'datetime',
        ];
    }

    // ---------------- get / set ----------------

    public function getIdPantauan(): int
    {
        return (int) $this->id_pantauan;
    }

    public function getKodePemesanan(): string
    {
        return $this->kode_pemesanan;
    }

    public function getIdPembeli(): int
    {
        return (int) $this->id_pembeli;
    }

    public function getIdLot(): int
    {
        return (int) $this->id_lot;
    }

    public function getHargaMaksimum(): ?string
    {
        return $this->harga_maksimum;
    }

    public function setHargaMaksimum(?string $harga): void
    {
        $this->harga_maksimum = $harga;
    }

    public function isNotifikasiAktif(): bool
    {
        return (bool) $this->notifikasi_aktif;
    }

    public function aktifkanNotifikasi(): void
    {
        $this->notifikasi_aktif = true;
        $this->save();
    }

    public function matikanNotifikasi(): void
    {
        $this->notifikasi_aktif = false;
        $this->save();
    }

    public function getWaktuDitambah(): ?\DateTimeInterface
    {
        return $this->waktu_ditambah;
    }

    // ---------------- relasi ----------------

    public function lot()
    {
        return $this->belongsTo(LotLelang::class, 'id_lot');
    }

    public function pembeli()
    {
        return $this->belongsTo(Pengguna::class, 'id_pembeli');
    }

    // ---------------- metode domain ----------------

    public function melebihiBatas(float $harga): bool
    {
        return $this->harga_maksimum !== null && $harga > (float) $this->harga_maksimum;
    }

    public function hapus(): void
    {
        $this->delete();
    }

    /** Wajib dipanggil di dalam DB::transaction (kode pemesanan dibuat dengan kunci baris). */
    public static function tambah(Pengguna $pembeli, LotLelang $lot): self
    {
        return static::create([
            'kode_pemesanan' => static::buatKodePemesanan(now()),
            'id_pembeli'     => $pembeli->getIdPengguna(),
            'id_lot'         => $lot->getIdLot(),
        ]);
    }

    /** Kode pemesanan unik PSN-[YYMMDD]-[urut 4 digit], contoh PSN-261114-0001. */
    public static function buatKodePemesanan(\DateTimeInterface $tanggal): string
    {
        $awalan = 'PSN-' . $tanggal->format('ymd') . '-';

        $terakhir = static::where('kode_pemesanan', 'like', $awalan . '%')
            ->lockForUpdate()
            ->orderByDesc('kode_pemesanan')
            ->value('kode_pemesanan');

        $urut = $terakhir ? ((int) substr($terakhir, -4)) + 1 : 1;

        return $awalan . str_pad((string) $urut, 4, '0', STR_PAD_LEFT);
    }

    public static function cari(int $idPembeli, int $idLot): ?self
    {
        return static::where('id_pembeli', $idPembeli)->where('id_lot', $idLot)->first();
    }

    /** @return Collection<int, DaftarPantauan> */
    public static function milikPembeli(int $idPembeli): Collection
    {
        return static::where('id_pembeli', $idPembeli)->orderByDesc('id_pantauan')->get();
    }

    /** @return Collection<int, Pengguna> */
    public static function pemantauLot(int $idLot): Collection
    {
        return Pengguna::whereIn('id_pengguna', static::where('id_lot', $idLot)->pluck('id_pembeli'))->get();
    }
}

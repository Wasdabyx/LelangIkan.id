<?php

namespace App\Models;

use Illuminate\Database\DeadlockException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Kelas entitas DaftarPantauan (class diagram DPPL).
 * Tugas 2: pembeli yang mendaftar ikut lelang sebuah lot setelah konfirmasi OTP,
 * ditandai dengan kode_pemesanan PNT-YYMMDD-#### (urut harian, dibuat di dalam transaksi).
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

    /** Daftarkan pembeli ke daftar pantauan (dipakai PemesananService setelah OTP). */
    public static function tambah(Pengguna $pembeli, LotLelang $lot): self
    {
        return static::daftarkan($pembeli->getIdPengguna(), $lot->getIdLot());
    }

    /**
     * Daftarkan pembeli ke daftar pantauan secara aman untuk pemakaian bersamaan (FR-64).
     *
     * Versi lama membuat kode pemesanan dari angka acak 1..9999 sehingga dua pembeli yang
     * mendaftar berdekatan bisa memperoleh kode sama dan salah satunya gagal (galat 500 pada
     * indeks unik kode_pemesanan). Versi pertama perbaikan memakai nomor urut dengan
     * lockForUpdate, tetapi 100 pendaftaran bersamaan saling menunggu kunci sehingga banyak
     * deadlock. Solusi akhir: baris disisipkan dengan kode sementara, lalu kode final
     * diturunkan dari kunci utama (id_pantauan) sehingga unik tanpa perlu membaca data lain.
     */
    public static function daftarkan(int $idPembeli, int $idLot, array $atribut = [], int $percobaan = 5): self
    {
        for ($ke = 1; ; $ke++) {
            try {
                return DB::transaction(function () use ($idPembeli, $idLot, $atribut) {
                    $pantauan = static::create([
                        'id_pembeli'     => $idPembeli,
                        'id_lot'         => $idLot,
                        'kode_pemesanan' => static::kodeSementara(),
                    ] + $atribut);

                    $pantauan->kode_pemesanan = static::buatKodePemesanan($pantauan->getKey(), now());
                    $pantauan->save();

                    return $pantauan;
                }, 3);
            } catch (UniqueConstraintViolationException | DeadlockException $e) {
                // Sudah terdaftar oleh permintaan lain (indeks unik id_pembeli+id_lot).
                if ($ada = static::cari($idPembeli, $idLot)) {
                    return $ada;
                }
                if ($ke >= $percobaan) {
                    throw $e;
                }
                usleep(random_int(5, 30) * 1000);
            }
        }
    }

    /** Kode sementara (15 karakter, sama dengan lebar kolom) sampai kode final dibuat. */
    protected static function kodeSementara(): string
    {
        return 'TMP' . strtoupper(bin2hex(random_bytes(6)));   // 15 karakter: selebar kolom
    }

    /**
     * Kode pemesanan unik PNT-[YYMMDD]-[nomor], contoh PNT-261123-0001.
     *
     * Nomor memakai id_pantauan agar unik tanpa membaca data lain. Kolomnya char(15), jadi
     * setelah id melewati 9999 nomornya ditulis basis-36 (tetap angka/huruf besar, tetap unik).
     */
    public static function buatKodePemesanan(int $idPantauan, ?\DateTimeInterface $tanggal = null): string
    {
        $tanggal ??= now();

        $nomor = $idPantauan <= 9999
            ? str_pad((string) $idPantauan, 4, '0', STR_PAD_LEFT)
            : strtoupper(base_convert((string) $idPantauan, 10, 36));

        return 'PNT-' . $tanggal->format('ymd') . '-' . $nomor;
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

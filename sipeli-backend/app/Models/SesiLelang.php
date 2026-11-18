<?php

namespace App\Models;

use App\Enums\ModeLelang;
use App\Enums\StatusSesi;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Kelas entitas SesiLelang (paket Lelang) - UC-03 s.d. UC-05.
 * Versi ini menjalankan mode NAIK (kelas LelangNaik pada diagram kelas): kenaikan minimum,
 * perpanjangan otomatis (anti-sniping), dan pemenang = penawaran tertinggi.
 * Kolom status dibiarkan string (tanpa cast enum) agar perbandingan konsisten dengan model lain.
 */
class SesiLelang extends Model
{
    protected $table = 'sesi_lelang';
    protected $primaryKey = 'id_sesi';
    public $timestamps = false;

    protected $fillable = [
        'id_lot', 'id_operator', 'mode', 'waktu_mulai', 'waktu_selesai_rencana', 'durasi_detik',
        'harga_berjalan', 'id_penawaran_pemenang', 'status', 'kenaikan_minimum', 'jumlah_perpanjangan',
        'maks_perpanjangan', 'ambang_perpanjangan_detik', 'waktu_selesai_aktual', 'alasan_tutup',
    ];

    protected $casts = [
        'waktu_mulai'           => 'datetime',
        'waktu_selesai_rencana' => 'datetime',
        'waktu_selesai_aktual'  => 'datetime',
        'harga_berjalan'        => 'decimal:2',
        'kenaikan_minimum'      => 'decimal:2',
    ];

    // ---------------- get ----------------
    public function getIdSesi(): int { return $this->id_sesi; }
    public function getIdLot(): int { return $this->id_lot; }
    public function getIdOperator(): int { return $this->id_operator; }
    public function getMode(): ModeLelang { return ModeLelang::from($this->mode); }
    public function getWaktuMulai(): \DateTimeInterface { return $this->waktu_mulai; }
    public function getWaktuSelesaiRencana(): \DateTimeInterface { return $this->waktu_selesai_rencana; }
    public function getDurasiDetik(): int { return (int) $this->durasi_detik; }
    public function getHargaBerjalan(): ?string { return $this->harga_berjalan; }
    public function getStatus(): StatusSesi { return StatusSesi::from($this->status); }
    public function getKenaikanMinimum(): string { return $this->kenaikan_minimum; }
    public function getJumlahPerpanjangan(): int { return (int) $this->jumlah_perpanjangan; }
    public function getMaksPerpanjangan(): int { return (int) $this->maks_perpanjangan; }
    public function getAlasanTutup(): ?string { return $this->alasan_tutup; }

    // ---------------- relasi ----------------
    public function lot()
    {
        return $this->belongsTo(LotLelang::class, 'id_lot', 'id_lot');
    }

    public function operator()
    {
        return $this->belongsTo(Pengguna::class, 'id_operator', 'id_pengguna');
    }

    public function daftarPenawaran()
    {
        return $this->hasMany(Penawaran::class, 'id_sesi', 'id_sesi');
    }

    public function penawaranPemenang()
    {
        return $this->belongsTo(Penawaran::class, 'id_penawaran_pemenang', 'id_penawaran');
    }

    public function transaksi()
    {
        return $this->hasOne(TransaksiPenjualan::class, 'id_sesi', 'id_sesi');
    }

    // ---------------- perilaku ----------------
    public function isMasihAktif(): bool
    {
        return $this->getStatus() === StatusSesi::AKTIF && $this->sisaDetik() > 0;
    }

    /** Sisa waktu dalam detik (0 bila waktunya sudah lewat). */
    public function sisaDetik(): int
    {
        $ms = now()->diffInMilliseconds($this->waktu_selesai_rencana, false);

        return $ms > 0 ? (int) ceil($ms / 1000) : 0;
    }

    /** Harga per kg saat ini: harga tertinggi, atau harga pembukaan bila belum ada tawaran. */
    public function hargaSaatIni(): string
    {
        return $this->harga_berjalan ?? $this->lot->getHargaPembukaan();
    }

    /** FR-18 / ALG-01 langkah 5-6: tawaran pertama minimal harga pembukaan, berikutnya + kenaikan minimum. */
    public function hargaMinimumBerikut(): float
    {
        if ($this->harga_berjalan === null) {
            return (float) $this->lot->getHargaPembukaan();
        }

        return (float) $this->harga_berjalan + (float) $this->kenaikan_minimum;
    }

    /** ALG-03 anti-sniping. Mengembalikan true bila sesi diperpanjang. Dipanggil di dalam transaksi. */
    public function perpanjangBilaPerlu(int $tambahanDetik): bool
    {
        $sisa = $this->sisaDetik();
        if ($sisa > (int) $this->ambang_perpanjangan_detik || $this->getJumlahPerpanjangan() >= $this->getMaksPerpanjangan()) {
            return false;
        }
        $this->waktu_selesai_rencana = now()->addSeconds($tambahanDetik);
        $this->jumlah_perpanjangan = $this->getJumlahPerpanjangan() + 1;
        $this->save();

        return true;
    }

    public function tertinggi(): ?Penawaran
    {
        return Penawaran::tertinggiPadaSesi($this->getIdSesi());
    }

    public static function buka(LotLelang $lot, Pengguna $operator, int $durasi, float $kenaikan, int $maksPerpanjangan, int $ambang): self
    {
        return static::create([
            'id_lot'                    => $lot->getIdLot(),
            'id_operator'               => $operator->getIdPengguna(),
            'mode'                      => ModeLelang::NAIK->value,
            'waktu_mulai'               => now(),
            'waktu_selesai_rencana'     => now()->addSeconds($durasi),
            'durasi_detik'              => $durasi,
            'status'                    => StatusSesi::AKTIF->value,
            'kenaikan_minimum'          => (string) $kenaikan,
            'jumlah_perpanjangan'       => 0,
            'maks_perpanjangan'         => $maksPerpanjangan,
            'ambang_perpanjangan_detik' => $ambang,
        ]);
    }

    /** FR-14: sesi AKTIF untuk satu lot (paling banyak satu). */
    public static function aktifUntukLot(int $idLot): ?self
    {
        return static::where('id_lot', $idLot)->where('status', StatusSesi::AKTIF->value)->first();
    }

    /** @return Collection<int, SesiLelang> sesi AKTIF di satu TPI */
    public static function cariAktif(int $idTpi): Collection
    {
        return static::where('status', StatusSesi::AKTIF->value)
            ->whereHas('lot.tangkapan.kedatangan', fn ($q) => $q->where('id_tpi', $idTpi))
            ->orderBy('waktu_selesai_rencana')
            ->get();
    }

    /** @return Collection<int, SesiLelang> sesi AKTIF yang waktunya sudah habis (untuk ditutup otomatis) */
    public static function kedaluwarsa(): Collection
    {
        return static::where('status', StatusSesi::AKTIF->value)
            ->where('waktu_selesai_rencana', '<=', now())
            ->get();
    }
}

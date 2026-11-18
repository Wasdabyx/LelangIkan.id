<?php

namespace App\Models;

use App\Enums\MetodeBayar;
use Illuminate\Database\Eloquent\Model;

/** Kelas entitas Pembayaran (paket Keuangan) - UC-06. */
class Pembayaran extends Model
{
    protected $table = 'pembayaran';
    protected $primaryKey = 'id_pembayaran';
    public $timestamps = false;

    protected $fillable = ['id_transaksi', 'metode', 'nominal', 'waktu_bayar', 'id_kasir', 'nomor_referensi'];

    protected $casts = [
        'nominal'     => 'decimal:2',
        'waktu_bayar' => 'datetime',
    ];

    public function getIdPembayaran(): int { return $this->id_pembayaran; }
    public function getIdTransaksi(): int { return $this->id_transaksi; }
    public function getMetode(): MetodeBayar { return MetodeBayar::from($this->metode); }
    public function getNominal(): string { return $this->nominal; }
    public function getWaktuBayar(): \DateTimeInterface { return $this->waktu_bayar; }
    public function getIdKasir(): int { return $this->id_kasir; }
    public function getNomorReferensi(): ?string { return $this->nomor_referensi; }

    public function transaksi()
    {
        return $this->belongsTo(TransaksiPenjualan::class, 'id_transaksi', 'id_transaksi');
    }

    public function kasir()
    {
        return $this->belongsTo(Pengguna::class, 'id_kasir', 'id_pengguna');
    }

    /** FR-30: nominal harus > 0 dan tidak melebihi sisa tagihan. */
    public static function validasiNominal(float $nominal, float $sisa): bool
    {
        return $nominal > 0 && $nominal <= $sisa;
    }
}

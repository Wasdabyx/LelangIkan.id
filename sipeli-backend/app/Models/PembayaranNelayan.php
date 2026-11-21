<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Kelas entitas PembayaranNelayan (paket Keuangan) - UC-08.
 * Satu baris ringkasan per kedatangan kapal; pencairan bertahap dicatat pada PencairanNelayan.
 * Atribut mengikuti kamus data DPPL (pembayaran_nelayan).
 */
class PembayaranNelayan extends Model
{
    protected $table = 'pembayaran_nelayan';
    protected $primaryKey = 'id_bayar_nelayan';
    public $timestamps = false;

    public const BELUM_DIBAYAR = 'BELUM_DIBAYAR';
    public const DIBAYAR_SEBAGIAN = 'DIBAYAR_SEBAGIAN';
    public const LUNAS = 'LUNAS';
    public const DITAHAN = 'DITAHAN';

    public const TUNAI = 'TUNAI';
    public const TRANSFER = 'TRANSFER_REKENING';
    public const KARTU = 'KARTU_NELAYAN';

    protected $fillable = [
        'id_kedatangan', 'total_bruto', 'potongan_retribusi', 'potongan_lain', 'keterangan_potongan',
        'total_netto', 'status', 'total_dibayar', 'cara_pencairan', 'nomor_referensi_bank',
        'waktu_bayar', 'id_kasir', 'waktu_dibuat',
    ];

    protected $casts = [
        'total_bruto'        => 'decimal:2',
        'potongan_retribusi' => 'decimal:2',
        'potongan_lain'      => 'decimal:2',
        'total_netto'        => 'decimal:2',
        'total_dibayar'      => 'decimal:2',
        'waktu_bayar'        => 'datetime',
        'waktu_dibuat'       => 'datetime',
    ];

    // ---------------- get / set ----------------

    public function getIdBayarNelayan(): int { return (int) $this->id_bayar_nelayan; }
    public function getIdKedatangan(): int { return (int) $this->id_kedatangan; }
    public function getTotalBruto(): string { return $this->total_bruto; }
    public function getPotonganRetribusi(): string { return $this->potongan_retribusi; }
    public function getPotonganLain(): string { return $this->potongan_lain; }
    public function getTotalNetto(): string { return $this->total_netto; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): void { $this->status = $status; }
    public function getCaraPencairan(): ?string { return $this->cara_pencairan; }
    public function setCaraPencairan(?string $cara): void { $this->cara_pencairan = $cara; }
    public function getWaktuBayar(): ?\DateTimeInterface { return $this->waktu_bayar; }

    /** Nomor bukti pembayaran: BNL-000001 */
    public function getKodeBukti(): string
    {
        return 'BNL-' . str_pad((string) $this->id_bayar_nelayan, 6, '0', STR_PAD_LEFT);
    }

    /** FR-58: sisa kewajiban yang belum dicairkan. */
    public function sisa(): float
    {
        return max(0, (float) $this->total_netto - (float) $this->total_dibayar);
    }

    // ---------------- relasi ----------------

    public function kedatangan()
    {
        return $this->belongsTo(KedatanganKapal::class, 'id_kedatangan', 'id_kedatangan');
    }

    public function kasir()
    {
        return $this->belongsTo(Pengguna::class, 'id_kasir', 'id_pengguna');
    }

    public function daftarPencairan()
    {
        return $this->hasMany(PencairanNelayan::class, 'id_bayar_nelayan', 'id_bayar_nelayan')->orderBy('id_pencairan');
    }

    // ---------------- pencarian ----------------

    public static function cariUntukKedatangan(int $idKedatangan): ?self
    {
        return static::where('id_kedatangan', $idKedatangan)->first();
    }

    /**
     * FR-58 / ALG-06: status pembayaran dihitung dari nominal yang sudah dicairkan
     * agar tetap benar meski satu kedatangan dicairkan beberapa kali.
     */
    public function segarkanStatus(): void
    {
        $dibayar = (float) DB::table('pencairan_nelayan')->where('id_bayar_nelayan', $this->id_bayar_nelayan)->sum('nominal');

        $this->total_dibayar = $dibayar;
        $this->status = match (true) {
            $dibayar <= 0.0                    => self::BELUM_DIBAYAR,
            $dibayar + 0.01 < (float) $this->total_netto => self::DIBAYAR_SEBAGIAN,
            default                            => self::LUNAS,
        };
        $this->save();
    }
}

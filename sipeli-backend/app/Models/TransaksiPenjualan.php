<?php

namespace App\Models;

use App\Enums\StatusTransaksi;
use Illuminate\Database\Eloquent\Model;

/** Kelas entitas TransaksiPenjualan (paket Keuangan) - UC-05 dan UC-06. */
class TransaksiPenjualan extends Model
{
    protected $table = 'transaksi_penjualan';
    protected $primaryKey = 'id_transaksi';
    public $timestamps = false;

    protected $fillable = [
        'id_lot', 'id_sesi', 'id_pembeli', 'id_tarif', 'berat_final', 'harga_akhir_per_kg', 'subtotal',
        'nilai_retribusi', 'biaya_layanan', 'total_tagihan', 'status', 'waktu_transaksi',
    ];

    protected $casts = [
        'berat_final'        => 'decimal:2',
        'harga_akhir_per_kg' => 'decimal:2',
        'subtotal'           => 'decimal:2',
        'nilai_retribusi'    => 'decimal:2',
        'biaya_layanan'      => 'decimal:2',
        'total_tagihan'      => 'decimal:2',
        'waktu_transaksi'    => 'datetime',
    ];

    public function getIdTransaksi(): int { return $this->id_transaksi; }
    public function getIdLot(): int { return $this->id_lot; }
    public function getIdSesi(): int { return $this->id_sesi; }
    public function getIdPembeli(): int { return $this->id_pembeli; }
    public function getBeratFinal(): string { return $this->berat_final; }
    public function getHargaAkhirPerKg(): string { return $this->harga_akhir_per_kg; }
    public function getSubtotal(): string { return $this->subtotal; }
    public function getNilaiRetribusi(): string { return $this->nilai_retribusi; }
    public function getBiayaLayanan(): string { return $this->biaya_layanan; }
    public function getTotalTagihan(): string { return $this->total_tagihan; }
    public function getStatus(): StatusTransaksi { return StatusTransaksi::from($this->status); }
    public function getWaktuTransaksi(): \DateTimeInterface { return $this->waktu_transaksi; }

    /** Nomor transaksi untuk ditampilkan / dipindai: TRX-000001 */
    public function getKodeTransaksi(): string
    {
        return self::kodeDariId($this->id_transaksi);
    }

    public function lot()
    {
        return $this->belongsTo(LotLelang::class, 'id_lot', 'id_lot');
    }

    public function sesi()
    {
        return $this->belongsTo(SesiLelang::class, 'id_sesi', 'id_sesi');
    }

    public function pembeli()
    {
        return $this->belongsTo(Pengguna::class, 'id_pembeli', 'id_pengguna');
    }

    public function tarif()
    {
        return $this->belongsTo(TarifRetribusi::class, 'id_tarif', 'id_tarif');
    }

    public function daftarPembayaran()
    {
        return $this->hasMany(Pembayaran::class, 'id_transaksi', 'id_transaksi')->orderBy('id_pembayaran');
    }

    public function totalDibayar(): float
    {
        return (float) $this->daftarPembayaran()->sum('nominal');
    }

    public function sisaTagihan(): float
    {
        return max(0, round((float) $this->total_tagihan - $this->totalDibayar(), 2));
    }

    /** Perbarui status setelah pembayaran: LUNAS bila sisa 0, selain itu DIBAYAR_SEBAGIAN. */
    public function perbaruiStatusBayar(): void
    {
        $this->status = $this->sisaTagihan() <= 0
            ? StatusTransaksi::LUNAS->value
            : StatusTransaksi::DIBAYAR_SEBAGIAN->value;
        $this->save();
    }

    /** ALG-04 langkah 5-7 + ALG-05: bentuk transaksi dari sesi yang dimenangkan. */
    public static function dariSesi(SesiLelang $sesi, Penawaran $pemenang, TarifRetribusi $tarif, float $biayaLayanan): self
    {
        $berat    = (float) $sesi->lot->getBeratAktual();
        $harga    = (float) $pemenang->getHargaPerKg();
        $subtotal = round($berat * $harga);              // dibulatkan ke rupiah terdekat
        $retribusi = $tarif->hitung($subtotal);          // dipotong dari hasil nelayan (UC-08)

        return static::create([
            'id_lot'             => $sesi->getIdLot(),
            'id_sesi'            => $sesi->getIdSesi(),
            'id_pembeli'         => $pemenang->getIdPembeli(),
            'id_tarif'           => $tarif->getIdTarif(),
            'berat_final'        => (string) $berat,
            'harga_akhir_per_kg' => (string) $harga,
            'subtotal'           => (string) $subtotal,
            'nilai_retribusi'    => (string) $retribusi,
            'biaya_layanan'      => (string) $biayaLayanan,
            'total_tagihan'      => (string) ($subtotal + $biayaLayanan),   // ALG-05 langkah 4
            'status'             => StatusTransaksi::BELUM_DIBAYAR->value,
            'waktu_transaksi'    => now(),
        ]);
    }

    public static function kodeDariId(int $id): string
    {
        return 'TRX-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    /** "TRX-000012", "trx12", atau "12" -> 12 */
    public static function idDariKode(string $kode): ?int
    {
        return preg_match('/^(TRX-?)?0*(\d{1,9})$/i', trim($kode), $m) ? (int) $m[2] : null;
    }
}

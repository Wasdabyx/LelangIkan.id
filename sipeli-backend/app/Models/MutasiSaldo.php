<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MutasiSaldo extends Model
{
    protected $table = 'mutasi_saldo';
    protected $primaryKey = 'id_mutasi';
    public $timestamps = false;
    protected $fillable = [
        'id_saldo', 'jenis', 'arah', 'nominal', 'saldo_akhir', 'metode', 'status',
        'id_kasir', 'id_penyetuju', 'nomor_referensi', 'keterangan', 'waktu', 'waktu_persetujuan',
    ];

    protected function casts(): array
    {
        return ['nominal' => 'decimal:2', 'saldo_akhir' => 'decimal:2', 'waktu' => 'datetime', 'waktu_persetujuan' => 'datetime'];
    }

    public function saldoPembeli()
    {
        return $this->belongsTo(SaldoPembeli::class, 'id_saldo', 'id_saldo');
    }

    public function kasir()
    {
        return $this->belongsTo(Pengguna::class, 'id_kasir', 'id_pengguna');
    }

    public function penyetuju()
    {
        return $this->belongsTo(Pengguna::class, 'id_penyetuju', 'id_pengguna');
    }
}

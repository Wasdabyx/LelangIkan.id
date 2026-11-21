<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaldoPembeli extends Model
{
    protected $table = 'saldo_pembeli';
    protected $primaryKey = 'id_saldo';
    public $timestamps = false;
    protected $fillable = ['id_pembeli', 'saldo', 'limit_kredit', 'status'];

    protected function casts(): array
    {
        return ['saldo' => 'decimal:2', 'limit_kredit' => 'decimal:2'];
    }

    public function pembeli()
    {
        return $this->belongsTo(Pengguna::class, 'id_pembeli', 'id_pengguna');
    }

    public function mutasi()
    {
        return $this->hasMany(MutasiSaldo::class, 'id_saldo', 'id_saldo')->orderByDesc('waktu');
    }

    /** QRY-03: daya beli = saldo + limit kredit (0 bila pembeli belum punya baris saldo). */
    public static function dayaBeli(int $idPembeli): float
    {
        $saldo = static::where('id_pembeli', $idPembeli)->first();

        return $saldo ? (float) $saldo->saldo + (float) $saldo->limit_kredit : 0.0;
    }
}

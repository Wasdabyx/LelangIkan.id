<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Kelas entitas Pencairan - rincian pencairan dana nelayan (UC-08, FR-67). */
class PencairanNelayan extends Model
{
    protected $table = 'pencairan_nelayan';
    protected $primaryKey = 'id_pencairan';
    public $timestamps = false;

    protected $fillable = ['id_bayar_nelayan', 'nominal', 'cara_pencairan', 'nomor_referensi', 'waktu', 'id_kasir', 'keterangan'];

    protected $casts = ['nominal' => 'decimal:2', 'waktu' => 'datetime'];

    public function bayarNelayan()
    {
        return $this->belongsTo(PembayaranNelayan::class, 'id_bayar_nelayan', 'id_bayar_nelayan');
    }

    public function kasir()
    {
        return $this->belongsTo(Pengguna::class, 'id_kasir', 'id_pengguna');
    }
}

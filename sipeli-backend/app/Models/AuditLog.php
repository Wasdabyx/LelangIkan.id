<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kelas entitas AuditLog (DPPL 3.3.3). Tanpa $timestamps: kolom waktu diisi AuditService.
 * Model ini sengaja tidak menyediakan operasi ubah/hapus pada antarmuka aplikasi (FR-54).
 */
class AuditLog extends Model
{
    protected $table = 'audit_log';
    protected $primaryKey = 'id_audit';
    public $timestamps = false;

    protected $fillable = [
        'id_pengguna', 'aksi', 'entitas', 'id_entitas', 'data_sebelum', 'data_sesudah', 'alamat_ip', 'waktu',
    ];

    protected $casts = [
        'waktu'         => 'datetime',
        'data_sebelum'  => 'array',
        'data_sesudah'  => 'array',
    ];

    public function pengguna()
    {
        return $this->belongsTo(Pengguna::class, 'id_pengguna', 'id_pengguna');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FR-63 + BT-03: lelang jarak jauh harus opsional dan dapat dimatikan administrator
 * tanpa mengganggu lelang di tempat.
 *
 * `lelang_jarak_jauh_aktif` = '1' (bawaan). Bila diubah menjadi '0' lewat dasbor
 * administrator (A7), tawaran yang datang dari aplikasi mobile ditolak dengan pesan
 * yang jelas, sedangkan penawaran DI_TEMPAT dari antarmuka web tetap berjalan.
 */
return new class extends Migration
{
    private array $kunci = ['lelang_jarak_jauh_aktif'];

    public function up(): void
    {
        DB::table('konfigurasi')->insertOrIgnore([
            'kunci'       => 'lelang_jarak_jauh_aktif',
            'nilai'       => '1',
            'deskripsi'   => 'Saklar lelang jarak jauh; 0 = hanya penawaran di tempat (FR-63, BT-03)',
            'waktu_ubah'  => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('konfigurasi')->whereIn('kunci', $this->kunci)->delete();
    }
};

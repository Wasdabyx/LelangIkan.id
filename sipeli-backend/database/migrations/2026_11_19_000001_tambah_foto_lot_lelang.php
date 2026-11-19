<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto lot hasil potret Petugas Timbang (opsional).
 * Berisi nama berkas di storage/app/private/foto-lot. Bila kosong, aplikasi memakai foto contoh jenis ikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lot_lelang', function (Blueprint $table) {
            $table->string('foto', 60)->nullable()->after('waktu_cetak_terakhir');
        });
    }

    public function down(): void
    {
        Schema::table('lot_lelang', function (Blueprint $table) {
            $table->dropColumn('foto');
        });
    }
};

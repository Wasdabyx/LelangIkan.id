<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahan kolom lot_lelang untuk UC-02:
 * - selisih_persen & perlu_verifikasi : ALG-08 / FC-02 (selisih berat > ambang)
 * - jumlah_cetak & waktu_cetak_terakhir: alur A3 (mencatat percobaan cetak label)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lot_lelang', function (Blueprint $table) {
            $table->decimal('selisih_persen', 6, 2)->nullable()->after('berat_aktual');
            $table->boolean('perlu_verifikasi')->default(false)->after('selisih_persen');
            $table->unsignedSmallInteger('jumlah_cetak')->default(0)->after('alasan');
            $table->dateTime('waktu_cetak_terakhir')->nullable()->after('jumlah_cetak');
        });
    }

    public function down(): void
    {
        Schema::table('lot_lelang', function (Blueprint $table) {
            $table->dropColumn(['selisih_persen', 'perlu_verifikasi', 'jumlah_cetak', 'waktu_cetak_terakhir']);
        });
    }
};

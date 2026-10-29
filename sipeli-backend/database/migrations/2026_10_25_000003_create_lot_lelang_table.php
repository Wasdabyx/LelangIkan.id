<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelompok "Lelang" (DPPL Tabel 2.4) - tahap ini baru lot_lelang
 * (dibutuhkan UC-02). sesi_lelang & penawaran dibuat pada tahap berikutnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_lelang', function (Blueprint $table) {
            $table->id('id_lot');
            $table->char('kode_lot', 16)->unique();           // [kode TPI]-[YYMMDD]-[urut] (FR-08)
            $table->unsignedBigInteger('id_tangkapan');
            $table->decimal('berat_aktual', 8, 2);
            $table->decimal('harga_pembukaan', 12, 2)->nullable(); // null => DRAFT_HARGA
            $table->enum('status', [
                'DRAFT_HARGA', 'MENUNGGU_LELANG', 'DILELANG', 'DIPERPANJANG',
                'TERJUAL', 'TIDAK_TERJUAL', 'DIBATALKAN', 'SELESAI',
            ])->default('DRAFT_HARGA');
            $table->unsignedBigInteger('id_petugas_timbang');
            $table->dateTime('waktu_timbang')->useCurrent();

            // Tambahan di luar ERD untuk memenuhi FR-07, FR-09, FR-11
            $table->boolean('timbang_manual')->default(false);    // BT-05: input manual
            $table->string('perangkat_timbang', 50)->nullable();  // FR-11: perangkat yang dipakai
            $table->string('alasan', 200)->nullable();            // alasan manual / konfirmasi selisih >15%

            $table->foreign('id_tangkapan')->references('id_tangkapan')->on('tangkapan');
            $table->foreign('id_petugas_timbang')->references('id_pengguna')->on('pengguna');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_lelang');
    }
};

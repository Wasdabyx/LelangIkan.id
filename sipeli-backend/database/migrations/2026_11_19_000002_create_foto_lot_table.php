<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel foto_lot (ERD revisi, kelas FotoLot): foto ikan pada lot yang dipotret Petugas Timbang.
 * Relasi lot_lelang 1 --- 0..* foto_lot (komposisi: foto ikut terhapus bila lot dihapus).
 * Berkas disimpan di storage/app/private/foto-lot/, path_berkas berisi "foto-lot/<nama acak>.jpg".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foto_lot', function (Blueprint $table) {
            $table->id('id_foto');
            $table->unsignedBigInteger('id_lot');
            $table->string('path_berkas', 255);
            $table->string('keterangan', 200)->nullable();
            $table->unsignedBigInteger('diunggah_oleh');
            $table->dateTime('waktu_unggah')->useCurrent();

            $table->foreign('id_lot')->references('id_lot')->on('lot_lelang')->cascadeOnDelete();
            $table->foreign('diunggah_oleh')->references('id_pengguna')->on('pengguna');
        });

        // Versi uji sebelumnya sempat memakai kolom lot_lelang.foto; kolom itu tidak dipakai lagi.
        if (Schema::hasColumn('lot_lelang', 'foto')) {
            Schema::table('lot_lelang', function (Blueprint $table) {
                $table->dropColumn('foto');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('foto_lot');
    }
};

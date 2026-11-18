<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel daftar_pantauan (kelas DaftarPantauan pada class diagram DPPL).
 * Tugas 2: pembeli yang mendaftar ikut lelang sebuah lot (dikonfirmasi OTP)
 * mendapat kode_pemesanan PSN-YYMMDD-####. Status lot tidak berubah; lelang tetap berjalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daftar_pantauan', function (Blueprint $table) {
            $table->id('id_pantauan');
            $table->char('kode_pemesanan', 15)->unique();          // PSN-261114-0001
            $table->unsignedBigInteger('id_pembeli');
            $table->unsignedBigInteger('id_lot');
            $table->decimal('harga_maksimum', 12, 2)->nullable();
            $table->boolean('notifikasi_aktif')->default(true);
            $table->dateTime('waktu_ditambah')->useCurrent();

            $table->foreign('id_pembeli')->references('id_pengguna')->on('pengguna');
            $table->foreign('id_lot')->references('id_lot')->on('lot_lelang');
            $table->unique(['id_pembeli', 'id_lot']);              // satu pembeli satu kali per lot
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daftar_pantauan');
    }
};

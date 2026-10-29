<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelompok "Kelembagaan dan pengguna" (DPPL Tabel 2.4):
 * peran, tpi, pengguna, konfigurasi
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peran', function (Blueprint $table) {
            $table->tinyIncrements('id_peran');
            $table->string('nama_peran', 40)->unique();
            $table->string('deskripsi', 200)->nullable();
        });

        Schema::create('tpi', function (Blueprint $table) {
            $table->id('id_tpi');
            $table->string('nama_tpi', 100);
            $table->string('alamat', 200)->nullable();
            $table->string('kabupaten', 80)->nullable();
            $table->decimal('kapasitas_ton', 8, 2)->nullable();
            $table->string('jam_operasi', 40)->nullable();
        });

        Schema::create('pengguna', function (Blueprint $table) {
            $table->id('id_pengguna');
            $table->string('nama_lengkap', 100);
            $table->string('nama_pengguna', 50)->unique();
            $table->string('surel', 120)->unique();
            $table->string('kata_sandi', 255);
            $table->unsignedTinyInteger('id_peran');
            $table->unsignedBigInteger('id_tpi')->nullable(); // null = administrator pusat
            $table->boolean('status_aktif')->default(true);
            $table->dateTime('waktu_dibuat')->useCurrent();

            $table->foreign('id_peran')->references('id_peran')->on('peran');
            $table->foreign('id_tpi')->references('id_tpi')->on('tpi');
        });

        Schema::create('konfigurasi', function (Blueprint $table) {
            $table->increments('id_konfigurasi');
            $table->string('kunci', 60)->unique();
            $table->string('nilai', 255);
            $table->string('deskripsi', 200)->nullable();
            $table->unsignedBigInteger('diubah_oleh')->nullable();
            $table->dateTime('waktu_ubah')->useCurrent();

            $table->foreign('diubah_oleh')->references('id_pengguna')->on('pengguna');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('konfigurasi');
        Schema::dropIfExists('pengguna');
        Schema::dropIfExists('tpi');
        Schema::dropIfExists('peran');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-11 FR-66 (kartu nelayan ber-QR) dan UC-13 FR-59 (tautan akses portal nelayan, maksimum 72 jam).
 * Kartu dipakai saat pendataan kedatangan; tautan dipakai nelayan untuk membuka portal baca-saja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kartu_nelayan', function (Blueprint $table) {
            $table->id('id_kartu');
            $table->foreignId('id_pemilik')->constrained('pemilik_kapal', 'id_pemilik')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('nomor_kartu', 20)->unique();
            $table->char('token_qr', 64)->unique();
            $table->enum('status', ['AKTIF', 'DIBLOKIR', 'KEDALUWARSA'])->default('AKTIF');
            $table->date('berlaku_sampai');
            $table->dateTime('waktu_terbit');
            $table->string('alasan_blokir', 200)->nullable();
        });

        Schema::create('tautan_akses_nelayan', function (Blueprint $table) {
            $table->id('id_tautan');
            $table->foreignId('id_pemilik')->constrained('pemilik_kapal', 'id_pemilik')->cascadeOnUpdate()->restrictOnDelete();
            $table->char('token', 64)->unique();
            $table->char('kode_verifikasi', 60);          // hash 4 digit verifikasi tambahan (FR-59)
            $table->dateTime('kedaluwarsa');
            $table->unsignedTinyInteger('jumlah_akses')->default(0);
            $table->dateTime('waktu_dibuat');
            $table->foreignId('id_penerbit')->nullable()->constrained('pengguna', 'id_pengguna')->cascadeOnUpdate()->nullOnDelete();
        });

        Schema::create('notifikasi', function (Blueprint $table) {
            $table->id('id_notifikasi');
            $table->foreignId('id_pengguna')->constrained('pengguna', 'id_pengguna')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('tipe', 40);
            $table->string('judul', 120);
            $table->text('isi');
            $table->boolean('status_baca')->default(false);
            $table->dateTime('waktu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi');
        Schema::dropIfExists('tautan_akses_nelayan');
        Schema::dropIfExists('kartu_nelayan');
    }
};

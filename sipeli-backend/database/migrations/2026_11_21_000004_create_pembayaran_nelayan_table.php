<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-08 (FR-37 s.d. FR-40, FR-67): pembayaran hasil lelang kepada nelayan/pemilik kapal.
 * Satu baris per kedatangan kapal; pembayaran bertahap dicatat pada tabel rinciannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembayaran_nelayan', function (Blueprint $table) {
            $table->id('id_bayar_nelayan');
            $table->foreignId('id_kedatangan')->unique()->constrained('kedatangan_kapal', 'id_kedatangan')->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('total_bruto', 12, 2);
            $table->decimal('potongan_retribusi', 12, 2);
            $table->decimal('potongan_lain', 12, 2)->default(0);
            $table->string('keterangan_potongan', 200)->nullable();
            $table->decimal('total_netto', 12, 2);
            $table->enum('status', ['BELUM_DIBAYAR', 'DIBAYAR_SEBAGIAN', 'LUNAS', 'DITAHAN'])->default('BELUM_DIBAYAR');
            $table->decimal('total_dibayar', 12, 2)->default(0);
            $table->enum('cara_pencairan', ['TUNAI', 'TRANSFER_REKENING', 'KARTU_NELAYAN'])->nullable();
            $table->string('nomor_referensi_bank', 40)->nullable();
            $table->dateTime('waktu_bayar')->nullable();
            $table->foreignId('id_kasir')->nullable()->constrained('pengguna', 'id_pengguna')->cascadeOnUpdate()->nullOnDelete();
            $table->dateTime('waktu_dibuat');
        });

        Schema::create('pencairan_nelayan', function (Blueprint $table) {
            $table->id('id_pencairan');
            $table->foreignId('id_bayar_nelayan')->constrained('pembayaran_nelayan', 'id_bayar_nelayan')->cascadeOnUpdate()->cascadeOnDelete();
            $table->decimal('nominal', 12, 2);
            $table->enum('cara_pencairan', ['TUNAI', 'TRANSFER_REKENING', 'KARTU_NELAYAN']);
            $table->string('nomor_referensi', 40)->nullable();
            $table->dateTime('waktu');
            $table->foreignId('id_kasir')->nullable()->constrained('pengguna', 'id_pengguna')->cascadeOnUpdate()->nullOnDelete();
            $table->string('keterangan', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pencairan_nelayan');
        Schema::dropIfExists('pembayaran_nelayan');
    }
};

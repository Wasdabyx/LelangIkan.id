<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saldo_pembeli', function (Blueprint $table) {
            $table->id('id_saldo');
            $table->unsignedBigInteger('id_pembeli')->unique();
            $table->decimal('saldo', 14, 2)->default(0);
            $table->decimal('limit_kredit', 14, 2)->default(0);
            $table->enum('status', ['AKTIF', 'DIBLOKIR'])->default('AKTIF');
            $table->foreign('id_pembeli')->references('id_pengguna')->on('pengguna');
        });

        Schema::create('mutasi_saldo', function (Blueprint $table) {
            $table->id('id_mutasi');
            $table->unsignedBigInteger('id_saldo');
            $table->enum('jenis', ['SETOR', 'PENARIKAN', 'PENYESUAIAN', 'PEMBAYARAN', 'PENGEMBALIAN']);
            $table->enum('arah', ['KREDIT', 'DEBIT']);
            $table->decimal('nominal', 14, 2);
            $table->decimal('saldo_akhir', 14, 2);
            $table->enum('metode', ['TUNAI', 'TRANSFER', 'QRIS'])->nullable();
            $table->enum('status', ['BERHASIL', 'MENUNGGU_PERSETUJUAN', 'DITOLAK'])->default('BERHASIL');
            $table->unsignedBigInteger('id_kasir');
            $table->unsignedBigInteger('id_penyetuju')->nullable();
            $table->string('nomor_referensi', 40)->nullable()->unique();
            $table->string('keterangan', 200)->nullable();
            $table->dateTime('waktu')->useCurrent();
            $table->dateTime('waktu_persetujuan')->nullable();

            $table->foreign('id_saldo')->references('id_saldo')->on('saldo_pembeli');
            $table->foreign('id_kasir')->references('id_pengguna')->on('pengguna');
            $table->foreign('id_penyetuju')->references('id_pengguna')->on('pengguna');
            $table->index(['id_saldo', 'waktu']);
            $table->index(['status', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutasi_saldo');
        Schema::dropIfExists('saldo_pembeli');
    }
};

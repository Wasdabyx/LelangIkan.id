<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi 21 November 2026:
 * 1. tpi.kode_tpi supaya nomor lot memakai kode TPI milik kedatangan (FR-08).
 * 2. transaksi_penjualan: jejak pembatalan manual oleh supervisor (FR-32).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tpi', function (Blueprint $table) {
            $table->string('kode_tpi', 10)->nullable()->after('nama_tpi');
        });
        DB::table('tpi')->where('id_tpi', 1)->update(['kode_tpi' => 'PLR']);

        Schema::table('transaksi_penjualan', function (Blueprint $table) {
            $table->unsignedBigInteger('id_pembatal')->nullable()->after('batas_bayar');
            $table->string('alasan_batal', 255)->nullable()->after('id_pembatal');
            $table->dateTime('waktu_batal')->nullable()->after('alasan_batal');
            $table->foreign('id_pembatal')->references('id_pengguna')->on('pengguna')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_penjualan', function (Blueprint $table) {
            $table->dropForeign(['id_pembatal']);
            $table->dropColumn(['id_pembatal', 'alasan_batal', 'waktu_batal']);
        });
        Schema::table('tpi', function (Blueprint $table) {
            $table->dropColumn('kode_tpi');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_penjualan', function (Blueprint $table) {
            $table->dateTime('batas_bayar')->nullable();
            $table->date('tanggal_pengambilan')->nullable();
        });

        Schema::table('pembayaran', function (Blueprint $table) {
            $table->unsignedBigInteger('id_kasir')->nullable()->change();
        });

        Schema::table('mutasi_saldo', function (Blueprint $table) {
            $table->unsignedBigInteger('id_kasir')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('mutasi_saldo', function (Blueprint $table) {
            $table->unsignedBigInteger('id_kasir')->nullable(false)->change();
        });

        Schema::table('pembayaran', function (Blueprint $table) {
            $table->unsignedBigInteger('id_kasir')->nullable(false)->change();
        });

        Schema::table('transaksi_penjualan', function (Blueprint $table) {
            $table->dropColumn(['batas_bayar', 'tanggal_pengambilan']);
        });
    }
};

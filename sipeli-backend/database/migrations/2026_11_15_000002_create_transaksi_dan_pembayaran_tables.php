<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UC-05 dan UC-06: tabel tarif_retribusi, transaksi_penjualan, dan pembayaran (sesuai ERD).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarif_retribusi', function (Blueprint $table) {
            $table->id('id_tarif');
            $table->string('nama', 80);
            $table->decimal('persentase', 5, 2);
            $table->decimal('nominal_minimum', 10, 2)->default(0);
            $table->date('berlaku_mulai');
            $table->date('berlaku_sampai')->nullable();
            $table->boolean('status')->default(true);
        });

        Schema::create('transaksi_penjualan', function (Blueprint $table) {
            $table->id('id_transaksi');
            $table->unsignedBigInteger('id_lot');
            $table->unsignedBigInteger('id_sesi')->unique();
            $table->unsignedBigInteger('id_pembeli');
            $table->unsignedBigInteger('id_tarif');
            $table->decimal('berat_final', 8, 2);
            $table->decimal('harga_akhir_per_kg', 12, 2);
            $table->decimal('subtotal', 14, 2);
            $table->decimal('nilai_retribusi', 12, 2);
            $table->decimal('biaya_layanan', 10, 2);
            $table->decimal('total_tagihan', 14, 2);
            $table->enum('status', ['BELUM_DIBAYAR', 'DIBAYAR_SEBAGIAN', 'LUNAS', 'DIBATALKAN'])->default('BELUM_DIBAYAR');
            $table->dateTime('waktu_transaksi');

            $table->foreign('id_lot')->references('id_lot')->on('lot_lelang');
            $table->foreign('id_sesi')->references('id_sesi')->on('sesi_lelang');
            $table->foreign('id_pembeli')->references('id_pengguna')->on('pengguna');
            $table->foreign('id_tarif')->references('id_tarif')->on('tarif_retribusi');
            $table->index(['status', 'waktu_transaksi']);
        });

        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id('id_pembayaran');
            $table->unsignedBigInteger('id_transaksi');
            $table->enum('metode', ['TUNAI', 'TRANSFER', 'SALDO', 'QRIS']);
            $table->decimal('nominal', 14, 2);
            $table->dateTime('waktu_bayar');
            $table->unsignedBigInteger('id_kasir');
            $table->string('nomor_referensi', 40)->nullable()->unique();

            $table->foreign('id_transaksi')->references('id_transaksi')->on('transaksi_penjualan');
            $table->foreign('id_kasir')->references('id_pengguna')->on('pengguna');
        });

        // Tarif retribusi bawaan (ALG-05) dan biaya layanan per transaksi
        DB::table('tarif_retribusi')->insert([
            'nama' => 'Retribusi Jasa Pelelangan Ikan', 'persentase' => 2.50, 'nominal_minimum' => 1000,
            'berlaku_mulai' => '2026-01-01', 'berlaku_sampai' => null, 'status' => true,
        ]);
        DB::table('konfigurasi')->insertOrIgnore([
            'kunci' => 'biaya_layanan', 'nilai' => '2000',
            'deskripsi' => 'Biaya layanan per transaksi yang ditagihkan ke pembeli (ALG-05)', 'waktu_ubah' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
        Schema::dropIfExists('transaksi_penjualan');
        Schema::dropIfExists('tarif_retribusi');
        DB::table('konfigurasi')->where('kunci', 'biaya_layanan')->delete();
    }
};

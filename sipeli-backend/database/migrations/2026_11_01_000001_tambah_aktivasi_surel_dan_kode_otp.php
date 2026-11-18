<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tugas 2 - Aktivasi akun lewat surel dan kode OTP
 * - pengguna.surel_terverifikasi_pada : waktu pembeli memasukkan kode aktivasi yang benar
 * - kode_otp : kode 6 digit (disimpan dalam bentuk hash) untuk AKTIVASI akun dan PEMESANAN lot
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengguna', function (Blueprint $table) {
            $table->dateTime('surel_terverifikasi_pada')->nullable()->after('no_hp');
        });

        // Akun yang sudah aktif sebelum fitur ini ada dianggap sudah terverifikasi
        DB::table('pengguna')->where('status_aktif', true)->update([
            'surel_terverifikasi_pada' => DB::raw('waktu_dibuat'),
        ]);

        Schema::create('kode_otp', function (Blueprint $table) {
            $table->id('id_otp');
            $table->unsignedBigInteger('id_pengguna');
            $table->enum('tujuan', ['AKTIVASI', 'PEMESANAN']);
            $table->unsignedBigInteger('id_lot')->nullable();      // diisi bila tujuan PEMESANAN
            $table->string('kode_hash', 255);                       // kode asli tidak pernah disimpan
            $table->dateTime('kedaluwarsa_pada');
            $table->unsignedTinyInteger('jumlah_percobaan')->default(0);
            $table->dateTime('terpakai_pada')->nullable();
            $table->dateTime('waktu_dibuat')->useCurrent();

            $table->foreign('id_pengguna')->references('id_pengguna')->on('pengguna');
            $table->foreign('id_lot')->references('id_lot')->on('lot_lelang');
            $table->index(['id_pengguna', 'tujuan', 'id_lot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kode_otp');
        Schema::table('pengguna', function (Blueprint $table) {
            $table->dropColumn('surel_terverifikasi_pada');
        });
    }
};

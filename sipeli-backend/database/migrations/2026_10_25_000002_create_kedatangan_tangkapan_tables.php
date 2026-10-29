<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelompok "Kedatangan dan tangkapan" (DPPL Tabel 2.4):
 * pemilik_kapal, kapal, jenis_ikan, grade_mutu, kedatangan_kapal, tangkapan
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemilik_kapal', function (Blueprint $table) {
            $table->id('id_pemilik');
            $table->string('nama', 100);
            $table->char('nik', 16)->unique();
            $table->string('no_telepon', 20)->nullable();
            $table->string('alamat', 200)->nullable();
            $table->string('no_rekening', 30)->nullable();
            $table->string('nama_bank', 50)->nullable();
        });

        Schema::create('kapal', function (Blueprint $table) {
            $table->id('id_kapal');
            $table->string('nama_kapal', 80);
            $table->string('nomor_registrasi', 40)->unique();
            $table->decimal('gt', 8, 2)->nullable();
            $table->unsignedBigInteger('id_pemilik');
            $table->enum('status', ['AKTIF', 'NONAKTIF'])->default('AKTIF');

            $table->foreign('id_pemilik')->references('id_pemilik')->on('pemilik_kapal');
        });

        Schema::create('jenis_ikan', function (Blueprint $table) {
            $table->id('id_jenis');
            $table->string('nama_jenis', 80)->unique();
            $table->string('nama_ilmiah', 120)->nullable();
            $table->enum('kategori', ['PELAGIS_BESAR', 'PELAGIS_KECIL', 'DEMERSAL', 'KRUSTASEA', 'MOLUSKA', 'LAINNYA'])
                  ->default('LAINNYA');
            $table->enum('satuan', ['KG', 'EKOR'])->default('KG');
            $table->boolean('status')->default(true);
        });

        Schema::create('grade_mutu', function (Blueprint $table) {
            $table->id('id_grade');
            $table->char('kode_grade', 2)->unique();
            $table->string('deskripsi', 150)->nullable();
            $table->decimal('faktor_harga', 5, 4)->default(1);
        });

        Schema::create('kedatangan_kapal', function (Blueprint $table) {
            $table->id('id_kedatangan');
            $table->unsignedBigInteger('id_kapal');
            $table->unsignedBigInteger('id_tpi');
            $table->date('tanggal');
            $table->time('jam');
            $table->string('nama_kapten', 100);
            $table->unsignedSmallInteger('jumlah_abk')->default(0);
            $table->string('nomor_surat', 50)->unique();
            $table->decimal('total_berat_estimasi', 10, 2)->default(0);
            $table->enum('status', ['DRAF', 'TERDAFTAR', 'DITIMBANG'])->default('TERDAFTAR');
            $table->unsignedBigInteger('id_petugas');
            $table->dateTime('waktu_dibuat')->useCurrent();

            $table->foreign('id_kapal')->references('id_kapal')->on('kapal');
            $table->foreign('id_tpi')->references('id_tpi')->on('tpi');
            $table->foreign('id_petugas')->references('id_pengguna')->on('pengguna');
        });

        Schema::create('tangkapan', function (Blueprint $table) {
            $table->id('id_tangkapan');
            $table->unsignedBigInteger('id_kedatangan');
            $table->unsignedBigInteger('id_jenis');
            $table->unsignedBigInteger('id_grade');
            $table->decimal('berat_estimasi', 8, 2);
            $table->unsignedSmallInteger('jumlah_keranjang')->default(1);
            $table->enum('status', ['BELUM_DITIMBANG', 'DITIMBANG'])->default('BELUM_DITIMBANG');

            $table->foreign('id_kedatangan')->references('id_kedatangan')->on('kedatangan_kapal')->cascadeOnDelete();
            $table->foreign('id_jenis')->references('id_jenis')->on('jenis_ikan');
            $table->foreign('id_grade')->references('id_grade')->on('grade_mutu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tangkapan');
        Schema::dropIfExists('kedatangan_kapal');
        Schema::dropIfExists('grade_mutu');
        Schema::dropIfExists('jenis_ikan');
        Schema::dropIfExists('kapal');
        Schema::dropIfExists('pemilik_kapal');
    }
};

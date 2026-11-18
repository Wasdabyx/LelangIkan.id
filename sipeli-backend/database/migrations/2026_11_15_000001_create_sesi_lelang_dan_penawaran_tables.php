<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UC-03 s.d. UC-05: tabel sesi_lelang dan penawaran (sesuai ERD).
 * sesi_lelang memakai satu tabel untuk dua mode (LelangNaik / LelangTurun); kolom mode TURUN boleh kosong.
 * Tambahan di luar ERD: waktu_selesai_aktual dan alasan_tutup (ALG-04 dan alur A3 penutupan manual).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesi_lelang', function (Blueprint $table) {
            $table->id('id_sesi');
            $table->unsignedBigInteger('id_lot');
            $table->unsignedBigInteger('id_operator');
            $table->enum('mode', ['NAIK', 'TURUN'])->default('NAIK');
            $table->dateTime('waktu_mulai');
            $table->dateTime('waktu_selesai_rencana');
            $table->smallInteger('durasi_detik')->unsigned();
            $table->decimal('harga_berjalan', 12, 2)->nullable();            // harga tertinggi per kg saat ini
            $table->unsignedBigInteger('id_penawaran_pemenang')->nullable();
            $table->enum('status', ['AKTIF', 'DITUTUP', 'DIBATALKAN'])->default('AKTIF');
            // LelangNaik
            $table->decimal('kenaikan_minimum', 12, 2)->nullable();
            $table->tinyInteger('jumlah_perpanjangan')->unsigned()->default(0);
            $table->tinyInteger('maks_perpanjangan')->unsigned()->default(5);
            $table->smallInteger('ambang_perpanjangan_detik')->unsigned()->default(15);
            // LelangTurun (belum dipakai pada versi ini)
            $table->decimal('harga_awal', 12, 2)->nullable();
            $table->decimal('harga_batas_bawah', 12, 2)->nullable();
            $table->decimal('penurunan_per_langkah', 12, 2)->nullable();
            $table->smallInteger('interval_detik')->unsigned()->nullable();
            // Tambahan (ALG-04, alur A3)
            $table->dateTime('waktu_selesai_aktual')->nullable();
            $table->string('alasan_tutup', 255)->nullable();

            $table->foreign('id_lot')->references('id_lot')->on('lot_lelang');
            $table->foreign('id_operator')->references('id_pengguna')->on('pengguna');
            $table->index(['status', 'waktu_selesai_rencana']);
        });

        Schema::create('penawaran', function (Blueprint $table) {
            $table->id('id_penawaran');
            $table->unsignedBigInteger('id_sesi');
            $table->unsignedBigInteger('id_pembeli');
            $table->decimal('nominal', 14, 2);                 // nilai total = harga_per_kg x berat lot
            $table->decimal('harga_per_kg', 12, 2);            // harga yang ditawarkan pembeli (Rp/kg)
            $table->dateTime('waktu_penawaran', 3);
            $table->enum('status', ['TERTINGGI', 'TERTINGGAL', 'DIBATALKAN']);
            $table->enum('kanal', ['DI_TEMPAT', 'JARAK_JAUH'])->default('JARAK_JAUH');
            $table->char('kunci_idempotensi', 36)->unique();   // FR-20
            $table->string('alamat_terminal', 45)->nullable();

            $table->foreign('id_sesi')->references('id_sesi')->on('sesi_lelang');
            $table->foreign('id_pembeli')->references('id_pengguna')->on('pengguna');
            $table->index(['id_sesi', 'status']);
        });

        Schema::table('sesi_lelang', function (Blueprint $table) {
            $table->foreign('id_penawaran_pemenang')->references('id_penawaran')->on('penawaran');
        });

        // Konfigurasi lelang (ALG-03). durasi_lelang_bawaan sudah ada sejak awal.
        $baris = [
            ['maks_perpanjangan',           '5',  'Batas perpanjangan otomatis per sesi (ALG-03)'],
            ['ambang_perpanjangan_detik',   '15', 'Tawaran pada N detik terakhir memicu perpanjangan (ALG-03)'],
            ['tambahan_perpanjangan_detik', '30', 'Lama satu kali perpanjangan dalam detik (ALG-03)'],
        ];
        foreach ($baris as [$kunci, $nilai, $deskripsi]) {
            DB::table('konfigurasi')->insertOrIgnore([
                'kunci' => $kunci, 'nilai' => $nilai, 'deskripsi' => $deskripsi, 'waktu_ubah' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('sesi_lelang', fn (Blueprint $t) => $t->dropForeign(['id_penawaran_pemenang']));
        Schema::dropIfExists('penawaran');
        Schema::dropIfExists('sesi_lelang');
        DB::table('konfigurasi')->whereIn('kunci', ['maks_perpanjangan', 'ambang_perpanjangan_detik', 'tambahan_perpanjangan_detik'])->delete();
    }
};

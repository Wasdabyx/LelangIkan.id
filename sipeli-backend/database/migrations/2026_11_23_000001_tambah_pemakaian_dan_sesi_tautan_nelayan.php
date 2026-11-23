<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FR-59: tautan portal nelayan benar-benar "sekali pakai".
 *
 * Sebelumnya token dapat dibuka berkali-kali sampai kedaluwarsa. Sekarang:
 * - tautan ditandai terpakai pada pembukaan pertama (`dipakai_pada`);
 * - pembukaan itu menerbitkan sesi portal berumur 30 menit (`sesi_token`), supaya
 *   halaman rekap dan tombol unduh bukti tetap bisa dipakai tanpa membuka ulang tautan.
 *
 * Tidak menambah tabel baru: kolom sesi menempel pada tautan, agar struktur basis data
 * tetap 27 tabel seperti ERD fisik DPPL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tautan_akses_nelayan', function (Blueprint $table) {
            $table->dateTime('dipakai_pada')->nullable()->after('kedaluwarsa');
            $table->char('sesi_token', 64)->nullable()->unique()->after('dipakai_pada');
            $table->dateTime('sesi_kedaluwarsa')->nullable()->after('sesi_token');
        });
    }

    public function down(): void
    {
        Schema::table('tautan_akses_nelayan', function (Blueprint $table) {
            $table->dropUnique(['sesi_token']);
            $table->dropColumn(['dipakai_pada', 'sesi_token', 'sesi_kedaluwarsa']);
        });
    }
};

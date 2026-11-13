<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tugas Week 9 - Registrasi Pembeli (UC-11, AS-03 SKPL)
 * - nik  : Nomor Induk Kependudukan 16 digit, identitas yang diverifikasi petugas
 * - no_hp: nomor ponsel pembeli (disimpan dalam bentuk 08xxxxxxxxxx)
 * Keduanya boleh kosong karena akun petugas yang sudah ada tidak memilikinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengguna', function (Blueprint $table) {
            $table->char('nik', 16)->nullable()->unique()->after('surel');
            $table->string('no_hp', 15)->nullable()->unique()->after('nik');
        });
    }

    public function down(): void
    {
        Schema::table('pengguna', function (Blueprint $table) {
            $table->dropUnique(['nik']);
            $table->dropUnique(['no_hp']);
            $table->dropColumn(['nik', 'no_hp']);
        });
    }
};

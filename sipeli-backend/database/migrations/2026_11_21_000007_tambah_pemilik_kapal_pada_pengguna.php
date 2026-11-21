<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-13 / Tabel IV.7: akun peran Nelayan (A8) perlu ditautkan ke baris pemilik kapal agar dapat
 * menerima kanal privat nelayan.{id_pemilik} (peristiwa SETTLEMENT_DIBAYAR).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pengguna', 'id_pemilik')) {
            Schema::table('pengguna', function (Blueprint $table) {
                $table->foreignId('id_pemilik')->nullable()->after('id_tpi')
                    ->constrained('pemilik_kapal', 'id_pemilik')->cascadeOnUpdate()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pengguna', 'id_pemilik')) {
            Schema::table('pengguna', function (Blueprint $table) {
                $table->dropConstrainedForeignId('id_pemilik');
            });
        }
    }
};

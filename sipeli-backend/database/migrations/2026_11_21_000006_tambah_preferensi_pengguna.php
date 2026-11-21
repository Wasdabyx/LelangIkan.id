<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-10 langkah 1 (DPPL): parameter laporan terakhir tersimpan untuk penggunaan berikutnya.
 * API bersifat stateless sehingga preferensi disimpan pada baris pengguna, bukan sesi PHP.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pengguna', 'preferensi')) {
            Schema::table('pengguna', function (Blueprint $table) {
                $table->json('preferensi')->nullable()->after('status_aktif');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pengguna', 'preferensi')) {
            Schema::table('pengguna', function (Blueprint $table) {
                $table->dropColumn('preferensi');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-12 Audit dan Penelusuran Aktivitas (FR-52..FR-55, NFR-14).
 * Tabel hanya diisi lewat AuditService; tidak ada endpoint ubah/hapus (FR-54).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->bigIncrements('id_audit');
            $table->unsignedBigInteger('id_pengguna')->nullable();
            $table->string('aksi', 40);
            $table->string('entitas', 40);
            $table->string('id_entitas', 40)->nullable();
            $table->json('data_sebelum')->nullable();
            $table->json('data_sesudah')->nullable();
            $table->string('alamat_ip', 45)->nullable();
            $table->dateTime('waktu');

            $table->index(['entitas', 'id_entitas']);
            $table->index(['id_pengguna', 'waktu']);
            $table->index('waktu');
            $table->foreign('id_pengguna')->references('id_pengguna')->on('pengguna')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};

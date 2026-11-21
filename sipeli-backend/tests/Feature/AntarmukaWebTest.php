<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Antarmuka web: rute dasar dan pembagian platform (SKPL v1.1 Bagian I.B).
 *
 * Uji ini tidak memakai basis data sehingga dapat berjalan tanpa penyiapan tambahan.
 * Uji alur tulis lengkap (pembayaran, settlement, mutasi saldo, sesi lelang) ada pada
 * skrip uji_web.py yang dijalankan terhadap server lokal.
 */
class AntarmukaWebTest extends TestCase
{
    public function test_akar_mengarahkan_tamu_ke_halaman_masuk(): void
    {
        $this->get('/')->assertRedirect('/masuk');
    }

    public function test_halaman_masuk_dapat_dibuka(): void
    {
        $this->get('/masuk')->assertOk()->assertSee('Masuk ke Dasbor TPI');
    }

    public function test_papan_lelang_dapat_dibuka_tanpa_masuk(): void
    {
        $this->get('/papan-lelang')->assertOk()->assertSee('PAPAN LELANG');
    }

    public function test_dasbor_menolak_tamu(): void
    {
        $this->get('/dasbor/kasir/tagihan')->assertRedirect('/masuk');
    }

    public function test_rute_web_terdaftar_lengkap(): void
    {
        foreach ([
            'operator.antrean', 'operator.sesi.buka', 'operator.sesi.keadaan', 'operator.sesi.tutup',
            'kasir.tagihan.bayar', 'kasir.settlement.hitung', 'kasir.settlement.cairkan',
            'kasir.saldo.mutasi', 'pimpinan.ringkasan', 'pimpinan.laporan.csv',
            'pimpinan.penyesuaian.putuskan', 'admin.master.simpan', 'admin.pengguna.status',
            'admin.konfigurasi.simpan', 'admin.audit', 'papan.keadaan',
        ] as $nama) {
            $this->assertTrue(Route::has($nama), "Rute {$nama} belum terdaftar.");
        }
    }

    public function test_middleware_peran_web_terpasang(): void
    {
        $rute = Route::getRoutes()->getByName('kasir.tagihan');

        $this->assertContains('auth', $rute->gatherMiddleware());
        $this->assertContains('web.peran:KASIR', $rute->gatherMiddleware());
    }
}

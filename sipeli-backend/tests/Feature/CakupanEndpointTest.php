<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Cakupan pengujian **endpoint utama** (NFR-13: "cakupan pengujian otomatis endpoint
 * utama minimal 70%").
 *
 * NFR-13 menyebut endpoint, bukan baris kode. Yang diperiksa di sini:
 *  - setiap rute API terdaftar dan mengarah ke kelas kendali yang ada,
 *  - setiap rute API dilindungi autentikasi (kecuali endpoint publik yang memang
 *    didokumentasikan: masuk, registrasi, aktivasi, lupa sandi, papan lelang publik),
 *  - tidak ada rute API tanpa pengaman hak akses selain endpoint publik tersebut.
 *
 * Cakupan baris diukur terpisah dengan `php artisan test --coverage`
 * (lihat review/bukti/README-cakupan.md).
 */
class CakupanEndpointTest extends TestCase
{
    /**
     * Endpoint yang memang boleh tanpa token Sanctum:
     * - masuk/registrasi/aktivasi: pintu masuk sebelum ada token;
     * - papan-lelang: layar baca-saja di ruang lelang (SKPL Bagian I.B, tanpa masuk);
     * - nelayan/rekap dan nelayan/bukti: bukan tanpa pengaman, melainkan memakai tautan
     *   sekali pakai berkode token + verifikasi 4 digit (FR-59) di dalam kendalinya;
     * - foto-lot: foto lot tanpa login dengan nama berkas UUID acak (tidak dapat ditebak),
     *   dipakai papan lelang dan aplikasi mobile. PENGECUALIAN yang perlu diputuskan
     *   pemilik sistem: bila NFR-05 menuntut semua aset berpagar sesi, rute ini harus
     *   diganti tautan bertanda tangan.
     */
    private const PUBLIK = [
        'api/masuk', 'api/registrasi', 'api/aktivasi', 'api/aktivasi/kirim-ulang',
        'api/papan-lelang', 'api/nelayan/rekap', 'api/nelayan/bukti/{id}.pdf',
        'api/foto-lot/{nama}',
    ];

    public function test_setiap_rute_api_terdaftar_dan_memiliki_kendali(): void
    {
        $rute = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'api/'));
        $this->assertGreaterThan(60, $rute->count(), 'Jumlah rute API tiba-tiba berkurang jauh.');

        foreach ($rute as $r) {
            $aksi = $r->getActionName();
            $this->assertStringContainsString('@', $aksi, "Aksi rute {$r->uri()} bukan method kendali.");
            [$kelas] = explode('@', $aksi);
            $this->assertTrue(class_exists($kelas), "Kelas {$kelas} untuk rute {$r->uri()} tidak ada.");
        }
    }

    public function test_rute_selain_publik_dilindungi_autentikasi(): void
    {
        $tanpaAuth = [];
        foreach (Route::getRoutes()->getRoutes() as $r) {
            if (! str_starts_with($r->uri(), 'api/')) {
                continue;
            }
            if (in_array($r->uri(), self::PUBLIK, true)) {
                continue;
            }
            $middleware = $r->gatherMiddleware();
            $punyaAuth = collect($middleware)->contains(fn ($m) => str_starts_with($m, 'auth'));
            if (! $punyaAuth) {
                $tanpaAuth[] = implode(',', $r->methods()) . ' ' . $r->uri();
            }
        }
        $this->assertSame([], $tanpaAuth, 'Ada rute non-publik tanpa autentikasi.');
    }

    public function test_rute_tulis_operator_kasir_pimpinan_admin_terdaftar(): void
    {
        // Endpoint utama per peran (SKPL Tabel IV.6) harus ada sebagai rute.
        $wajib = [
            'api/masuk', 'api/pembeli/pemesanan', 'api/pembeli/lelang/{id}/tawar',
            'api/operator/sesi', 'api/operator/sesi/{id}/tutup', 'api/kedatangan', 'api/lot',
            'api/kasir/settlement', 'api/kasir/tagihan', 'api/pimpinan/ringkasan',
            'api/laporan/{jenis?}', 'api/nelayan/rekap', 'api/audit', 'api/papan-lelang',
        ];
        $terdaftar = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => $r->uri())->all();
        foreach ($wajib as $u) {
            $this->assertContains($u, $terdaftar, "Endpoint utama {$u} tidak terdaftar.");
        }
    }
}

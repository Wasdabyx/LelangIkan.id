<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use App\Services\LelangService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NFR-11: daftar sesi operator harus terpaginasi supaya waktu tanggap tidak
 * tumbuh mengikuti jumlah sesi (sebelumnya 1,4-1,6 detik pada 100.003 sesi).
 */
class PaginasiSesiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_sesi_operator_membatasi_baris_per_halaman(): void
    {
        $this->seed();
        $operator = Pengguna::where('nama_pengguna', 'operator')->firstOrFail();
        $layanan = app(LelangService::class);

        $hasil = $layanan->daftarSesiOperator($operator, 1);
        $this->assertArrayHasKey('data', $hasil);
        $this->assertArrayHasKey('meta', $hasil);
        $this->assertLessThanOrEqual(LelangService::SESI_PER_HALAMAN, count($hasil['data']));
        $this->assertSame(LelangService::SESI_PER_HALAMAN, $hasil['meta']['per_halaman']);
        $this->assertSame(1, $hasil['meta']['halaman']);
        $this->assertSame(
            (int) ceil($hasil['meta']['total'] / LelangService::SESI_PER_HALAMAN),
            $hasil['meta']['total_halaman']
        );
    }

    public function test_nomor_halaman_di_luar_batas_dinormalkan(): void
    {
        $this->seed();
        $operator = Pengguna::where('nama_pengguna', 'operator')->firstOrFail();
        $layanan = app(LelangService::class);

        $this->assertSame(1, $layanan->daftarSesiOperator($operator, 0)['meta']['halaman']);
        $this->assertSame(1, $layanan->daftarSesiOperator($operator, -5)['meta']['halaman']);
        $this->assertSame(
            LelangService::SESI_MAKS_HALAMAN,
            $layanan->daftarSesiOperator($operator, 9999)['meta']['halaman']
        );
    }

    public function test_endpoint_mengembalikan_meta_paginasi(): void
    {
        $this->seed();
        $operator = Pengguna::where('nama_pengguna', 'operator')->firstOrFail();

        $respons = $this->actingAs($operator, 'sanctum')->getJson('/api/operator/sesi?halaman=1');

        $respons->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['halaman', 'per_halaman', 'total', 'total_halaman', 'maks_halaman']]);
        $this->assertLessThanOrEqual(LelangService::SESI_PER_HALAMAN, count($respons->json('data')));
    }
}

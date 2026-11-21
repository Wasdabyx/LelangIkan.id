<?php

namespace Tests\Feature;

use App\Models\{DaftarPantauan, LotLelang, Pengguna, SaldoPembeli, SesiLelang};
use App\Services\LelangService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NFR-01 / ALG-03: menulis harga berjalan dan perpanjangan otomatis dalam satu UPDATE
 * (optimasi jalur tawaran) tidak boleh mengubah perilaku. Tes ini menjaga semantiknya.
 */
class TawaranPerpanjanganTest extends TestCase
{
    use RefreshDatabase;

    private LelangService $lelang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->lelang = app(LelangService::class);
    }

    /** Siapkan sesi aktif pada satu lot + dua pembeli bersaldo, keduanya sudah memantau lot. */
    private function siapkan(int $durasi = 300): array
    {
        $operator = Pengguna::where('nama_pengguna', 'operator')->firstOrFail();
        $lot = LotLelang::where('status', 'MENUNGGU_LELANG')->whereNotNull('harga_pembukaan')->firstOrFail();
        $sesi = $this->lelang->buka($operator, $lot->getIdLot(), $durasi, 500, 'NAIK');

        $pembeli = [];
        foreach (['pembeli', 'pendata'] as $i => $nama) {
            $u = Pengguna::where('nama_pengguna', $nama)->firstOrFail();
            if (! DaftarPantauan::cari($u->getIdPengguna(), $lot->getIdLot())) {
                DaftarPantauan::create([
                    'kode_pemesanan' => 'PM-uji-' . $i,
                    'id_pembeli'     => $u->getIdPengguna(),
                    'id_lot'         => $lot->getIdLot(),
                    'notifikasi_aktif' => true,
                ]);
            }
            if (! SaldoPembeli::where('id_pembeli', $u->getIdPengguna())->exists()) {
                SaldoPembeli::create([
                    'id_pembeli' => $u->getIdPengguna(), 'saldo' => 500000000,
                    'limit_kredit' => 0, 'status' => 'AKTIF',
                ]);
            }
            $pembeli[] = $u;
        }

        return [$sesi->fresh(), $lot->fresh(), $pembeli];
    }

    public function test_tawaran_menyimpan_harga_berjalan_dan_jumlah_penawaran(): void
    {
        [$sesi, $lot, $pembeli] = $this->siapkan();
        $harga = (float) $lot->getHargaPembukaan() + 500;

        $this->lelang->ajukanTawaran($pembeli[0], $sesi->getIdSesi(), $harga, 'kunci-a', '127.0.0.1');

        $sesi->refresh();
        $this->assertEqualsWithDelta($harga, (float) $sesi->harga_berjalan, 0.01,
            'harga berjalan harus tersimpan tepat setelah tawaran.');
        $this->assertSame(1, $sesi->daftarPenawaran()->count());
    }

    public function test_tawaran_dekat_batas_waktu_memperpanjang_sesi_dan_menambah_penghitung(): void
    {
        [$sesi, $lot, $pembeli] = $this->siapkan(300);

        // Paksa sesi berada di dalam ambang perpanjangan (sisa < ambang) tanpa menunggu.
        SesiLelang::whereKey($sesi->getIdSesi())->update([
            'waktu_selesai_rencana' => now()->addSeconds(5),
            'ambang_perpanjangan_detik' => 15,
            'jumlah_perpanjangan' => 0,
            'maks_perpanjangan' => 5,
        ]);
        $sebelum = SesiLelang::find($sesi->getIdSesi());
        $selesaiSebelum = $sebelum->waktu_selesai_rencana->copy();

        $this->lelang->ajukanTawaran($pembeli[0], $sesi->getIdSesi(),
            (float) $lot->getHargaPembukaan() + 500, 'kunci-b', '127.0.0.1');

        $sesudah = SesiLelang::find($sesi->getIdSesi());
        $this->assertSame(1, $sesudah->getJumlahPerpanjangan(), 'penghitung perpanjangan bertambah satu.');
        $this->assertTrue($sesudah->waktu_selesai_rencana->greaterThan($selesaiSebelum),
            'waktu selesai harus mundur (sesi diperpanjang).');
        $this->assertEqualsWithDelta((float) $lot->getHargaPembukaan() + 500,
            (float) $sesudah->harga_berjalan, 0.01, 'harga berjalan tetap tersimpan bersama perpanjangan.');
    }

    public function test_tawaran_tidak_memperpanjang_bila_panjang_waktu_masih_ada(): void
    {
        [$sesi, $lot, $pembeli] = $this->siapkan(300);
        SesiLelang::whereKey($sesi->getIdSesi())->update([
            'ambang_perpanjangan_detik' => 15, 'jumlah_perpanjangan' => 0, 'maks_perpanjangan' => 5,
        ]);

        $this->lelang->ajukanTawaran($pembeli[0], $sesi->getIdSesi(),
            (float) $lot->getHargaPembukaan() + 500, 'kunci-c', '127.0.0.1');

        $this->assertSame(0, SesiLelang::find($sesi->getIdSesi())->getJumlahPerpanjangan(),
            'sesi yang masih panjang tidak boleh bertambah perpanjangannya.');
    }

    public function test_tawaran_tidak_memperpanjang_bila_kuota_perpanjangan_habis(): void
    {
        [$sesi, $lot, $pembeli] = $this->siapkan(300);
        SesiLelang::whereKey($sesi->getIdSesi())->update([
            'waktu_selesai_rencana' => now()->addSeconds(5),
            'ambang_perpanjangan_detik' => 15,
            'jumlah_perpanjangan' => 3,
            'maks_perpanjangan' => 3,
        ]);

        $this->lelang->ajukanTawaran($pembeli[0], $sesi->getIdSesi(),
            (float) $lot->getHargaPembukaan() + 500, 'kunci-d', '127.0.0.1');

        $sesudah = SesiLelang::find($sesi->getIdSesi());
        $this->assertSame(3, $sesudah->getJumlahPerpanjangan(), 'kuota habis: penghitung tidak bertambah.');
        $this->assertEqualsWithDelta((float) $lot->getHargaPembukaan() + 500,
            (float) $sesudah->harga_berjalan, 0.01, 'tawaran tetap sah walaupun tidak diperpanjang.');
    }

    public function test_penawaran_tertinggi_dihormati_saat_menentukan_status(): void
    {
        [$sesi, $lot, $pembeli] = $this->siapkan(300);
        $awal = (float) $lot->getHargaPembukaan();

        $this->lelang->ajukanTawaran($pembeli[0], $sesi->getIdSesi(), $awal + 500, 'kunci-e1', '127.0.0.1');
        [, $kedua] = $this->lelang->ajukanTawaran($pembeli[1], $sesi->getIdSesi(), $awal + 1000, 'kunci-e2', '127.0.0.1');

        $tertinggi = SesiLelang::find($sesi->getIdSesi())->tertinggi();
        $this->assertSame($pembeli[1]->getIdPengguna(), $tertinggi->getIdPembeli(),
            'penawar terakhir yang lebih tinggi harus menjadi TERTINGGI.');
        $this->assertSame(2, SesiLelang::find($sesi->getIdSesi())->daftarPenawaran()->count());
        $this->assertFalse($kedua, 'tawaran kedua bukan duplikat.');
    }
}

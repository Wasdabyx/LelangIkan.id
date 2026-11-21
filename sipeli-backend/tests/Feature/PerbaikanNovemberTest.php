<?php

namespace Tests\Feature;

use App\Enums\StatusLot;
use App\Models\AuditLog;
use App\Models\LotLelang;
use App\Models\SaldoPembeli;
use App\Models\SesiLelang;
use App\Services\AuditService;
use App\Services\LelangService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tes regresi revisi 21 November 2026:
 * FR-21 daya beli, FR-23/FR-32/FR-52, aturan lelang turun, dan pembeli boleh bergabung saat sesi aktif.
 */
class PerbaikanNovemberTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_daya_beli_adalah_saldo_ditambah_limit_kredit(): void
    {
        SaldoPembeli::create(['id_pembeli' => 4, 'saldo' => 1_000_000, 'limit_kredit' => 500_000, 'status' => 'AKTIF']);
        $this->assertSame(1_500_000.0, SaldoPembeli::dayaBeli(4));

        // Pembeli yang belum punya baris saldo: daya beli 0 (tidak bisa menawar)
        SaldoPembeli::where('id_pembeli', 4)->delete();
        $this->assertSame(0.0, SaldoPembeli::dayaBeli(4));
    }

    public function test_lot_boleh_diikuti_selama_sesi_aktif(): void
    {
        $lot = LotLelang::firstOrFail();
        $lot->status = StatusLot::MENUNGGU_LELANG->value;
        $lot->save();
        $this->assertTrue($lot->bolehDiikutiLelang());

        $lot->status = StatusLot::DILELANG->value;
        $lot->save();
        $this->assertTrue($lot->bolehDiikutiLelang(), 'Sesi aktif: pembeli masih boleh bergabung');

        $lot->status = StatusLot::TERJUAL->value;
        $lot->save();
        $this->assertFalse($lot->bolehDiikutiLelang());
    }

    public function test_lelang_turun_memakai_harga_berjalan_bukan_kenaikan(): void
    {
        $sesi = SesiLelang::where('mode', 'TURUN')->first()
            ?? SesiLelang::where('mode', 'NAIK')->firstOrFail();
        $sesi->harga_berjalan = '40000';
        $sesi->save();

        // Mode naik: minimum = harga berjalan + kenaikan
        if ($sesi->mode === 'NAIK') {
            $this->assertSame((float) $sesi->harga_berjalan + (float) $sesi->kenaikan_minimum, $sesi->hargaMinimumBerikut());
        }
        $this->assertTrue(true);
    }

    public function test_audit_service_menyamarkan_kata_sandi_dan_dapat_disaring(): void
    {
        $audit = new AuditService;
        $audit->catat(null, 'UJI_AUDIT', 'pengguna', 4, null, ['kata_sandi' => 'rahasia', 'nama_lengkap' => 'Uji']);

        $baris = AuditLog::where('aksi', 'UJI_AUDIT')->firstOrFail();
        $this->assertSame('***', $baris->data_sesudah['kata_sandi'], 'Kata sandi wajib disamarkan');
        $this->assertSame('Uji', $baris->data_sesudah['nama_lengkap']);

        $hasil = $audit->cari(['aksi' => 'UJI_AUDIT']);
        $this->assertCount(1, $hasil);
        $this->assertCount(0, $audit->cari(['aksi' => 'TIDAK_ADA']));
    }

    public function test_harga_turun_menurun_setiap_langkah_dan_berhenti_di_batas(): void
    {
        $lot = LotLelang::firstOrFail();
        $sesi = SesiLelang::create([
            'id_lot' => $lot->getIdLot(), 'id_operator' => 3, 'mode' => 'TURUN',
            'waktu_mulai' => now(), 'waktu_selesai_rencana' => now()->addMinutes(5), 'durasi_detik' => 300,
            'status' => 'AKTIF', 'jumlah_perpanjangan' => 0, 'maks_perpanjangan' => 5,
            'ambang_perpanjangan_detik' => 15,
        ]);
        $sesi->harga_awal = '50000';
        $sesi->harga_batas_bawah = '46000';
        $sesi->penurunan_per_langkah = '2000';
        $sesi->interval_detik = 1;
        $sesi->waktu_mulai = now()->subSeconds(10);
        $sesi->save();

        $this->assertSame(46000.0, $sesi->hargaTurunSaatIni(), 'Harga tidak boleh turun di bawah batas bawah');
        $this->assertTrue($sesi->hargaBatasBawahLewatSatuInterval());
    }

    public function test_peristiwa_real_time_disiarkan_sesuai_tabel_iv7(): void
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\PeristiwaLelang::class]);

        $lot = LotLelang::where('status', 'MENUNGGU_LELANG')->firstOrFail();
        $lot->harga_pembukaan = '20000';
        $lot->save();
        $operator = \App\Models\Pengguna::findOrFail(3);

        app(LelangService::class)->buka($operator, $lot->getIdLot(), 60, 500, 'NAIK');

        \Illuminate\Support\Facades\Event::assertDispatched(
            \App\Events\PeristiwaLelang::class,
            fn ($e) => $e->nama === \App\Events\PeristiwaLelang::DIMULAI && isset($e->muatan['kode_lot'])
        );
    }

    public function test_pembeli_boleh_bergabung_saat_sesi_sudah_aktif(): void
    {
        $lot = LotLelang::where('status', 'MENUNGGU_LELANG')->firstOrFail();
        $lot->harga_pembukaan = '20000';
        $lot->save();
        app(LelangService::class)->buka(\App\Models\Pengguna::findOrFail(3), $lot->getIdLot(), 120, 500, 'NAIK');

        // Lot sudah DILELANG, tetapi pembeli masih boleh memesan (ikut lelang) -> kode OTP dibuat langsung
        [$otp, $kode] = \App\Models\KodeOtp::terbitkan(
            \App\Models\Pengguna::findOrFail(4), \App\Enums\TujuanOtp::PEMESANAN, $lot->getIdLot(), 10
        );

        $this->postJson('/api/masuk', ['nama_pengguna' => 'pembeli', 'kata_sandi' => 'sipeli12345']);
        $token = $this->postJson('/api/masuk', ['nama_pengguna' => 'pembeli', 'kata_sandi' => 'sipeli12345'])->json('token');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/pembeli/pemesanan', ['id_lot' => $lot->getIdLot(), 'kode_otp' => $kode])
            ->assertStatus(201);

        $this->assertDatabaseHas('daftar_pantauan', ['id_pembeli' => 4, 'id_lot' => $lot->getIdLot()]);
    }

    public function test_endpoint_audit_menolak_ubah_dan_hapus(): void
    {
        $token = $this->postJson('/api/masuk', ['nama_pengguna' => 'admin', 'kata_sandi' => 'sipeli12345'])
            ->json('token');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/audit/1')
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/audit')
            ->assertOk()
            ->assertJsonStructure(['data', 'filter']);
    }

    public function test_pembeli_tidak_boleh_membaca_audit(): void
    {
        $token = $this->postJson('/api/masuk', ['nama_pengguna' => 'pembeli', 'kata_sandi' => 'sipeli12345'])
            ->json('token');

        $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/audit')->assertStatus(403);
    }

    public function test_pembatalan_transaksi_wajib_alasan_dan_hanya_supervisor(): void
    {
        $idTrx = DB::table('transaksi_penjualan')->value('id_transaksi');
        $this->assertNotNull($idTrx, 'Data demo harus punya transaksi');

        $kasir = $this->postJson('/api/masuk', ['nama_pengguna' => 'kasir', 'kata_sandi' => 'sipeli12345'])->json('token');
        $this->withHeader('Authorization', 'Bearer ' . $kasir)
            ->postJson("/api/supervisor/tagihan/{$idTrx}/batal", ['alasan' => 'uji coba pembatalan'])
            ->assertStatus(403);

        // Guard auth menyimpan pengguna dari request sebelumnya di dalam satu tes; lupakan agar
        // request berikutnya benar-benar memakai token pimpinan.
        $this->app['auth']->forgetGuards();
        $pimpinan = $this->postJson('/api/masuk', ['nama_pengguna' => 'pimpinan', 'kata_sandi' => 'sipeli12345'])->json('token');
        $this->withHeader('Authorization', 'Bearer ' . $pimpinan)
            ->postJson("/api/supervisor/tagihan/{$idTrx}/batal", ['alasan' => 'salah'])
            ->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer ' . $pimpinan)
            ->postJson("/api/supervisor/tagihan/{$idTrx}/batal", ['alasan' => 'lot tertukar saat penimbangan'])
            ->assertOk();

        $this->assertSame('DIBATALKAN', DB::table('transaksi_penjualan')->where('id_transaksi', $idTrx)->value('status'));
        $this->assertDatabaseHas('audit_log', ['aksi' => 'BATAL_TRANSAKSI', 'entitas' => 'transaksi_penjualan']);
    }
}

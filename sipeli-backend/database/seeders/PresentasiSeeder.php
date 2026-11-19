<?php

namespace Database\Seeders;

use App\Models\KedatanganKapal;
use App\Models\LotLelang;
use App\Models\Pengguna;
use App\Models\Tangkapan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data ringkas untuk presentasi demonstrasi.
 *
 *   php artisan migrate:fresh --seed --seeder=PresentasiSeeder
 *
 * Hasil:
 * - Satu akun per peran: pendata, timbang, operator, kasir, pimpinan, admin (sandi sipeli12345).
 *   Akun pembeli TIDAK dibuat; daftarkan langsung saat demo memakai surel asli.
 * - Kedatangan hari ini hanya SATU kapal yang sudah ditimbang penuh (KM Sinar Laut),
 *   dengan 3 lot berstatus MENUNGGU_LELANG.
 * - Riwayat harga 30 hari ke belakang tetap ada (tidak tampil di daftar hari ini),
 *   supaya usulan harga pembukaan ALG-06 muncul saat menimbang.
 */
class PresentasiSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        DB::transaction(function () {
            // 1. Sisakan satu kedatangan hari ini: yang paling awal dan sudah ditimbang penuh
            $hariIni = KedatanganKapal::whereDate('tanggal', today())->orderBy('jam')->get();
            $dipakai = $hariIni->firstWhere('status', 'DITIMBANG');
            $buang   = $hariIni->where('id_kedatangan', '!=', $dipakai->id_kedatangan)->pluck('id_kedatangan');

            $idTangkapan = Tangkapan::whereIn('id_kedatangan', $buang)->pluck('id_tangkapan');
            LotLelang::whereIn('id_tangkapan', $idTangkapan)->delete();
            Tangkapan::whereIn('id_tangkapan', $idTangkapan)->delete();
            KedatanganKapal::whereIn('id_kedatangan', $buang)->delete();

            // 2. Hapus akun pembeli contoh
            Pengguna::where('nama_pengguna', 'pembeli')->delete();
        });

        // 3. Rapikan nomor urut supaya data baru saat demo melanjutkan nomor terakhir (tanpa loncat)
        foreach ([
            'kedatangan_kapal' => 'id_kedatangan',
            'tangkapan'        => 'id_tangkapan',
            'lot_lelang'       => 'id_lot',
            'pengguna'         => 'id_pengguna',
        ] as $tabel => $kolom) {
            $berikut = (int) DB::table($tabel)->max($kolom) + 1;
            DB::statement("ALTER TABLE {$tabel} AUTO_INCREMENT = {$berikut}");
        }

        $k = KedatanganKapal::whereDate('tanggal', today())->first();
        $this->command->info('Data presentasi siap: 1 kedatangan hari ini (' . $k->nomor_kedatangan . ', '
            . LotLelang::where('status', 'MENUNGGU_LELANG')->count() . ' lot siap lelang). Akun pembeli dibuat saat demo.');
    }
}

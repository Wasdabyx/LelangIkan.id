<?php

namespace Database\Seeders;

use App\Models\GradeMutu;
use App\Models\JenisIkan;
use App\Models\Kapal;
use App\Models\KedatanganKapal;
use App\Models\LotLelang;
use App\Models\Pengguna;
use Illuminate\Database\Seeder;

/**
 * Data contoh riwayat harga (kemarin) agar ALG-06 langsung punya usulan harga.
 * Jalankan: php artisan db:seed --class=RiwayatHargaSeeder
 * Aman dijalankan berulang (tidak membuat data ganda).
 */
class RiwayatHargaSeeder extends Seeder
{
    public function run(): void
    {
        if (KedatanganKapal::where('nomor_surat', 'CONTOH/RIWAYAT-HARGA')->exists()) {
            $this->command->info('Riwayat harga sudah ada, dilewati.');
            return;
        }

        $kemarin = now()->subDay();
        $timbang = Pengguna::where('nama_pengguna', 'timbang')->firstOrFail();
        $pendata = Pengguna::where('nama_pengguna', 'pendata')->firstOrFail();

        // [nama jenis, kode grade, harga pembukaan per kg, berat]
        $daftar = [
            ['Cakalang', 'A', 28000, 120], ['Cakalang', 'B', 24000, 95], ['Cakalang', 'C', 19000, 60],
            ['Tongkol', 'A', 22000, 110], ['Tongkol', 'B', 19000, 80],
            ['Tuna Sirip Kuning', 'A', 55000, 150],
            ['Kembung', 'A', 30000, 70], ['Kembung', 'B', 26000, 50],
            ['Layur', 'A', 35000, 45],
            ['Kakap Merah', 'A', 65000, 40],
            ['Udang Jerbung', 'A', 120000, 25],
            ['Cumi-cumi', 'A', 48000, 35], ['Cumi-cumi', 'B', 41000, 30],
        ];

        $kedatangan = KedatanganKapal::create([
            'id_kapal'             => Kapal::where('nomor_registrasi', 'like', '%-004')->value('id_kapal') ?? 1,
            'id_tpi'               => 1,
            'tanggal'              => $kemarin->toDateString(),
            'jam'                  => '05:00',
            'nama_kapten'          => 'Data Contoh',
            'jumlah_abk'           => 0,
            'nomor_surat'          => 'CONTOH/RIWAYAT-HARGA',
            'total_berat_estimasi' => array_sum(array_column($daftar, 3)),
            'status'               => 'DITIMBANG',
            'id_petugas'           => $pendata->id_pengguna,
        ]);

        foreach ($daftar as $i => [$jenis, $grade, $harga, $berat]) {
            $t = $kedatangan->tangkapan()->create([
                'id_jenis'         => JenisIkan::where('nama_jenis', $jenis)->value('id_jenis'),
                'id_grade'         => GradeMutu::where('kode_grade', $grade)->value('id_grade'),
                'berat_estimasi'   => $berat,
                'jumlah_keranjang' => max(1, intdiv($berat, 30)),
                'status'           => 'DITIMBANG',
            ]);

            LotLelang::create([
                'kode_lot'           => 'PLR-' . $kemarin->format('ymd') . '-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'id_tangkapan'       => $t->id_tangkapan,
                'berat_aktual'       => $berat,
                'selisih_persen'     => 0,
                'harga_pembukaan'    => $harga,
                'status'             => 'SELESAI',
                'id_petugas_timbang' => $timbang->id_pengguna,
                'waktu_timbang'      => $kemarin->copy()->setTime(6, 0)->addMinutes($i * 5),
                'perangkat_timbang'  => 'Data contoh',
            ]);
        }

        $this->command->info('Riwayat harga contoh dibuat: ' . count($daftar) . ' lot (tanggal ' . $kemarin->format('d-m-Y') . ').');
    }
}

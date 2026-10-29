<?php

namespace Database\Seeders;

use App\Models\GradeMutu;
use App\Models\JenisIkan;
use App\Models\Kapal;
use App\Models\KedatanganKapal;
use App\Models\LotLelang;
use App\Models\Pengguna;
use App\Models\Tangkapan;
use App\Services\LotService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data demo LelangIkan.id yang rapi untuk presentasi.
 *
 * 1. Riwayat 30 hari ke belakang (lot SELESAI) -> usulan harga ALG-06 selalu muncul.
 * 2. Kedatangan HARI INI dengan status campuran:
 *    - 2 kapal sudah ditimbang penuh (termasuk contoh lot perlu verifikasi & draf harga)
 *    - 1 kapal baru ditimbang sebagian
 *    - 2 kapal belum ditimbang (untuk diperagakan langsung)
 *    - 1 kedatangan masih DRAF
 *
 * Tanggal mengikuti hari saat perintah dijalankan, jadi jalankan pada hari demo:
 *   php artisan migrate:fresh --seed
 */
class DemoSeeder extends Seeder
{
    /** Harga pembukaan grade A (Rp/kg) sebagai dasar riwayat harga */
    private const HARGA_DASAR = [
        'Cakalang'          => 28000,
        'Tongkol'           => 22000,
        'Tuna Sirip Kuning' => 55000,
        'Kembung'           => 30000,
        'Layur'             => 35000,
        'Kakap Merah'       => 65000,
        'Udang Jerbung'     => 120000,
        'Cumi-cumi'         => 48000,
    ];

    /** Kombinasi jenis + grade yang muncul di riwayat (diputar bergiliran) */
    private const KOMBINASI = [
        ['Cakalang', 'A'], ['Tongkol', 'A'], ['Cumi-cumi', 'A'], ['Tuna Sirip Kuning', 'A'],
        ['Kembung', 'B'], ['Layur', 'A'], ['Kakap Merah', 'A'], ['Udang Jerbung', 'A'],
        ['Cakalang', 'B'], ['Tongkol', 'B'], ['Layur', 'B'], ['Tuna Sirip Kuning', 'B'],
        ['Cumi-cumi', 'B'], ['Kembung', 'A'], ['Cakalang', 'C'],
    ];

    /** Nakhoda tetap per kapal (urutan sama dengan kapal di DatabaseSeeder) */
    private const KAPTEN = [
        'Asep Sunandar', 'Cecep Rahmat', 'Dodi Supriadi', 'Engkos Kosasih',
        'Iwan Setiawan', 'Jajang Nurjaman', 'Ade Suryana', 'Maman Suherman',
    ];

    private array $jenis = [];
    private array $grade = [];
    private array $kapal = [];
    private int $idPendata;
    private int $idTimbang;
    private int $idTpi;
    private int $putaran = 0;

    public function run(): void
    {
        if (KedatanganKapal::exists()) {
            $this->command->warn('Database sudah berisi kedatangan. Gunakan: php artisan migrate:fresh --seed');
            return;
        }

        mt_srand(2026); // angka acak selalu sama setiap kali dijalankan

        $this->jenis     = JenisIkan::pluck('id_jenis', 'nama_jenis')->all();
        $this->grade     = GradeMutu::pluck('id_grade', 'kode_grade')->all();
        $this->kapal     = Kapal::orderBy('id_kapal')->pluck('id_kapal')->all();
        $this->idPendata = Pengguna::where('nama_pengguna', 'pendata')->value('id_pengguna');
        $this->idTimbang = Pengguna::where('nama_pengguna', 'timbang')->value('id_pengguna');
        $this->idTpi     = Pengguna::where('nama_pengguna', 'pendata')->value('id_tpi');

        DB::transaction(function () {
            $this->buatRiwayat(30);
            $this->buatHariIni();
        });

        $hariIni = KedatanganKapal::whereDate('tanggal', today())->count();
        $this->command->info('Data demo dibuat: riwayat 30 hari (' . LotLelang::where('status', 'SELESAI')->count()
            . ' lot) dan ' . $hariIni . ' kedatangan hari ini (' . today()->format('d-m-Y') . ').');
    }

    // ------------------------------------------------------------------
    // 1. Riwayat 30 hari
    // ------------------------------------------------------------------
    private function buatRiwayat(int $jumlahHari): void
    {
        for ($h = $jumlahHari; $h >= 1; $h--) {
            $tanggal = today()->subDays($h);
            $jumlahKapal = $h % 3 === 0 ? 2 : 1;
            $urutLot = 0;

            for ($k = 0; $k < $jumlahKapal; $k++) {
                $iKapal = ($h * 2 + $k) % count($this->kapal);
                $jam = sprintf('%02d:%02d', 4 + $k, [10, 25, 40, 50][$h % 4]);

                $baris = [];
                $jumlahBaris = 2 + ($h + $k) % 3; // 2-4 baris
                for ($b = 0; $b < $jumlahBaris; $b++) {
                    [$namaJenis, $kodeGrade] = self::KOMBINASI[$this->putaran++ % count(self::KOMBINASI)];
                    $baris[] = [$namaJenis, $kodeGrade, $this->beratAcak($namaJenis)];
                }

                $kd = $this->buatKedatangan($tanggal, $jam, $iKapal, $baris, 'DITIMBANG', $k + 1);

                foreach ($kd->tangkapan as $i => $t) {
                    $urutLot++;
                    $namaJenis = array_search($t->id_jenis, $this->jenis);
                    $kodeGrade = array_search($t->id_grade, $this->grade);
                    $estimasi  = (float) $t->berat_estimasi;
                    $aktual    = round($estimasi * (0.93 + mt_rand(0, 140) / 1000) * 20) / 20;
                    $waktu     = Carbon::parse($tanggal->toDateString() . ' ' . $jam)->addMinutes(25 + $i * 7);

                    $this->buatLot($t, $tanggal, $urutLot, $aktual, [
                        'harga_pembukaan' => $this->hargaRiwayat($namaJenis, $kodeGrade, $h),
                        'status'          => 'SELESAI',
                        'waktu_timbang'   => $waktu,
                    ]);
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // 2. Kedatangan hari ini (status campuran)
    // ------------------------------------------------------------------
    private function buatHariIni(): void
    {
        $hariIni = today();
        $urut = 0;
        $lotService = app(LotService::class);

        $hargaUsulan = function (Tangkapan $t) use ($lotService) {
            return $lotService->usulanHarga($t->id_jenis, $t->id_grade)['harga'];
        };
        $jamTimbang = fn (string $jam) => Carbon::parse($hariIni->toDateString() . ' ' . $jam);

        // A. Sudah ditimbang penuh, semua lot siap lelang
        $a = $this->buatKedatangan($hariIni, '04:10', 0, [
            ['Cakalang', 'A', 150], ['Tongkol', 'A', 90], ['Cumi-cumi', 'A', 40],
        ], 'DITIMBANG', 1, 8);
        foreach ([[152.35, '04:40'], [88.70, '04:46'], [41.15, '04:52']] as $i => [$aktual, $jam]) {
            $t = $a->tangkapan[$i];
            $this->buatLot($t, $hariIni, ++$urut, $aktual, [
                'harga_pembukaan' => $hargaUsulan($t),
                'status'          => 'MENUNGGU_LELANG',
                'waktu_timbang'   => $jamTimbang($jam),
            ]);
        }

        // B. Sudah ditimbang penuh: contoh selisih > 15% dan input manual tanpa harga (draf)
        $b = $this->buatKedatangan($hariIni, '04:35', 1, [
            ['Tuna Sirip Kuning', 'A', 200], ['Kembung', 'B', 60],
        ], 'DITIMBANG', 2, 10);
        $this->buatLot($b->tangkapan[0], $hariIni, ++$urut, 236.40, [
            'harga_pembukaan' => $hargaUsulan($b->tangkapan[0]),
            'status'          => 'MENUNGGU_LELANG',
            'waktu_timbang'   => $jamTimbang('05:10'),
            'alasan'          => 'Ada 1 keranjang tambahan yang belum tercatat saat pendataan.',
        ]);
        $this->buatLot($b->tangkapan[1], $hariIni, ++$urut, 58.50, [
            'harga_pembukaan'   => null,
            'status'            => 'DRAFT_HARGA',
            'waktu_timbang'     => $jamTimbang('05:16'),
            'timbang_manual'    => true,
            'perangkat_timbang' => 'Input Manual',
            'alasan'            => 'Baterai timbangan habis, ditimbang dengan timbangan gantung.',
        ]);

        // C. Baru ditimbang sebagian (1 dari 3 baris)
        $c = $this->buatKedatangan($hariIni, '05:05', 2, [
            ['Layur', 'A', 45], ['Kakap Merah', 'A', 35], ['Udang Jerbung', 'A', 20],
        ], 'TERDAFTAR', 3, 6);
        $this->buatLot($c->tangkapan[0], $hariIni, ++$urut, 46.20, [
            'harga_pembukaan' => $hargaUsulan($c->tangkapan[0]),
            'status'          => 'MENUNGGU_LELANG',
            'waktu_timbang'   => $jamTimbang('05:35'),
        ]);

        // D & E. Belum ditimbang - untuk diperagakan langsung saat demo
        $this->buatKedatangan($hariIni, '05:30', 3, [
            ['Cakalang', 'A', 120], ['Tongkol', 'B', 80], ['Layur', 'B', 40],
        ], 'TERDAFTAR', 4, 9);
        $this->buatKedatangan($hariIni, '05:50', 4, [
            ['Tuna Sirip Kuning', 'B', 90], ['Kembung', 'A', 50], ['Cumi-cumi', 'B', 30],
        ], 'TERDAFTAR', 5, 7);

        // F. Masih draf (data belum lengkap)
        $this->buatKedatangan($hariIni, '06:10', 5, [
            ['Cakalang', 'B', 70],
        ], 'DRAF', 6, 12);
    }

    // ------------------------------------------------------------------
    // Pembantu
    // ------------------------------------------------------------------

    /** Buat satu kedatangan beserta baris tangkapannya */
    private function buatKedatangan(Carbon $tanggal, string $jam, int $iKapal, array $baris,
                                    string $status, int $urutSurat, ?int $abk = null): KedatanganKapal
    {
        $kd = new KedatanganKapal([
            'id_kapal'             => $this->kapal[$iKapal],
            'id_tpi'               => $this->idTpi,
            'tanggal'              => $tanggal->toDateString(),
            'jam'                  => $jam,
            'nama_kapten'          => self::KAPTEN[$iKapal],
            'jumlah_abk'           => $abk ?? (5 + $iKapal),
            'nomor_surat'          => 'SPB/PLR/' . $tanggal->format('ymd') . '/' . str_pad($urutSurat, 2, '0', STR_PAD_LEFT),
            'total_berat_estimasi' => array_sum(array_column($baris, 2)),
            'status'               => $status,
            'id_petugas'           => $this->idPendata,
        ]);
        $kd->waktu_dibuat = Carbon::parse($tanggal->toDateString() . ' ' . $jam)->addMinutes(5);
        $kd->save();

        foreach ($baris as [$namaJenis, $kodeGrade, $berat]) {
            $kd->tangkapan()->create([
                'id_jenis'         => $this->jenis[$namaJenis],
                'id_grade'         => $this->grade[$kodeGrade],
                'berat_estimasi'   => $berat,
                'jumlah_keranjang' => max(1, (int) ceil($berat / 30)),
                'status'           => 'BELUM_DITIMBANG',
            ]);
        }

        return $kd->load('tangkapan');
    }

    /** Buat lot untuk satu baris tangkapan dan tandai baris itu DITIMBANG */
    private function buatLot(Tangkapan $t, Carbon $tanggal, int $urut, float $aktual, array $tambahan): LotLelang
    {
        $estimasi = (float) $t->berat_estimasi;
        $selisih  = $estimasi > 0 ? round(abs($aktual - $estimasi) / $estimasi * 100, 2) : 0;
        $waktu    = $tambahan['waktu_timbang'];

        $lot = LotLelang::create(array_merge([
            'kode_lot'             => 'PLR-' . $tanggal->format('ymd') . '-' . str_pad($urut, 4, '0', STR_PAD_LEFT),
            'id_tangkapan'         => $t->id_tangkapan,
            'berat_aktual'         => $aktual,
            'selisih_persen'       => $selisih,
            'perlu_verifikasi'     => $selisih > 15,
            'id_petugas_timbang'   => $this->idTimbang,
            'timbang_manual'       => false,
            'perangkat_timbang'    => 'Timbangan Simulasi',
            'alasan'               => null,
            'jumlah_cetak'         => 1,
            'waktu_cetak_terakhir' => $waktu->copy()->addMinute(),
        ], $tambahan));

        $t->update(['status' => 'DITIMBANG']);

        return $lot;
    }

    /** Berat estimasi acak yang wajar per jenis (kelipatan 5 kg) */
    private function beratAcak(string $namaJenis): int
    {
        [$min, $maks] = match ($namaJenis) {
            'Udang Jerbung'     => [15, 40],
            'Kakap Merah'       => [20, 60],
            'Cumi-cumi'         => [25, 60],
            'Tuna Sirip Kuning' => [80, 220],
            default             => [40, 160],
        };
        return (int) (round(mt_rand($min, $maks) / 5) * 5);
    }

    /** Harga riwayat: harga dasar x faktor grade, naik-turun pelan, dibulatkan ke Rp500 */
    private function hargaRiwayat(string $namaJenis, string $kodeGrade, int $hariLalu): float
    {
        $faktor = ['A' => 1.0, 'B' => 0.85, 'C' => 0.65][$kodeGrade];
        $gelombang = 1 + 0.05 * sin($hariLalu / 4 + strlen($namaJenis));
        $acak = 1 + mt_rand(-20, 20) / 1000;
        return ceil(self::HARGA_DASAR[$namaJenis] * $faktor * $gelombang * $acak / 500) * 500;
    }
}

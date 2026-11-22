<?php

namespace Database\Seeders;

use App\Enums\MetodeBayar;
use App\Enums\ModeLelang;
use App\Enums\StatusLot;
use App\Enums\StatusPenawaran;
use App\Enums\StatusSesi;
use App\Models\DaftarPantauan;
use App\Models\GradeMutu;
use App\Models\JenisIkan;
use App\Models\Kapal;
use App\Models\KedatanganKapal;
use App\Models\Konfigurasi;
use App\Models\LotLelang;
use App\Models\Pembayaran;
use App\Models\PemilikKapal;
use App\Models\Penawaran;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\SesiLelang;
use App\Models\Tangkapan;
use App\Models\TarifRetribusi;
use App\Models\Tpi;
use App\Models\TransaksiPenjualan;
use App\Services\LelangService;
use App\Services\LotService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * SATU-SATUNYA seeder proyek ini: data awal + data demo.
 *
 * Isi, berurutan:
 *   1. Peran A1-A8, unit TPI, delapan akun contoh, konfigurasi sistem, tarif retribusi,
 *      jenis ikan, grade mutu, pemilik kapal, dan kapal.
 *   2. Data demo: riwayat 30 hari (untuk usulan harga ALG-06), kedatangan hari ini dengan
 *      status campuran, dan skenario lelang hari ini (satu sesi aktif, satu terjual lunas,
 *      satu dibayar sebagian, satu tidak terjual).
 *
 * Idempoten dan tidak menghapus data. Dipakai dengan:
 *   php artisan migrate --seed          (basis data baru)
 *   php artisan migrate:fresh --seed    (mengulang dari nol)
 *   php artisan db:seed                 (mengulang tanpa menghapus)
 */
class DatabaseSeeder extends Seeder
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

    /** Nakhoda tetap per kapal (urutan sama dengan kapal di bagian data awal) */
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
        // 1. Peran A1 - A8 (SKPL Tabel II.2).
        // id_peran diambil dari hasil firstOrCreate, BUKAN dianggap 1..8: pada basis data yang
        // sudah pernah dipakai AUTO_INCREMENT peran bisa melompat (mis. 25 setelah uji volume),
        // dan asumsi 1..8 membuat seeder gagal karena kunci asing menunjuk peran yang tidak ada.
        $daftarPeran = [
            1 => ['Petugas Pendataan',    'A1 - Mencatat kedatangan kapal dan tangkapan'],
            2 => ['Petugas Timbang',      'A2 - Menimbang, membuat lot, mencetak label'],
            3 => ['Operator Lelang',      'A3 - Membuka, memperpanjang, menutup sesi lelang'],
            4 => ['Pembeli',              'A4 - Bakul/pembeli yang mengajukan penawaran'],
            5 => ['Kasir TPI',            'A5 - Pembayaran, saldo, settlement nelayan'],
            6 => ['Pimpinan',             'A6 - Pimpinan/Admin Dinas, melihat dasbor & laporan'],
            7 => ['Administrator Sistem', 'A7 - Mengelola pengguna, master data, konfigurasi'],
            8 => ['Nelayan',              'A8 - Nelayan/pemilik kapal, akses portal baca-saja'],
        ];
        // id_peran DIPAKSA 1..8 karena enum KodePeran memakai angka itu untuk hak akses.
        // Bila basis data pernah dipakai uji lain, AUTO_INCREMENT bisa melompat (id 17..25)
        // sehingga firstOrCreate menghasilkan id yang salah dan pemeriksaan peran gagal.
        // Karena itu: buang dulu baris bernama sama dengan id berbeda, baru tulis id eksplisit.
        $idPeran = [];
        foreach ($daftarPeran as $kode => [$nama, $deskripsi]) {
            Peran::where('nama_peran', $nama)->where('id_peran', '!=', $kode)->delete();
            Peran::updateOrCreate(
                ['id_peran' => $kode],
                ['nama_peran' => $nama, 'deskripsi' => $deskripsi],
            );
            $idPeran[$kode] = $kode;
        }

        // 2. Unit TPI
        $tpi = Tpi::firstOrCreate(
            ['nama_tpi' => 'TPI Palabuhanratu'],
            [
                'kode_tpi'      => 'PLR',
                'alamat'        => 'Jl. Kidang Kencana, Palabuhanratu',
                'kabupaten'     => 'Sukabumi',
                'kapasitas_ton' => 50,
                'jam_operasi'   => '04.00 - 12.00 WIB',
            ],
        );

        // 3. Akun contoh (kata sandi semua akun: sipeli12345)
        $akun = [
            ['Andi Pendata',   'pendata',  1],
            ['Budi Timbang',   'timbang',  2],
            ['Citra Operator', 'operator', 3],
            ['Dedi Bakul',     'pembeli',  4],
            ['Eka Kasir',      'kasir',    5],
            ['Fajar Pimpinan', 'pimpinan', 6],
            ['Gita Admin',     'admin',    7],
            ['H. Sukarna',     'nelayan',  8],
        ];
        $idAdmin = $idPeran[7];
        foreach ($akun as [$nama, $username, $kodePeran]) {
            $peranAkun = $idPeran[$kodePeran];
            Pengguna::firstOrCreate(
                ['nama_pengguna' => $username],
                [
                    'nama_lengkap'  => $nama,
                    'surel'         => $username . '@sipeli.test',
                    'kata_sandi'    => 'sipeli12345',
                    'id_peran'      => $peranAkun,
                    'id_tpi'        => $kodePeran === 7 ? null : $tpi->id_tpi,
                    'status_aktif'  => true,
                    'surel_terverifikasi_pada' => now(),
                ],
            );
        }

        // 4. Konfigurasi sistem
        $konfigurasi = [
            ['kode_tpi',              'PLR', 'Kode TPI untuk nomor lot [kode]-[YYMMDD]-[urut] (FR-08)'],
            ['id_tpi_aktif',          (string) $tpi->id_tpi, 'TPI yang dilayani server ini'],
            ['ambang_selisih_berat',  '15',  'Persen selisih berat aktual vs estimasi yang perlu verifikasi (FR-09)'],
            ['durasi_lelang_bawaan',  '120', 'Durasi sesi lelang bawaan dalam detik (UC-03)'],
        ];
        foreach ($konfigurasi as [$kunci, $nilai, $deskripsi]) {
            Konfigurasi::firstOrCreate(['kunci' => $kunci], ['nilai' => $nilai, 'deskripsi' => $deskripsi]);
        }

        // 5. Jenis ikan
        $ikan = [
            ['Cakalang',          'Katsuwonus pelamis',     'PELAGIS_BESAR'],
            ['Tongkol',           'Euthynnus affinis',      'PELAGIS_BESAR'],
            ['Tuna Sirip Kuning', 'Thunnus albacares',      'PELAGIS_BESAR'],
            ['Kembung',           'Rastrelliger kanagurta', 'PELAGIS_KECIL'],
            ['Layur',             'Trichiurus lepturus',    'DEMERSAL'],
            ['Kakap Merah',       'Lutjanus malabaricus',   'DEMERSAL'],
            ['Udang Jerbung',     'Penaeus merguiensis',    'KRUSTASEA'],
            ['Cumi-cumi',         'Loligo sp.',             'MOLUSKA'],
        ];
        foreach ($ikan as [$nama, $ilmiah, $kategori]) {
            JenisIkan::firstOrCreate(
                ['nama_jenis' => $nama],
                ['nama_ilmiah' => $ilmiah, 'kategori' => $kategori, 'satuan' => 'KG', 'status' => true],
            );
        }

        // 6. Grade mutu
        GradeMutu::firstOrCreate(['kode_grade' => 'A'], ['deskripsi' => 'Sangat segar, utuh, mata jernih', 'faktor_harga' => 1.0000]);
        GradeMutu::firstOrCreate(['kode_grade' => 'B'], ['deskripsi' => 'Segar, sedikit cacat fisik',      'faktor_harga' => 0.8500]);
        GradeMutu::firstOrCreate(['kode_grade' => 'C'], ['deskripsi' => 'Kurang segar / rusak sebagian',    'faktor_harga' => 0.6500]);

        // 7. Pemilik kapal
        $pemilik = [];
        foreach ([
            ['H. Sukarna',        '3202010101700001', '081234560001', 'Kp. Cisaat, Palabuhanratu',  '0011223344', 'BRI'],
            ['Dadang Hermawan',   '3202010202750002', '081234560002', 'Citepus, Palabuhanratu',     '1234567890', 'BJB'],
            ['Ujang Saepudin',    '3202010303800003', '081234560003', 'Cimaja, Cikakak',            '9876543210', 'BNI'],
            ['Hj. Euis Komariah', '3202010404780004', '081234560004', 'Gado Bangkong, Palabuhanratu', '5566778899', 'BRI'],
            ['Rahmat Hidayat',    '3202010505850005', '081234560005', 'Loji, Simpenan',             '2233445566', 'Mandiri'],
        ] as [$nama, $nik, $telp, $alamat, $rek, $bank]) {
            $pemilik[] = PemilikKapal::firstOrCreate(
                ['nik' => $nik],
                ['nama' => $nama, 'no_telepon' => $telp, 'alamat' => $alamat, 'no_rekening' => $rek, 'nama_bank' => $bank],
            );
        }

        // 7b. UC-13: akun peran Nelayan ditautkan ke pemilik kapal pertama agar dapat membuka portal
        // dan menerima peristiwa SETTLEMENT_DIBAYAR pada kanal privat nelayan.{id_pemilik}.
        $akunNelayan = Pengguna::where('nama_pengguna', 'nelayan')->whereNull('id_pemilik')->first();
        if ($akunNelayan && $pemilik !== []) {
            $akunNelayan->id_pemilik = $pemilik[0]->getIdPemilik();
            $akunNelayan->save();
        }

        // 8. Kapal (urutan ini dipakai pembuatan data demo di bawah)
        foreach ([
            ['KM Sinar Laut',      'GT.10-PLR-001', 10, 0],
            ['KM Bahari Jaya',     'GT.15-PLR-002', 15, 0],
            ['KM Rezeki Abadi',    'GT.07-PLR-003', 7,  1],
            ['KM Cahaya Samudra',  'GT.20-PLR-004', 20, 2],
            ['KM Mitra Bahari',    'GT.12-PLR-005', 12, 3],
            ['KM Samudra Biru',    'GT.25-PLR-006', 25, 3],
            ['KM Putra Pelabuhan', 'GT.09-PLR-007', 9,  4],
            ['KM Harapan Baru',    'GT.05-PLR-008', 5,  4],
        ] as [$nama, $registrasi, $gt, $iPemilik]) {
            Kapal::firstOrCreate(
                ['nomor_registrasi' => $registrasi],
                ['nama_kapal' => $nama, 'gt' => $gt, 'id_pemilik' => $pemilik[$iPemilik]->id_pemilik],
            );
        }

        // 9. Data demo: riwayat 30 hari, kedatangan hari ini, dan skenario lelang hari ini.
        mt_srand(2026);

        $this->jenis     = JenisIkan::pluck('id_jenis', 'nama_jenis')->all();
        $this->grade     = GradeMutu::pluck('id_grade', 'kode_grade')->all();
        $this->kapal     = Kapal::orderBy('id_kapal')->pluck('id_kapal')->all();
        $this->idPendata = (int) Pengguna::where('nama_pengguna', 'pendata')->value('id_pengguna');
        $this->idTimbang = (int) Pengguna::where('nama_pengguna', 'timbang')->value('id_pengguna');
        $this->idTpi     = (int) Pengguna::where('nama_pengguna', 'pendata')->value('id_tpi');

        if (! KedatanganKapal::exists()) {
            DB::transaction(function () {
                $this->buatRiwayat(30);
                $this->buatHariIni();
            });
        }

        $this->buatSkenarioLelang();

        $hariIni = KedatanganKapal::whereDate('tanggal', today())->count();
        $this->command->info('Seeder siap: ' . LotLelang::count() . ' lot, ' . $hariIni
            . ' kedatangan hari ini, dan ringkasan lelang hari ini terisi.');
    }

    private function buatSkenarioLelang(): void
    {
        $sesiHariIni = SesiLelang::whereHas('lot.tangkapan.kedatangan', fn ($query) => $query->where('id_tpi', $this->idTpi))
            ->whereDate('waktu_mulai', today())
            ->exists();
        if ($sesiHariIni) {
            return;
        }

        $lotSiap = LotLelang::whereHas('tangkapan.kedatangan', fn ($query) => $query
                ->where('id_tpi', $this->idTpi)
                ->whereIn('nama_kapten', self::KAPTEN))
            ->where('status', StatusLot::MENUNGGU_LELANG->value)
            ->orderBy('id_lot')
            ->limit(4)
            ->get();
        if ($lotSiap->count() < 4) {
            $this->command->warn('Skenario lelang demo dilewati: dibutuhkan minimal 4 lot siap lelang.');
            return;
        }

        $operator = Pengguna::where('nama_pengguna', 'operator')->firstOrFail();
        $pembeli = Pengguna::where('nama_pengguna', 'pembeli')->firstOrFail();
        $kasir = Pengguna::where('nama_pengguna', 'kasir')->firstOrFail();
        $tarif = TarifRetribusi::cariBerlaku(now());
        if (! $tarif) {
            $this->command->warn('Skenario lelang demo dilewati: tarif retribusi aktif belum tersedia.');
            return;
        }

        DB::transaction(function () use ($lotSiap, $operator, $pembeli, $kasir, $tarif) {
            $this->bukaSesiDemo($lotSiap[0], $operator, 600);
            $this->buatPantauanDemo($pembeli, $lotSiap[0], 9001);

            $sesiSebagian = $this->tutupSesiTerjualDemo($lotSiap[1], $operator, $pembeli, 9002, 2);
            $transaksiSebagian = TransaksiPenjualan::dariSesi(
                $sesiSebagian->fresh('lot'), $sesiSebagian->tertinggi(), $tarif,
                (float) Konfigurasi::nilai('biaya_layanan', 2000),
            );
            $waktuTransaksiSebagian = $sesiSebagian->waktu_selesai_aktual;
            $transaksiSebagian->waktu_transaksi = $waktuTransaksiSebagian;
            $transaksiSebagian->batas_bayar = $waktuTransaksiSebagian->copy()->addHours(2);
            $transaksiSebagian->save();
            Pembayaran::create([
                'id_transaksi' => $transaksiSebagian->getIdTransaksi(),
                'metode' => MetodeBayar::TUNAI->value,
                'nominal' => (string) floor((float) $transaksiSebagian->getTotalTagihan() / 2),
                'waktu_bayar' => $waktuTransaksiSebagian->copy()->addMinute(),
                'id_kasir' => $kasir->getIdPengguna(),
            ]);
            $transaksiSebagian->perbaruiStatusBayar();

            $sesiLunas = $this->tutupSesiTerjualDemo($lotSiap[2], $operator, $pembeli, 9003, 3);
            $transaksiLunas = TransaksiPenjualan::dariSesi(
                $sesiLunas->fresh('lot'), $sesiLunas->tertinggi(), $tarif,
                (float) Konfigurasi::nilai('biaya_layanan', 2000),
            );
            $waktuTransaksiLunas = $sesiLunas->waktu_selesai_aktual;
            $transaksiLunas->waktu_transaksi = $waktuTransaksiLunas;
            $transaksiLunas->batas_bayar = $waktuTransaksiLunas->copy()->addHours(2);
            $transaksiLunas->save();
            Pembayaran::create([
                'id_transaksi' => $transaksiLunas->getIdTransaksi(),
                'metode' => MetodeBayar::TUNAI->value,
                'nominal' => $transaksiLunas->getTotalTagihan(),
                'waktu_bayar' => $waktuTransaksiLunas->copy()->addMinute(),
                'id_kasir' => $kasir->getIdPengguna(),
            ]);
            $transaksiLunas->perbaruiStatusBayar();
            $lotSiap[2]->pindahStatus(StatusLot::SELESAI);

            $sesiTidakTerjual = $this->bukaSesiDemo($lotSiap[3], $operator, 120);
            $this->tutupSesiDemo($sesiTidakTerjual);
            $lotSiap[3]->pindahStatus(StatusLot::TIDAK_TERJUAL);
            $this->buatPantauanDemo($pembeli, $lotSiap[3], 9004);
        });
    }

    private function bukaSesiDemo(LotLelang $lot, Pengguna $operator, int $durasi): SesiLelang
    {
        $kenaikan = (new LelangService)->usulanKenaikan($lot);
        $sesi = SesiLelang::buka($lot, $operator, $durasi, ModeLelang::NAIK, $kenaikan, null, null, null, null, 5, 15);
        $lot->pindahStatus(StatusLot::DILELANG);

        return $sesi;
    }

    private function tutupSesiTerjualDemo(LotLelang $lot, Pengguna $operator, Pengguna $pembeli, int $urutan, int $menit): SesiLelang
    {
        $sesi = $this->bukaSesiDemo($lot, $operator, 120);
        $harga = (float) $lot->getHargaPembukaan() + (new LelangService)->usulanKenaikan($lot);
        $waktu = now()->subMinutes($menit);
        if (! $waktu->isToday()) {
            $waktu = now();
        }
        $this->buatPantauanDemo($pembeli, $lot, $urutan);
        $penawaran = Penawaran::create([
            'id_sesi' => $sesi->getIdSesi(),
            'id_pembeli' => $pembeli->getIdPengguna(),
            'harga_per_kg' => (string) $harga,
            'nominal' => (string) round($harga * (float) $lot->getBeratAktual()),
            'waktu_penawaran' => $waktu,
            'status' => StatusPenawaran::TERTINGGI->value,
            'kanal' => 'JARAK_JAUH',
            'kunci_idempotensi' => (string) \Illuminate\Support\Str::uuid(),
            'alamat_terminal' => null,
        ]);

        $sesi->harga_berjalan = (string) $harga;
        $sesi->id_penawaran_pemenang = $penawaran->getIdPenawaran();
        $sesi->status = StatusSesi::DITUTUP->value;
        $sesi->waktu_mulai = $waktu->copy()->subMinutes(2);
        $sesi->waktu_selesai_rencana = $waktu;
        $sesi->waktu_selesai_aktual = $waktu;
        $sesi->save();
        $lot->pindahStatus(StatusLot::TERJUAL);

        return $sesi;
    }

    private function tutupSesiDemo(SesiLelang $sesi): void
    {
        $waktu = now()->subMinutes(1);
        $sesi->status = StatusSesi::DITUTUP->value;
        $sesi->waktu_mulai = $waktu->copy()->subMinutes(2);
        $sesi->waktu_selesai_rencana = $waktu;
        $sesi->waktu_selesai_aktual = $waktu;
        $sesi->save();
    }

    private function buatPantauanDemo(Pengguna $pembeli, LotLelang $lot, int $urutan): void
    {
        DaftarPantauan::firstOrCreate(
            ['id_pembeli' => $pembeli->getIdPengguna(), 'id_lot' => $lot->getIdLot()],
            ['kode_pemesanan' => 'PSN-' . today()->format('ymd') . '-' . str_pad((string) $urutan, 4, '0', STR_PAD_LEFT)],
        );
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

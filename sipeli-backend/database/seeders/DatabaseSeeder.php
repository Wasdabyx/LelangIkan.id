<?php

namespace Database\Seeders;

use App\Models\GradeMutu;
use App\Models\JenisIkan;
use App\Models\Kapal;
use App\Models\Konfigurasi;
use App\Models\PemilikKapal;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Tpi;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Data awal LelangIkan.id: data master + data demo (DemoSeeder).
     * Jalankan (MENGHAPUS semua data lama): php artisan migrate:fresh --seed
     */
    public function run(): void
    {
        // 1. Peran A1 - A8 (SKPL Tabel II.2) -> id_peran otomatis 1 s.d. 8 sesuai urutan
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
        foreach ($daftarPeran as [$nama, $deskripsi]) {
            Peran::create(['nama_peran' => $nama, 'deskripsi' => $deskripsi]);
        }

        // 2. Unit TPI
        $tpi = Tpi::create([
            'nama_tpi'      => 'TPI Palabuhanratu',
            'alamat'        => 'Jl. Kidang Kencana, Palabuhanratu',
            'kabupaten'     => 'Sukabumi',
            'kapasitas_ton' => 50,
            'jam_operasi'   => '04.00 - 12.00 WIB',
        ]);

        // 3. Akun contoh (kata sandi semua akun: sipeli12345)
        $akun = [
            ['Andi Pendata',   'pendata',  1],
            ['Budi Timbang',   'timbang',  2],
            ['Citra Operator', 'operator', 3],
            ['Dedi Bakul',     'pembeli',  4],
            ['Eka Kasir',      'kasir',    5],
            ['Fajar Pimpinan', 'pimpinan', 6],
            ['Gita Admin',     'admin',    7],
        ];
        foreach ($akun as [$nama, $username, $idPeran]) {
            Pengguna::create([
                'nama_lengkap'  => $nama,
                'nama_pengguna' => $username,
                'surel'         => $username . '@sipeli.test',
                'kata_sandi'    => 'sipeli12345',   // di-hash otomatis oleh model
                'id_peran'      => $idPeran,
                'id_tpi'        => $idPeran === 7 ? null : $tpi->id_tpi,
                'status_aktif'  => true,
            ]);
        }

        // 4. Konfigurasi sistem
        $konfigurasi = [
            ['kode_tpi',              'PLR', 'Kode TPI untuk nomor lot [kode]-[YYMMDD]-[urut] (FR-08)'],
            ['id_tpi_aktif',          (string) $tpi->id_tpi, 'TPI yang dilayani server ini'],
            ['ambang_selisih_berat',  '15',  'Persen selisih berat aktual vs estimasi yang perlu verifikasi (FR-09)'],
            ['durasi_lelang_bawaan',  '120', 'Durasi sesi lelang bawaan dalam detik (UC-03)'],
        ];
        foreach ($konfigurasi as [$kunci, $nilai, $deskripsi]) {
            Konfigurasi::create(['kunci' => $kunci, 'nilai' => $nilai, 'deskripsi' => $deskripsi]);
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
            JenisIkan::create([
                'nama_jenis' => $nama, 'nama_ilmiah' => $ilmiah,
                'kategori' => $kategori, 'satuan' => 'KG', 'status' => true,
            ]);
        }

        // 6. Grade mutu
        GradeMutu::create(['kode_grade' => 'A', 'deskripsi' => 'Sangat segar, utuh, mata jernih', 'faktor_harga' => 1.0000]);
        GradeMutu::create(['kode_grade' => 'B', 'deskripsi' => 'Segar, sedikit cacat fisik',      'faktor_harga' => 0.8500]);
        GradeMutu::create(['kode_grade' => 'C', 'deskripsi' => 'Kurang segar / rusak sebagian',    'faktor_harga' => 0.6500]);

        // 7. Pemilik kapal
        $pemilik = [];
        foreach ([
            ['H. Sukarna',        '3202010101700001', '081234560001', 'Kp. Cisaat, Palabuhanratu',  '0011223344', 'BRI'],
            ['Dadang Hermawan',   '3202010202750002', '081234560002', 'Citepus, Palabuhanratu',     '1234567890', 'BJB'],
            ['Ujang Saepudin',    '3202010303800003', '081234560003', 'Cimaja, Cikakak',            '9876543210', 'BNI'],
            ['Hj. Euis Komariah', '3202010404780004', '081234560004', 'Gado Bangkong, Palabuhanratu', '5566778899', 'BRI'],
            ['Rahmat Hidayat',    '3202010505850005', '081234560005', 'Loji, Simpenan',             '2233445566', 'Mandiri'],
        ] as [$nama, $nik, $telp, $alamat, $rek, $bank]) {
            $pemilik[] = PemilikKapal::create([
                'nama' => $nama, 'nik' => $nik, 'no_telepon' => $telp,
                'alamat' => $alamat, 'no_rekening' => $rek, 'nama_bank' => $bank,
            ]);
        }

        // 8. Kapal (urutan ini dipakai DemoSeeder)
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
            Kapal::create([
                'nama_kapal' => $nama, 'nomor_registrasi' => $registrasi,
                'gt' => $gt, 'id_pemilik' => $pemilik[$iPemilik]->id_pemilik,
            ]);
        }

        // 9. Data demo: riwayat 30 hari + kedatangan hari ini
        $this->call(DemoSeeder::class);
    }
}

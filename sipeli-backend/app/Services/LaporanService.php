<?php

namespace App\Services;

use App\Enums\StatusTransaksi;
use App\Models\KedatanganKapal;
use App\Models\PembayaranNelayan;
use App\Models\SesiLelang;
use App\Models\TransaksiPenjualan;
use Illuminate\Support\Facades\DB;

/**
 * Kelas kendali LaporanService - UC-10 Pelaporan dan Analitik Harga Ikan (A6).
 *
 * FR-44 laporan volume, nilai, dan harga rata-rata per jenis ikan, grade, dan rentang tanggal
 * FR-45 tren harga harian dan perbandingan dengan periode sebelumnya
 * FR-46 rekap retribusi harian dan bulanan per TPI
 * FR-47 ekspor laporan ke CSV dan PDF
 *
 * Semua agregasi memakai kueri tersusun (group by) agar memenuhi NFR-11.
 */
class LaporanService
{
    private const STATUS_DIHITUNG = [
        StatusTransaksi::BELUM_DIBAYAR->value,
        StatusTransaksi::DIBAYAR_SEBAGIAN->value,
        StatusTransaksi::LUNAS->value,
    ];

    /** FR-44: volume, nilai, dan harga rata-rata per jenis ikan dan grade. */
    public function hargaDanVolume(int $idTpi, string $dari, string $sampai): array
    {
        $baris = $this->kueriTransaksi($idTpi, $dari, $sampai)
            ->join('lot_lelang as l', 'l.id_lot', '=', 'transaksi_penjualan.id_lot')
            ->join('tangkapan as t', 't.id_tangkapan', '=', 'l.id_tangkapan')
            ->join('jenis_ikan as j', 'j.id_jenis', '=', 't.id_jenis')
            ->join('grade_mutu as g', 'g.id_grade', '=', 't.id_grade')
            ->groupBy('j.id_jenis', 'j.nama_jenis', 'g.id_grade', 'g.kode_grade')
            ->orderBy('j.nama_jenis')
            ->orderBy('g.kode_grade')
            ->selectRaw('j.nama_jenis, g.kode_grade, COUNT(*) as jumlah_transaksi,
                SUM(transaksi_penjualan.berat_final) as volume_kg,
                SUM(transaksi_penjualan.subtotal) as nilai,
                AVG(transaksi_penjualan.harga_akhir_per_kg) as harga_rata_rata,
                MIN(transaksi_penjualan.harga_akhir_per_kg) as harga_terendah,
                MAX(transaksi_penjualan.harga_akhir_per_kg) as harga_tertinggi')
            ->get()
            ->map(fn ($r) => [
                'nama_jenis'        => $r->nama_jenis,
                'kode_grade'        => $r->kode_grade,
                'jumlah_transaksi'  => (int) $r->jumlah_transaksi,
                'volume_kg'         => round((float) $r->volume_kg, 2),
                'nilai'             => round((float) $r->nilai, 2),
                'harga_rata_rata'   => round((float) $r->harga_rata_rata, 2),
                'harga_terendah'    => round((float) $r->harga_terendah, 2),
                'harga_tertinggi'   => round((float) $r->harga_tertinggi, 2),
            ])->all();

        return [
            'periode' => ['dari' => $dari, 'sampai' => $sampai],
            'baris'   => $baris,
            'total'   => [
                'volume_kg' => round(array_sum(array_column($baris, 'volume_kg')), 2),
                'nilai'     => round(array_sum(array_column($baris, 'nilai')), 2),
            ],
        ];
    }

    /** FR-45: tren harga harian dibandingkan periode sebelumnya dengan panjang yang sama. */
    public function trenHarga(int $idTpi, string $dari, string $sampai): array
    {
        $awal = \Illuminate\Support\Carbon::parse($dari);
        $akhir = \Illuminate\Support\Carbon::parse($sampai);
        $panjang = $awal->diffInDays($akhir) + 1;
        $dariSebelum = $awal->copy()->subDays($panjang)->toDateString();
        $sampaiSebelum = $awal->copy()->subDay()->toDateString();

        $harian = fn (string $a, string $b) => $this->kueriTransaksi($idTpi, $a, $b)
            ->groupByRaw('DATE(transaksi_penjualan.waktu_transaksi)')
            ->orderByRaw('DATE(transaksi_penjualan.waktu_transaksi)')
            ->selectRaw('DATE(transaksi_penjualan.waktu_transaksi) as tanggal, COUNT(*) as jumlah_transaksi,
                SUM(berat_final) as volume_kg, SUM(subtotal) as nilai,
                AVG(harga_akhir_per_kg) as harga_rata_rata')
            ->get()
            ->map(fn ($r) => [
                'tanggal'          => $r->tanggal,
                'jumlah_transaksi' => (int) $r->jumlah_transaksi,
                'volume_kg'        => round((float) $r->volume_kg, 2),
                'nilai'            => round((float) $r->nilai, 2),
                'harga_rata_rata'  => round((float) $r->harga_rata_rata, 2),
            ])->all();

        $sekarang = $harian($dari, $sampai);
        $sebelumnya = $harian($dariSebelum, $sampaiSebelum);

        $rata = fn (array $rows) => $rows === [] ? 0.0 : round(array_sum(array_column($rows, 'harga_rata_rata')) / count($rows), 2);
        $rataSekarang = $rata($sekarang);
        $rataSebelum = $rata($sebelumnya);
        $perubahan = $rataSebelum > 0 ? round(($rataSekarang - $rataSebelum) / $rataSebelum * 100, 2) : null;

        return [
            'periode'             => ['dari' => $dari, 'sampai' => $sampai],
            'harian'              => $sekarang,
            'periode_pembanding'  => ['dari' => $dariSebelum, 'sampai' => $sampaiSebelum],
            'pembanding'          => $sebelumnya,
            'harga_rata_rata'     => $rataSekarang,
            'harga_rata_rata_pembanding' => $rataSebelum,
            'perubahan_persen'    => $perubahan,
            'arah'                => $perubahan === null ? 'TIDAK_ADA_DATA' : ($perubahan > 0 ? 'NAIK' : ($perubahan < 0 ? 'TURUN' : 'STABIL')),
        ];
    }

    /** FR-46: rekap retribusi harian dan bulanan per TPI. */
    public function rekapRetribusi(int $idTpi, string $dari, string $sampai): array
    {
        $harian = $this->kueriTransaksi($idTpi, $dari, $sampai)
            ->groupByRaw('DATE(transaksi_penjualan.waktu_transaksi)')
            ->orderByRaw('DATE(transaksi_penjualan.waktu_transaksi)')
            ->selectRaw('DATE(transaksi_penjualan.waktu_transaksi) as tanggal, COUNT(*) as jumlah_transaksi,
                SUM(subtotal) as nilai_transaksi, SUM(nilai_retribusi) as retribusi')
            ->get()
            ->map(fn ($r) => [
                'tanggal'          => $r->tanggal,
                'jumlah_transaksi' => (int) $r->jumlah_transaksi,
                'nilai_transaksi'  => round((float) $r->nilai_transaksi, 2),
                'retribusi'        => round((float) $r->retribusi, 2),
            ])->all();

        $bulanan = $this->kueriTransaksi($idTpi, $dari, $sampai)
            ->groupByRaw("DATE_FORMAT(transaksi_penjualan.waktu_transaksi, '%Y-%m')")
            ->orderByRaw("DATE_FORMAT(transaksi_penjualan.waktu_transaksi, '%Y-%m')")
            ->selectRaw("DATE_FORMAT(transaksi_penjualan.waktu_transaksi, '%Y-%m') as bulan,
                COUNT(*) as jumlah_transaksi, SUM(subtotal) as nilai_transaksi, SUM(nilai_retribusi) as retribusi")
            ->get()
            ->map(fn ($r) => [
                'bulan'            => $r->bulan,
                'jumlah_transaksi' => (int) $r->jumlah_transaksi,
                'nilai_transaksi'  => round((float) $r->nilai_transaksi, 2),
                'retribusi'        => round((float) $r->retribusi, 2),
            ])->all();

        return [
            'periode'         => ['dari' => $dari, 'sampai' => $sampai],
            'harian'          => $harian,
            'bulanan'         => $bulanan,
            'total_retribusi' => round((float) array_sum(array_column($harian, 'retribusi')), 2),
        ];
    }

    /** Kinerja lelang: jumlah sesi, lot terjual/tidak terjual, tingkat keberhasilan, durasi. */
    public function kinerjaLelang(int $idTpi, string $dari, string $sampai): array
    {
        $sesi = SesiLelang::query()
            ->whereHas('lot.tangkapan.kedatangan', fn ($q) => $q->where('id_tpi', $idTpi))
            ->whereBetween(DB::raw('DATE(waktu_mulai)'), [$dari, $sampai]);

        $total = (clone $sesi)->count();
        $terjual = (clone $sesi)->whereHas('lot', fn ($q) => $q->where('status', 'TERJUAL'))->count();
        $diperpanjang = (clone $sesi)->where('jumlah_perpanjangan', '>', 0)->count();

        $settlement = PembayaranNelayan::query()
            ->whereHas('kedatangan', fn ($q) => $q->where('id_tpi', $idTpi)->whereBetween('tanggal', [$dari, $sampai]));

        return [
            'periode'          => ['dari' => $dari, 'sampai' => $sampai],
            'jumlah_sesi'      => $total,
            'sesi_terjual'     => $terjual,
            'sesi_tidak_terjual' => max(0, $total - $terjual),
            'tingkat_keberhasilan_persen' => $total > 0 ? round($terjual / $total * 100, 1) : 0.0,
            'sesi_diperpanjang' => $diperpanjang,
            'kedatangan_disettlement' => (clone $settlement)->count(),
            'kedatangan_lunas' => (clone $settlement)->where('status', 'LUNAS')->count(),
        ];
    }

    /** Ringkasan seluruh jenis laporan untuk satu permintaan. */
    public function semua(int $idTpi, string $dari, string $sampai): array
    {
        return [
            'harga_dan_volume' => $this->hargaDanVolume($idTpi, $dari, $sampai),
            'tren_harga'       => $this->trenHarga($idTpi, $dari, $sampai),
            'retribusi'        => $this->rekapRetribusi($idTpi, $dari, $sampai),
            'kinerja_lelang'   => $this->kinerjaLelang($idTpi, $dari, $sampai),
        ];
    }

    /**
     * FR-47: bentuk laporan sebagai baris CSV.
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, string|float>>}
     */
    public function sebagaiCsv(string $jenis, array $laporan): array
    {
        return match ($jenis) {
            'retribusi' => [
                ['tanggal', 'jumlah_transaksi', 'nilai_transaksi', 'retribusi'],
                array_map(fn ($r) => [$r['tanggal'], $r['jumlah_transaksi'], $r['nilai_transaksi'], $r['retribusi']],
                    $laporan['retribusi']['harian']),
            ],
            'kinerja' => [
                ['indikator', 'nilai'],
                [
                    ['jumlah_sesi', $laporan['kinerja_lelang']['jumlah_sesi']],
                    ['sesi_terjual', $laporan['kinerja_lelang']['sesi_terjual']],
                    ['sesi_tidak_terjual', $laporan['kinerja_lelang']['sesi_tidak_terjual']],
                    ['tingkat_keberhasilan_persen', $laporan['kinerja_lelang']['tingkat_keberhasilan_persen']],
                    ['sesi_diperpanjang', $laporan['kinerja_lelang']['sesi_diperpanjang']],
                    ['kedatangan_lunas', $laporan['kinerja_lelang']['kedatangan_lunas']],
                ],
            ],
            'tren' => [
                ['tanggal', 'jumlah_transaksi', 'volume_kg', 'nilai', 'harga_rata_rata'],
                array_map(fn ($r) => [$r['tanggal'], $r['jumlah_transaksi'], $r['volume_kg'], $r['nilai'], $r['harga_rata_rata']],
                    $laporan['tren_harga']['harian']),
            ],
            default => [
                ['nama_jenis', 'kode_grade', 'jumlah_transaksi', 'volume_kg', 'nilai', 'harga_rata_rata', 'harga_terendah', 'harga_tertinggi'],
                array_map(fn ($r) => [$r['nama_jenis'], $r['kode_grade'], $r['jumlah_transaksi'], $r['volume_kg'],
                    $r['nilai'], $r['harga_rata_rata'], $r['harga_terendah'], $r['harga_tertinggi']],
                    $laporan['harga_dan_volume']['baris']),
            ],
        };
    }

    // ---------------- internal ----------------

    private function kueriTransaksi(int $idTpi, string $dari, string $sampai)
    {
        return TransaksiPenjualan::query()
            ->whereIn('transaksi_penjualan.status', self::STATUS_DIHITUNG)
            ->whereBetween(DB::raw('DATE(transaksi_penjualan.waktu_transaksi)'), [$dari, $sampai])
            ->whereExists(function ($q) use ($idTpi) {
                $q->select(DB::raw(1))
                    ->from('lot_lelang as ll')
                    ->join('tangkapan as tk', 'tk.id_tangkapan', '=', 'll.id_tangkapan')
                    ->join('kedatangan_kapal as kd', 'kd.id_kedatangan', '=', 'tk.id_kedatangan')
                    ->whereColumn('ll.id_lot', 'transaksi_penjualan.id_lot')
                    ->where('kd.id_tpi', $idTpi);
            });
    }
}

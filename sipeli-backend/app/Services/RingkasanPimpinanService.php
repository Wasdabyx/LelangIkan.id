<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;

class RingkasanPimpinanService
{
    public function ringkasan(int $idTpi, string $tanggal): array
    {
        $sesiAktif = $this->sesiTpi($idTpi)
            ->where('sesi.status', 'AKTIF')
            ->where('sesi.waktu_selesai_rencana', '>', now())
            ->whereDate('sesi.waktu_mulai', $tanggal)
            ->count('sesi.id_sesi');

        $sesiDitutup = $this->sesiTpi($idTpi)
            ->leftJoin('transaksi_penjualan as transaksi', 'transaksi.id_sesi', '=', 'sesi.id_sesi')
            ->where('sesi.status', 'DITUTUP')
            ->whereDate('sesi.waktu_selesai_aktual', $tanggal);

        $terjual = (clone $sesiDitutup)
            ->whereNotNull('transaksi.id_transaksi')
            ->where('transaksi.status', '!=', 'DIBATALKAN')
            ->count('sesi.id_sesi');
        $tidakTerjual = (clone $sesiDitutup)
            ->where(function (Builder $query) {
                $query->whereNull('transaksi.id_transaksi')->orWhere('transaksi.status', 'DIBATALKAN');
            })
            ->count('sesi.id_sesi');

        $transaksiHariIni = $this->transaksiTpi($idTpi)
            ->whereDate('transaksi.waktu_transaksi', $tanggal)
            ->where('transaksi.status', '!=', 'DIBATALKAN');
        $nilaiPenjualan = (clone $transaksiHariIni)->sum('transaksi.subtotal');

        $totalPembayaran = DB::table('pembayaran as pembayaran')
            ->join('transaksi_penjualan as transaksi', 'transaksi.id_transaksi', '=', 'pembayaran.id_transaksi')
            ->join('lot_lelang as lot', 'lot.id_lot', '=', 'transaksi.id_lot')
            ->join('tangkapan as tangkapan', 'tangkapan.id_tangkapan', '=', 'lot.id_tangkapan')
            ->join('kedatangan_kapal as kedatangan', 'kedatangan.id_kedatangan', '=', 'tangkapan.id_kedatangan')
            ->where('kedatangan.id_tpi', $idTpi)
            ->whereDate('pembayaran.waktu_bayar', $tanggal)
            ->sum('pembayaran.nominal');

        $pembayaranPerTransaksi = DB::table('pembayaran')
            ->select('id_transaksi')
            ->selectRaw('SUM(nominal) as total_dibayar')
            ->groupBy('id_transaksi');

        $tagihan = $this->transaksiTpi($idTpi)
            ->leftJoinSub($pembayaranPerTransaksi, 'pembayaran_total', function ($join) {
                $join->on('pembayaran_total.id_transaksi', '=', 'transaksi.id_transaksi');
            })
            ->whereDate('transaksi.waktu_transaksi', $tanggal)
            ->whereIn('transaksi.status', ['BELUM_DIBAYAR', 'DIBAYAR_SEBAGIAN'])
            ->selectRaw('COUNT(*) as jumlah')
            ->selectRaw('COALESCE(SUM(CASE WHEN transaksi.total_tagihan > COALESCE(pembayaran_total.total_dibayar, 0) THEN transaksi.total_tagihan - COALESCE(pembayaran_total.total_dibayar, 0) ELSE 0 END), 0) as sisa')
            ->first();

        return [
            'tanggal' => $tanggal,
            'sesi' => [
                'aktif' => $sesiAktif,
                'ditutup' => $terjual + $tidakTerjual,
            ],
            'lot' => [
                'terjual' => $terjual,
                'tidak_terjual' => $tidakTerjual,
            ],
            'penjualan' => [
                'subtotal' => round((float) $nilaiPenjualan, 2),
            ],
            'tagihan' => [
                'belum_lunas' => (int) $tagihan->jumlah,
                'sisa' => round((float) $tagihan->sisa, 2),
            ],
            'pembayaran' => [
                'diterima' => round((float) $totalPembayaran, 2),
            ],
        ];
    }

    private function sesiTpi(int $idTpi): Builder
    {
        return DB::table('sesi_lelang as sesi')
            ->join('lot_lelang as lot', 'lot.id_lot', '=', 'sesi.id_lot')
            ->join('tangkapan as tangkapan', 'tangkapan.id_tangkapan', '=', 'lot.id_tangkapan')
            ->join('kedatangan_kapal as kedatangan', 'kedatangan.id_kedatangan', '=', 'tangkapan.id_kedatangan')
            ->where('kedatangan.id_tpi', $idTpi);
    }

    private function transaksiTpi(int $idTpi): Builder
    {
        return DB::table('transaksi_penjualan as transaksi')
            ->join('lot_lelang as lot', 'lot.id_lot', '=', 'transaksi.id_lot')
            ->join('tangkapan as tangkapan', 'tangkapan.id_tangkapan', '=', 'lot.id_tangkapan')
            ->join('kedatangan_kapal as kedatangan', 'kedatangan.id_kedatangan', '=', 'tangkapan.id_kedatangan')
            ->where('kedatangan.id_tpi', $idTpi);
    }
}

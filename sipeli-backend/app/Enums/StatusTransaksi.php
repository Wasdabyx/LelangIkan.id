<?php

namespace App\Enums;

/** Status transaksi penjualan (kolom transaksi_penjualan.status). */
enum StatusTransaksi: string
{
    case BELUM_DIBAYAR = 'BELUM_DIBAYAR';
    case DIBAYAR_SEBAGIAN = 'DIBAYAR_SEBAGIAN';
    case LUNAS = 'LUNAS';
    case DIBATALKAN = 'DIBATALKAN';

    public static function nilai(): array
    {
        return array_column(self::cases(), 'value');
    }
}

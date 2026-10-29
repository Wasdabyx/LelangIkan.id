<?php

namespace App\Enums;

/**
 * Status lot lelang (kolom lot_lelang.status), sesuai diagram keadaan SKPL Gambar III.16.
 */
enum StatusLot: string
{
    case DRAFT_HARGA     = 'DRAFT_HARGA';
    case MENUNGGU_LELANG = 'MENUNGGU_LELANG';
    case DILELANG        = 'DILELANG';
    case DIPERPANJANG    = 'DIPERPANJANG';
    case TERJUAL         = 'TERJUAL';
    case TIDAK_TERJUAL   = 'TIDAK_TERJUAL';
    case DIBATALKAN      = 'DIBATALKAN';
    case SELESAI         = 'SELESAI';

    /** Daftar semua nilai, dipakai untuk validasi (Rule::in). */
    public static function nilai(): array
    {
        return array_column(self::cases(), 'value');
    }
}

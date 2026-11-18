<?php

namespace App\Enums;

/** Status penawaran (kolom penawaran.status). */
enum StatusPenawaran: string
{
    case TERTINGGI = 'TERTINGGI';
    case TERTINGGAL = 'TERTINGGAL';
    case DIBATALKAN = 'DIBATALKAN';

    public static function nilai(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace App\Enums;

/** Status sesi lelang (kolom sesi_lelang.status). */
enum StatusSesi: string
{
    case AKTIF = 'AKTIF';
    case DITUTUP = 'DITUTUP';
    case DIBATALKAN = 'DIBATALKAN';

    public static function nilai(): array
    {
        return array_column(self::cases(), 'value');
    }
}

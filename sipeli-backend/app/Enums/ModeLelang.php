<?php

namespace App\Enums;

/** Mode sesi lelang (kolom sesi_lelang.mode). Versi ini menjalankan lelang NAIK. */
enum ModeLelang: string
{
    case NAIK = 'NAIK';
    case TURUN = 'TURUN';

    public static function nilai(): array
    {
        return array_column(self::cases(), 'value');
    }
}

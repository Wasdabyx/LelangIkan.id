<?php

namespace App\Enums;

/** Kanal penawaran (kolom penawaran.kanal). */
enum KanalPenawaran: string
{
    case DI_TEMPAT = 'DI_TEMPAT';
    case JARAK_JAUH = 'JARAK_JAUH';

    public static function nilai(): array
    {
        return array_column(self::cases(), 'value');
    }
}

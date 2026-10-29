<?php

namespace App\Enums;

/**
 * Status kapal (kolom kapal.status).
 */
enum StatusKapal: string
{
    case AKTIF    = 'AKTIF';
    case NONAKTIF = 'NONAKTIF';

    /** Daftar semua nilai, dipakai untuk validasi (Rule::in). */
    public static function nilai(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace App\Enums;

/**
 * Status baris tangkapan (kolom tangkapan.status).
 */
enum StatusTangkapan: string
{
    case BELUM_DITIMBANG = 'BELUM_DITIMBANG';
    case DITIMBANG       = 'DITIMBANG';

    /** Daftar semua nilai, dipakai untuk validasi (Rule::in). */
    public static function nilai(): array
    {
        return array_column(self::cases(), 'value');
    }
}

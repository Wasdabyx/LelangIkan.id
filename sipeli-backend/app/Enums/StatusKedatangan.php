<?php

namespace App\Enums;

/**
 * Status kedatangan kapal (kolom kedatangan_kapal.status): DRAF -> TERDAFTAR -> DITIMBANG.
 */
enum StatusKedatangan: string
{
    case DRAF      = 'DRAF';
    case TERDAFTAR = 'TERDAFTAR';
    case DITIMBANG = 'DITIMBANG';

    /** Daftar semua nilai, dipakai untuk validasi (Rule::in). */
    public static function nilai(): array
    {
        return array_column(self::cases(), 'value');
    }
}

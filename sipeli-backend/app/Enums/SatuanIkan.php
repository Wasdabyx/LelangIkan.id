<?php

namespace App\Enums;

/**
 * Satuan jual jenis ikan (kolom jenis_ikan.satuan).
 */
enum SatuanIkan: string
{
    case KG   = 'KG';
    case EKOR = 'EKOR';

    /** Daftar semua nilai, dipakai untuk validasi (Rule::in). */
    public static function nilai(): array
    {
        return array_column(self::cases(), 'value');
    }
}

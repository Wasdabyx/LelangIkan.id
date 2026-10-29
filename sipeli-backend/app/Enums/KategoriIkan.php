<?php

namespace App\Enums;

/**
 * Kategori jenis ikan (kolom jenis_ikan.kategori).
 */
enum KategoriIkan: string
{
    case PELAGIS_BESAR = 'PELAGIS_BESAR';
    case PELAGIS_KECIL = 'PELAGIS_KECIL';
    case DEMERSAL      = 'DEMERSAL';
    case KRUSTASEA     = 'KRUSTASEA';
    case MOLUSKA       = 'MOLUSKA';
    case LAINNYA       = 'LAINNYA';

    /** Daftar semua nilai, dipakai untuk validasi (Rule::in). */
    public static function nilai(): array
    {
        return array_column(self::cases(), 'value');
    }
}

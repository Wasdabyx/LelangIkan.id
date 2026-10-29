<?php

namespace App\Enums;

/**
 * Peran pengguna (SKPL Tabel II.2, aktor A1 - A8).
 * Nilai angka sama dengan kolom peran.id_peran.
 */
enum KodePeran: int
{
    case PETUGAS_PENDATAAN = 1;
    case PETUGAS_TIMBANG   = 2;
    case OPERATOR_LELANG   = 3;
    case PEMBELI           = 4;
    case KASIR             = 5;
    case PIMPINAN          = 6;
    case ADMINISTRATOR     = 7;
    case NELAYAN           = 8;

    /** Nama peran, sama dengan kolom peran.nama_peran. */
    public function label(): string
    {
        return match ($this) {
            self::PETUGAS_PENDATAAN => 'Petugas Pendataan',
            self::PETUGAS_TIMBANG   => 'Petugas Timbang',
            self::OPERATOR_LELANG   => 'Operator Lelang',
            self::PEMBELI           => 'Pembeli',
            self::KASIR             => 'Kasir TPI',
            self::PIMPINAN          => 'Pimpinan',
            self::ADMINISTRATOR     => 'Administrator Sistem',
            self::NELAYAN           => 'Nelayan',
        };
    }

    /** Daftar semua nilai, misalnya [1, 2, ..., 8]. */
    public static function nilai(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace App\Enums;

/** Metode pembayaran (kolom pembayaran.metode). SALDO menunggu fitur Deposit Saldo (UC-07). */
enum MetodeBayar: string
{
    case TUNAI = 'TUNAI';
    case TRANSFER = 'TRANSFER';
    case SALDO = 'SALDO';
    case QRIS = 'QRIS';

    public static function nilai(): array
    {
        return array_column(self::cases(), 'value');
    }
}

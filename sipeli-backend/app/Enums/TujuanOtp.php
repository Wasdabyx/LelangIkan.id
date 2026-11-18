<?php

namespace App\Enums;

/** Kegunaan kode OTP (Tugas 2). Nilai sama dengan kolom kode_otp.tujuan. */
enum TujuanOtp: string
{
    case AKTIVASI  = 'AKTIVASI';    // aktivasi akun pembeli setelah registrasi
    case PEMESANAN = 'PEMESANAN';   // konfirmasi pemesanan (ikut lelang) sebuah lot

    public function label(): string
    {
        return match ($this) {
            self::AKTIVASI  => 'aktivasi akun',
            self::PEMESANAN => 'pemesanan lot',
        };
    }
}

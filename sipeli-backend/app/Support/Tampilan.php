<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Pemformatan tampilan antarmuka web. Angka rupiah selalu ditulis utuh (bukan disingkat)
 * karena nominal pelelangan dipakai sebagai dasar pembayaran dan retribusi.
 */
final class Tampilan
{
    /** 1250000 -> "1.250.000" (tanpa "Rp", pemisah ribuan titik sesuai kaidah Indonesia). */
    public static function angka(mixed $nilai, int $desimal = 0): string
    {
        return number_format((float) $nilai, $desimal, ',', '.');
    }

    /** 1250000 -> "Rp1.250.000" */
    public static function rupiah(mixed $nilai): string
    {
        return 'Rp' . self::angka($nilai);
    }

    /** 1250.5 kg -> "1.250,5 kg" */
    public static function kg(mixed $nilai): string
    {
        return self::angka($nilai, 2) . ' kg';
    }

    /** "2026-11-21 09:12:03" -> "21 Nov 2026 09:12" */
    public static function waktu(?string $nilai): string
    {
        if (! $nilai) {
            return '-';
        }

        try {
            return Carbon::parse($nilai)->locale('id')->translatedFormat('d M Y H:i');
        } catch (\Throwable) {
            return $nilai;
        }
    }

    /** "2026-11-21" -> "21 November 2026" */
    public static function tanggal(?string $nilai): string
    {
        if (! $nilai) {
            return '-';
        }

        try {
            return Carbon::parse($nilai)->locale('id')->translatedFormat('d F Y');
        } catch (\Throwable) {
            return $nilai;
        }
    }

    /** 95 -> "01:35" (sisa waktu sesi lelang). */
    public static function detik(int $detik): string
    {
        $detik = max(0, $detik);

        return sprintf('%02d:%02d', intdiv($detik, 60), $detik % 60);
    }

    /** Warna lencana status (kelas Tailwind yang sudah ada di app.css). */
    public static function warnaStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'AKTIF', 'LUNAS', 'SELESAI', 'DIBAYAR', 'TERJUAL' => 'bg-emerald-100 text-emerald-800',
            'BELUM_DIBAYAR', 'MENUNGGU_PERSETUJUAN', 'DITUTUP' => 'bg-amber-100 text-amber-800',
            'DIBAYAR_SEBAGIAN', 'DIPERPANJANG'               => 'bg-sky-100 text-sky-800',
            'DIBATALKAN', 'TIDAK_TERJUAL', 'GAGAL', 'NONAKTIF' => 'bg-rose-100 text-rose-800',
            default                                          => 'bg-slate-100 text-slate-700',
        };
    }
}

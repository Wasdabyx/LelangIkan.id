<?php

namespace App\Services;

use App\Enums\TujuanOtp;
use App\Mail\KodeOtpMail;
use App\Models\KodeOtp;
use App\Models\LotLelang;
use App\Models\Pengguna;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Kelas kendali OtpService (Tugas 2): menerbitkan, mengirim, dan memverifikasi kode OTP 6 digit.
 * Aturan: berlaku 10 menit, kirim ulang paling cepat 60 detik, maksimal 5 kali salah,
 * hanya kode terbaru yang berlaku, dan setiap kode hanya dapat dipakai sekali.
 */
class OtpService
{
    public const BERLAKU_MENIT    = 10;
    public const JEDA_KIRIM_DETIK = 60;

    /**
     * Terbitkan kode baru lalu kirim ke surel pengguna.
     * @param array|null $infoLot ringkasan lot untuk isi surel pemesanan
     */
    public function kirim(Pengguna $pengguna, TujuanOtp $tujuan, ?LotLelang $lot = null, ?array $infoLot = null): array
    {
        $idLot = $lot?->getIdLot();

        // 1. Jeda kirim ulang (mencegah surel dikirim beruntun)
        $terakhir = KodeOtp::terakhirDibuat($pengguna->getIdPengguna(), $tujuan, $idLot);
        if ($terakhir) {
            $lewat = (int) $terakhir->getWaktuDibuat()->diffInSeconds(now(), true);
            if ($lewat < self::JEDA_KIRIM_DETIK) {
                $tunggu = self::JEDA_KIRIM_DETIK - $lewat;
                throw new HttpResponseException(response()->json([
                    'message'      => "Tunggu {$tunggu} detik sebelum meminta kode baru.",
                    'tunggu_detik' => $tunggu,
                ], 429));
            }
        }

        // 2. Batalkan kode lama yang belum terpakai, lalu terbitkan kode baru
        [$otp, $kode] = DB::transaction(function () use ($pengguna, $tujuan, $idLot) {
            KodeOtp::batalkanYangLama($pengguna->getIdPengguna(), $tujuan, $idLot);

            return KodeOtp::terbitkan($pengguna, $tujuan, $idLot, self::BERLAKU_MENIT);
        });

        // 3. Kirim surel. Bila gagal, kode dihapus agar pengguna bisa langsung meminta ulang.
        try {
            Mail::to($pengguna->getSurel(), $pengguna->getNamaLengkap())->send(new KodeOtpMail(
                $pengguna->getNamaLengkap(),
                $tujuan->value,
                $kode,
                $otp->getKedaluwarsaPada()->format('H:i'),
                self::BERLAKU_MENIT,
                $infoLot,
            ));
        } catch (\Throwable $e) {
            report($e);
            $otp->delete();
            throw new HttpResponseException(response()->json([
                'message' => 'Surel gagal dikirim. Periksa koneksi internet server dan pengaturan MAIL_ di file .env.',
            ], 503));
        }

        return [
            'surel_tersamar'    => $pengguna->getSurelTersamar(),
            'berlaku_detik'     => self::BERLAKU_MENIT * 60,
            'kirim_ulang_detik' => self::JEDA_KIRIM_DETIK,
        ];
    }

    /**
     * Periksa kode yang diketik pengguna. Berhasil = kode langsung ditandai terpakai.
     * Transaksi dibuat sendiri agar hitungan percobaan salah tetap tersimpan walau terjadi galat.
     */
    public function verifikasi(Pengguna $pengguna, TujuanOtp $tujuan, string $kode, ?int $idLot = null): void
    {
        $hasil = DB::transaction(function () use ($pengguna, $tujuan, $kode, $idLot) {
            $otp = KodeOtp::aktifTerakhir($pengguna->getIdPengguna(), $tujuan, $idLot, true);

            if (! $otp) {
                return 'Belum ada kode yang berlaku. Tekan Kirim Ulang untuk meminta kode baru.';
            }
            if ($otp->isKedaluwarsa()) {
                return 'Kode sudah kedaluwarsa. Tekan Kirim Ulang untuk meminta kode baru.';
            }
            if ($otp->sisaPercobaan() === 0) {
                return 'Kode salah ' . KodeOtp::MAKS_PERCOBAAN . ' kali. Tekan Kirim Ulang untuk meminta kode baru.';
            }
            if (! $otp->cocokkan($kode)) {
                $sisa = $otp->sisaPercobaan();

                return $sisa > 0
                    ? "Kode OTP salah. Sisa {$sisa} kali percobaan."
                    : 'Kode salah ' . KodeOtp::MAKS_PERCOBAAN . ' kali. Tekan Kirim Ulang untuk meminta kode baru.';
            }

            $otp->tandaiTerpakai();

            return null;
        });

        if ($hasil !== null) {
            throw ValidationException::withMessages(['kode_otp' => $hasil]);
        }
    }
}

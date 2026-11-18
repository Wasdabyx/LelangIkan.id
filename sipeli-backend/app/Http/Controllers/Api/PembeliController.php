<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PemesananService;
use App\Support\PolaInput;
use Illuminate\Http\Request;

/**
 * Tugas 2 - Pembeli (A4): daftar lot siap lelang dan pemesanan (ikut lelang) dengan OTP surel.
 */
class PembeliController extends Controller
{
    public function __construct(private PemesananService $layanan)
    {
    }

    /** GET /api/pembeli/lot */
    public function lot(Request $request)
    {
        return response()->json(['data' => $this->layanan->lotTersedia($request->user())]);
    }

    /** GET /api/pembeli/pemesanan */
    public function pemesanan(Request $request)
    {
        return response()->json(['data' => $this->layanan->milikPembeli($request->user())]);
    }

    /** POST /api/pembeli/pemesanan/otp  { id_lot } */
    public function kirimOtp(Request $request)
    {
        $data = $request->validate(['id_lot' => 'required|integer'], [
            'id_lot.required' => 'Lot wajib dipilih.',
            'id_lot.integer'  => 'Lot tidak valid.',
        ]);
        $info = $this->layanan->kirimOtp($request->user(), (int) $data['id_lot']);

        return response()->json(['pesan' => "Kode OTP telah dikirim ke {$info['surel_tersamar']}."] + $info);
    }

    /** POST /api/pembeli/pemesanan  { id_lot, kode_otp } */
    public function konfirmasi(Request $request)
    {
        $data = $request->validate([
            'id_lot'   => 'required|integer',
            'kode_otp' => ['required', 'string', 'regex:' . PolaInput::KODE_OTP],
        ], [
            'id_lot.required'   => 'Lot wajib dipilih.',
            'id_lot.integer'    => 'Lot tidak valid.',
            'kode_otp.required' => 'Kode OTP wajib diisi.',
            'kode_otp.regex'    => 'Kode OTP harus 6 digit angka.',
        ]);

        $pemesanan = $this->layanan->konfirmasi($request->user(), (int) $data['id_lot'], $data['kode_otp']);

        return response()->json([
            'pesan' => 'Pemesanan berhasil. Bukti pemesanan juga dikirim ke surel Anda.',
            'data'  => $this->layanan->ringkas($pemesanan),
        ], 201);
    }
}

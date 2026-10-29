<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AutentikasiService;
use Illuminate\Http\Request;

class AutentikasiController extends Controller
{
    public function __construct(private AutentikasiService $autentikasi)
    {
    }

    /** POST /api/masuk */
    public function masuk(Request $request)
    {
        $data = $request->validate([
            'nama_pengguna' => 'required|string|max:50',
            'kata_sandi'    => 'required|string|max:255',
            'perangkat'     => 'nullable|string|max:100',
        ], [
            'nama_pengguna.required' => 'Nama pengguna wajib diisi.',
            'kata_sandi.required'    => 'Kata sandi wajib diisi.',
        ]);

        $hasil = $this->autentikasi->masuk(
            $data['nama_pengguna'],
            $data['kata_sandi'],
            $data['perangkat'] ?? 'aplikasi-mobile'
        );

        return response()->json(['pesan' => 'Berhasil masuk.'] + $hasil);
    }

    /** GET /api/saya - data pengguna pemilik token */
    public function saya(Request $request)
    {
        return response()->json([
            'pengguna' => $this->autentikasi->dataPengguna($request->user()),
        ]);
    }

    /** POST /api/keluar */
    public function keluar(Request $request)
    {
        $this->autentikasi->keluar($request->user());

        return response()->json(['pesan' => 'Berhasil keluar.']);
    }
}

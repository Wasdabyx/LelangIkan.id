<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrasiPembeliRequest;
use App\Services\AutentikasiService;
use App\Support\PolaInput;
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
            'nama_pengguna' => ['required', 'string', 'max:50', 'regex:' . PolaInput::NAMA_PENGGUNA_MASUK],
            'kata_sandi'    => 'required|string|max:255',
            'perangkat'     => 'nullable|string|max:100',
        ], [
            'nama_pengguna.required' => 'Nama pengguna wajib diisi.',
            'nama_pengguna.regex'    => 'Format nama pengguna tidak valid.',
            'kata_sandi.required'    => 'Kata sandi wajib diisi.',
        ]);

        $hasil = $this->autentikasi->masuk(
            $data['nama_pengguna'],
            $data['kata_sandi'],
            $data['perangkat'] ?? 'aplikasi-mobile'
        );

        return response()->json(['pesan' => 'Berhasil masuk.'] + $hasil);
    }

    /** POST /api/registrasi - Registrasi Pembeli (Tugas Week 9) */
    public function daftar(RegistrasiPembeliRequest $request)
    {
        $pengguna = $this->autentikasi->daftarPembeli($request->validated());

        return response()->json([
            'pesan'    => 'Pendaftaran berhasil. Akun Anda belum aktif dan menunggu verifikasi identitas oleh petugas TPI.',
            'pengguna' => [
                'nama_lengkap'  => $pengguna->getNamaLengkap(),
                'nama_pengguna' => $pengguna->getNamaPengguna(),
                'surel'         => $pengguna->getSurel(),
                'no_hp'         => $pengguna->getNoHp(),
                'status_aktif'  => $pengguna->isStatusAktif(),
            ],
        ], 201);
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

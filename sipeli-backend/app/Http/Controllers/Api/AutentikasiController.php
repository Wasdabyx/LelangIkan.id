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
            $data['perangkat'] ?? 'aplikasi-mobile',
            $request->ip(),   // NFR-06: batas percobaan juga dihitung per alamat IP
        );

        return response()->json(['pesan' => 'Berhasil masuk.'] + $hasil);
    }

    /** POST /api/registrasi - Registrasi Pembeli (Week 9), lalu kode aktivasi dikirim ke surel (Tugas 2) */
    public function daftar(RegistrasiPembeliRequest $request)
    {
        [$pengguna, $aktivasi] = $this->autentikasi->daftarPembeli($request->validated());

        return response()->json([
            'pesan'    => $aktivasi['surel_terkirim']
                ? "Pendaftaran berhasil. Kode aktivasi 6 digit telah dikirim ke {$aktivasi['surel_tersamar']}."
                : 'Pendaftaran berhasil, tetapi surel aktivasi gagal dikirim. Tekan Kirim Ulang pada layar berikutnya.',
            'pengguna' => [
                'nama_lengkap'  => $pengguna->getNamaLengkap(),
                'nama_pengguna' => $pengguna->getNamaPengguna(),
                'surel'         => $pengguna->getSurelTersamar(),
                'no_hp'         => $pengguna->getNoHp(),
                'status_aktif'  => $pengguna->isStatusAktif(),
            ],
            'aktivasi' => $aktivasi,
        ], 201);
    }

    /** POST /api/aktivasi - masukkan kode 6 digit dari surel (Tugas 2) */
    public function aktivasi(Request $request)
    {
        $data = $request->validate($this->aturanKredensial() + [
            'kode_otp'  => ['required', 'string', 'regex:' . PolaInput::KODE_OTP],
            'perangkat' => 'nullable|string|max:100',
        ], $this->pesanKredensial() + [
            'kode_otp.required' => 'Kode aktivasi wajib diisi.',
            'kode_otp.regex'    => 'Kode aktivasi harus 6 digit angka.',
        ]);

        $hasil = $this->autentikasi->aktivasi(
            $data['nama_pengguna'], $data['kata_sandi'], $data['kode_otp'],
            $data['perangkat'] ?? 'aplikasi-mobile', $request->ip()
        );

        return response()->json(['pesan' => 'Akun berhasil diaktivasi. Selamat datang di LelangIkan.id.'] + $hasil);
    }

    /** POST /api/aktivasi/kirim-ulang (Tugas 2) */
    public function kirimUlangAktivasi(Request $request)
    {
        $data = $request->validate($this->aturanKredensial(), $this->pesanKredensial());
        $info = $this->autentikasi->kirimUlangAktivasi($data['nama_pengguna'], $data['kata_sandi'], $request->ip());

        return response()->json(['pesan' => "Kode aktivasi baru telah dikirim ke {$info['surel_tersamar']}."] + $info);
    }

    private function aturanKredensial(): array
    {
        return [
            'nama_pengguna' => ['required', 'string', 'max:50', 'regex:' . PolaInput::NAMA_PENGGUNA_MASUK],
            'kata_sandi'    => 'required|string|max:255',
        ];
    }

    private function pesanKredensial(): array
    {
        return [
            'nama_pengguna.required' => 'Nama pengguna wajib diisi.',
            'nama_pengguna.regex'    => 'Format nama pengguna tidak valid.',
            'kata_sandi.required'    => 'Kata sandi wajib diisi.',
        ];
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

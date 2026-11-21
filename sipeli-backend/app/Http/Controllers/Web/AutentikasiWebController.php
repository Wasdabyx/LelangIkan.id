<?php

namespace App\Http\Controllers\Web;

use App\Enums\KodePeran;
use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use App\Services\AuditService;
use App\Services\AutentikasiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Validation\ValidationException;

/**
 * Masuk/keluar antarmuka web memakai guard sesi Laravel, tetapi pemeriksaan kredensial,
 * pembatasan percobaan (5 kali / blokir 60 detik), dan pencatatan audit tetap memakai
 * AutentikasiService yang sama dengan aplikasi mobile agar aturan keamanan tidak bercabang.
 */
class AutentikasiWebController extends Controller
{
    /** Peran yang dilayani antarmuka web (SKPL I.B). */
    public const PERAN_WEB = [
        KodePeran::OPERATOR_LELANG,
        KodePeran::KASIR,
        KodePeran::PIMPINAN,
        KodePeran::ADMINISTRATOR,
    ];

    public function __construct(
        private AutentikasiService $autentikasi,
        private AuditService $audit,
    ) {
    }

    public function formulir()
    {
        if (Auth::check()) {
            return redirect()->route($this->beranda());
        }

        return view('web.auth.masuk');
    }

    public function masuk(Request $request)
    {
        $data = $request->validate([
            'nama_pengguna' => ['required', 'string', 'max:50'],
            'kata_sandi'    => ['required', 'string', 'max:255'],
        ], [
            'nama_pengguna.required' => 'Nama pengguna wajib diisi.',
            'kata_sandi.required'    => 'Kata sandi wajib diisi.',
        ]);

        // Validasi kredensial memakai jalur yang sama dengan API (termasuk batas percobaan).
        $hasil = $this->autentikasi->masuk($data['nama_pengguna'], $data['kata_sandi'], 'antarmuka-web', $request->ip());
        $pengguna = Pengguna::find($hasil['pengguna']['id_pengguna'] ?? 0);

        if (! $pengguna) {
            throw ValidationException::withMessages(['nama_pengguna' => 'Nama pengguna atau kata sandi salah.']);
        }

        if (! $pengguna->periksaKewenangan(self::PERAN_WEB)) {
            // Token yang baru terbit tidak boleh tertinggal pada perangkat web.
            PersonalAccessToken::findToken($hasil['token'])?->delete();

            throw ValidationException::withMessages([
                'nama_pengguna' => 'Peran ' . ($pengguna->peran?->nama_peran ?? '-')
                    . ' dilayani aplikasi mobile. Antarmuka web untuk operator lelang, kasir, pimpinan, dan administrator.',
            ]);
        }

        PersonalAccessToken::findToken($hasil['token'])?->delete();

        Auth::login($pengguna, false);
        $request->session()->regenerate();

        $this->audit->catat($pengguna, 'MASUK_WEB', 'pengguna', $pengguna->getIdPengguna(), null, [
            'perangkat' => 'antarmuka-web',
            'ip'        => $request->ip(),
        ]);

        return redirect()->intended(route($this->beranda()));
    }

    public function keluar(Request $request)
    {
        $pengguna = $request->user();
        if ($pengguna) {
            $this->audit->catat($pengguna, 'KELUAR_WEB', 'pengguna', $pengguna->getIdPengguna(), null, null);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('web.masuk');
    }

    /** Halaman pertama sesuai peran pemegang akun. */
    public function beranda(): string
    {
        $pengguna = Auth::user();

        return match (true) {
            $pengguna?->periksaKewenangan([KodePeran::OPERATOR_LELANG]) => 'operator.antrean',
            $pengguna?->periksaKewenangan([KodePeran::KASIR])           => 'kasir.tagihan',
            $pengguna?->periksaKewenangan([KodePeran::PIMPINAN])        => 'pimpinan.ringkasan',
            $pengguna?->periksaKewenangan([KodePeran::ADMINISTRATOR])   => 'admin.master',
            default                                                     => 'web.masuk',
        };
    }
}

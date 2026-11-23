<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\PortalNelayanService;
use Illuminate\Http\Request;

/**
 * UC-13 / FR-56..FR-60: portal nelayan versi web (baca-saja, tanpa masuk).
 * Akses memakai token pada tautan + verifikasi 4 digit yang disampaikan kasir secara terpisah.
 * Halaman ini hanya menampilkan rekap hasil lelang dan status pembayaran; tidak ada aksi tulis.
 */
class PortalNelayanWebController extends Controller
{
    public function __construct(private PortalNelayanService $layanan)
    {
    }

    /** GET /portal-nelayan : formulir token + kode */
    public function formulir(Request $request)
    {
        return view('web.portal-nelayan', [
            'rekap' => null,
            'token' => (string) $request->query('token', ''),
            'kode'  => '',
            'dari'  => '',
            'sampai' => '',
        ]);
    }

    /** POST /portal-nelayan : buka rekap */
    public function tampil(Request $request)
    {
        $data = $request->validate([
            'token'  => ['required', 'string'],
            'kode'   => ['required', 'string', 'size:4'],
            'dari'   => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ], [
            'token.required'  => 'Token akses wajib diisi (ada pada tautan dari kasir).',
            'kode.required'   => 'Kode verifikasi 4 digit wajib diisi.',
            'kode.size'       => 'Kode verifikasi terdiri atas 4 digit.',
            'sampai.after_or_equal' => 'Tanggal akhir tidak boleh lebih awal daripada tanggal awal.',
        ]);

        // Validasi token/kode/kedaluwarsa dijalankan PortalNelayanService (sama dengan jalur API),
        // sehingga pesan "tautan tidak dikenali / kedaluwarsa / kode tidak sesuai" juga sama.
        $tautan = $this->layanan->buka($data['token'], $data['kode']);
        $rekap = $this->layanan->rekap($tautan->pemilik, $data['dari'] ?? null, $data['sampai'] ?? null);

        return view('web.portal-nelayan', [
            'rekap'  => $rekap,
            'token'  => $data['token'],
            'kode'   => $data['kode'],
            'dari'   => $data['dari'] ?? $rekap['periode']['dari'],
            'sampai' => $data['sampai'] ?? $rekap['periode']['sampai'],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PembayaranNelayan;
use App\Models\PemilikKapal;
use App\Models\TautanAksesNelayan;
use App\Services\PortalNelayanService;
use App\Services\SettlementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * UC-13: portal nelayan baca-saja (A8) dan penerbitan tautan akses oleh kasir (A5).
 * Akses portal memakai token khusus (?token=) + verifikasi 4 digit, bukan token Sanctum (FR-59).
 */
class PortalNelayanController extends Controller
{
    public function __construct(private PortalNelayanService $layanan, private SettlementService $settlement)
    {
    }

    /** POST /api/nelayan/tautan: kasir menerbitkan tautan akses (FR-59). */
    public function terbitkanTautan(Request $request)
    {
        $data = $request->validate([
            'id_pemilik' => ['required', 'integer', 'exists:pemilik_kapal,id_pemilik'],
            'umur_jam'   => ['nullable', 'integer', 'min:1', 'max:72'],
        ]);

        $pemilik = PemilikKapal::findOrFail($data['id_pemilik']);
        $hasil = $this->layanan->terbitkanTautan($pemilik, $request->user(), $data['umur_jam'] ?? null);

        return response()->json([
            'pesan' => 'Tautan akses portal nelayan diterbitkan. Sampaikan kode verifikasi secara terpisah kepada pemilik kapal.',
            'data'  => $hasil,
        ], 201);
    }

    /**
     * GET /api/nelayan/rekap?token=&kode=&dari=&sampai=
     * FR-56, FR-57, FR-58: rekap hasil lelang dan status pembayaran.
     */
    public function rekap(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'kode'  => ['required', 'string', 'size:4'],
            'dari'  => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ]);

        $tautan = $this->layanan->buka($data['token'], $data['kode']);
        $hasil = $this->layanan->rekap($tautan->pemilik, $data['dari'] ?? null, $data['sampai'] ?? null);

        // FR-59: tautan hanya sekali pakai; panggilan berikutnya memakai `sesi` ini.
        return response()->json([
            'data'            => $hasil,
            'sesi'            => $tautan->sesi_token,
            'sesi_kedaluwarsa' => $tautan->sesi_kedaluwarsa?->format('Y-m-d H:i'),
        ]);
    }

    /** GET /api/nelayan/bukti/{id}.pdf?token=&kode= : unduhan bukti pembayaran (FR-60). */
    public function buktiPdf(Request $request, int $id)
    {
        // FR-59: utamakan sesi portal (tautan sudah tidak bisa dipakai dua kali).
        $sesi = trim((string) $request->query('sesi', ''));
        $tautan = $sesi !== ''
            ? $this->layanan->bukaSesi($sesi)
            : $this->layanan->buka((string) $request->query('token'), (string) $request->query('kode'));

        $pembayaran = PembayaranNelayan::findOrFail($id);

        $kedatangan = $pembayaran->kedatangan;
        $milikPemilik = $kedatangan?->kapal?->getIdPemilik() === $tautan->getIdPemilik();
        abort_unless($milikPemilik, 403, 'Bukti ini bukan milik kapal Anda.');

        $pdf = Pdf::loadView('pdf.bukti-nelayan', [
            'p'       => $this->settlement->rincianPembayaran($pembayaran),
            'dicetak' => now(),
        ])->setPaper('a5', 'portrait');

        return $pdf->download($pembayaran->getKodeBukti() . '.pdf');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PembayaranNelayan;
use App\Services\SettlementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * UC-08: perhitungan retribusi dan pembayaran kepada nelayan (Kasir TPI, A5).
 * Rute mengikuti SKPL Tabel IV.6: POST /api/settlement/{idKedatangan},
 * POST /api/pembayaran-nelayan/{id}/cairkan, dan POST /api/nelayan/tautan.
 */
class SettlementController extends Controller
{
    public function __construct(private SettlementService $layanan)
    {
    }

    /** Daftar kedatangan yang siap diselesaikan (punya transaksi lunas). */
    public function daftar(Request $request)
    {
        $data = $this->layanan->daftarKedatangan($request->user(), $request->query('tanggal'));

        return response()->json(['data' => $data]);
    }

    /** Hitung settlement (bruto, retribusi, netto) untuk satu kedatangan. */
    public function hitung(Request $request, int $idKedatangan)
    {
        $data = $request->validate([
            'potongan_lain'       => ['nullable', 'numeric', 'min:0'],
            'keterangan_potongan' => ['nullable', 'string', 'max:200'],
        ]);

        $pembayaran = $this->layanan->hitung(
            $request->user(),
            $idKedatangan,
            (float) ($data['potongan_lain'] ?? 0),
            $data['keterangan_potongan'] ?? null,
        );

        return response()->json([
            'pesan' => 'Perhitungan settlement tersimpan.',
            'data'  => $this->layanan->rincianPembayaran($pembayaran),
        ], 201);
    }

    /** Rincian satu settlement tanpa menghitung ulang. */
    public function rincian(int $id)
    {
        $pembayaran = PembayaranNelayan::findOrFail($id);

        return response()->json(['data' => $this->layanan->rincianPembayaran($pembayaran)]);
    }

    /** FR-40 dan FR-67: pencairan dana nelayan (tunai, transfer, kartu nelayan). */
    public function cairkan(Request $request, int $id)
    {
        $data = $request->validate([
            'cara_pencairan'  => ['required', Rule::in(SettlementService::CARA_CAIR)],
            'nominal'         => ['nullable', 'numeric', 'gt:0'],
            'nomor_referensi' => ['nullable', 'string', 'max:40'],
        ]);

        $pembayaran = $this->layanan->cairkan(
            $request->user(),
            $id,
            $data['cara_pencairan'],
            isset($data['nominal']) ? (float) $data['nominal'] : null,
            $data['nomor_referensi'] ?? null,
        );

        return response()->json([
            'pesan' => 'Pencairan dana nelayan tercatat. Bukti ' . $pembayaran->getKodeBukti() . ' siap dicetak.',
            'data'  => $this->layanan->rincianPembayaran($pembayaran),
        ], 201);
    }

    /** FR-60: unduhan bukti pembayaran nelayan dalam bentuk PDF. */
    public function buktiPdf(Request $request, int $id)
    {
        $pembayaran = PembayaranNelayan::findOrFail($id);
        $rincian = $this->layanan->rincianPembayaran($pembayaran);

        $pdf = Pdf::loadView('pdf.bukti-nelayan', ['p' => $rincian, 'dicetak' => now()])
            ->setPaper('a5', 'portrait');

        return $pdf->download($pembayaran->getKodeBukti() . '.pdf');
    }
}

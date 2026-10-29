<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SimpanLotRequest;
use App\Models\LotLelang;
use App\Services\PenimbanganService;

/**
 * UC-02 Penimbangan dan Pembuatan Lot Lelang
 */
class PenimbanganController extends Controller
{
    public function __construct(private PenimbanganService $layanan)
    {
    }

    /** GET /api/penimbangan/{kode}  (kode: KDT-00001 atau 1) */
    public function kedatangan(string $kode)
    {
        $id = $this->layanan->idDariKode($kode);
        $data = $id ? $this->layanan->ambilKedatangan($id) : null;

        if (! $data) {
            return response()->json([
                'message' => "Kedatangan {$kode} tidak ditemukan. Periksa kembali nomor kedatangan.",
            ], 404);
        }

        return response()->json(['data' => $data]);
    }

    /** POST /api/lot */
    public function simpan(SimpanLotRequest $request)
    {
        $lot = $this->layanan->buatLot($request->validated(), $request->user());

        return response()->json([
            'pesan' => $lot->status === 'DRAFT_HARGA'
                ? 'Lot dibuat sebagai DRAFT_HARGA (harga pembukaan belum diisi).'
                : 'Lot berhasil dibuat dan siap dilelang.',
            'data'  => $this->layanan->ringkasLot($lot),
        ], 201);
    }

    /** GET /api/lot/{kode}  (hasil pindai QR label) */
    public function lihatLot(string $kode)
    {
        $lot = LotLelang::where('kode_lot', strtoupper(trim($kode)))->first();
        if (! $lot) {
            return response()->json(['message' => "Lot {$kode} tidak ditemukan."], 404);
        }

        return response()->json(['data' => $this->layanan->ringkasLot($lot)]);
    }

    /** POST /api/lot/{id}/cetak  (alur A3: catat percobaan cetak) */
    public function catatCetak(int $id)
    {
        $lot = LotLelang::find($id);
        if (! $lot) {
            return response()->json(['message' => 'Lot tidak ditemukan.'], 404);
        }

        $lot = $this->layanan->catatCetak($lot);

        return response()->json([
            'pesan'        => 'Percobaan cetak label dicatat.',
            'jumlah_cetak' => $lot->jumlah_cetak,
        ]);
    }
}

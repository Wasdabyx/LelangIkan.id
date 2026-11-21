<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PapanLelangService;
use Illuminate\Http\Request;

/**
 * UC-09: papan lelang publik/TPI (FR-41, FR-42, FR-43).
 * GET /api/papan-lelang menjadi cadangan polling bila kanal WebSocket terputus (NFR-10).
 * Identitas penawar disamarkan pada semua keluaran (FR-43).
 */
class PapanLelangController extends Controller
{
    public function __construct(private PapanLelangService $layanan)
    {
    }

    public function index(Request $request)
    {
        $idTpi = $request->user()?->getIdTpi() ?? $this->layanan->tpiAktif() ?? 1;

        return response()->json(['data' => $this->layanan->keadaan((int) $idTpi)]);
    }
}

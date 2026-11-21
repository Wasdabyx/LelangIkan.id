<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RingkasanPimpinanService;
use Illuminate\Http\Request;

class PimpinanController extends Controller
{
    public function __construct(private RingkasanPimpinanService $ringkasan)
    {
    }

    public function ringkasan(Request $request)
    {
        $data = $request->validate([
            'tanggal' => ['sometimes', 'date_format:Y-m-d'],
        ]);
        $idTpi = $request->user()->getIdTpi();

        if ($idTpi === null) {
            return response()->json(['message' => 'Akun pimpinan belum terhubung ke TPI.'], 403);
        }

        return response()->json([
            'data' => $this->ringkasan->ringkasan($idTpi, $data['tanggal'] ?? today()->toDateString()),
        ]);
    }
}

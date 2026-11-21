<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Konfigurasi;
use App\Services\PapanLelangService;

/**
 * Papan tampilan lelang ruang pelelangan (FR-41, FR-43) dan portal baca-saja.
 * Halaman dapat dibuka tanpa masuk karena ditujukan untuk layar besar di ruang lelang.
 */
class PapanController extends Controller
{
    public function __construct(private PapanLelangService $papan)
    {
    }

    /** GET /papan-lelang */
    public function papan()
    {
        $idTpi = $this->papan->tpiAktif() ?? (int) Konfigurasi::nilai('id_tpi_aktif', 1);

        return view('web.papan', [
            'idTpi' => $idTpi,
            'awal'  => $this->papan->keadaan($idTpi),
        ]);
    }

    /** GET /papan-lelang/keadaan (JSON, dipanggil papan tiap 1 detik). */
    public function keadaan()
    {
        $idTpi = $this->papan->tpiAktif() ?? (int) Konfigurasi::nilai('id_tpi_aktif', 1);

        return response()->json($this->papan->keadaan($idTpi));
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatusKedatangan;
use App\Http\Controllers\Controller;
use App\Http\Requests\SimpanKedatanganRequest;
use App\Services\RegistrasiKedatanganService;
use Illuminate\Http\Request;

/**
 * UC-01 Registrasi Kedatangan Kapal dan Data Tangkapan
 */
class KedatanganController extends Controller
{
    public function __construct(private RegistrasiKedatanganService $layanan)
    {
    }

    /** GET /api/kapal?cari=... */
    public function cariKapal(Request $request)
    {
        $kataKunci = trim((string) $request->query('cari', ''));

        if (mb_strlen($kataKunci) < 3) {
            return response()->json([
                'message' => 'Ketik minimal 3 karakter untuk mencari kapal.',
                'errors'  => ['cari' => ['Ketik minimal 3 karakter untuk mencari kapal.']],
            ], 422);
        }

        return response()->json(['data' => $this->layanan->cariKapal($kataKunci)]);
    }

    /** GET /api/referensi/tangkapan */
    public function referensi()
    {
        return response()->json($this->layanan->ambilReferensi());
    }

    /** GET /api/kedatangan?tanggal=YYYY-MM-DD (bawaan: hari ini) */
    public function index(Request $request)
    {
        $tanggal = $request->query('tanggal', now()->toDateString());

        return response()->json([
            'tanggal' => $tanggal,
            'data'    => $this->layanan->daftarKedatangan($tanggal, $request->user()->id_tpi),
        ]);
    }

    /** POST /api/kedatangan */
    public function store(SimpanKedatanganRequest $request)
    {
        $kedatangan = $this->layanan->simpanKedatangan($request->validated(), $request->user());

        return response()->json([
            'pesan' => $kedatangan->status === 'DRAF'
                ? 'Kedatangan disimpan sebagai draf.'
                : 'Kedatangan berhasil disimpan.',
            'data'  => $this->layanan->ringkas($kedatangan),
        ], 201);
    }

    /** GET /api/kedatangan/{id}  (alur A2: buka draf untuk dilengkapi) */
    public function detail(Request $request, int $id)
    {
        $data = $this->layanan->ambilDetail($id, $request->user());
        if (! $data) {
            return response()->json(['message' => 'Kedatangan tidak ditemukan.'], 404);
        }

        return response()->json(['data' => $data]);
    }

    /** PUT /api/kedatangan/{id}  (alur A2: simpan perubahan draf, tetap DRAF atau langsung TERDAFTAR) */
    public function perbarui(SimpanKedatanganRequest $request, int $id)
    {
        $kedatangan = $this->layanan->perbaruiDraf($id, $request->validated(), $request->user());
        if (! $kedatangan) {
            return response()->json(['message' => 'Kedatangan tidak ditemukan.'], 404);
        }

        return response()->json([
            'pesan' => $kedatangan->getStatus() === StatusKedatangan::DRAF
                ? 'Perubahan draf disimpan.'
                : 'Kedatangan berhasil disimpan.',
            'data'  => $this->layanan->ringkas($kedatangan),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LelangService;
use Illuminate\Http\Request;

/** UC-03 dan UC-05 - Operator Lelang (A3). */
class OperatorLelangController extends Controller
{
    public function __construct(private LelangService $lelang)
    {
    }

    /** GET /api/operator/antrean */
    public function antrean(Request $request)
    {
        return response()->json(['data' => $this->lelang->antrean($this->lelang->idTpi($request->user()))]);
    }

    /** GET /api/operator/sesi  (aktif + hari ini) */
    public function daftarSesi(Request $request)
    {
        return response()->json(['data' => $this->lelang->daftarSesiOperator($request->user())]);
    }

    /** POST /api/operator/sesi  { id_lot, durasi_detik, kenaikan_minimum? } */
    public function buka(Request $request)
    {
        $data = $request->validate([
            'id_lot'           => 'required|integer',
            'durasi_detik'     => 'required|integer|between:' . LelangService::DURASI_MIN . ',' . LelangService::DURASI_MAKS,
            'mode' => 'nullable|in:NAIK,TURUN',
            'kenaikan_minimum' => 'nullable|integer|between:100,1000000',
            'harga_awal' => 'required_if:mode,TURUN|nullable|integer|min:100',
            'harga_batas_bawah' => 'required_if:mode,TURUN|nullable|integer|min:100',
            'penurunan_per_langkah' => 'required_if:mode,TURUN|nullable|integer|min:1',
            'interval_detik' => 'required_if:mode,TURUN|nullable|integer|between:1,600',
        ], [
            'id_lot.required'          => 'Lot wajib dipilih.',
            'durasi_detik.required'    => 'Durasi sesi wajib diisi.',
            'durasi_detik.integer'     => 'Durasi sesi harus berupa angka (detik).',
            'durasi_detik.between'     => 'Durasi sesi 30 sampai 600 detik.',
            'kenaikan_minimum.integer' => 'Kenaikan minimum harus berupa angka rupiah.',
            'kenaikan_minimum.between' => 'Kenaikan minimum Rp100 sampai Rp1.000.000 per kg.',
        ]);

        $sesi = $this->lelang->buka(
            $request->user(), (int) $data['id_lot'], (int) $data['durasi_detik'],
            isset($data['kenaikan_minimum']) ? (float) $data['kenaikan_minimum'] : null,
            $data['mode'] ?? 'NAIK', isset($data['harga_awal']) ? (float) $data['harga_awal'] : null,
            isset($data['harga_batas_bawah']) ? (float) $data['harga_batas_bawah'] : null,
            isset($data['penurunan_per_langkah']) ? (float) $data['penurunan_per_langkah'] : null,
            isset($data['interval_detik']) ? (int) $data['interval_detik'] : null,
        );

        return response()->json([
            'pesan' => 'Sesi lelang dibuka. Peserta dapat mulai menawar.',
            'data'  => $this->lelang->status($sesi->getIdSesi(), $request->user(), true),
        ], 201);
    }

    /** GET /api/operator/sesi/{id}  (dipanggil tiap 1 detik) */
    public function status(Request $request, int $id)
    {
        return response()->json(['data' => $this->lelang->status($id, $request->user(), true)]);
    }

    /** POST /api/operator/sesi/{id}/tutup  { alasan }  (alur A3: tutup lebih awal) */
    public function tutup(Request $request, int $id)
    {
        $data = $request->validate([
            'alasan' => 'required|string|min:5|max:255',
        ], [
            'alasan.required' => 'Alasan penutupan wajib diisi.',
            'alasan.min'      => 'Alasan penutupan minimal 5 karakter.',
            'alasan.max'      => 'Alasan penutupan maksimal 255 karakter.',
        ]);
        $this->lelang->tutupManual($request->user(), $id, trim($data['alasan']));

        return response()->json([
            'pesan' => 'Sesi lelang ditutup.',
            'data'  => $this->lelang->status($id, $request->user(), true),
        ]);
    }
}

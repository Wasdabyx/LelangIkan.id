<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DepositService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepositController extends Controller
{
    public function __construct(private DepositService $deposit)
    {
    }

    public function cariPembeli(Request $request)
    {
        $data = $request->validate(['cari' => 'required|string|min:2|max:100']);

        return response()->json(['data' => $this->deposit->cariPembeli($request->user(), $data['cari'])]);
    }

    public function rincian(Request $request, int $id)
    {
        return response()->json(['data' => $this->deposit->rincian($request->user(), $id)]);
    }

    public function mutasi(Request $request, int $id)
    {
        $data = $request->validate([
            'jenis' => ['required', Rule::in(['SETOR', 'PENARIKAN', 'PENYESUAIAN'])],
            'nominal' => 'required|numeric|gt:0|max:999999999999',
            'metode' => ['nullable', Rule::in(['TUNAI', 'TRANSFER', 'QRIS'])],
            'arah' => ['nullable', Rule::in(['KREDIT', 'DEBIT'])],
            'nomor_referensi' => ['nullable', 'required_if:metode,TRANSFER', 'string', 'regex:/^[A-Za-z0-9-]{6,40}$/'],
            'keterangan' => 'nullable|string|max:200',
        ]);
        $mutasi = $this->deposit->buatMutasi(
            $request->user(), $id, $data['jenis'], (float) $data['nominal'], $data['metode'] ?? null,
            $data['arah'] ?? null, isset($data['nomor_referensi']) ? strtoupper($data['nomor_referensi']) : null,
            $data['keterangan'] ?? null,
        );

        return response()->json([
            'pesan' => $mutasi->status === 'MENUNGGU_PERSETUJUAN' ? 'Penyesuaian menunggu persetujuan pimpinan.' : 'Mutasi saldo berhasil dicatat.',
            'data' => $mutasi,
        ], 201);
    }

    public function menungguPersetujuan(Request $request)
    {
        return response()->json(['data' => $this->deposit->penyesuaianMenunggu($request->user())]);
    }

    public function putuskan(Request $request, int $id)
    {
        $data = $request->validate([
            'keputusan' => ['required', Rule::in(['SETUJU', 'TOLAK'])],
            'catatan' => 'nullable|string|max:180',
        ]);
        if ($data['keputusan'] === 'TOLAK' && mb_strlen(trim((string) ($data['catatan'] ?? ''))) < 5) {
            return response()->json(['message' => 'Alasan penolakan minimal 5 karakter.', 'errors' => ['catatan' => ['Alasan penolakan minimal 5 karakter.']]], 422);
        }
        $mutasi = $this->deposit->putuskanPenyesuaian(
            $request->user(), $id, $data['keputusan'] === 'SETUJU', $data['catatan'] ?? null,
        );

        return response()->json(['pesan' => 'Keputusan penyesuaian saldo tersimpan.', 'data' => $mutasi]);
    }
}

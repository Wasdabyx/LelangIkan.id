<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;

/** UC-12 Audit dan Penelusuran Aktivitas (Administrator A7, Pimpinan A6 boleh membaca). */
class AuditController extends Controller
{
    public function __construct(private AuditService $audit)
    {
    }

    /** GET /api/audit?id_pengguna=&entitas=&aksi=&dari=&sampai=&format=csv (FR-52, FR-53, FR-55) */
    public function index(Request $request)
    {
        $filter = $request->validate([
            'id_pengguna' => 'nullable|integer',
            'entitas'     => 'nullable|string|max:40',
            'aksi'        => 'nullable|string|max:40',
            'dari'        => 'nullable|date_format:Y-m-d',
            'sampai'      => 'nullable|date_format:Y-m-d',
            'format'      => 'nullable|in:csv',
            'batas'       => 'nullable|integer|between:1,500',
        ]);

        if (($filter['format'] ?? null) === 'csv') {
            $csv = $this->audit->eksporCsv($filter);

            return response($csv, 200, [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="jejak-audit-' . now()->format('Ymd-His') . '.csv"',
            ]);
        }

        return response()->json([
            'data'   => $this->audit->cari($filter, (int) ($filter['batas'] ?? 100)),
            'filter' => array_filter($filter, fn ($v) => $v !== null && $v !== ''),
        ]);
    }

    /**
     * FR-54: perubahan dan penghapusan catatan audit tidak dilayani sistem.
     * Endpoint ini sengaja ada agar percobaan pengubahan dijawab tegas dan tercatat.
     */
    public function tolakUbah(Request $request)
    {
        $this->audit->catat($request->user(), 'TOLAK_UBAH_AUDIT', 'audit_log', null,
            null, ['metode' => $request->method(), 'jalur' => $request->path()]);

        return response()->json([
            'message' => 'Catatan audit tidak dapat diubah atau dihapus oleh peran mana pun (FR-54).',
        ], 403);
    }
}

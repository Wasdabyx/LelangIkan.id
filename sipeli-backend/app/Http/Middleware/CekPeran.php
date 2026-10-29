<?php

namespace App\Http\Middleware;

use App\Enums\KodePeran;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kendali akses berbasis peran (SKPL BT-06, NFR-05).
 * Pemakaian di route: ->middleware('peran:1,2')  (id_peran yang diizinkan)
 * atau dengan nama: ->middleware('peran:PETUGAS_PENDATAAN,PETUGAS_TIMBANG')
 */
class CekPeran
{
    public function handle(Request $request, Closure $next, string ...$daftarPeran): Response
    {
        $pengguna = $request->user();
        $diizinkan = array_filter(array_map([$this, 'keKodePeran'], $daftarPeran));

        if (! $pengguna || ! $pengguna->periksaKewenangan($diizinkan)) {
            return response()->json([
                'message' => 'Anda tidak memiliki hak akses untuk fungsi ini.',
            ], 403);
        }

        return $next($request);
    }

    /** '2' atau 'PETUGAS_TIMBANG' -> KodePeran::PETUGAS_TIMBANG */
    private function keKodePeran(string $teks): ?KodePeran
    {
        $teks = trim($teks);
        if (ctype_digit($teks)) {
            return KodePeran::tryFrom((int) $teks);
        }
        foreach (KodePeran::cases() as $kode) {
            if ($kode->name === strtoupper($teks)) {
                return $kode;
            }
        }

        return null;
    }
}

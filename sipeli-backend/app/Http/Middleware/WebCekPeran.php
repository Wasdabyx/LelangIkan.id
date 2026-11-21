<?php

namespace App\Http\Middleware;

use App\Enums\KodePeran;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kendali akses berbasis peran untuk antarmuka web (SKPL I.B: antarmuka web untuk operator
 * lelang, kasir, pimpinan/dinas, dan administrator sistem).
 *
 * Berbeda dengan App\Http\Middleware\CekPeran yang membalas JSON 403 untuk API, middleware ini
 * menampilkan halaman 403 sehingga pengguna web mendapat penjelasan, bukan potongan JSON.
 *
 * Pemakaian di route: ->middleware('web.peran:5') atau ->middleware('web.peran:KASIR,PIMPINAN')
 */
class WebCekPeran
{
    public function handle(Request $request, Closure $next, string ...$daftarPeran): Response
    {
        $pengguna = $request->user();
        $diizinkan = array_filter(array_map([$this, 'keKodePeran'], $daftarPeran));

        if (! $pengguna || ! $pengguna->periksaKewenangan($diizinkan)) {
            abort(403, 'Peran akun Anda tidak berhak membuka halaman ini.');
        }

        return $next($request);
    }

    /** '5', 'KASIR', atau 'kasir' -> KodePeran::KASIR */
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

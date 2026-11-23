<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php', [
        'prefix' => 'api',
        'middleware' => ['api', 'auth:sanctum'],
    ])
    ->withMiddleware(function (Middleware $middleware) {
        // Permintaan API tanpa token tidak dialihkan ke halaman login (cukup balas 401).
        // Antarmuka web memakai halaman masuk tersendiri; papan lelang tetap dapat dibuka tanpa masuk.
        $middleware->redirectGuestsTo(fn ($request) => $request->is('api/*') ? null : '/masuk');

        // Di balik proksi HTTPS (mis. preview/penerapan), percayai header X-Forwarded-*
        // agar tautan aset dan redirect memakai skema https.
        $middleware->trustProxies(at: '*');

        // Alias middleware hak akses berbasis peran: ->middleware('peran:1,2')
        $middleware->alias([
            'peran' => \App\Http\Middleware\CekPeran::class,
            // Antarmuka web: 403 dalam bentuk halaman, bukan JSON
            'web.peran' => \App\Http\Middleware\WebCekPeran::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Semua error pada alamat /api/* dikirim dalam format JSON
        $exceptions->shouldRenderJsonWhen(
            fn ($request, $e) => $request->is('api/*')
        );

        // Token CSRF kedaluwarsa ("419 Page Expired"): penyebab tersering adalah tab lama
        // yang dibiarkan terbuka, sesi hilang (migrate:fresh / session:clear), atau masuk
        // dengan peran lain di browser yang sama sehingga sesi di-regenerate.
        // Pengguna web dialihkan ke halaman masuk dengan pesan, bukan halaman buntu.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, $request) {
            // Laravel mengubah TokenMismatchException menjadi HttpException 419 sebelum
            // callback render dipanggil, jadi status 419 yang diperiksa di sini.
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'pesan' => 'Sesi kedaluwarsa. Muat ulang halaman lalu masuk kembali.',
                    'kode'  => 'SESI_KEDALUWARSA',
                ], 419);
            }

            return redirect()->route('web.masuk')->withErrors([
                'nama_pengguna' => 'Sesi Anda berakhir (halaman dibiarkan terlalu lama, data di-reset, '
                    . 'atau Anda masuk dengan peran lain di browser ini tanpa keluar). Silakan masuk kembali.',
            ]);
        });
    })->create();

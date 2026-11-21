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
    })->create();

<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /* Hanya menyentuh permintaan yang benar-benar mengembalikan
           Inertia::render(); halaman Blade melewatinya tanpa berubah,
           jadi 170-an halaman lama tidak ikut terpengaruh. */
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,

            /* Ditaruh SESUDAH HandleInertiaRequests supaya ia melihat
               tanggapan yang sudah jadi — termasuk yang berasal dari
               halaman Blade, yang justru menjadi alasan keberadaannya. */
            \App\Http\Middleware\PastikanTanggapanInertia::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();

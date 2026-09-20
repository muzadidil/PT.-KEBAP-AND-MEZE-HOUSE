<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
        | Bahasa dipasang di grup `web`, bukan di daftar middleware panel.
        |
        | Alasannya: setiap ketukan tombol di halaman kasir adalah permintaan
        | Livewire ke /livewire/update, dan permintaan itu tidak melewati
        | middleware panel. Kalau bahasanya hanya dipasang di panel, halaman
        | awal tampil berbahasa Indonesia tapi nama menu yang tersalin ke
        | keranjang dan ke struk akan berbahasa Inggris. Di grup `web`,
        | keduanya memakai bahasa yang sama.
        */
        $middleware->web(append: [
            SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

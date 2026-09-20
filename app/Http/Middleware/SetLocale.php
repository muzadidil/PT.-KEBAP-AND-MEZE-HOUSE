<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bahasa mengikuti pilihan pengguna yang sedang masuk. Bawaannya Inggris —
 * pemiliknya berbahasa Inggris, sedangkan kasir yang lebih nyaman berbahasa
 * Indonesia cukup mengubahnya sekali dan pilihannya tersimpan di akun.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Auth::user()?->locale ?? config('app.locale');

        if (in_array($locale, config('app.supported_locales'), true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}

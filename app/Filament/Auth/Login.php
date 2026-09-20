<?php

namespace App\Filament\Auth;

use App\Support\Branding;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Halaman masuk berpanel dua: sisi kiri slideshow latar, sisi kanan kartu
 * masuk. Dipakai kedua panel (kasir dan admin).
 *
 * Logika autentikasinya sama sekali tidak disentuh — yang diganti hanya
 * tata letaknya lewat $layout. Dengan begitu pembaruan Filament tidak
 * pernah bentrok dengan tampilan buatan sendiri di sini.
 */
class Login extends BaseLogin
{
    protected static string $layout = 'components.layouts.auth';

    public function getHeading(): string|Htmlable|null
    {
        return Branding::businessName();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return Branding::tagline();
    }
}

<?php

namespace App\Filament\Auth;

use App\Models\User;
use App\Support\Branding;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Halaman masuk berpanel dua: sisi kiri slideshow latar, sisi kanan kartu
 * masuk. Dipakai kedua panel (kasir dan admin).
 *
 * Tata letaknya diganti lewat $layout. Dari logika autentikasinya hanya satu
 * hal yang diubah: panel mana yang boleh dimasuki; lihat
 * isUserAllowedToAccessPanel().
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

    /**
     * Akun diterima di halaman masuk mana pun, asal boleh masuk ke panelnya
     * sendiri; App\Http\Responses\LoginResponse lalu membawanya ke sana.
     *
     * Bawaan Filament menolak akun yang bukan milik panel ini dengan pesan
     * "kredensial tidak cocok". Sejak admin tidak lagi membuka panel kasir,
     * itu berarti admin yang mengetik alamat utama situs ditolak dengan kata
     * sandi yang benar, dan pesannya menyuruhnya memeriksa kata sandi.
     */
    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        if (! $user instanceof User) {
            return parent::isUserAllowedToAccessPanel($user);
        }

        $home = $user->role ? Filament::getPanel($user->role->panel()) : null;

        return $home !== null && $user->canAccessPanel($home);
    }
}

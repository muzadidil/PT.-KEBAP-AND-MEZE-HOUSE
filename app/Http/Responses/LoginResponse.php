<?php

namespace App\Http\Responses;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as Responsable;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Setelah masuk, tiap orang dibawa ke panelnya sendiri: kasir ke halaman
 * kasir, Super Admin dan Admin ke backoffice. Tidak peduli di halaman masuk
 * mana ia mengetik kata sandinya.
 */
class LoginResponse implements Responsable
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User || ! $user->role) {
            return redirect()->intended(Filament::getUrl());
        }

        // Alamat yang tadi hendak dibuka sebelum diminta masuk hanya dipakai
        // kalau ia di panel yang sama. Kalau tidak, orangnya akan mendarat
        // di halaman 403 tepat setelah berhasil masuk.
        if (Filament::getCurrentOrDefaultPanel()->getId() === $user->role->panel()) {
            return redirect()->intended($user->homeUrl());
        }

        // Lewat helper, bukan $request->session(): masuk terjadi lewat
        // permintaan Livewire, yang tidak selalu membawa penyimpan sesinya.
        session()->forget('url.intended');

        return redirect()->to($user->homeUrl());
    }
}

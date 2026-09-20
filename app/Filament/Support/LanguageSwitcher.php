<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Pemilih bahasa di menu pengguna, dipakai kedua panel.
 *
 * Bahasa yang sedang aktif tidak ikut ditampilkan, jadi yang terlihat hanya
 * pilihan yang benar-benar mengubah sesuatu. Pilihannya disimpan di akun,
 * bukan di sesi, supaya tidak perlu diatur ulang tiap kali masuk.
 */
class LanguageSwitcher
{
    /** @return array<int, Action> */
    public static function menuItems(): array
    {
        $items = [];

        foreach (config('app.locale_names') as $locale => $name) {
            $items[] = Action::make("locale_{$locale}")
                ->label($name)
                ->icon(Heroicon::OutlinedLanguage)
                ->visible(fn () => app()->getLocale() !== $locale)
                ->action(function () use ($locale) {
                    $user = Auth::user();

                    if ($user) {
                        $user->forceFill(['locale' => $locale])->save();
                    }

                    // Muat ulang halaman supaya seluruh label ikut berganti,
                    // bukan hanya bagian yang dirender ulang Livewire.
                    return redirect(request()->header('Referer') ?? url()->current());
                });
        }

        return $items;
    }
}

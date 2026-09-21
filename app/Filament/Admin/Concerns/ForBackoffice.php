<?php

namespace App\Filament\Admin\Concerns;

/**
 * Bagian yang dipakai kedua peran backoffice: Super Admin dan Admin.
 *
 * Untuk halaman yang bukan pengaturan maupun laporan — Progres Rapat,
 * misalnya, yang tugasnya dibagi ke keduanya. Kasir tetap tidak bisa masuk
 * ke panel admin sama sekali.
 */
trait ForBackoffice
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->isSuperAdmin() === true || $user?->isAdmin() === true;
    }
}

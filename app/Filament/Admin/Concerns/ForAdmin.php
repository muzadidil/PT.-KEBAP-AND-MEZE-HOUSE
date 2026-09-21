<?php

namespace App\Filament\Admin\Concerns;

/**
 * Bagian laporan: pembukuan, pengeluaran, dan seluruh laporan.
 *
 * Hanya Admin. Super Admin sengaja tidak ikut: perannya mengatur, bukan
 * membaca angka. Lihat ForSuperAdmin untuk alasan memakai canAccess().
 */
trait ForAdmin
{
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }
}

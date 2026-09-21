<?php

namespace App\Filament\Admin\Concerns;

/**
 * Bagian pengaturan: pengguna, menu, pemasok, data induk, tampilan.
 *
 * Hanya Super Admin. Dipasang di resource maupun halaman; Filament memakai
 * canAccess() untuk menyembunyikan menunya sekaligus menolak alamatnya,
 * jadi tidak ada halaman yang tersembunyi dari menu tapi masih bisa dibuka
 * lewat alamat langsung.
 */
trait ForSuperAdmin
{
    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() === true;
    }
}

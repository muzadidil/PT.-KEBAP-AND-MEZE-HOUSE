<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Tiga peran, tiga pekerjaan yang tidak saling tumpang tindih:
 *
 *   Super Admin  mengatur: pengguna, menu, pemasok, data induk, tampilan
 *   Admin        memegang laporan: pembukuan, pengeluaran, seluruh laporan
 *   Kasir        memegang penjualan: halaman kasir, rekap harian
 *
 * Super Admin dan Admin sama-sama masuk ke panel admin, tapi menu yang
 * terlihat berbeda; lihat App\Filament\Admin\Concerns.
 */
enum UserRole: string implements HasLabel
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Cashier = 'cashier';

    public function getLabel(): string
    {
        return __("enums.role.{$this->value}");
    }

    /** Panel tempat peran ini bekerja, dan tujuannya setelah masuk. */
    public function panel(): string
    {
        return $this === self::Cashier ? 'cashier' : 'admin';
    }
}

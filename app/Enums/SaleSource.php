<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Pos   — dicatat per transaksi lewat halaman kasir, ada rincian itemnya.
 * Quick — rekap harian: kasir hanya mengisi total per channel.
 *
 * Keduanya disimpan di tabel yang sama supaya seluruh laporan cukup
 * membaca satu sumber dan tidak ada angka yang terhitung dua kali.
 */
enum SaleSource: string implements HasLabel
{
    case Pos = 'pos';
    case Quick = 'quick';

    public function getLabel(): string
    {
        return __("enums.source.{$this->value}");
    }
}

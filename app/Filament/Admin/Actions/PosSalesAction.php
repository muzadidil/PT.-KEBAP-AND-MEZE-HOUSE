<?php

namespace App\Filament\Admin\Actions;

use Filament\Actions\Action;

/**
 * Tombol impor Moka di Pemasukan Harian. Dulu tombol "Impor penjualan kasir"
 * yang hanya mengenal laporan per struk; sekarang tombol yang sama dengan di
 * Penjualan per Menu, yang mengenali jenis laporannya dari isi berkas
 * (MokaImport). Namanya tetap `posSalesImport` supaya yang merujuknya tidak
 * berubah.
 */
class PosSalesAction
{
    public static function make(): Action
    {
        return MokaImportAction::make('posSalesImport', 'gray');
    }
}

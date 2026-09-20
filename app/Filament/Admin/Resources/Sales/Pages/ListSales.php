<?php

namespace App\Filament\Admin\Resources\Sales\Pages;

use App\Filament\Admin\Resources\Sales\SaleResource;
use Filament\Resources\Pages\ListRecords;

class ListSales extends ListRecords
{
    protected static string $resource = SaleResource::class;

    /**
     * Tanpa tombol tambah: penjualan lahir di halaman kasir atau rekap
     * harian, bukan diketik langsung ke daftar ini.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}

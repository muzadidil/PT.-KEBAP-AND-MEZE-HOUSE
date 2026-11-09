<?php

namespace App\Filament\Admin\Resources\Zeytin\MokaItemSales\Pages;

use App\Filament\Admin\Actions\MokaImportAction;
use App\Filament\Admin\Resources\Zeytin\MokaItemSales\MokaItemSaleResource;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

class ManageMokaItemSales extends ManageRecords
{
    protected static string $resource = MokaItemSaleResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('moka.page.subheading');
    }

    /** Tombol yang sama dengan di Pemasukan Harian: jenis laporannya dikenali dari isi berkas. */
    protected function getHeaderActions(): array
    {
        return [MokaImportAction::make('mokaImport')];
    }
}

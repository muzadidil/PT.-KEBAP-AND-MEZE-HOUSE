<?php

namespace App\Filament\Admin\Resources\Zeytin\SupplierTransfers\Pages;

use App\Filament\Admin\Resources\Zeytin\SupplierTransfers\SupplierTransferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSupplierTransfers extends ManageRecords
{
    protected static string $resource = SupplierTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

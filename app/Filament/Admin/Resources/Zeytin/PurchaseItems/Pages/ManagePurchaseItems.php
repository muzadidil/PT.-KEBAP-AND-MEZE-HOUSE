<?php

namespace App\Filament\Admin\Resources\Zeytin\PurchaseItems\Pages;

use App\Filament\Admin\Actions\ExcelActions;
use App\Filament\Admin\Resources\Zeytin\PurchaseItems\PurchaseItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePurchaseItems extends ManageRecords
{
    protected static string $resource = PurchaseItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...ExcelActions::make(PurchaseItemResource::excel()),
            CreateAction::make(),
        ];
    }
}

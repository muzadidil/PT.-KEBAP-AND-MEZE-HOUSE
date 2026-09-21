<?php

namespace App\Filament\Admin\Resources\Zeytin\Purchases\Pages;

use App\Filament\Admin\Resources\Zeytin\Purchases\PurchaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePurchases extends ManageRecords
{
    protected static string $resource = PurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

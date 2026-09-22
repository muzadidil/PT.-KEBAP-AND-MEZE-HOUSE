<?php

namespace App\Filament\Admin\Resources\Products\Pages;

use App\Filament\Admin\Actions\ExcelActions;
use App\Filament\Admin\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProducts extends ManageRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...ExcelActions::make(ProductResource::excel()),
            CreateAction::make(),
        ];
    }
}

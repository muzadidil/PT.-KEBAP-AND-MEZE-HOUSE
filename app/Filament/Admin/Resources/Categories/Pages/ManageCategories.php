<?php

namespace App\Filament\Admin\Resources\Categories\Pages;

use App\Filament\Admin\Actions\ExcelActions;
use App\Filament\Admin\Resources\Categories\CategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCategories extends ManageRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...ExcelActions::make(CategoryResource::excel()),
            CreateAction::make(),
        ];
    }
}

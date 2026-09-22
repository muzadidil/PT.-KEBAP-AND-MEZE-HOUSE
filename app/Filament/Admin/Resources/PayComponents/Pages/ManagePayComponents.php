<?php

namespace App\Filament\Admin\Resources\PayComponents\Pages;

use App\Filament\Admin\Actions\ExcelActions;
use App\Filament\Admin\Resources\PayComponents\PayComponentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePayComponents extends ManageRecords
{
    protected static string $resource = PayComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...ExcelActions::make(PayComponentResource::excel()),
            CreateAction::make(),
        ];
    }
}

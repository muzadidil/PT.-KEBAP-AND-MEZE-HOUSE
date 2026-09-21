<?php

namespace App\Filament\Admin\Resources\Zeytin\OutstandingBills\Pages;

use App\Filament\Admin\Resources\Zeytin\OutstandingBills\OutstandingBillResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOutstandingBills extends ManageRecords
{
    protected static string $resource = OutstandingBillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

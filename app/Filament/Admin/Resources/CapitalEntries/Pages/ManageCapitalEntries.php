<?php

namespace App\Filament\Admin\Resources\CapitalEntries\Pages;

use App\Filament\Admin\Resources\CapitalEntries\CapitalEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCapitalEntries extends ManageRecords
{
    protected static string $resource = CapitalEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

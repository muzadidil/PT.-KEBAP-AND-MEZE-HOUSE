<?php

namespace App\Filament\Admin\Resources\Zeytin\OwnerSalaries\Pages;

use App\Filament\Admin\Resources\Zeytin\OwnerSalaries\OwnerSalaryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOwnerSalaries extends ManageRecords
{
    protected static string $resource = OwnerSalaryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

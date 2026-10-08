<?php

namespace App\Filament\Admin\Resources\Zeytin\BalanceAdjustments\Pages;

use App\Filament\Admin\Resources\Zeytin\BalanceAdjustments\BalanceAdjustmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBalanceAdjustments extends ManageRecords
{
    protected static string $resource = BalanceAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

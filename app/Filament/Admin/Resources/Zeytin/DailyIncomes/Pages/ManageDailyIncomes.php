<?php

namespace App\Filament\Admin\Resources\Zeytin\DailyIncomes\Pages;

use App\Filament\Admin\Actions\ExcelActions;
use App\Filament\Admin\Resources\Zeytin\DailyIncomes\DailyIncomeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDailyIncomes extends ManageRecords
{
    protected static string $resource = DailyIncomeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...ExcelActions::make(DailyIncomeResource::excel()),
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Admin\Resources\Expenses\Pages;

use App\Filament\Admin\Actions\ExcelActions;
use App\Filament\Admin\Resources\Expenses\ExpenseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExpenses extends ManageRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...ExcelActions::make(ExpenseResource::excel()),
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Admin\Resources\Employees\Pages;

use App\Filament\Admin\Actions\ExcelActions;
use App\Filament\Admin\Resources\Employees\EmployeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployees extends ManageRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...ExcelActions::make(EmployeeResource::excel()),
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Admin\Resources\Zeytin\Payrolls\Pages;

use App\Filament\Admin\Actions\ExcelActions;
use App\Filament\Admin\Resources\Zeytin\Payrolls\PayrollResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePayrolls extends ManageRecords
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...ExcelActions::make(PayrollResource::excel()),
            CreateAction::make(),
        ];
    }
}

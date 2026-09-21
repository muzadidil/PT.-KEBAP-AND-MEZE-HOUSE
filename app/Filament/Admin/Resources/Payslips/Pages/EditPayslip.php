<?php

namespace App\Filament\Admin\Resources\Payslips\Pages;

use App\Filament\Admin\Resources\Payslips\PayslipResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPayslip extends EditRecord
{
    protected static string $resource = PayslipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PayslipResource::pdfAction(),
            DeleteAction::make(),
        ];
    }
}

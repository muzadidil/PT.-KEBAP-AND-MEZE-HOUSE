<?php

namespace App\Filament\Admin\Resources\Zeytin\PaymentMethods\Pages;

use App\Filament\Admin\Actions\ExcelActions;
use App\Filament\Admin\Resources\Zeytin\PaymentMethods\PaymentMethodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePaymentMethods extends ManageRecords
{
    protected static string $resource = PaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...ExcelActions::make(PaymentMethodResource::excel()),
            CreateAction::make(),
        ];
    }
}

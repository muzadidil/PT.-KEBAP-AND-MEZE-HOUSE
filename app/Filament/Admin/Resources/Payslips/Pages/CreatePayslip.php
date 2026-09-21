<?php

namespace App\Filament\Admin\Resources\Payslips\Pages;

use App\Filament\Admin\Resources\Payslips\PayslipResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayslip extends CreateRecord
{
    protected static string $resource = PayslipResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }

    /** Kembali ke daftar, tempat slip yang baru dibuat bisa langsung diunduh. */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}

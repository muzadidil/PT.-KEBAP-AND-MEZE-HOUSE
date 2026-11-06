<?php

namespace App\Filament\Admin\Resources\Zeytin\Payrolls\Pages;

use App\Filament\Admin\Actions\ExcelActions;
use App\Filament\Admin\Resources\Zeytin\Payrolls\PayrollResource;
use App\Models\Payroll;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Filament\Resources\Pages\ManageRecords;

class ManagePayrolls extends ManageRecords
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...ExcelActions::make(PayrollResource::excel()),
            Action::make('payMonth')
                ->label(__('payroll_paid.action'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->modalHeading(__('payroll_paid.heading'))
                ->modalDescription(__('payroll_paid.description'))
                ->modalSubmitActionLabel(__('payroll_paid.submit'))
                ->fillForm(fn () => [
                    'month' => now()->subMonth()->startOfMonth()->toDateString(),
                    'paid_on' => now()->toDateString(),
                    'paid_via' => Payroll::VIA_BANK,
                ])
                ->schema([
                    DatePicker::make('month')
                        ->label(__('payroll_paid.field.month'))
                        ->native(false)
                        ->displayFormat('M Y')
                        ->required(),
                    ...PayrollResource::paymentFields(),
                ])
                ->action(function (array $data) {
                    $month = Carbon::parse($data['month'])->startOfMonth();

                    PayrollResource::markPaid(
                        Payroll::query()->whereDate('month', $month->toDateString())->whereNull('paid_on')->get(),
                        (string) $data['paid_on'],
                        (string) $data['paid_via'],
                    );
                }),
            CreateAction::make(),
        ];
    }
}

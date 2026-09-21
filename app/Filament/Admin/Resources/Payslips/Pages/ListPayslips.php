<?php

namespace App\Filament\Admin\Resources\Payslips\Pages;

use App\Filament\Admin\Resources\Payslips\PayslipResource;
use App\Support\Payroll\PayslipLetterhead;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;

class ListPayslips extends ListRecords
{
    protected static string $resource = PayslipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->letterheadAction(),
            CreateAction::make(),
        ];
    }

    /** Tab "Pengaturan" di aplikasi Slip Gaji: kop dan penandatangan. */
    protected function letterheadAction(): Action
    {
        return Action::make('letterhead')
            ->label(__('payroll.action.letterhead'))
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->color('gray')
            ->fillForm(fn () => PayslipLetterhead::get())
            ->schema([
                Text::make(__('payroll.letterhead.logo')),

                TextInput::make('company')
                    ->label(__('payroll.letterhead.company'))
                    ->maxLength(120),

                TextInput::make('address')
                    ->label(__('payroll.letterhead.address'))
                    ->maxLength(200),

                TextInput::make('city')
                    ->label(__('payroll.letterhead.city'))
                    ->maxLength(60),

                TextInput::make('signer_name')
                    ->label(__('payroll.letterhead.signer_name'))
                    ->maxLength(120),

                TextInput::make('signer_title')
                    ->label(__('payroll.letterhead.signer_title'))
                    ->maxLength(80),
            ])
            ->action(function (array $data) {
                PayslipLetterhead::save($data);

                Notification::make()->success()->title(__('payroll.letterhead.saved'))->send();
            });
    }
}

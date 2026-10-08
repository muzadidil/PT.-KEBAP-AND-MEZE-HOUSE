<?php

namespace App\Filament\Admin\Pages\Zeytin;

use App\Filament\Admin\Concerns\ForAdmin;
use App\Filament\Admin\Pages\Reports\Concerns\HasPeriod;
use App\Support\Money;
use App\Support\Zeytin\BankBalance;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;
use UnitEnum;

/**
 * Perkiraan sisa uang di rekening perusahaan. Hanya melihat dan menghitung;
 * tidak mengubah data pembukuan. Lihat App\Support\Zeytin\BankBalance untuk
 * rumus dan batasannya.
 */
class BankBalancePage extends Page
{
    use ForAdmin;
    use HasPeriod;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?int $navigationSort = 13;

    protected static ?string $slug = 'bank-balance';

    protected string $view = 'filament.admin.pages.zeytin.bank-balance';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('zeytin.nav.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('bank_balance.nav');
    }

    public function getTitle(): string
    {
        return __('bank_balance.nav');
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function report(): array
    {
        return BankBalance::report($this->fromDate(), $this->toDate());
    }

    protected function forget(): void
    {
        unset($this->report);
    }

    public function money(?int $amount): string
    {
        return Money::format($amount);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('opening')
                ->label(__('bank_balance.opening.action'))
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->color('gray')
                ->modalHeading(__('bank_balance.opening.heading'))
                ->modalDescription(__('bank_balance.opening.description'))
                ->fillForm(fn () => BankBalance::opening())
                ->schema([
                    TextInput::make('amount')
                        ->label(__('bank_balance.opening.amount'))
                        ->prefix('Rp')->numeric()->integer()->minValue(0)->required(),
                    DatePicker::make('date')
                        ->label(__('bank_balance.opening.date'))
                        ->helperText(__('bank_balance.opening.date_hint'))
                        ->native(false),
                ])
                ->action(function (array $data) {
                    BankBalance::setOpening((int) $data['amount'], $data['date'] ?? null);
                    unset($this->report);

                    Notification::make()->title(__('bank_balance.saved'))->success()->send();
                }),
        ];
    }
}

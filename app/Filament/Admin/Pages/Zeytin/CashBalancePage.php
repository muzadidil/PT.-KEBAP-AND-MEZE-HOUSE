<?php

namespace App\Filament\Admin\Pages\Zeytin;

use App\Filament\Admin\Concerns\ForAdmin;
use App\Filament\Admin\Pages\Reports\Concerns\HasPeriod;
use App\Support\Money;
use App\Support\Zeytin\CashBalance;
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
 * Sisa uang cash di kasir: saldo awal + penjualan cash − belanja tunai.
 * Hanya melihat dan menghitung; tidak mengubah data pembukuan. Lihat
 * App\Support\Zeytin\CashBalance untuk rumus dan batasannya.
 */
class CashBalancePage extends Page
{
    use ForAdmin;
    use HasPeriod;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static ?int $navigationSort = 12;

    protected static ?string $slug = 'cash-balance';

    protected string $view = 'filament.admin.pages.zeytin.cash-balance';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('zeytin.nav.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('cash.nav');
    }

    public function getTitle(): string
    {
        return __('cash.nav');
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function report(): array
    {
        return CashBalance::report($this->fromDate(), $this->toDate());
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
                ->label(__('cash.opening.action'))
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->color('gray')
                ->modalHeading(__('cash.opening.heading'))
                ->modalDescription(__('cash.opening.description'))
                ->fillForm(fn () => CashBalance::opening())
                ->schema([
                    TextInput::make('amount')
                        ->label(__('cash.opening.amount'))
                        ->prefix('Rp')->numeric()->integer()->minValue(0)->required(),
                    DatePicker::make('date')
                        ->label(__('cash.opening.date'))
                        ->helperText(__('cash.opening.date_hint'))
                        ->native(false),
                ])
                ->action(function (array $data) {
                    CashBalance::setOpening((int) $data['amount'], $data['date'] ?? null);
                    unset($this->report);

                    Notification::make()->title(__('cash.saved'))->success()->send();
                }),
        ];
    }
}

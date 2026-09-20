<?php

namespace App\Filament\Admin\Pages\Reports;

use App\Support\Ledger;
use App\Support\Money;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Neraca per satu tanggal.
 *
 * Tidak ada saldo yang disimpan: seluruh angkanya dihitung ulang dari
 * penjualan, pengeluaran, dan catatan modal setiap kali halaman dibuka.
 * Keseimbangannya berlaku secara aljabar, bukan kebetulan — alasannya ada
 * di App\Support\Ledger::balanceSheet(), dan pembuktiannya di
 * tests/Feature/BalanceSheetTest.
 */
class BalanceSheet extends Page
{
    protected static ?int $navigationSort = 100;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected string $view = 'filament.admin.pages.reports.balance-sheet';

    #[Url]
    public string $asOf = '';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.group.reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.balance_sheet');
    }

    public function getTitle(): string
    {
        return __('nav.balance_sheet');
    }

    public function mount(): void
    {
        $this->asOf = $this->asOf ?: Carbon::today()->toDateString();
    }

    public function asOfDate(): Carbon
    {
        return Carbon::parse($this->asOf ?: Carbon::today())->endOfDay();
    }

    public function updatedAsOf(): void
    {
        unset($this->sheet);
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function sheet(): array
    {
        return Ledger::balanceSheet($this->asOfDate());
    }

    public function money(?int $amount): string
    {
        return Money::format($amount);
    }
}

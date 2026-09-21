<?php

namespace App\Filament\Admin\Pages\Reports;

use App\Models\Purchase;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

/**
 * Belanja tunai — sheet Expense di berkas Excel klien.
 *
 * Membaca Belanja Tunai di Pembukuan Bulanan, bukan menu Pengeluaran:
 * totalnya sama dengan kartu Belanja tunai di Buku Besar untuk rentang yang
 * sama.
 */
class CashExpenses extends ExpenseReport
{
    protected static ?int $navigationSort = 50;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function getNavigationLabel(): string
    {
        return __('nav.cash_expenses');
    }

    public function getTitle(): string
    {
        return __('nav.cash_expenses');
    }

    public function source(): string
    {
        return __('report.source.cash_expenses');
    }

    protected function baseQuery(): Builder
    {
        return Purchase::query();
    }

    protected function detailColumns(): array
    {
        return [
            TextColumn::make('item')
                ->label(__('zeytin.field.item'))
                ->description(fn (Purchase $record) => $record->vendor ?: null)
                ->searchable(['item', 'vendor'])
                ->wrap(),

            TextColumn::make('qty')
                ->label(__('zeytin.field.qty'))
                ->formatStateUsing(fn (Purchase $record) => trim($record->qty.' '.$record->unit))
                ->alignEnd(),
        ];
    }
}

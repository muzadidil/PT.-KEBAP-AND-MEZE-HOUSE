<?php

namespace App\Filament\Admin\Pages\Reports;

use App\Enums\PaymentMethod;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use BackedEnum;

/** Pengeluaran yang dibayar tunai dari laci kasir. */
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

    protected function scopeQuery(Builder $query): Builder
    {
        return $query->where('method', PaymentMethod::Cash);
    }

    protected function extraColumns(): array
    {
        return [
            TextColumn::make('category')->label(__('field.category'))->badge(),
            TextColumn::make('paidByOwner.name')
                ->label(__('field.paid_by_owner'))
                ->placeholder('—')
                ->badge()
                ->color('warning'),
        ];
    }
}

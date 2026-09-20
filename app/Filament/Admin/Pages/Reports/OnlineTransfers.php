<?php

namespace App\Filament\Admin\Pages\Reports;

use App\Enums\PaymentMethod;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pengeluaran yang dibayar lewat transfer online, bukan dari laci kasir.
 * Dipisah karena inilah yang harus dicocokkan dengan mutasi rekening.
 */
class OnlineTransfers extends ExpenseReport
{
    protected static ?int $navigationSort = 60;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    public static function getNavigationLabel(): string
    {
        return __('nav.online_transfers');
    }

    public function getTitle(): string
    {
        return __('nav.online_transfers');
    }

    protected function scopeQuery(Builder $query): Builder
    {
        return $query->where('method', PaymentMethod::Transfer);
    }

    protected function extraColumns(): array
    {
        return [
            TextColumn::make('category')->label(__('field.category'))->badge(),
            TextColumn::make('supplier.name')
                ->label(__('field.supplier'))
                ->placeholder('—')
                ->badge()
                ->color('gray'),
        ];
    }
}

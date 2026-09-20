<?php

namespace App\Filament\Admin\Pages\Reports;

use App\Enums\ExpenseCategory;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pajak yang dicatat, baik yang sudah disetor maupun yang masih terutang.
 *
 * Yang belum dibayar sengaja ikut ditampilkan, karena justru itulah yang
 * perlu diketahui: angka yang sama muncul sebagai Utang Pajak di neraca.
 */
class Tax extends ExpenseReport
{
    protected static ?int $navigationSort = 80;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    public static function getNavigationLabel(): string
    {
        return __('nav.tax');
    }

    public function getTitle(): string
    {
        return __('nav.tax');
    }

    protected function scopeQuery(Builder $query): Builder
    {
        return $query->where('category', ExpenseCategory::Tax);
    }

    protected function extraColumns(): array
    {
        return [
            TextColumn::make('method')->label(__('field.method'))->badge(),
            TextColumn::make('due_on')
                ->label(__('field.due_on'))
                ->date('d/m/Y')
                ->placeholder('—'),
        ];
    }

    protected function extraFilters(): array
    {
        return [
            TernaryFilter::make('is_paid')->label(__('field.is_paid')),
        ];
    }
}

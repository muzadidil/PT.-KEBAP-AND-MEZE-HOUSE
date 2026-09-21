<?php

namespace App\Filament\Admin\Pages\Reports;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pajak yang dicatat, baik yang sudah disetor maupun yang masih terutang.
 *
 * Masih membaca menu Pengeluaran (kategori Pajak): berkas Excel klien tidak
 * punya sheet pajak, dan tempat mencatat pajak di Pembukuan Bulanan belum
 * diputuskan. Layarnya menyebutkan itu, supaya angkanya tidak dikira bagian
 * dari Buku Besar.
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

    public function source(): string
    {
        return __('report.source.tax');
    }

    protected function baseQuery(): Builder
    {
        return Expense::query()->with('supplier')->where('category', ExpenseCategory::Tax);
    }

    protected function dateColumn(): string
    {
        return 'spent_on';
    }

    protected function amountColumn(): string
    {
        return 'amount';
    }

    protected function detailColumns(): array
    {
        return [
            TextColumn::make('description')
                ->label(__('field.description'))
                ->description(fn (Expense $record) => $record->supplier?->name)
                ->searchable()
                ->wrap(),

            TextColumn::make('method')->label(__('field.method'))->badge(),

            TextColumn::make('due_on')
                ->label(__('field.due_on'))
                ->date('d/m/Y')
                ->placeholder('—'),
        ];
    }

    protected function trailingColumns(): array
    {
        return [
            IconColumn::make('is_paid')
                ->label(__('field.is_paid'))
                ->boolean(),
        ];
    }

    protected function extraFilters(): array
    {
        return [
            TernaryFilter::make('is_paid')->label(__('field.is_paid')),
        ];
    }
}

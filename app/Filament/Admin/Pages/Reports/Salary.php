<?php

namespace App\Filament\Admin\Pages\Reports;

use App\Enums\ExpenseCategory;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

/**
 * Seluruh pembayaran gaji, baik gaji pegawai maupun gaji pemilik.
 *
 * Gaji pemilik dikenali dari kolom "Ditalangi oleh" yang terisi: kalau
 * pemilik membayar dirinya sendiri dari kantong usaha, kolom itu kosong;
 * kalau ia menalangi gaji orang lain, kolomnya terisi namanya.
 */
class Salary extends ExpenseReport
{
    protected static ?int $navigationSort = 70;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    public static function getNavigationLabel(): string
    {
        return __('nav.salary');
    }

    public function getTitle(): string
    {
        return __('nav.salary');
    }

    protected function scopeQuery(Builder $query): Builder
    {
        return $query->where('category', ExpenseCategory::Salary);
    }

    protected function extraColumns(): array
    {
        return [
            TextColumn::make('method')->label(__('field.method'))->badge(),
            TextColumn::make('paidByOwner.name')
                ->label(__('field.paid_by_owner'))
                ->placeholder('—')
                ->badge()
                ->color('warning'),
        ];
    }
}

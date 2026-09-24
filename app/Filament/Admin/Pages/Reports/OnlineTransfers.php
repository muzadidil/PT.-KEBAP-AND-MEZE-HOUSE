<?php

namespace App\Filament\Admin\Pages\Reports;

use App\Filament\Admin\Resources\Zeytin\SupplierTransfers\SupplierTransferResource;
use App\Models\SupplierTransfer;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Transfer ke pemasok — sheet Supplier Transfer Payment.
 *
 * Membaca Transfer Pemasok di Pembukuan Bulanan: totalnya sama dengan kartu
 * Transfer pemasok di Buku Besar untuk rentang yang sama. Kolom status
 * menunjukkan siapa yang membayar (PT KEBAP PAID, ASLAN PAID).
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

    public function source(): string
    {
        return __('report.source.online_transfers');
    }

    protected function baseQuery(): Builder
    {
        return SupplierTransfer::query();
    }

    protected function detailColumns(): array
    {
        return [
            TextColumn::make('item')
                ->label(__('zeytin.field.item'))
                ->description(fn (SupplierTransfer $record) => $record->vendor ?: null)
                ->searchable(['item', 'vendor'])
                ->wrap(),

            TextColumn::make('method')
                ->label(__('zeytin.field.method'))
                ->placeholder('—')
                ->badge()
                ->color('gray'),
        ];
    }

    protected function trailingColumns(): array
    {
        return [
            TextColumn::make('status')
                ->label(__('zeytin.field.status'))
                ->placeholder('—')
                ->badge()
                ->color(fn (?string $state) => str_contains((string) $state, 'KEBAP') ? 'success' : 'warning'),
        ];
    }

    protected function extraFilters(): array
    {
        return [
            SelectFilter::make('status')
                ->label(__('zeytin.field.status'))
                ->options(SupplierTransferResource::statuses()),
        ];
    }

    protected function exportDetails(): array
    {
        return [
            ['label' => __('zeytin.field.item'), 'value' => fn (SupplierTransfer $row) => $row->item],
            ['label' => __('zeytin.field.vendor'), 'value' => fn (SupplierTransfer $row) => $row->vendor],
            ['label' => __('zeytin.field.method'), 'value' => fn (SupplierTransfer $row) => $row->method],
        ];
    }

    protected function exportTrailing(): array
    {
        return [
            ['label' => __('zeytin.field.status'), 'value' => fn (SupplierTransfer $row) => $row->status],
        ];
    }

    public function filterColumns(): array
    {
        return ['status'];
    }

    public function searchColumns(): array
    {
        return ['item', 'vendor'];
    }
}

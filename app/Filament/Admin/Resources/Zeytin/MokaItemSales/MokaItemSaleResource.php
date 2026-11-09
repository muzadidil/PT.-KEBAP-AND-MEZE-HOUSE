<?php

namespace App\Filament\Admin\Resources\Zeytin\MokaItemSales;

use App\Filament\Admin\Concerns\ForAdmin;
use App\Filament\Admin\Resources\Zeytin\Concerns\BookkeepingResource;
use App\Filament\Admin\Resources\Zeytin\MokaItemSales\Pages\ManageMokaItemSales;
use App\Models\MokaItemSale;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Rekap penjualan per menu dari laporan Report Item Sales Moka.
 *
 * Hanya dibaca: baris datang dari berkas Moka dan tidak diketik atau diedit
 * di sini, karena angka yang diubah tangan tidak lagi sama dengan Moka dan
 * tidak ada yang bisa memeriksanya. Salah impor dibereskan dengan memilih
 * barisnya lalu menghapus, atau mengimpor ulang periode yang sama.
 *
 * Tidak dibaca laporan penjualan mana pun — penjualan yang sama sudah
 * tercatat di Pemasukan Harian (lihat MokaItemSale).
 */
class MokaItemSaleResource extends Resource
{
    use BookkeepingResource;
    use ForAdmin;

    protected static ?string $model = MokaItemSale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static ?int $navigationSort = 15;

    public static function getNavigationLabel(): string
    {
        return __('moka.nav.item_sales');
    }

    public static function getModelLabel(): string
    {
        return __('moka.nav.item_sales');
    }

    public static function getPluralModelLabel(): string
    {
        return __('moka.nav.item_sales');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $count = fn ($state) => number_format((int) $state, 0, ',', '.');

        return $table
            // Periode terbaru dulu; di dalamnya, yang paling laku di atas.
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByDesc('period_start'))
            ->defaultSort('net_sales', 'desc')
            ->columns([
                TextColumn::make('period_start')
                    ->label(__('moka.col.period'))
                    ->formatStateUsing(fn ($state, MokaItemSale $record) => $record->periodLabel())
                    ->sortable(),

                TextColumn::make('item_name')
                    ->label(__('moka.col.item_name'))
                    ->description(fn (MokaItemSale $record) => $record->variant_name ?: null)
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('category_name')
                    ->label(__('moka.col.category_name'))
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('qty_sold')
                    ->label(__('moka.col.qty_sold'))
                    ->formatStateUsing($count)
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label(__('report.total'))->formatStateUsing($count)),

                TextColumn::make('qty_refunded')
                    ->label(__('moka.col.qty_refunded'))
                    ->formatStateUsing($count)
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                static::moneyColumn('gross_sales', __('moka.col.gross_sales')),

                static::moneyColumn('discount', __('moka.col.discount'))
                    ->toggleable(isToggledHiddenByDefault: true),

                static::moneyColumn('refund', __('moka.col.refund'))
                    ->toggleable(isToggledHiddenByDefault: true),

                static::moneyColumn('net_sales', __('moka.col.net_sales'))
                    ->weight('bold')
                    ->summarize(
                        Sum::make()
                            ->label(__('report.total'))
                            ->formatStateUsing(fn ($state) => Money::format((int) $state)),
                    ),
            ])
            ->filters([
                SelectFilter::make('period')
                    ->label(__('moka.filter.period'))
                    ->options(fn () => MokaItemSale::periodOptions())
                    ->query(function (Builder $query, array $data) {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        [$start, $end] = explode('|', $data['value']);

                        return $query->inPeriod($start, $end);
                    }),

                SelectFilter::make('category_name')
                    ->label(__('moka.filter.category'))
                    ->options(fn () => MokaItemSale::query()
                        ->whereNotNull('category_name')
                        ->distinct()
                        ->orderBy('category_name')
                        ->pluck('category_name', 'category_name')
                        ->all())
                    ->searchable(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('moka.page.empty_heading'))
            ->emptyStateDescription(__('moka.page.empty_description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMokaItemSales::route('/'),
        ];
    }
}

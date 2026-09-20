<?php

namespace App\Filament\Admin\Resources\Sales;

use App\Enums\SalesChannel;
use App\Enums\SaleSource;
use App\Filament\Admin\Resources\Sales\Pages\ListSales;
use App\Models\Sale;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Seluruh penjualan, baik dari mesin kasir maupun rekap harian.
 *
 * Tidak ada tombol tambah di sini: penjualan lahir di halaman kasir atau
 * rekap harian, bukan diketik langsung ke daftar. Menghapus tetap bisa,
 * untuk membatalkan transaksi yang salah catat.
 */
class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.group.sales');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.sales');
    }

    public static function getModelLabel(): string
    {
        return __('nav.sales');
    }

    public static function getPluralModelLabel(): string
    {
        return __('nav.sales');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sold_on', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('items', 'user'))
            ->columns([
                TextColumn::make('sold_on')
                    ->label(__('field.date'))
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('code')
                    ->label(__('field.code'))
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('source')
                    ->label(__('field.source'))
                    ->badge()
                    ->color(fn (SaleSource $state) => $state === SaleSource::Pos ? 'primary' : 'gray'),

                TextColumn::make('channel')
                    ->label(__('field.channel'))
                    ->badge(),

                // Rincian item hanya ada untuk transaksi kasir; rekap harian
                // memang tidak punya, dan itu ditampilkan apa adanya.
                TextColumn::make('items')
                    ->label(__('field.items'))
                    ->placeholder('—')
                    ->formatStateUsing(fn (Sale $record) => $record->items
                        ->map(fn ($item) => "{$item->qty}× {$item->name}")
                        ->implode(', ') ?: null)
                    ->wrap()
                    ->limit(60),

                TextColumn::make('discount')
                    ->label(__('field.discount'))
                    ->formatStateUsing(fn (int $state) => $state > 0 ? Money::format($state) : '—')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total')
                    ->label(__('field.total'))
                    ->formatStateUsing(fn (int $state) => Money::format($state))
                    ->alignEnd()
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label(__('report.total'))
                            ->formatStateUsing(fn ($state) => Money::format((int) $state))
                    ),

                TextColumn::make('user.name')
                    ->label(__('field.cashier'))
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('field.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('channel')
                    ->label(__('field.channel'))
                    ->options(SalesChannel::class)
                    ->multiple(),

                SelectFilter::make('source')
                    ->label(__('field.source'))
                    ->options(SaleSource::class),

                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label(__('report.from'))->native(false),
                        DatePicker::make('until')->label(__('report.to'))->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('sold_on', '>=', $date))
                        ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('sold_on', '<=', $date))),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSales::route('/'),
        ];
    }
}

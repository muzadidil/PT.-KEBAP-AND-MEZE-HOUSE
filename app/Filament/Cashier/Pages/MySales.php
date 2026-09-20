<?php

namespace App\Filament\Cashier\Pages;

use App\Enums\SalesChannel;
use App\Models\Sale;
use App\Support\Money;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Transaksi yang dicatat oleh kasir yang sedang masuk.
 *
 * Sengaja hanya miliknya sendiri: kasir perlu mencocokkan isi laci di akhir
 * giliran, bukan melihat penjualan rekannya. Yang butuh gambaran menyeluruh
 * adalah pemilik, dan itu ada di panel admin.
 */
class MySales extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?int $navigationSort = 0;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected string $view = 'filament.cashier.pages.my-sales';

    public static function getNavigationLabel(): string
    {
        return __('nav.my_sales');
    }

    public function getTitle(): string
    {
        return __('nav.my_sales');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Sale::query()->with('items')->where('user_id', Auth::id()))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('sold_on')
                    ->label(__('field.date'))
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('code')
                    ->label(__('field.code'))
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('channel')
                    ->label(__('field.channel'))
                    ->badge(),

                TextColumn::make('items')
                    ->label(__('field.items'))
                    ->placeholder('—')
                    ->formatStateUsing(fn (Sale $record) => $record->items
                        ->map(fn ($item) => "{$item->qty}× {$item->name}")
                        ->implode(', ') ?: null)
                    ->wrap()
                    ->limit(60),

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

                TextColumn::make('created_at')
                    ->label(__('field.created_at'))
                    ->dateTime('H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('channel')
                    ->label(__('field.channel'))
                    ->options(SalesChannel::class)
                    ->multiple(),

                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label(__('report.from'))->native(false),
                        DatePicker::make('until')->label(__('report.to'))->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('sold_on', '>=', $date))
                        ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('sold_on', '<=', $date))),
            ])
            ->emptyStateHeading(__('report.no_data'));
    }
}

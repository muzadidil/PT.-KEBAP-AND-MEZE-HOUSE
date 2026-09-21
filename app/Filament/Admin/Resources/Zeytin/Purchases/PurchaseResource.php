<?php

namespace App\Filament\Admin\Resources\Zeytin\Purchases;

use App\Filament\Admin\Resources\Zeytin\Concerns\BookkeepingResource;
use App\Filament\Admin\Resources\Zeytin\Purchases\Pages\ManagePurchases;
use App\Models\Purchase;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Belanja harian yang dibayar tunai — sheet Expense.
 *
 * Totalnya tidak diisi tangan. Rumusnya milik berkas Excel,
 * `=((qty × price) + tax − disc)`, dan dihitung di model supaya halaman ini,
 * pengimpor, dan seeder tidak mungkin berbeda pendapat.
 */
class PurchaseResource extends Resource
{
    use BookkeepingResource;

    protected static ?string $model = Purchase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('zeytin.nav.purchases');
    }

    public static function getModelLabel(): string
    {
        return __('zeytin.nav.purchases');
    }

    public static function getPluralModelLabel(): string
    {
        return __('zeytin.nav.purchases');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('date')
                ->label(__('zeytin.field.date'))
                ->native(false)
                ->default(now())
                ->required(),

            static::vendorInput(),

            static::itemInput()->columnSpanFull(),

            TextInput::make('qty')
                ->label(__('zeytin.field.qty'))
                ->numeric()
                ->default(1)
                ->required(),

            TextInput::make('unit')
                ->label(__('zeytin.field.unit'))
                ->maxLength(40),

            static::money('price')->helperText(__('zeytin.help.price')),

            static::money('disc'),

            static::money('tax')->helperText(__('zeytin.help.total')),

            static::sourceField(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label(__('zeytin.field.date'))
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('item')
                    ->label(__('zeytin.field.item'))
                    ->description(fn (Purchase $record) => $record->vendor ?: null)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('qty')
                    ->label(__('zeytin.field.qty'))
                    ->description(fn (Purchase $record) => $record->unit ?: null)
                    ->alignEnd(),

                static::moneyColumn('price'),

                static::totalColumn(),

                static::sourceColumn(),
            ])
            ->filters([
                static::periodFilter(),
                static::sourceFilter(),
            ])
            ->recordActions([
                EditAction::make(),
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
            'index' => ManagePurchases::route('/'),
        ];
    }
}

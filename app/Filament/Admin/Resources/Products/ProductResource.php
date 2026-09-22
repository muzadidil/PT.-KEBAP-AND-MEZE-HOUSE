<?php

namespace App\Filament\Admin\Resources\Products;

use App\Filament\Admin\Concerns\ForSuperAdmin;
use App\Filament\Admin\Resources\Products\Pages\ManageProducts;
use App\Models\Category;
use App\Models\Product;
use App\Support\Excel\Column;
use App\Support\Excel\ExcelSheet;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Daftar menu yang muncul di halaman kasir.
 *
 * Menu tidak perlu dihapus kalau sudah tidak dijual — cukup dinonaktifkan.
 * Struk lama tetap utuh karena nama dan harganya disalin ke baris transaksi
 * saat item masuk keranjang.
 */
class ProductResource extends Resource
{
    use ForSuperAdmin;

    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.group.master');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.products');
    }

    public static function getModelLabel(): string
    {
        return __('nav.products');
    }

    public static function getPluralModelLabel(): string
    {
        return __('nav.products');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category_id')
                ->label(__('field.category'))
                ->relationship('category', 'name_en')
                ->required()
                ->preload()
                ->searchable(),

            TextInput::make('sku')
                ->label(__('field.sku'))
                ->maxLength(30)
                ->unique(ignoreRecord: true),

            TextInput::make('name_en')
                ->label(__('field.name_en'))
                ->required()
                ->maxLength(120),

            TextInput::make('name_id')
                ->label(__('field.name_id'))
                ->helperText(__('field.help.name_id'))
                ->maxLength(120),

            TextInput::make('price')
                ->label(__('field.price'))
                ->prefix('Rp')
                ->numeric()
                ->minValue(0)
                ->required(),

            TextInput::make('sort')
                ->label(__('field.sort'))
                ->numeric()
                ->default(0),

            Toggle::make('active')
                ->label(__('field.active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name_en')
                    ->label(__('field.name_en'))
                    ->description(fn (Product $record) => $record->name_id)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category.name_en')
                    ->label(__('field.category'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('sku')
                    ->label(__('field.sku'))
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('price')
                    ->label(__('field.price'))
                    ->money('IDR', 0)
                    ->alignEnd()
                    ->sortable(),

                IconColumn::make('active')
                    ->label(__('field.active'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label(__('field.category'))
                    ->relationship('category', 'name_en')
                    ->preload(),

                TernaryFilter::make('active')
                    ->label(__('field.active')),
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

    /** Dicocokkan lewat kode dulu, lalu lewat nama Inggrisnya. */
    public static function excel(): ExcelSheet
    {
        return ExcelSheet::make(Product::class, __('nav.products'))
            ->columns([
                Column::relation('category_id', 'field.category', Category::class, 'name_en')->required(),
                Column::text('sku', 'field.sku')->maxLength(30)->asText()->width(12),
                Column::text('name_en', 'field.name_en')->required()->maxLength(120)->width(28),
                Column::text('name_id', 'field.name_id')->maxLength(120)->width(28),
                Column::money('price', 'field.price')->required(),
                Column::number('sort', 'field.sort'),
                Column::boolean('active', 'field.active')->default(true),
            ])
            ->matchBy(['sku'], ['name_en'])
            ->examples([[
                'category_id' => Category::query()->orderBy('sort')->value('id'),
                'sku' => 'KB-01', 'name_en' => 'Chicken Kebab', 'name_id' => 'Kebab Ayam',
                'price' => 45_000, 'sort' => 1, 'active' => true,
            ]]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProducts::route('/'),
        ];
    }
}

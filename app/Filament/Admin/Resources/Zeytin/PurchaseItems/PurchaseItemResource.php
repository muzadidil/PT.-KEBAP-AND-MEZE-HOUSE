<?php

namespace App\Filament\Admin\Resources\Zeytin\PurchaseItems;

use App\Filament\Admin\Concerns\ForSuperAdmin;
use App\Filament\Admin\Resources\Zeytin\Concerns\BookkeepingResource;
use App\Filament\Admin\Resources\Zeytin\PurchaseItems\Pages\ManagePurchaseItems;
use App\Models\PurchaseItem;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Barang yang biasa dibelanjakan, beserta harga terakhirnya.
 *
 * Bukan halaman Menu: menu adalah yang dijual ke tamu, ini bahan yang dibeli
 * dari pemasok. Gunanya mempercepat pengisian — memilih satu barang di
 * formulir belanja ikut mengisi satuan dan harganya, jadi sepuluh kolom
 * cukup tiga ketikan.
 */
class PurchaseItemResource extends Resource
{
    use BookkeepingResource;
    use ForSuperAdmin;

    protected static ?string $model = PurchaseItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?int $navigationSort = 33;

    /**
     * Data induk, bukan catatan harian: diatur Super Admin bersama pemasok
     * dan menu, bukan diisi Admin bersama pembukuan.
     */
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.group.master');
    }

    public static function getNavigationLabel(): string
    {
        return __('zeytin.nav.purchase_items');
    }

    public static function getModelLabel(): string
    {
        return __('zeytin.nav.purchase_items');
    }

    public static function getPluralModelLabel(): string
    {
        return __('zeytin.nav.purchase_items');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('zeytin.field.item'))
                ->required()
                ->maxLength(200)
                ->columnSpanFull(),

            TextInput::make('unit')
                ->label(__('zeytin.field.unit'))
                ->maxLength(40),

            static::money('price')->helperText(__('zeytin.help.price')),

            static::vendorInput(),

            Toggle::make('active')
                ->label(__('field.active'))
                ->default(true),

            TextInput::make('note')
                ->label(__('zeytin.field.note'))
                ->maxLength(200)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('zeytin.field.item'))
                    ->description(fn (PurchaseItem $record) => $record->note ?: null)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('unit')
                    ->label(__('zeytin.field.unit'))
                    ->placeholder('—'),

                static::moneyColumn('price', __('zeytin.field.last_price')),

                TextColumn::make('vendor')
                    ->label(__('zeytin.field.vendor'))
                    ->placeholder('—')
                    ->searchable(),

                IconColumn::make('active')
                    ->label(__('field.active'))
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('active')->label(__('field.active')),
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
            'index' => ManagePurchaseItems::route('/'),
        ];
    }
}

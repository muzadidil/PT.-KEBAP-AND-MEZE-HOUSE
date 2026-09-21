<?php

namespace App\Filament\Admin\Resources\Zeytin\SupplierTransfers;

use App\Filament\Admin\Concerns\ForAdmin;
use App\Filament\Admin\Resources\Zeytin\Concerns\BookkeepingResource;
use App\Filament\Admin\Resources\Zeytin\SupplierTransfers\Pages\ManageSupplierTransfers;
use App\Models\SupplierTransfer;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Pembayaran ke pemasok lewat rekening — sheet Supplier Transfer Payment.
 *
 * Di berkas Excel aslinya angka ini tidak pernah ikut hitungan laba, jadi
 * laba terlihat jauh lebih besar dari yang sebenarnya. Di sini ikut sebagai
 * pengeluaran, atas persetujuan klien.
 */
class SupplierTransferResource extends Resource
{
    use BookkeepingResource;
    use ForAdmin;

    protected static ?string $model = SupplierTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('zeytin.nav.transfers');
    }

    public static function getModelLabel(): string
    {
        return __('zeytin.nav.transfers');
    }

    public static function getPluralModelLabel(): string
    {
        return __('zeytin.nav.transfers');
    }

    /** Siapa yang menalangi. "—" ikut jadi pilihan karena di berkas
     * aslinya lebih dari separuh barisnya memang kosong, dan yang belum
     * jelas lebih baik dibiarkan begitu daripada ditebak. */
    public static function statuses(): array
    {
        return [
            'PT KEBAP PAID' => __('zeytin.status.kebap_paid'),
            'ASLAN PAID' => __('zeytin.status.aslan_paid'),
            '—' => __('zeytin.status.none'),
        ];
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

            static::methodInput(),

            Select::make('status')
                ->label(__('zeytin.field.status'))
                ->options(static::statuses())
                ->native(false),

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
                    ->description(fn (SupplierTransfer $record) => $record->vendor ?: null)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('qty')
                    ->label(__('zeytin.field.qty'))
                    ->description(fn (SupplierTransfer $record) => $record->unit ?: null)
                    ->alignEnd(),

                static::moneyColumn('price'),

                static::totalColumn(),

                TextColumn::make('method')
                    ->label(__('zeytin.field.method'))
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->label(__('zeytin.field.status'))
                    ->placeholder('—')
                    ->badge()
                    ->color('warning'),

                static::sourceColumn(),
            ])
            ->filters([
                static::periodFilter(),

                SelectFilter::make('status')
                    ->label(__('zeytin.field.status'))
                    ->options(static::statuses()),

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
            'index' => ManageSupplierTransfers::route('/'),
        ];
    }
}

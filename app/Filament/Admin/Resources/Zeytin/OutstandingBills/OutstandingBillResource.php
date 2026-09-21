<?php

namespace App\Filament\Admin\Resources\Zeytin\OutstandingBills;

use App\Filament\Admin\Concerns\ForAdmin;
use App\Filament\Admin\Resources\Zeytin\Concerns\BookkeepingResource;
use App\Filament\Admin\Resources\Zeytin\OutstandingBills\Pages\ManageOutstandingBills;
use App\Models\OutstandingBill;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Tagihan yang sudah diterima tapi belum tentu dibayar — sheet Outstanding INV.
 *
 * Yang belum beres mengurangi saldo global, dan tidak dibatasi rentang
 * tanggal: utang bulan lalu tetap utang hari ini.
 */
class OutstandingBillResource extends Resource
{
    use BookkeepingResource;
    use ForAdmin;

    protected static ?string $model = OutstandingBill::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 50;

    public static function getNavigationLabel(): string
    {
        return __('zeytin.nav.outstanding');
    }

    public static function getModelLabel(): string
    {
        return __('zeytin.nav.outstanding');
    }

    public static function getPluralModelLabel(): string
    {
        return __('zeytin.nav.outstanding');
    }

    protected static function statuses(): array
    {
        return [
            'Need the payment' => __('zeytin.status.need'),
            'Waiting the payment' => __('zeytin.status.waiting'),
            'PAID' => __('zeytin.status.paid'),
        ];
    }

    /**
     * Jumlah tagihan yang belum beres, ditempel di menu sebagai lencana.
     *
     * Utang yang tidak kelihatan adalah utang yang terlupa. Disaring di PHP
     * karena statusnya teks bebas dari berkas Excel — "PAID", "Sudah lunas",
     * dan "settled" semuanya berarti beres.
     */
    public static function getNavigationBadge(): ?string
    {
        $unsettled = OutstandingBill::query()
            ->get()
            ->reject(fn (OutstandingBill $bill) => $bill->isSettled())
            ->count();

        return $unsettled ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('date')
                ->label(__('zeytin.field.date'))
                ->native(false)
                ->default(now())
                ->required(),

            DatePicker::make('due_date')
                ->label(__('zeytin.field.due_date'))
                ->native(false),

            static::vendorInput(),

            Select::make('status')
                ->label(__('zeytin.field.status'))
                ->options(static::statuses())
                ->default('Need the payment')
                ->native(false),

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

                TextColumn::make('due_date')
                    ->label(__('zeytin.field.due_date'))
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('item')
                    ->label(__('zeytin.field.item'))
                    ->description(fn (OutstandingBill $record) => $record->vendor ?: null)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('qty')
                    ->label(__('zeytin.field.qty'))
                    ->alignEnd(),

                static::totalColumn(),

                TextColumn::make('status')
                    ->label(__('zeytin.field.status'))
                    ->placeholder('—')
                    ->badge()
                    ->color(fn (OutstandingBill $record) => $record->isSettled() ? 'success' : 'warning'),

                // Dihitung dari statusnya, bukan kolom tersendiri: dua
                // penanda yang harus dijaga tetap sama akan berselisih.
                IconColumn::make('settled')
                    ->label(__('zeytin.status.paid'))
                    ->state(fn (OutstandingBill $record) => $record->isSettled())
                    ->boolean(),

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
            'index' => ManageOutstandingBills::route('/'),
        ];
    }
}

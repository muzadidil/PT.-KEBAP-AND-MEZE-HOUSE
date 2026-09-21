<?php

namespace App\Filament\Admin\Resources\Zeytin\PaymentMethods;

use App\Filament\Admin\Resources\Zeytin\Concerns\BookkeepingResource;
use App\Filament\Admin\Resources\Zeytin\PaymentMethods\Pages\ManagePaymentMethods;
use App\Models\PaymentMethodOption;
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

/**
 * Cara pembayaran ke pemasok, didaftar sekali lalu dipakai sebagai saran.
 *
 * Di berkas Excel isinya diketik ulang tiap baris ("Transfer", "COD",
 * "Cash"), jadi ejaannya gampang berbeda-beda dan pengelompokan laporannya
 * ikut berantakan.
 */
class PaymentMethodResource extends Resource
{
    use BookkeepingResource;

    protected static ?string $model = PaymentMethodOption::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 80;

    public static function getNavigationLabel(): string
    {
        return __('zeytin.nav.payment_methods');
    }

    public static function getModelLabel(): string
    {
        return __('zeytin.nav.payment_methods');
    }

    public static function getPluralModelLabel(): string
    {
        return __('zeytin.nav.payment_methods');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('zeytin.field.method'))
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(60),

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
                    ->label(__('zeytin.field.method'))
                    ->description(fn (PaymentMethodOption $record) => $record->note ?: null)
                    ->searchable()
                    ->sortable(),

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
            'index' => ManagePaymentMethods::route('/'),
        ];
    }
}

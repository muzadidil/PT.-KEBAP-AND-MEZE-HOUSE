<?php

namespace App\Filament\Admin\Resources\Zeytin\BalanceAdjustments;

use App\Filament\Admin\Concerns\ForAdmin;
use App\Filament\Admin\Resources\Zeytin\BalanceAdjustments\Pages\ManageBalanceAdjustments;
use App\Filament\Admin\Resources\Zeytin\Concerns\BookkeepingResource;
use App\Models\BalanceAdjustment;
use App\Support\Money;
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
 * Koreksi Saldo: penyesuaian bertanggal dan beralasan atas Sisa Cash Kasir
 * atau Saldo Bank (biaya transfer, selisih uang di laci, dan sejenisnya).
 * Tidak mengubah catatan lama; hanya menggeser saldo mulai tanggalnya.
 */
class BalanceAdjustmentResource extends Resource
{
    use BookkeepingResource;
    use ForAdmin;

    protected static ?string $model = BalanceAdjustment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?int $navigationSort = 14;

    public static function getNavigationLabel(): string
    {
        return __('balance_adjustment.nav');
    }

    public static function getModelLabel(): string
    {
        return __('balance_adjustment.nav');
    }

    public static function getPluralModelLabel(): string
    {
        return __('balance_adjustment.nav');
    }

    public static function accounts(): array
    {
        return [
            BalanceAdjustment::CASH => __('balance_adjustment.account.cash'),
            BalanceAdjustment::BANK => __('balance_adjustment.account.bank'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('date')
                ->label(__('balance_adjustment.field.date'))
                ->helperText(__('balance_adjustment.help.date'))
                ->native(false)
                ->default(now())
                ->required(),

            Select::make('account')
                ->label(__('balance_adjustment.field.account'))
                ->options(static::accounts())
                ->default(BalanceAdjustment::BANK)
                ->required()
                ->native(false),

            Select::make('direction')
                ->label(__('balance_adjustment.field.direction'))
                ->options([
                    'minus' => __('balance_adjustment.direction.minus'),
                    'plus' => __('balance_adjustment.direction.plus'),
                ])
                ->default('minus')
                ->required()
                ->native(false),

            TextInput::make('amount')
                ->label(__('balance_adjustment.field.amount'))
                ->prefix('Rp')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->required(),

            TextInput::make('reason')
                ->label(__('balance_adjustment.field.reason'))
                ->placeholder(__('balance_adjustment.help.reason'))
                ->required()
                ->maxLength(200)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label(__('balance_adjustment.field.date'))
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('account')
                    ->label(__('balance_adjustment.field.account'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => static::accounts()[$state] ?? $state)
                    ->color(fn (string $state) => $state === BalanceAdjustment::CASH ? 'success' : 'info'),

                TextColumn::make('amount')
                    ->label(__('balance_adjustment.field.amount'))
                    ->formatStateUsing(fn ($state, BalanceAdjustment $record) => ($record->direction === 'minus' ? '− ' : '+ ').Money::format((int) $state))
                    ->color(fn (BalanceAdjustment $record) => $record->direction === 'minus' ? 'danger' : 'success')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('reason')
                    ->label(__('balance_adjustment.field.reason'))
                    ->wrap()
                    ->searchable(),
            ])
            ->filters([
                static::periodFilter('date'),

                SelectFilter::make('account')
                    ->label(__('balance_adjustment.field.account'))
                    ->options(static::accounts()),
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
            'index' => ManageBalanceAdjustments::route('/'),
        ];
    }
}

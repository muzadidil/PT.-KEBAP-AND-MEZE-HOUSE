<?php

namespace App\Filament\Admin\Resources\CapitalEntries;

use App\Enums\CapitalDirection;
use App\Enums\PaymentMethod;
use App\Filament\Admin\Resources\CapitalEntries\Pages\ManageCapitalEntries;
use App\Models\CapitalEntry;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Setoran dan penarikan modal pemilik.
 *
 * Sengaja dipisah dari pengeluaran karena keduanya bukan beban: tidak
 * mengurangi laba, hanya memindahkan uang antara pemilik dan usaha. Kalau
 * dicampur ke pengeluaran, laba akan terlihat lebih kecil dari yang
 * sebenarnya setiap kali pemilik menarik uang.
 */
class CapitalEntryResource extends Resource
{
    protected static ?string $model = CapitalEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.group.expenses');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.capital');
    }

    public static function getModelLabel(): string
    {
        return __('nav.capital');
    }

    public static function getPluralModelLabel(): string
    {
        return __('nav.capital');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('entry_on')
                ->label(__('field.date'))
                ->native(false)
                ->default(now())
                ->required(),

            Select::make('owner_id')
                ->label(__('field.owner'))
                ->relationship('owner', 'name')
                ->preload()
                ->required(),

            Select::make('direction')
                ->label(__('field.direction'))
                ->options(CapitalDirection::class)
                ->default(CapitalDirection::In)
                ->required(),

            Select::make('method')
                ->label(__('field.method'))
                ->options(PaymentMethod::class)
                ->default(PaymentMethod::Cash)
                ->required(),

            TextInput::make('amount')
                ->label(__('field.amount'))
                ->prefix('Rp')
                ->numeric()
                ->minValue(0)
                ->required(),

            Textarea::make('note')
                ->label(__('field.note'))
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('entry_on', 'desc')
            ->columns([
                TextColumn::make('entry_on')
                    ->label(__('field.date'))
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('owner.name')
                    ->label(__('field.owner'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('direction')
                    ->label(__('field.direction'))
                    ->badge(),

                TextColumn::make('method')
                    ->label(__('field.method'))
                    ->badge(),

                TextColumn::make('amount')
                    ->label(__('field.amount'))
                    ->formatStateUsing(fn (int $state) => Money::format($state))
                    ->alignEnd()
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label(__('report.total'))
                            ->formatStateUsing(fn ($state) => Money::format((int) $state))
                    ),

                TextColumn::make('note')
                    ->label(__('field.note'))
                    ->placeholder('—')
                    ->limit(40),
            ])
            ->filters([
                SelectFilter::make('owner_id')
                    ->label(__('field.owner'))
                    ->relationship('owner', 'name')
                    ->preload(),

                SelectFilter::make('direction')
                    ->label(__('field.direction'))
                    ->options(CapitalDirection::class),
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
            'index' => ManageCapitalEntries::route('/'),
        ];
    }
}

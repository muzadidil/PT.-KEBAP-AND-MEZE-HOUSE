<?php

namespace App\Filament\Admin\Resources\Suppliers;

use App\Filament\Admin\Concerns\ForSuperAdmin;
use App\Filament\Admin\Resources\Suppliers\Pages\ManageSuppliers;
use App\Models\Supplier;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
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
 * Data pemasok. Tiap pengeluaran berkategori Pemasok boleh ditautkan ke satu
 * baris di sini, sehingga total belanja per pemasok terbaca tanpa perlu
 * laporan tersendiri.
 */
class SupplierResource extends Resource
{
    use ForSuperAdmin;

    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.group.master');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.suppliers');
    }

    public static function getModelLabel(): string
    {
        return __('field.supplier');
    }

    public static function getPluralModelLabel(): string
    {
        return __('nav.suppliers');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('field.name'))
                ->required()
                ->maxLength(120)
                ->columnSpanFull(),

            TextInput::make('supplies')
                ->label(__('field.supplies'))
                ->maxLength(160)
                ->columnSpanFull(),

            TextInput::make('contact_person')
                ->label(__('field.contact_person'))
                ->maxLength(120),

            TextInput::make('phone')
                ->label(__('field.phone'))
                ->tel()
                ->maxLength(30),

            TextInput::make('email')
                ->label(__('field.email'))
                ->email()
                ->maxLength(120),

            Toggle::make('active')
                ->label(__('field.active'))
                ->default(true),

            Textarea::make('address')
                ->label(__('field.address'))
                ->rows(2)
                ->columnSpanFull(),

            Textarea::make('note')
                ->label(__('field.note'))
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('field.name'))
                    ->description(fn (Supplier $record) => $record->supplies)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('contact_person')
                    ->label(__('field.contact_person'))
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('phone')
                    ->label(__('field.phone'))
                    ->placeholder('—')
                    ->searchable(),

                // Total belanja ke pemasok ini, dihitung dari pengeluaran —
                // tidak ada angka yang disimpan terpisah dan bisa menyimpang.
                TextColumn::make('expenses_sum_amount')
                    ->label(__('report.total'))
                    ->sum('expenses', 'amount')
                    ->formatStateUsing(fn ($state) => Money::format((int) $state))
                    ->alignEnd(),

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
            'index' => ManageSuppliers::route('/'),
        ];
    }
}

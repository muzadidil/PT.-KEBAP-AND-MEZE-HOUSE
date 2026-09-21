<?php

namespace App\Filament\Admin\Resources\PayComponents;

use App\Filament\Admin\Concerns\ForSuperAdmin;
use App\Filament\Admin\Resources\PayComponents\Pages\ManagePayComponents;
use App\Models\PayComponent;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * Item pendapatan dan potongan untuk slip gaji — kartu "Item Pendapatan"
 * dan "Item Potongan" di pengaturan aplikasi Slip Gaji.
 *
 * Bedanya dari aslinya: itemnya bisa diubah, bukan cuma dihapus. Menghapus
 * item tidak mengubah slip lama, karena slip menyimpan teks dan nominalnya
 * sendiri.
 */
class PayComponentResource extends Resource
{
    use ForSuperAdmin;

    protected static ?string $model = PayComponent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('payroll.nav.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('payroll.nav.components');
    }

    public static function getModelLabel(): string
    {
        return __('payroll.nav.components');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payroll.nav.components');
    }

    /** @return array<string, string> */
    public static function types(): array
    {
        return [
            PayComponent::EARNING => __('payroll.type.earning'),
            PayComponent::DEDUCTION => __('payroll.type.deduction'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->label(__('payroll.field.type'))
                ->options(static::types())
                ->default(PayComponent::EARNING)
                ->native(false)
                ->required()
                ->live(),

            TextInput::make('name')
                ->label(__('payroll.field.component'))
                ->required()
                ->maxLength(80)
                // Nama boleh sama asal jenisnya beda: "Lembur" bisa jadi
                // pendapatan, dan juga potongan kalau ada yang kelebihan.
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('type', $get('type'))),

            TextInput::make('default_amount')
                ->label(__('payroll.field.default_amount'))
                ->helperText(__('payroll.help.default_amount'))
                ->prefix('Rp')
                ->numeric()
                ->minValue(0)
                ->default(0),

            Toggle::make('fixed')
                ->label(__('payroll.field.fixed'))
                ->helperText(__('payroll.help.fixed')),

            Toggle::make('active')
                ->label(__('field.active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->defaultGroup('type')
            ->groups([
                Group::make('type')
                    ->label(__('payroll.field.type'))
                    ->getTitleFromRecordUsing(fn (PayComponent $record) => static::types()[$record->type] ?? $record->type),
            ])
            ->columns([
                TextColumn::make('name')
                    ->label(__('payroll.field.component'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('default_amount')
                    ->label(__('payroll.field.default_amount'))
                    ->formatStateUsing(fn ($state) => $state ? Money::format((int) $state) : '—')
                    ->alignEnd(),

                IconColumn::make('fixed')
                    ->label(__('payroll.field.fixed'))
                    ->boolean(),

                IconColumn::make('active')
                    ->label(__('field.active'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('payroll.field.type'))
                    ->options(static::types()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePayComponents::route('/'),
        ];
    }
}

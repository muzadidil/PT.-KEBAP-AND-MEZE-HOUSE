<?php

namespace App\Filament\Admin\Resources\Owners;

use App\Filament\Admin\Resources\Owners\Pages\ManageOwners;
use App\Models\Owner;
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
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Pemilik dan porsi tanggungannya. Porsi disimpan sebagai data, bukan angka
 * mati di kode, supaya kesepakatan 60/40 bisa diubah tanpa menyentuh kode.
 */
class OwnerResource extends Resource
{
    protected static ?string $model = Owner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.group.master');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.owners');
    }

    public static function getModelLabel(): string
    {
        return __('field.owner');
    }

    public static function getPluralModelLabel(): string
    {
        return __('nav.owners');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('field.name'))
                ->required()
                ->maxLength(80),

            TextInput::make('share_percent')
                ->label(__('field.share_percent'))
                ->helperText(__('field.help.share_percent'))
                ->suffix('%')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->required()
                ->default(0),

            Toggle::make('active')
                ->label(__('field.active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('share_percent', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('field.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('share_percent')
                    ->label(__('field.share_percent'))
                    ->suffix('%')
                    ->alignEnd()
                    ->sortable()
                    // Jumlah seluruh porsi semestinya 100%. Ditampilkan di
                    // kaki tabel supaya salah ketik langsung kelihatan.
                    ->summarize(Sum::make()->label(__('report.total'))->suffix('%')),

                IconColumn::make('active')
                    ->label(__('field.active'))
                    ->boolean(),
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
            'index' => ManageOwners::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Admin\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\Users\Pages\ManageUsers;
use App\Models\User;
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
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 50;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.group.master');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.users');
    }

    public static function getModelLabel(): string
    {
        return __('nav.users');
    }

    public static function getPluralModelLabel(): string
    {
        return __('nav.users');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('field.name'))
                ->required()
                ->maxLength(120),

            TextInput::make('email')
                ->label(__('field.email'))
                ->email()
                ->required()
                ->unique(ignoreRecord: true),

            /*
            | Kosongkan saat menyunting berarti kata sandi tidak diubah.
            | Tanpa aturan ini, menyunting nama pengguna saja akan diam-diam
            | mengosongkan kata sandinya.
            */
            TextInput::make('password')
                ->label(__('field.password'))
                ->password()
                ->revealable()
                ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                ->dehydrated(fn (?string $state) => filled($state))
                ->required(fn (string $operation) => $operation === 'create')
                ->minLength(8),

            Select::make('role')
                ->label(__('field.role'))
                ->options(UserRole::class)
                ->default(UserRole::Cashier)
                ->required(),

            Select::make('locale')
                ->label(__('field.language'))
                ->options(config('app.locale_names'))
                ->default(config('app.locale'))
                ->required(),

            Toggle::make('active')
                ->label(__('field.active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('field.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('field.email'))
                    ->searchable(),

                TextColumn::make('role')
                    ->label(__('field.role'))
                    ->badge(),

                TextColumn::make('locale')
                    ->label(__('field.language'))
                    ->formatStateUsing(fn (string $state) => config("app.locale_names.{$state}", $state)),

                IconColumn::make('active')
                    ->label(__('field.active'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label(__('field.role'))
                    ->options(UserRole::class),
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
            'index' => ManageUsers::route('/'),
        ];
    }
}

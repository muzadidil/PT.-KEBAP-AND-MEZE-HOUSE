<?php

namespace App\Filament\Admin\Resources\Categories;

use App\Filament\Admin\Concerns\ForSuperAdmin;
use App\Filament\Admin\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use App\Support\Excel\Column;
use App\Support\Excel\ExcelSheet;
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
use Filament\Tables\Table;
use UnitEnum;

class CategoryResource extends Resource
{
    use ForSuperAdmin;

    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.group.master');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.categories');
    }

    public static function getModelLabel(): string
    {
        return __('field.category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('nav.categories');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name_en')
                ->label(__('field.name_en'))
                ->required()
                ->maxLength(80),

            TextInput::make('name_id')
                ->label(__('field.name_id'))
                ->helperText(__('field.help.name_id'))
                ->maxLength(80),

            TextInput::make('sort')
                ->label(__('field.sort'))
                ->numeric()
                ->default(0),

            Toggle::make('active')
                ->label(__('field.active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name_en')
                    ->label(__('field.name_en'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name_id')
                    ->label(__('field.name_id'))
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('products_count')
                    ->label(__('nav.products'))
                    ->counts('products')
                    ->alignEnd(),

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

    public static function excel(): ExcelSheet
    {
        return ExcelSheet::make(Category::class, __('nav.categories'))
            ->columns([
                Column::text('name_en', 'field.name_en')->required()->maxLength(80)->width(26),
                Column::text('name_id', 'field.name_id')->maxLength(80)->width(26),
                Column::number('sort', 'field.sort'),
                Column::boolean('active', 'field.active')->default(true),
            ])
            ->matchBy(['name_en'])
            ->examples([['name_en' => 'Kebab', 'name_id' => 'Kebab', 'sort' => 1, 'active' => true]]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCategories::route('/'),
        ];
    }
}

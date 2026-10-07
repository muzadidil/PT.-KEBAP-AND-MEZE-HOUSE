<?php

namespace App\Filament\Admin\Resources\Zeytin\OwnerSalaries;

use App\Filament\Admin\Concerns\ForAdmin;
use App\Filament\Admin\Resources\Zeytin\Concerns\BookkeepingResource;
use App\Filament\Admin\Resources\Zeytin\OwnerSalaries\Pages\ManageOwnerSalaries;
use App\Models\Owner;
use App\Models\OwnerSalary;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

/**
 * Gaji pemilik (Aslan, Leo): pemilik, periode (tahun dan bulan), dan
 * nominal. Satu baris per pemilik per bulan. Berdiri sendiri: belum ikut
 * hitungan Buku Besar Bulanan.
 */
class OwnerSalaryResource extends Resource
{
    use BookkeepingResource;
    use ForAdmin;

    protected static ?string $model = OwnerSalary::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?int $navigationSort = 45;

    public static function getNavigationLabel(): string
    {
        return __('zeytin.nav.owner_salary');
    }

    public static function getModelLabel(): string
    {
        return __('zeytin.nav.owner_salary');
    }

    public static function getPluralModelLabel(): string
    {
        return __('zeytin.nav.owner_salary');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('owner_id')
                ->label(__('zeytin.field.owner'))
                ->options(fn () => Owner::query()->where('active', true)->orderBy('name')->pluck('name', 'id'))
                ->required()
                ->native(false),

            DatePicker::make('month')
                ->label(__('zeytin.field.period'))
                ->helperText(__('zeytin.help.month'))
                ->native(false)
                ->displayFormat('F Y')
                ->default(now()->startOfMonth())
                ->required()
                // Tanggal mana pun yang dipilih dipotong ke awal bulannya.
                ->dehydrateStateUsing(fn ($state) => $state ? Carbon::parse($state)->startOfMonth() : null)
                // Satu pemilik, satu gaji per bulan.
                ->rules([
                    fn (Get $get, ?OwnerSalary $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                        $exists = OwnerSalary::query()
                            ->where('owner_id', $get('owner_id'))
                            ->whereDate('month', Carbon::parse($value)->startOfMonth()->toDateString())
                            ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                            ->exists();

                        if ($exists) {
                            $fail(__('zeytin.help.owner_salary_exists'));
                        }
                    },
                ]),

            TextInput::make('amount')
                ->label(__('zeytin.field.amount'))
                ->prefix('Rp')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->required()
                ->columnSpanFull(),

            static::noteField(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('month', 'desc')
            ->columns([
                TextColumn::make('month')
                    ->label(__('zeytin.field.period'))
                    ->date('F Y')
                    ->sortable(),

                TextColumn::make('owner.name')
                    ->label(__('zeytin.field.owner'))
                    ->searchable(),

                static::totalColumn('amount')->label(__('zeytin.field.amount')),

                TextColumn::make('note')
                    ->label(__('zeytin.field.note'))
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->filters([
                static::periodFilter('month'),

                SelectFilter::make('owner_id')
                    ->label(__('zeytin.field.owner'))
                    ->options(fn () => Owner::query()->orderBy('name')->pluck('name', 'id')),
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
            'index' => ManageOwnerSalaries::route('/'),
        ];
    }
}

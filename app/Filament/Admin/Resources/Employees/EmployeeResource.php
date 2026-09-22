<?php

namespace App\Filament\Admin\Resources\Employees;

use App\Filament\Admin\Concerns\ForSuperAdmin;
use App\Filament\Admin\Resources\Employees\Pages\ManageEmployees;
use App\Filament\Admin\Resources\Zeytin\Payrolls\PayrollResource;
use App\Models\Employee;
use App\Support\Excel\Column;
use App\Support\Excel\ExcelSheet;
use App\Support\Money;
use BackedEnum;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Data karyawan — tab "Karyawan" di aplikasi Slip Gaji: nama, NIK,
 * jabatan, dan gaji pokok yang otomatis terisi saat membuat slip.
 *
 * Tidak ada tombol hapus: karyawan yang keluar dinonaktifkan, supaya slip
 * lamanya tetap menunjuk ke orangnya.
 */
class EmployeeResource extends Resource
{
    use ForSuperAdmin;

    protected static ?string $model = Employee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('payroll.nav.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('payroll.nav.employees');
    }

    public static function getModelLabel(): string
    {
        return __('payroll.nav.employees');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payroll.nav.employees');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('payroll.field.name'))
                ->required()
                ->maxLength(120)
                ->columnSpanFull(),

            TextInput::make('nik')
                ->label(__('payroll.field.nik'))
                ->helperText(__('payroll.help.nik'))
                ->maxLength(40)
                ->unique(ignoreRecord: true),

            TextInput::make('position')
                ->label(__('payroll.field.position'))
                ->maxLength(80),

            Select::make('section')
                ->label(__('payroll.field.section'))
                ->options(PayrollResource::sections())
                ->native(false),

            TextInput::make('basic_salary')
                ->label(__('payroll.field.basic_salary'))
                ->prefix('Rp')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),

            Toggle::make('active')
                ->label(__('field.active'))
                ->helperText(__('payroll.help.active'))
                ->default(true)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('payroll.field.name'))
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nik')
                    ->label(__('payroll.field.nik'))
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('position')
                    ->label(__('payroll.field.position'))
                    ->placeholder('—'),

                TextColumn::make('section')
                    ->label(__('payroll.field.section'))
                    ->formatStateUsing(fn (?string $state) => PayrollResource::sections()[$state] ?? $state)
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('basic_salary')
                    ->label(__('payroll.field.basic_salary'))
                    ->formatStateUsing(fn ($state) => Money::format((int) $state))
                    ->alignEnd()
                    ->sortable(),

                IconColumn::make('active')
                    ->label(__('field.active'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('section')
                    ->label(__('payroll.field.section'))
                    ->options(PayrollResource::sections()),

                TernaryFilter::make('active')
                    ->label(__('field.active'))
                    ->default(true),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    /** Dicocokkan lewat NIK dulu, lalu lewat nama. */
    public static function excel(): ExcelSheet
    {
        return ExcelSheet::make(Employee::class, __('payroll.nav.employees'))
            ->columns([
                Column::text('name', 'payroll.field.name')->required()->maxLength(120)->width(28),
                Column::text('nik', 'payroll.field.nik')->maxLength(40)->asText(),
                Column::text('position', 'payroll.field.position')->maxLength(80),
                Column::choice('section', 'payroll.field.section', fn () => PayrollResource::sections()),
                Column::money('basic_salary', 'payroll.field.basic_salary'),
                Column::boolean('active', 'field.active')->default(true),
            ])
            ->matchBy(['nik'], ['name'])
            ->examples([[
                'name' => 'Sinta Dewi', 'nik' => 'ZT-001', 'position' => 'Waiter',
                'section' => 'Front Staff', 'basic_salary' => 3_000_000, 'active' => true,
            ]]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmployees::route('/'),
        ];
    }
}

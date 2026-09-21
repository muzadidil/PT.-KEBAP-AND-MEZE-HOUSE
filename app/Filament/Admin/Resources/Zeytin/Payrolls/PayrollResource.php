<?php

namespace App\Filament\Admin\Resources\Zeytin\Payrolls;

use App\Filament\Admin\Concerns\ForSuperAdmin;
use App\Filament\Admin\Resources\Zeytin\Concerns\BookkeepingResource;
use App\Filament\Admin\Resources\Zeytin\Payrolls\Pages\ManagePayrolls;
use App\Models\Payroll;
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
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Gaji per orang per bulan — sheet Payroll.
 *
 * Terpisah dari laporan Salary yang sudah ada: laporan itu membaca tabel
 * `expenses`, yaitu uang gaji yang keluar dari kas. Yang di sini adalah
 * daftar orangnya beserta gaji pokok dan potongan BPJS, yang di berkas
 * Excel memang sheet tersendiri.
 */
class PayrollResource extends Resource
{
    use BookkeepingResource;
    use ForSuperAdmin;

    protected static ?string $model = Payroll::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 40;

    /**
     * Gaji per orang hanya untuk Super Admin; Admin cukup melihat totalnya
     * di Buku Besar Bulanan. Karena itu halamannya di grup Penggajian,
     * bersama karyawan dan slip gaji, bukan di Pembukuan Bulanan.
     */
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('payroll.nav.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('zeytin.nav.payroll');
    }

    public static function getModelLabel(): string
    {
        return __('zeytin.nav.payroll');
    }

    public static function getPluralModelLabel(): string
    {
        return __('zeytin.nav.payroll');
    }

    public static function sections(): array
    {
        return [
            'Front Staff' => __('zeytin.section.front'),
            'Kitchen Staff' => __('zeytin.section.kitchen'),
            'Owner' => __('zeytin.section.owner'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('month')
                ->label(__('zeytin.field.month'))
                ->helperText(__('zeytin.help.month'))
                ->native(false)
                ->displayFormat('M Y')
                ->default(now()->startOfMonth())
                ->required()
                // Bulan disimpan sebagai tanggal 1, jadi tanggal mana pun
                // yang dipilih dipotong ke awal bulannya.
                ->dehydrateStateUsing(fn ($state) => $state ? Carbon::parse($state)->startOfMonth() : null),

            Select::make('section')
                ->label(__('zeytin.field.section'))
                ->options(static::sections())
                ->default('Front Staff')
                ->native(false),

            TextInput::make('name')
                ->label(__('zeytin.field.name'))
                ->required()
                ->maxLength(120)
                ->columnSpanFull(),

            static::money('basic'),

            static::money('bpjs'),

            static::sourceField(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('month', 'desc')
            ->columns([
                TextColumn::make('month')
                    ->label(__('zeytin.field.month'))
                    ->date('M Y')
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('zeytin.field.name'))
                    ->searchable(),

                TextColumn::make('section')
                    ->label(__('zeytin.field.section'))
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),

                static::moneyColumn('basic'),

                static::moneyColumn('bpjs'),

                static::totalColumn('grand_total'),

                static::sourceColumn(),
            ])
            ->filters([
                static::periodFilter('month'),

                SelectFilter::make('section')
                    ->label(__('zeytin.field.section'))
                    ->options(static::sections()),

                static::sourceFilter(),
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
            'index' => ManagePayrolls::route('/'),
        ];
    }
}

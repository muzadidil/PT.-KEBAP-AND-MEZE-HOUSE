<?php

namespace App\Filament\Admin\Resources\Zeytin\DailyIncomes;

use App\Filament\Admin\Concerns\ForAdmin;
use App\Filament\Admin\Resources\Zeytin\Concerns\BookkeepingResource;
use App\Filament\Admin\Resources\Zeytin\DailyIncomes\Pages\ManageDailyIncomes;
use App\Models\DailyIncome;
use App\Support\Excel\ExcelSource;
use App\Support\Money;
use App\Support\Zeytin\Channels;
use App\Support\Zeytin\Workbook\SpecSource;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Rekap pemasukan per hari, sepadan dengan sheet Income.
 *
 * Satu baris per tanggal. Menyimpan tanggal yang sudah ada berarti mengganti
 * barisnya, bukan menambah baris kedua — sama seperti di berkas Excel, di
 * mana satu hari memang satu baris.
 */
class DailyIncomeResource extends Resource
{
    use BookkeepingResource;
    use ForAdmin;

    protected static ?string $model = DailyIncome::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('zeytin.nav.daily_income');
    }

    public static function getModelLabel(): string
    {
        return __('zeytin.nav.daily_income');
    }

    public static function getPluralModelLabel(): string
    {
        return __('zeytin.nav.daily_income');
    }

    public static function form(Schema $schema): Schema
    {
        $channels = [];

        foreach (Channels::all() as $channel) {
            $channels[] = static::money($channel['key'], $channel['label'])
                // Petty cash tidak ikut Total Sales. Dikatakan di formulirnya
                // sendiri, bukan hanya di dokumentasi, supaya tidak dikira
                // angkanya hilang karena salah hitung.
                ->helperText($channel['in_sales'] ? null : __('zeytin.hint.petty_cash'));
        }

        return $schema->components([
            DatePicker::make('date')
                ->label(__('zeytin.field.date'))
                ->native(false)
                ->default(now())
                ->required()
                ->unique(ignoreRecord: true)
                ->columnSpanFull(),

            ...$channels,

            TextInput::make('note')
                ->label(__('zeytin.field.note'))
                ->maxLength(200)
                ->columnSpanFull(),

            static::sourceField(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $channels = [];

        foreach (Channels::all() as $channel) {
            $channels[] = static::moneyColumn($channel['key'], $channel['label'])
                ->toggleable(isToggledHiddenByDefault: ! $channel['in_sales']);
        }

        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label(__('zeytin.field.date'))
                    ->date('d/m/Y')
                    ->sortable(),

                ...$channels,

                // Turunan, jadi dihitung di sini dan tidak pernah disimpan.
                TextColumn::make('total_sales')
                    ->label(__('zeytin.col.total_sales'))
                    ->state(fn (DailyIncome $record) => Money::format($record->totalSales()))
                    ->alignEnd()
                    ->weight('bold'),

                static::sourceColumn(),
            ])
            ->filters([
                static::periodFilter(),
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

    /**
     * Template dan impor Excel: sheet "Income" dari template bulanan, dibaca
     * pengimpor yang sama dengan menu Impor Excel.
     */
    public static function excel(): ExcelSource
    {
        return SpecSource::make('Income', __('zeytin.nav.daily_income'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDailyIncomes::route('/'),
        ];
    }
}

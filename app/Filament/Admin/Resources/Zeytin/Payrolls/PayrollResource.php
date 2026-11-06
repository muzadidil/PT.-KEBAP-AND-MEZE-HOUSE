<?php

namespace App\Filament\Admin\Resources\Zeytin\Payrolls;

use App\Filament\Admin\Concerns\ForSuperAdmin;
use App\Filament\Admin\Resources\Zeytin\Concerns\BookkeepingResource;
use App\Filament\Admin\Resources\Zeytin\Payrolls\Pages\ManagePayrolls;
use App\Models\Payroll;
use App\Support\Excel\ExcelSource;
use App\Support\Money;
use App\Support\Zeytin\Workbook\SpecSource;
use BackedEnum;
use Filament\Actions\BulkAction;
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
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
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

                TextColumn::make('paid_on')
                    ->label(__('payroll_paid.col'))
                    ->date('d M Y')
                    ->placeholder(__('payroll_paid.unpaid'))
                    ->description(fn (Payroll $record) => $record->paid_via ? __('payroll_paid.via.'.$record->paid_via) : null)
                    ->color(fn (Payroll $record) => $record->paid_on ? 'success' : 'gray')
                    ->sortable(),

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
                    BulkAction::make('markPaid')
                        ->label(__('payroll_paid.bulk.paid'))
                        ->icon(Heroicon::OutlinedBanknotes)
                        ->modalHeading(__('payroll_paid.bulk.paid_heading'))
                        ->modalDescription(__('payroll_paid.bulk.paid_description'))
                        ->modalSubmitActionLabel(__('payroll_paid.submit'))
                        ->fillForm(fn () => ['paid_on' => now()->toDateString(), 'paid_via' => Payroll::VIA_BANK])
                        ->schema(static::paymentFields())
                        ->action(function (Collection $records, array $data) {
                            static::markPaid($records->where('paid_on', null), (string) $data['paid_on'], (string) $data['paid_via']);
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('markUnpaid')
                        ->label(__('payroll_paid.bulk.unpaid'))
                        ->icon(Heroicon::OutlinedArrowUturnLeft)
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading(__('payroll_paid.bulk.unpaid_heading'))
                        ->modalDescription(__('payroll_paid.bulk.unpaid_description'))
                        ->action(function (Collection $records) {
                            $records->each->update(['paid_on' => null, 'paid_via' => null]);

                            Notification::make()->success()->title(__('payroll_paid.unmarked', ['count' => $records->count()]))->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    public static function paymentFields(): array
    {
        return [
            DatePicker::make('paid_on')
                ->label(__('payroll_paid.field.paid_on'))
                ->helperText(__('payroll_paid.field.paid_on_help'))
                ->native(false)
                ->displayFormat('d M Y')
                ->maxDate(now()->addDay())
                ->required(),

            Select::make('paid_via')
                ->label(__('payroll_paid.field.paid_via'))
                ->options([
                    Payroll::VIA_BANK => __('payroll_paid.via.bank'),
                    Payroll::VIA_CASH => __('payroll_paid.via.cash'),
                ])
                ->default(Payroll::VIA_BANK)
                ->native(false)
                ->required(),
        ];
    }

    /**
     * Mencatat gaji sebagai sudah dibayar: mulai tanggal itu, jumlahnya
     * mengurangi Saldo Bank (atau Sisa Cash). Hanya baris yang belum
     * bertanggal bayar, jadi gaji yang sama tidak terhitung dua kali.
     *
     * @param  iterable<int, Payroll>  $rows
     */
    public static function markPaid(iterable $rows, string $date, string $via): void
    {
        $rows = collect($rows)->filter(fn (Payroll $row) => $row->paid_on === null);

        if ($rows->isEmpty()) {
            Notification::make()->warning()->title(__('payroll_paid.nothing'))->body(__('payroll_paid.nothing_body'))->send();

            return;
        }

        $rows->each->update(['paid_on' => $date, 'paid_via' => $via]);

        Notification::make()
            ->success()
            ->title(__('payroll_paid.marked'))
            ->body(__('payroll_paid.marked_body', [
                'count' => $rows->count(),
                'total' => Money::format((int) $rows->sum('grand_total')),
                'date' => Carbon::parse($date)->translatedFormat('j M Y'),
                'via' => __('payroll_paid.via.'.$via),
            ]))
            ->send();
    }

    /**
     * Template dan impor Excel: sheet "Payroll" dari template bulanan, dibaca
     * pengimpor yang sama dengan menu Impor Excel.
     */
    public static function excel(): ExcelSource
    {
        return SpecSource::make('Payroll', __('zeytin.nav.payroll'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePayrolls::route('/'),
        ];
    }
}

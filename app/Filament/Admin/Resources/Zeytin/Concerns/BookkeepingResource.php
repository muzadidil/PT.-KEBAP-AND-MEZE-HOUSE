<?php

namespace App\Filament\Admin\Resources\Zeytin\Concerns;

use App\Models\Employee;
use App\Models\PaymentMethodOption;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Support\Money;
use App\Support\Zeytin\RecordSource;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Bagian yang sama di ketujuh halaman pembukuan bulanan.
 *
 * Dikumpulkan di sini bukan demi ringkas, tapi supaya ketujuhnya tidak bisa
 * berbeda: kolom uang diformat dengan cara yang sama, penanda asal baris
 * berarti hal yang sama, dan menyimpan lewat formulir selalu menandai
 * barisnya sebagai ketikan orang di ketujuh halaman itu.
 */
trait BookkeepingResource
{
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('zeytin.nav.group');
    }

    /**
     * Menyimpan lewat formulir selalu menandai barisnya sebagai ketikan orang.
     *
     * Itu yang membuat kalimat "impor tidak menimpa ketikan Anda" benar-benar
     * berlaku: baris hasil impor yang Anda betulkan di sini berhenti jadi
     * milik berkas Excel, jadi impor bulan depan tidak mengembalikannya ke
     * angka yang salah. Anda yang terakhir menyentuhnya.
     */
    protected static function sourceField(): Hidden
    {
        return Hidden::make('source')
            ->dehydrateStateUsing(fn () => RecordSource::MANUAL);
    }

    protected static function money(string $name, ?string $label = null): TextInput
    {
        return TextInput::make($name)
            ->label($label ?? __('zeytin.field.'.$name))
            ->prefix('Rp')
            ->numeric()
            ->default(0);
    }

    protected static function moneyColumn(string $name, ?string $label = null): TextColumn
    {
        return TextColumn::make($name)
            ->label($label ?? __('zeytin.field.'.$name))
            ->formatStateUsing(fn ($state) => Money::format((int) $state))
            ->alignEnd()
            ->sortable();
    }

    /** Kolom uang yang ikut dijumlahkan di kaki tabel. */
    protected static function totalColumn(string $name = 'total'): TextColumn
    {
        return static::moneyColumn($name)->summarize(
            Sum::make()
                ->label(__('report.total'))
                ->formatStateUsing(fn ($state) => Money::format((int) $state)),
        );
    }

    /**
     * Penanda dari mana barisnya datang.
     *
     * Bukan hiasan: yang bertanda Excel boleh diganti impor berikutnya, yang
     * bertanda ketikan tidak. Tanpa ditampilkan, satu-satunya cara tahu
     * sebuah baris akan hilang setelah impor berikutnya adalah menunggu ia
     * hilang.
     */
    protected static function sourceColumn(): TextColumn
    {
        return TextColumn::make('source')
            ->label(__('zeytin.field.source'))
            ->badge()
            ->formatStateUsing(fn (string $state) => __('zeytin.source.'.$state))
            ->color(fn (string $state) => $state === RecordSource::IMPORT ? 'info' : 'gray');
    }

    protected static function sourceFilter(): SelectFilter
    {
        return SelectFilter::make('source')
            ->label(__('zeytin.field.source'))
            ->options(RecordSource::options());
    }

    protected static function periodFilter(string $column = 'date'): Filter
    {
        return Filter::make('period')
            ->schema([
                DatePicker::make('from')->label(__('report.from'))->native(false),
                DatePicker::make('until')->label(__('report.to'))->native(false),
            ])
            ->query(fn (Builder $query, array $data) => $query
                ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate($column, '>=', $date))
                ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate($column, '<=', $date)));
    }

    /**
     * Kotak nama pemasok: bisa diketik bebas, dengan daftar saran.
     *
     * Sengaja bukan daftar pilihan tertutup. Nama pemasok di berkas klien
     * tidak rapi, dan daftar tertutup hanya akan membuat orang berhenti
     * mencatat begitu ada nama yang belum terdaftar.
     */
    protected static function vendorInput(string $name = 'vendor'): TextInput
    {
        return TextInput::make($name)
            ->label(__('zeytin.field.vendor'))
            ->maxLength(160)
            ->datalist(fn () => Supplier::query()->orderBy('name')->pluck('name')->all());
    }

    /**
     * Kotak nama barang, ikut mengisi satuan dan harga.
     *
     * Yang sudah terisi tidak ditimpa: harga pemasok berubah terus, dan
     * formulir yang memaksakan harga lama membuat catatannya salah dengan
     * rapi. Jadi saran hanya mengisi kotak yang masih kosong.
     */
    protected static function itemInput(bool $withUnit = true): TextInput
    {
        return TextInput::make('item')
            ->label(__('zeytin.field.item'))
            ->required()
            ->maxLength(200)
            ->datalist(fn () => PurchaseItem::query()->orderBy('name')->pluck('name')->all())
            ->live(onBlur: true)
            ->afterStateUpdated(function (?string $state, Get $get, Set $set) use ($withUnit) {
                $master = PurchaseItem::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $state))])
                    ->first();

                if (! $master) {
                    return;
                }

                if ($withUnit && blank($get('unit'))) {
                    $set('unit', $master->unit);
                }

                if (! (int) $get('price')) {
                    $set('price', $master->price);
                }
            });
    }

    protected static function methodInput(string $name = 'method'): TextInput
    {
        return TextInput::make($name)
            ->label(__('zeytin.field.method'))
            ->maxLength(60)
            ->datalist(fn () => PaymentMethodOption::query()
                ->where('active', true)
                ->orderBy('name')
                ->pluck('name')
                ->all());
    }

    /** Catatan bebas pada pengeluaran — "gelas pecah", "ganti yang hilang". */
    protected static function noteField(): TextInput
    {
        return TextInput::make('note')
            ->label(__('zeytin.field.note'))
            ->placeholder(__('zeytin.help.note_example'))
            ->maxLength(200)
            ->columnSpanFull();
    }

    /**
     * Potong gaji karyawan karena pengeluaran ini.
     *
     * Disimpan sebagai EmployeeDeduction yang terhubung ke baris ini, lalu
     * otomatis masuk slip gaji karyawan itu. Mengosongkan karyawannya
     * menghapus potongannya. Setelah masuk slip, isiannya dikunci: slip
     * yang sudah dibuat tidak boleh berubah diam-diam dari halaman lain.
     */
    protected static function deductionFields(): Group
    {
        // Di dalam grup berelasi, $record adalah potongannya sendiri
        // (EmployeeDeduction), bukan baris pengeluaran.
        $applied = fn (?Model $record) => filled($record?->payslip_id);

        return Group::make([
            Select::make('employee_id')
                ->label(__('zeytin.field.deduct_employee'))
                ->options(fn () => Employee::query()->active()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->placeholder(__('zeytin.help.no_deduction'))
                ->live()
                ->disabled($applied),

            TextInput::make('amount')
                ->label(__('zeytin.field.deduct_amount'))
                ->prefix('Rp')
                ->numeric()
                ->minValue(1)
                ->required(fn (Get $get) => filled($get('employee_id')))
                ->visible(fn (Get $get) => filled($get('employee_id')))
                ->disabled($applied)
                ->helperText(fn (?Model $record) => $applied($record)
                    ? __('zeytin.help.deduction_applied', ['number' => $record->payslip?->number])
                    : __('zeytin.help.deduction')),
        ])
            ->relationship('deduction', condition: fn (?array $state) => filled($state['employee_id'] ?? null))
            ->columns(2)
            ->columnSpanFull();
    }

    /** Catatan dan potongan gaji di tabel: di bawah nama barangnya. */
    protected static function noteDescription(Model $record): ?string
    {
        $parts = array_filter([
            $record->vendor ?: null,
            $record->note ? '“'.$record->note.'”' : null,
            $record->deduction
                ? __('zeytin.help.deducted_from', [
                    'name' => $record->deduction->employee?->name,
                    'amount' => Money::format($record->deduction->amount),
                ])
                : null,
        ]);

        return $parts ? implode(' · ', $parts) : null;
    }
}

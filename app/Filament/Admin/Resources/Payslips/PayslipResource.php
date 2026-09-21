<?php

namespace App\Filament\Admin\Resources\Payslips;

use App\Filament\Admin\Concerns\ForSuperAdmin;
use App\Filament\Admin\Resources\Payslips\Pages\CreatePayslip;
use App\Filament\Admin\Resources\Payslips\Pages\EditPayslip;
use App\Filament\Admin\Resources\Payslips\Pages\ListPayslips;
use App\Models\Employee;
use App\Models\PayComponent;
use App\Models\Payslip;
use App\Support\Money;
use App\Support\Payroll\PayslipPdf;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Slip gaji — tab "Buat Slip" dan "Riwayat" di aplikasi Slip Gaji.
 *
 * Pilih karyawan, gaji pokoknya terisi sendiri; tambah tunjangan dan
 * potongan dari daftar Komponen Gaji atau ketik bebas; pratinjau kertas
 * slipnya berubah seiring isian. PDF-nya dicetak dari templat yang sama.
 *
 * Hanya Super Admin: gaji per orang tidak boleh terlihat oleh Admin.
 */
class PayslipResource extends Resource
{
    use ForSuperAdmin;

    protected static ?string $model = Payslip::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('payroll.nav.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('payroll.nav.payslips');
    }

    public static function getModelLabel(): string
    {
        return __('payroll.nav.payslips');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payroll.nav.payslips');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'xl' => 2])
            ->components([
                Group::make([
                    Section::make()
                        ->columns(2)
                        ->schema([
                            Select::make('employee_id')
                                ->label(__('payroll.field.employee'))
                                ->helperText(__('payroll.help.snapshot'))
                                // Karyawan nonaktif tidak ditawarkan, kecuali
                                // pemilik slip yang sedang diubah.
                                ->relationship('employee', 'name', fn (Builder $query, ?Payslip $record) => $query
                                    ->where(fn (Builder $q) => $q->where('active', true)->orWhere('id', $record?->employee_id))
                                    ->orderBy('name'))
                                ->getOptionLabelFromRecordUsing(fn (Employee $employee) => $employee->position
                                    ? $employee->name.' — '.$employee->position
                                    : $employee->name)
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn ($state, Set $set) => $set('basic_salary', Employee::find($state)?->basic_salary ?? 0))
                                ->columnSpanFull(),

                            DatePicker::make('period')
                                ->label(__('payroll.field.period'))
                                ->native(false)
                                ->displayFormat('F Y')
                                ->default(now()->startOfMonth())
                                ->required()
                                ->live()
                                ->rule(fn (Get $get, ?Payslip $record): Closure => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                                    $existing = Payslip::query()
                                        ->where('employee_id', $get('employee_id'))
                                        ->whereDate('period', Carbon::parse($value)->startOfMonth())
                                        ->when($record, fn (Builder $q) => $q->whereKeyNot($record->getKey()))
                                        ->first();

                                    if ($existing) {
                                        $fail(__('payroll.duplicate', ['number' => $existing->number]));
                                    }
                                }),

                            DatePicker::make('issued_on')
                                ->label(__('payroll.field.issued_on'))
                                ->native(false)
                                ->default(now())
                                ->required()
                                ->live(),

                            TextInput::make('basic_salary')
                                ->label(__('payroll.field.basic_salary'))
                                ->prefix('Rp')
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->required()
                                ->live(onBlur: true)
                                ->columnSpanFull(),
                        ]),

                    static::lines('earnings', PayComponent::EARNING, 'add_earning'),

                    static::lines('deductions', PayComponent::DEDUCTION, 'add_deduction'),
                ]),

                View::make('filament.admin.payslips.preview')
                    ->viewData(fn (Get $get, ?Payslip $record) => [
                        'paper' => PayslipPdf::paper(static::draft($get, $record)),
                    ]),
            ]);
    }

    /**
     * Baris tunjangan atau potongan.
     *
     * Kotak keterangannya kotak ketik dengan daftar saran, seperti "Custom…"
     * di aplikasi aslinya: memilih item dari Komponen Gaji mengisi nominal
     * bawaannya, dan item "fix" nominalnya terkunci — di sini maupun di
     * model, yang menegakkannya lagi saat menyimpan.
     */
    protected static function lines(string $name, string $type, string $addLabel): Repeater
    {
        return Repeater::make($name)
            ->label(__('payroll.field.'.$name))
            ->helperText(__('payroll.help.lines'))
            ->table([
                TableColumn::make(__('payroll.field.label')),
                TableColumn::make(__('payroll.field.amount')),
            ])
            ->schema([
                TextInput::make('label')
                    ->datalist(fn () => PayComponent::query()->active()->where('type', $type)->orderBy('name')->pluck('name')->all())
                    ->required()
                    ->maxLength(80)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Set $set) use ($type) {
                        $component = PayComponent::match($type, $state);

                        if ($component && ($component->default_amount || $component->fixed)) {
                            $set('amount', $component->default_amount);
                        }
                    }),

                TextInput::make('amount')
                    ->prefix('Rp')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->live(onBlur: true)
                    ->readOnly(fn (Get $get) => (bool) PayComponent::match($type, $get('label'))?->fixed),
            ])
            ->defaultItems(0)
            ->addActionLabel(__('payroll.action.'.$addLabel))
            ->live();
    }

    /** Slip dari isian formulir yang belum disimpan, untuk pratinjau. */
    protected static function draft(Get $get, ?Payslip $record): Payslip
    {
        $slip = $record ? $record->replicate() : new Payslip;

        // Slip yang sedang diubah memakai salinan data karyawannya sendiri,
        // kecuali karyawannya diganti di formulir.
        if ($record) {
            $slip->syncOriginal();
        }

        $slip->fill([
            'employee_id' => $get('employee_id') ?: null,
            'period' => $get('period') ?: now(),
            'issued_on' => $get('issued_on') ?: now(),
            'basic_salary' => (int) $get('basic_salary'),
            'earnings' => array_values($get('earnings') ?? []),
            'deductions' => array_values($get('deductions') ?? []),
        ]);

        $slip->prepare();

        if ($slip->isDirty('period')) {
            $slip->number = null;
        }

        return $slip;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('period', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label(__('payroll.field.number'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('employee_name')
                    ->label(__('payroll.field.employee'))
                    ->description(fn (Payslip $record) => $record->employee_position)
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('period')
                    ->label(__('payroll.field.period'))
                    ->date('F Y')
                    ->sortable(),

                TextColumn::make('total_earnings')
                    ->label(__('payroll.field.total_earnings'))
                    ->formatStateUsing(fn ($state) => Money::format((int) $state))
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('total_deductions')
                    ->label(__('payroll.field.total_deductions'))
                    ->formatStateUsing(fn ($state) => Money::format((int) $state))
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('net_pay')
                    ->label(__('payroll.field.net_pay'))
                    ->formatStateUsing(fn ($state) => Money::format((int) $state))
                    ->alignEnd()
                    ->weight('bold')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('employee_id')
                    ->label(__('payroll.field.employee'))
                    ->relationship('employee', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                static::pdfAction(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /** PDF slip dibuka di tab baru; lihat App\Http\Controllers\PdfController. */
    public static function pdfAction(): Action
    {
        return Action::make('pdf')
            ->label(__('payroll.action.pdf'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->url(fn (Payslip $record) => route('filament.admin.pdf.payslip', $record), shouldOpenInNewTab: true);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayslips::route('/'),
            'create' => CreatePayslip::route('/create'),
            'edit' => EditPayslip::route('/{record}/edit'),
        ];
    }
}

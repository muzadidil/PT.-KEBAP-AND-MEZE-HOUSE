<?php

namespace App\Filament\Admin\Pages\Reports;

use App\Filament\Admin\Concerns\ForAdmin;
use App\Models\Setting;
use App\Models\TaxFiling;
use App\Models\TaxFilingLog;
use App\Support\Money;
use App\Support\Tax\TaxFilingExport;
use App\Support\Tax\TaxFilingReport;
use App\Support\Tax\TaxMonthExport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Laporan Pajak: tempat mengerjakan pelaporan PPh Final dan PPN/PBJT.
 *
 * Angkanya ditarik dari pembukuan yang sudah ada, lalu boleh dikoreksi per
 * bulan. Koreksi disimpan terpisah (tax_filings) dan HANYA berlaku di sini:
 * Buku Besar, Neraca, dan laporan penjualan tidak ikut berubah. Tiap
 * perubahan dicatat di riwayat.
 *
 * Berbeda dengan menu Pajak, yang hanya mendaftar pengeluaran berkategori
 * Pajak.
 */
class TaxFilings extends Page
{
    use ForAdmin;

    protected static ?int $navigationSort = 85;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected string $view = 'filament.admin.pages.reports.tax-filings';

    #[Url]
    public int $year = 0;

    /** 0 = ringkasan setahun; 1–12 = rincian satu bulan. */
    #[Url]
    public int $month = 0;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.group.reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.tax_filing');
    }

    public function getTitle(): string
    {
        return __('nav.tax_filing');
    }

    public function mount(): void
    {
        $this->year = $this->year ?: Carbon::today()->year;
        $this->month = $this->month >= 1 && $this->month <= 12 ? $this->month : 0;
    }

    public function openMonth(int $month): void
    {
        $this->month = max(0, min(12, $month));
        unset($this->monthReport);
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function monthReport(): array
    {
        return TaxFilingReport::month($this->year, max(1, $this->month));
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function report(): array
    {
        return TaxFilingReport::year($this->year);
    }

    /** @return Collection<int, TaxFilingLog> */
    #[Computed]
    public function history(): Collection
    {
        return TaxFilingLog::query()
            ->with('user')
            ->where('year', $this->year)
            ->latest('id')
            ->limit(20)
            ->get();
    }

    /** @return array<int, int> */
    public function years(): array
    {
        $current = Carbon::today()->year;

        return range($current + 1, $current - 5);
    }

    public function updatedYear(): void
    {
        unset($this->report, $this->history, $this->monthReport);
    }

    public function money(?int $amount): string
    {
        return Money::format($amount);
    }

    public function pdfUrl(): string
    {
        return route('filament.admin.pdf.tax-filing', array_filter(['year' => $this->year, 'month' => $this->month]));
    }

    protected function getHeaderActions(): array
    {
        return [$this->ratesAction()];
    }

    /** Tarif bawaan untuk semua bulan; tiap bulan boleh menimpanya sendiri. */
    public function ratesAction(): Action
    {
        return Action::make('rates')
            ->label(__('tax_filing.rates.action'))
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('gray')
            ->modalHeading(__('tax_filing.rates.heading'))
            ->modalDescription(__('tax_filing.rates.description'))
            ->fillForm(fn () => TaxFilingReport::defaults())
            ->schema([
                TextInput::make('final_rate')
                    ->label(__('tax_filing.field.final_rate'))
                    ->numeric()->minValue(0)->maxValue(100)->suffix('%')->required(),
                TextInput::make('ppn_rate')
                    ->label(__('tax_filing.field.ppn_rate'))
                    ->helperText(__('tax_filing.rates.ppn_hint'))
                    ->numeric()->minValue(0)->maxValue(100)->suffix('%')->required(),
                Toggle::make('ppn_inclusive')
                    ->label(__('tax_filing.field.ppn_inclusive'))
                    ->helperText(__('tax_filing.rates.inclusive_hint')),
            ])
            ->action(function (array $data) {
                Setting::put('tax.final_rate', (float) $data['final_rate']);
                Setting::put('tax.ppn_rate', (float) $data['ppn_rate']);
                Setting::put('tax.ppn_inclusive', (bool) $data['ppn_inclusive']);

                unset($this->report);

                Notification::make()->title(__('tax_filing.saved'))->success()->send();
            });
    }

    /** Koreksi satu bulan; dibuka dari tombol Ubah di tabel. */
    public function editMonthAction(): Action
    {
        return Action::make('editMonth')
            ->modalHeading(fn (array $arguments) => __('tax_filing.edit.heading', [
                'month' => Carbon::create($this->year, (int) ($arguments['month'] ?? 1), 1)->translatedFormat('F Y'),
            ]))
            ->modalDescription(__('tax_filing.edit.description'))
            ->modalSubmitActionLabel(__('tax_filing.edit.save'))
            ->fillForm(function (array $arguments): array {
                $filing = TaxFiling::query()
                    ->where(['year' => $this->year, 'month' => (int) $arguments['month']])
                    ->first();

                return [
                    'revenue_override' => $filing?->revenue_override,
                    'final_rate' => $filing?->final_rate,
                    'ppn_rate' => $filing?->ppn_rate,
                    'ppn_inclusive' => match ($filing?->ppn_inclusive) {
                        true => 'yes',
                        false => 'no',
                        default => 'default',
                    },
                    'ppn_input' => $filing?->ppn_input ?? 0,
                    'paid_final' => $filing?->paid_final ?? 0,
                    'paid_ppn' => $filing?->paid_ppn ?? 0,
                    'is_reported' => $filing?->is_reported ?? false,
                    'note' => $filing?->note,
                ];
            })
            ->schema([
                TextInput::make('revenue_override')
                    ->label(__('tax_filing.field.revenue_override'))
                    ->helperText(__('tax_filing.edit.revenue_hint'))
                    ->numeric()->integer()->minValue(0)->prefix('Rp'),
                TextInput::make('final_rate')
                    ->label(__('tax_filing.field.final_rate'))
                    ->helperText(__('tax_filing.edit.rate_hint'))
                    ->numeric()->minValue(0)->maxValue(100)->suffix('%'),
                TextInput::make('ppn_rate')
                    ->label(__('tax_filing.field.ppn_rate'))
                    ->helperText(__('tax_filing.edit.rate_hint'))
                    ->numeric()->minValue(0)->maxValue(100)->suffix('%'),
                Select::make('ppn_inclusive')
                    ->label(__('tax_filing.field.ppn_inclusive'))
                    ->options([
                        'default' => __('tax_filing.edit.use_default'),
                        'yes' => __('tax_filing.edit.inclusive_yes'),
                        'no' => __('tax_filing.edit.inclusive_no'),
                    ])
                    ->required()->native(false),
                TextInput::make('ppn_input')
                    ->label(__('tax_filing.field.ppn_input'))
                    ->numeric()->integer()->minValue(0)->prefix('Rp')->required(),
                TextInput::make('paid_final')
                    ->label(__('tax_filing.field.paid_final'))
                    ->numeric()->integer()->minValue(0)->prefix('Rp')->required(),
                TextInput::make('paid_ppn')
                    ->label(__('tax_filing.field.paid_ppn'))
                    ->numeric()->integer()->minValue(0)->prefix('Rp')->required(),
                Toggle::make('is_reported')->label(__('tax_filing.field.is_reported')),
                Textarea::make('note')->label(__('field.note'))->rows(2)->maxLength(1000),
            ])
            ->action(function (array $data, array $arguments) {
                $this->saveMonth((int) $arguments['month'], $data);

                Notification::make()->title(__('tax_filing.saved'))->success()->send();
            });
    }

    /**
     * Menyimpan koreksi dan mencatat tiap kolom yang berubah.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveMonth(int $month, array $data): void
    {
        $filing = TaxFiling::query()->firstOrNew(['year' => $this->year, 'month' => $month]);

        $new = [
            'revenue_override' => filled($data['revenue_override'] ?? null) ? (int) $data['revenue_override'] : null,
            'final_rate' => filled($data['final_rate'] ?? null) ? (float) $data['final_rate'] : null,
            'ppn_rate' => filled($data['ppn_rate'] ?? null) ? (float) $data['ppn_rate'] : null,
            'ppn_inclusive' => match ($data['ppn_inclusive'] ?? 'default') {
                'yes' => true,
                'no' => false,
                default => null,
            },
            'ppn_input' => (int) ($data['ppn_input'] ?? 0),
            'paid_final' => (int) ($data['paid_final'] ?? 0),
            'paid_ppn' => (int) ($data['paid_ppn'] ?? 0),
            'is_reported' => (bool) ($data['is_reported'] ?? false),
            'note' => filled($data['note'] ?? null) ? (string) $data['note'] : null,
        ];

        $changes = [];

        foreach ($new as $field => $value) {
            $old = $filing->exists ? $filing->{$field} : match ($field) {
                'ppn_input', 'paid_final', 'paid_ppn' => 0,
                'is_reported' => false,
                default => null,
            };

            if ($old !== $value) {
                $changes[$field] = [$old, $value];
            }
        }

        if ($changes === []) {
            return;
        }

        $filing->fill($new);
        $filing->user_id ??= auth()->id();
        $filing->save();

        foreach ($changes as $field => [$old, $value]) {
            TaxFilingLog::create([
                'year' => $this->year,
                'month' => $month,
                'field' => $field,
                'old_value' => $this->plain($old),
                'new_value' => $this->plain($value),
                'user_id' => auth()->id(),
            ]);
        }

        unset($this->report, $this->history, $this->monthReport);
    }

    protected function plain(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    public function exportExcel(): StreamedResponse
    {
        $export = $this->month > 0
            ? new TaxMonthExport($this->monthReport)
            : new TaxFilingExport($this->report);
        $book = $export->build();

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, $export->filename(), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}

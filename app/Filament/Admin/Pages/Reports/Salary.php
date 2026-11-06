<?php

namespace App\Filament\Admin\Pages\Reports;

use App\Filament\Admin\Concerns\ForAdmin;
use App\Filament\Admin\Pages\Reports\Concerns\HasPeriod;
use App\Models\Payroll;
use App\Support\Money;
use App\Support\Zeytin\DailyLedger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use UnitEnum;

/**
 * Gaji per bulan — hanya totalnya.
 *
 * Membaca Gaji di Penggajian, baris yang sama yang dijumlahkan Buku Besar.
 * Gaji per orang tidak ditampilkan: itu hanya untuk Super Admin, sedangkan
 * halaman ini milik Admin. Yang terlihat di sini jumlah orang dan total gaji
 * tiap bulan, cukup untuk mencocokkan kartu Gaji di Buku Besar.
 */
class Salary extends Page
{
    use ForAdmin;
    use HasPeriod;

    protected static ?int $navigationSort = 70;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected string $view = 'filament.admin.pages.reports.salary';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.group.reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.salary');
    }

    public function getTitle(): string
    {
        return __('nav.salary');
    }

    protected static function defaultFrom(): Carbon
    {
        return Carbon::today()->startOfYear();
    }

    /** @return Collection<int, array{month: Carbon, people: int, total: int, paid_count: int, paid_on: ?Carbon, paid_via: ?string}> */
    #[Computed]
    public function rows(): Collection
    {
        return DailyLedger::payroll($this->fromDate(), $this->toDate())
            ->selectRaw('month, COUNT(*) as people, SUM(grand_total) as total, SUM(CASE WHEN paid_on IS NULL THEN 0 ELSE 1 END) as paid_count, MAX(paid_on) as paid_on, MAX(paid_via) as paid_via')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => Carbon::parse($row->month),
                'people' => (int) $row->people,
                'total' => (int) $row->total,
                'paid_count' => (int) $row->paid_count,
                'paid_on' => $row->paid_on ? Carbon::parse($row->paid_on) : null,
                'paid_via' => $row->paid_via ?: null,
            ]);
    }

    protected function forget(): void
    {
        unset($this->rows);
    }

    public function money(?int $amount): string
    {
        return Money::format($amount);
    }

    /**
     * Mencatat kapan gaji satu bulan dibayar. Mulai tanggal itu jumlahnya
     * mengurangi Saldo Bank (lewat bank) atau Sisa Cash (tunai).
     */
    public function payAction(): Action
    {
        return Action::make('pay')
            ->label(__('payroll_paid.report.btn_record'))
            ->modalHeading(fn (array $arguments) => __('payroll_paid.report.heading', ['month' => $this->monthLabel($arguments)]))
            ->modalDescription(__('payroll_paid.report.description'))
            ->modalSubmitActionLabel(__('payroll_paid.submit'))
            ->fillForm(function (array $arguments) {
                $row = $this->rows->first(fn (array $r) => $r['month']->toDateString() === ($arguments['month'] ?? ''));

                return [
                    'paid_on' => ($row['paid_on'] ?? null)?->toDateString() ?: Carbon::today()->toDateString(),
                    'paid_via' => $row['paid_via'] ?? Payroll::VIA_BANK,
                ];
            })
            ->schema([
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
                    ->native(false)
                    ->required(),
            ])
            ->action(function (array $data, array $arguments) {
                $count = Payroll::setPaymentForMonth(Carbon::parse($arguments['month']), (string) $data['paid_on'], (string) $data['paid_via']);
                unset($this->rows);

                Notification::make()
                    ->success()
                    ->title(__('payroll_paid.marked'))
                    ->body(__('payroll_paid.report.saved_body', [
                        'month' => $this->monthLabel($arguments),
                        'count' => $count,
                        'date' => Carbon::parse($data['paid_on'])->translatedFormat('j M Y'),
                        'via' => __('payroll_paid.via.'.$data['paid_via']),
                    ]))
                    ->send();
            });
    }

    /** Membatalkan catatan bayar satu bulan: tidak lagi mengurangi saldo. */
    public function unpayAction(): Action
    {
        return Action::make('unpay')
            ->label(__('payroll_paid.report.btn_undo'))
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => __('payroll_paid.report.undo_heading', ['month' => $this->monthLabel($arguments)]))
            ->modalDescription(__('payroll_paid.bulk.unpaid_description'))
            ->action(function (array $arguments) {
                Payroll::setPaymentForMonth(Carbon::parse($arguments['month']), null, null);
                unset($this->rows);

                Notification::make()->success()->title(__('payroll_paid.report.undone'))->send();
            });
    }

    /** @param  array<string, mixed>  $arguments */
    protected function monthLabel(array $arguments): string
    {
        return Carbon::parse($arguments['month'] ?? 'now')->translatedFormat('F Y');
    }
}

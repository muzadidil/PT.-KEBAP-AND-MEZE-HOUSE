<?php

namespace App\Filament\Admin\Pages\Zeytin;

use App\Filament\Admin\Concerns\ForAdmin;
use App\Support\Money;
use App\Support\Zeytin\Channels;
use App\Support\Zeytin\DailyLedger;
use App\Support\Zeytin\PeriodExport;
use App\Support\Zeytin\PeriodPdf;
use BackedEnum;
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
 * Buku besar bulanan: seluruh angka pembukuan untuk satu rentang tanggal.
 *
 * Harian, bulanan, dan tahunan adalah tampilan yang sama digulung berbeda,
 * bukan tiga laporan yang menghitung sendiri-sendiri. Ketiganya dan unduhan
 * Excel-nya membaca satu hasil yang sama dari DailyLedger, jadi tidak mungkin
 * ada dua angka berbeda untuk hal yang sama.
 */
class MonthlyLedger extends Page
{
    use ForAdmin;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.admin.pages.zeytin.monthly-ledger';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $grouping = 'daily';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('zeytin.nav.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('zeytin.nav.ledger');
    }

    public function getTitle(): string
    {
        return __('zeytin.nav.ledger');
    }

    public function mount(): void
    {
        $this->from = $this->from ?: Carbon::today()->startOfMonth()->toDateString();
        $this->to = $this->to ?: Carbon::today()->toDateString();
    }

    public function fromDate(): Carbon
    {
        return Carbon::parse($this->from ?: Carbon::today()->startOfMonth())->startOfDay();
    }

    public function toDate(): Carbon
    {
        $to = Carbon::parse($this->to ?: Carbon::today())->startOfDay();

        // Rentang terbalik tidak pernah berguna, dan kalau dibiarkan akan
        // memberi tabel kosong tanpa penjelasan. Diperlakukan sebagai satu
        // hari saja — perlakuan yang sama dengan laporan penjualan kasir.
        return $to->lt($this->fromDate()) ? $this->fromDate() : $to;
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function report(): array
    {
        return DailyLedger::periodReport($this->fromDate(), $this->toDate());
    }

    /** @return Collection<int, array<string, mixed>> */
    #[Computed]
    public function rows(): Collection
    {
        return match ($this->grouping) {
            'monthly' => DailyLedger::groupByMonth($this->report['rows']),
            'yearly' => DailyLedger::groupByYear($this->report['rows']),
            default => $this->report['rows'],
        };
    }

    /**
     * Saldo per channel, disederhanakan jadi dua kategori.
     *
     * Dulu tabel ini menampilkan lima baris terpisah (Cash, BNI, Grab Food,
     * Go Food, Go Pay) tanpa penanda mana yang tunai — orang harus tahu
     * sendiri bahwa keempat yang terakhir itu non-tunai. Sekarang dua baris
     * saja, dan pembagiannya mengikuti penanda `is_cash` di config, bukan
     * nama channel yang ditulis di sini.
     *
     * @return array<int, array{label: string, amount: int, excluded: bool}>
     */
    #[Computed]
    public function channelSummary(): array
    {
        $byChannel = $this->report['by_channel'];
        $cash = 0;

        foreach (Channels::cash() as $key) {
            $cash += $byChannel[$key];
        }

        $rows = [
            ['label' => __('zeytin.ledger.cash'), 'amount' => $cash, 'excluded' => false],
            ['label' => __('zeytin.ledger.noncash'), 'amount' => $this->report['total_sales'] - $cash, 'excluded' => false],
        ];

        /*
         * Petty cash tetap tampil terpisah dan ditandai "excl.": ia bukan
         * tunai maupun non-tunai dalam konteks ini, karena memang sengaja
         * tidak ikut Total Sales sama sekali — rumus Excel aslinya melewati
         * kolomnya. Ditampilkan supaya tidak dikira hilang karena salah
         * hitung.
         */
        foreach (Channels::all() as $channel) {
            if (! $channel['in_sales']) {
                $rows[] = [
                    'label' => $channel['label'],
                    'amount' => $byChannel[$channel['key']],
                    'excluded' => true,
                ];
            }
        }

        return $rows;
    }

    public function applyPreset(string $preset): void
    {
        $today = Carbon::today();

        [$from, $to] = match ($preset) {
            'today' => [$today->copy(), $today->copy()],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()],
            'last_month' => [
                $today->copy()->subMonthNoOverflow()->startOfMonth(),
                $today->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'this_year' => [$today->copy()->startOfYear(), $today->copy()],
            'last_year' => [
                $today->copy()->subYearNoOverflow()->startOfYear(),
                $today->copy()->subYearNoOverflow()->endOfYear(),
            ],
            'last_7' => [$today->copy()->subDays(6), $today->copy()],
            'last_30' => [$today->copy()->subDays(29), $today->copy()],
            default => [$this->fromDate(), $this->toDate()],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();

        $this->forget();
    }

    public function setGrouping(string $grouping): void
    {
        $this->grouping = in_array($grouping, ['daily', 'monthly', 'yearly'], true) ? $grouping : 'daily';

        $this->forget();
    }

    public function updated(): void
    {
        $this->forget();
    }

    protected function forget(): void
    {
        unset($this->report, $this->rows, $this->channelSummary);
    }

    /**
     * Unduhan Excel dibuat dari laporan yang sedang tampil, jadi berkas yang
     * diunduh tidak mungkin berbeda dari yang dilihat di layar.
     */
    public function exportExcel(): StreamedResponse
    {
        $export = new PeriodExport($this->report);
        $book = $export->build();

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $export->filename(), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Laporan PDF untuk dikirim ke pemilik, dari laporan dan pengelompokan
     * yang sedang tampil — sama seperti unduhan Excel di atas.
     */
    public function exportPdf(): StreamedResponse
    {
        $pdf = new PeriodPdf($this->report, $this->grouping);
        $content = $pdf->render();

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $pdf->filename(), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /** Label kolom pertama, mengikuti pengelompokan yang dipilih. */
    public function periodLabel(array $row): string
    {
        return PeriodPdf::label($row, $this->grouping);
    }

    public function money(?int $amount): string
    {
        return Money::format($amount);
    }

    /** @return array<int, array<string, mixed>> */
    public function cards(): array
    {
        $report = $this->report;
        $opening = Money::format((int) config('zeytin.supplier_cash_opening'));

        return [
            ['label' => __('zeytin.card.sales'), 'value' => $report['total_sales'], 'hint' => __('zeytin.hint.recorded_days', [
                'recorded' => $report['recorded_days'],
                'days' => $report['days'],
            ])],
            ['label' => __('zeytin.card.cashless'), 'value' => $report['cashless']],
            ['label' => __('zeytin.card.cash_expense'), 'value' => $report['cash_expense']],
            ['label' => __('zeytin.card.transfers'), 'value' => $report['transfers']],
            ['label' => __('zeytin.card.payroll'), 'value' => $report['payroll']],
            ['label' => __('zeytin.card.total_expenses'), 'value' => $report['total_expenses']],
            ['label' => __('zeytin.card.remaining_supplier_cash'), 'value' => $report['remaining_supplier_cash'], 'hint' => __('zeytin.hint.supplier_cash', ['opening' => $opening])],
            ['label' => __('zeytin.card.outstanding'), 'value' => $report['outstanding'], 'hint' => __('zeytin.hint.outstanding')],
            ['label' => __('zeytin.card.profit'), 'value' => $report['net_profit'], 'negative' => $report['net_profit'] < 0],
            ['label' => __('zeytin.card.global_balance'), 'value' => $report['global_balance'], 'hint' => __('zeytin.hint.global_balance'), 'negative' => $report['global_balance'] < 0],
        ];
    }
}

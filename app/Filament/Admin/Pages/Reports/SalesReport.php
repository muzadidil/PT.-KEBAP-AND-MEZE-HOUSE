<?php

namespace App\Filament\Admin\Pages\Reports;

use App\Filament\Admin\Concerns\ForAdmin;
use App\Support\Ledger;
use App\Support\Money;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Induk keempat laporan penjualan: harian, mingguan, bulanan, tahunan.
 *
 * Keempatnya berbeda hanya pada besar periodenya, dan semuanya dijumlahkan
 * dari agregat harian yang sama di App\Support\Ledger. Karena itu tidak
 * mungkin laporan bulanan dan laporan harian menghasilkan angka berbeda
 * untuk rentang yang sama — keduanya menjumlahkan baris yang sama persis.
 * Invariannya diuji di tests/Feature/SalesReportTest.
 */
abstract class SalesReport extends Page
{
    use ForAdmin;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected string $view = 'filament.admin.pages.reports.sales';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.group.reports');
    }

    public function mount(): void
    {
        $this->from = $this->from ?: static::defaultFrom()->toDateString();
        $this->to = $this->to ?: static::defaultTo()->toDateString();
    }

    protected static function defaultFrom(): Carbon
    {
        return Carbon::today()->startOfMonth();
    }

    protected static function defaultTo(): Carbon
    {
        return Carbon::today();
    }

    public function fromDate(): Carbon
    {
        return Carbon::parse($this->from ?: static::defaultFrom())->startOfDay();
    }

    public function toDate(): Carbon
    {
        $to = Carbon::parse($this->to ?: static::defaultTo())->startOfDay();

        // Rentang terbalik tidak pernah berguna, dan kalau dibiarkan akan
        // memberi tabel kosong tanpa penjelasan. Diperlakukan sebagai satu
        // hari saja.
        return $to->lt($this->fromDate()) ? $this->fromDate() : $to;
    }

    /** @return Collection<int, array<string, mixed>> */
    abstract protected function buildRows(Carbon $from, Carbon $to): Collection;

    /** Label periode di kolom pertama, mis. "September 2026". */
    abstract public function periodLabel(array $row): string;

    /** @return Collection<int, array<string, mixed>> */
    #[Computed]
    public function rows(): Collection
    {
        return $this->buildRows($this->fromDate(), $this->toDate());
    }

    /** @return array<string, int> */
    #[Computed]
    public function totals(): array
    {
        $totals = ['cash' => 0, 'cashless' => 0, 'grab' => 0, 'total' => 0, 'transactions' => 0];

        foreach ($this->rows as $row) {
            foreach (array_keys($totals) as $field) {
                $totals[$field] += $row[$field] ?? 0;
            }
        }

        return $totals;
    }

    /** Rata-rata harian atas rentang yang dipilih, bukan atas jumlah baris. */
    #[Computed]
    public function averagePerDay(): int
    {
        $days = $this->fromDate()->diffInDays($this->toDate()) + 1;

        return $days > 0 ? intdiv($this->totals['total'], $days) : 0;
    }

    public function applyPreset(string $preset): void
    {
        $today = Carbon::today();

        [$from, $to] = match ($preset) {
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()],
            'last_month' => [
                $today->copy()->subMonthNoOverflow()->startOfMonth(),
                $today->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'this_year' => [$today->copy()->startOfYear(), $today->copy()],
            'last_7' => [$today->copy()->subDays(6), $today->copy()],
            'last_30' => [$today->copy()->subDays(29), $today->copy()],
            default => [$this->fromDate(), $this->toDate()],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();

        unset($this->rows, $this->totals, $this->averagePerDay);
    }

    public function updated(): void
    {
        unset($this->rows, $this->totals, $this->averagePerDay);
    }

    /**
     * Unduhan CSV dibuat langsung dari baris yang sedang tampil, jadi berkas
     * yang diunduh tidak mungkin berbeda dari yang dilihat di layar.
     */
    public function exportCsv(): StreamedResponse
    {
        $rows = $this->rows;
        $totals = $this->totals;
        $heading = [
            __('report.period'),
            __('enums.channel.cash'),
            __('enums.channel.cashless'),
            __('enums.channel.grab'),
            __('report.total'),
            __('report.transactions'),
        ];

        $filename = str(static::getNavigationLabel())->slug().'-'.$this->from.'-'.$this->to.'.csv';

        return response()->streamDownload(function () use ($rows, $totals, $heading) {
            $handle = fopen('php://output', 'wb');

            // BOM supaya Excel membaca huruf beraksen dengan benar.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $heading);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $this->periodLabel($row),
                    $row['cash'],
                    $row['cashless'],
                    $row['grab'],
                    $row['total'],
                    $row['transactions'],
                ]);
            }

            fputcsv($handle, [
                __('report.grand_total'),
                $totals['cash'],
                $totals['cashless'],
                $totals['grab'],
                $totals['total'],
                $totals['transactions'],
            ]);

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Dipakai oleh view untuk memformat angka. */
    public function money(?int $amount): string
    {
        return Money::format($amount);
    }

    /** Ringkasan pemasukan sepanjang rentang, untuk kartu di atas tabel. */
    #[Computed]
    public function summary(): array
    {
        return Ledger::profit($this->fromDate(), $this->toDate());
    }
}

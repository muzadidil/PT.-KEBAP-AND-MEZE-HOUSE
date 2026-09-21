<?php

namespace App\Filament\Admin\Pages\Reports;

use App\Filament\Admin\Concerns\ForAdmin;
use App\Filament\Admin\Pages\Reports\Concerns\HasPeriod;
use App\Filament\Admin\Pages\Zeytin\MonthlyLedger;
use App\Support\Money;
use App\Support\Zeytin\Channels;
use App\Support\Zeytin\DailyLedger;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Induk keempat laporan penjualan: harian, mingguan, bulanan, tahunan.
 *
 * Sumbernya Pemasukan Harian di Pembukuan Bulanan, dihitung oleh
 * App\Support\Zeytin\DailyLedger — mesin yang sama dengan Buku Besar
 * Bulanan, PDF, dan unduhan Excel-nya. Dulu laporan ini membaca transaksi
 * kasir, sehingga Penjualan Tahunan menampilkan 95 juta sementara Buku
 * Besar menampilkan 280 juta untuk bulan yang sama: dua sumber, dua angka.
 * Sekarang satu sumber, jadi untuk rentang yang sama angkanya selalu sama.
 *
 * Keempatnya hanya berbeda besar periodenya; semuanya menjumlahkan baris
 * harian yang sama. Invariannya diuji di tests/Feature/Zeytin/SalesReportPagesTest.
 */
abstract class SalesReport extends Page
{
    use ForAdmin;
    use HasPeriod;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected string $view = 'filament.admin.pages.reports.sales';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.group.reports');
    }

    /**
     * Baris harian digulung ke periode laporan ini.
     *
     * @param  Collection<int, array<string, mixed>>  $days
     * @return Collection<int, array<string, mixed>>
     */
    abstract protected function group(Collection $days): Collection;

    /** Label periode di kolom pertama, mis. "September 2026". */
    abstract public function periodLabel(array $row): string;

    /** Apakah barisnya per hari; kalau tidak, kolom hari tercatat ditampilkan. */
    public function isDaily(): bool
    {
        return false;
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
        return $this->group($this->report['rows']);
    }

    /**
     * Rata-rata per hari yang benar-benar tercatat.
     *
     * Bukan dibagi seluruh hari di rentang: Penjualan Tahunan membuka lima
     * tahun, padahal pembukuannya baru mulai Agustus 2026, sehingga 280 juta
     * dibagi 1.725 hari tampil sebagai "Rp 162.888 per hari" — angka yang
     * tidak menggambarkan satu hari pun.
     */
    public function averagePerDay(): int
    {
        $days = $this->report['recorded_days'];

        return $days > 0 ? intdiv($this->report['total_sales'], $days) : 0;
    }

    /** Buku Besar Bulanan untuk rentang yang sama, tempat angkanya dirinci. */
    public function ledgerUrl(): string
    {
        return MonthlyLedger::getUrl(['from' => $this->from, 'to' => $this->to]);
    }

    protected function forget(): void
    {
        unset($this->report, $this->rows);
    }

    /**
     * Unduhan CSV dibuat langsung dari baris yang sedang tampil, jadi berkas
     * yang diunduh tidak mungkin berbeda dari yang dilihat di layar.
     */
    public function exportCsv(): StreamedResponse
    {
        $rows = $this->rows;
        $report = $this->report;
        $daily = $this->isDaily();

        $heading = [__('report.period')];

        if (! $daily) {
            $heading[] = __('report.days');
        }

        foreach (Channels::all() as $channel) {
            $heading[] = $channel['label'];
        }

        $heading[] = __('zeytin.col.total_sales');

        $filename = str(static::getNavigationLabel())->slug().'-'.$this->from.'-'.$this->to.'.csv';

        return response()->streamDownload(function () use ($rows, $report, $heading, $daily) {
            $handle = fopen('php://output', 'wb');

            // BOM supaya Excel membaca huruf beraksen dengan benar.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $heading);

            foreach ($rows as $row) {
                $line = [$this->periodLabel($row)];

                if (! $daily) {
                    $line[] = $row['recorded_days'].' / '.$row['days'];
                }

                foreach (Channels::keys() as $key) {
                    $line[] = $row[$key];
                }

                $line[] = $row['total_sales'];

                fputcsv($handle, $line);
            }

            $footer = [__('report.grand_total')];

            if (! $daily) {
                $footer[] = $report['recorded_days'].' / '.$report['days'];
            }

            foreach (Channels::keys() as $key) {
                $footer[] = $report['by_channel'][$key];
            }

            $footer[] = $report['total_sales'];

            fputcsv($handle, $footer);

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Dipakai oleh view untuk memformat angka. */
    public function money(?int $amount): string
    {
        return Money::format($amount);
    }
}

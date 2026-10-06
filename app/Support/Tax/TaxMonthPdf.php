<?php

namespace App\Support\Tax;

use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Canvas;
use Dompdf\FontMetrics;

/** PDF Laporan Pajak satu bulan: ringkasan pajak plus penjualan per hari, A4 tegak. */
class TaxMonthPdf
{
    /** @param  array<string, mixed>  $report  hasil TaxFilingReport::month() */
    public function __construct(protected array $report) {}

    public function render(): string
    {
        $pdf = Pdf::loadView('pdf.tax-month', [
            'report' => ['days' => static::scaledDays($this->report)] + $this->report,
            'lines' => TaxMonthExport::pdfSummaryLines($this->report['summary']),
            'showDays' => true,
            'columns' => TaxMonthExport::dayColumns(),
            'letterhead' => config('zeytin.letterhead'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isFontSubsettingEnabled', true);

        $pdf->render();
        $pdf->getDomPDF()->getCanvas()->page_script(
            function (int $page, int $pages, Canvas $canvas, FontMetrics $metrics): void {
                $font = $metrics->getFont('DejaVu Sans');
                $text = __('zeytin.pdf.page_of', ['page' => $page, 'pages' => $pages]);
                $width = $metrics->getTextWidth($text, $font, 6.5);

                $canvas->text($canvas->get_width() - 40 - $width, $canvas->get_height() - 40, $text, $font, 6.5, [0.42, 0.45, 0.5]);
            },
        );

        return $pdf->output();
    }

    /**
     * Rincian harian disesuaikan dengan omzet di ringkasan (setelah
     * potongan), supaya total tabel sama persis dengan omzet itu. Selisih
     * pembulatan ditaruh di hari terakhir yang ada penjualannya.
     *
     * @param  array<string, mixed>  $report
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public static function scaledDays(array $report): \Illuminate\Support\Collection
    {
        $days = $report['days'];
        $sales = (int) $report['sales'];
        $target = (int) $report['summary']['tax_base'];

        if ($sales <= 0 || $target === $sales) {
            return $days;
        }

        $factor = $target / $sales;
        $inSales = \App\Support\Zeytin\Channels::inSales();

        $days = $days->map(function (array $day) use ($factor, $inSales) {
            foreach (\App\Support\Zeytin\Channels::keys() as $key) {
                $day[$key] = (int) round($day[$key] * $factor);
            }

            $day['total_sales'] = array_sum(array_map(fn ($key) => $day[$key], $inSales));

            return $day;
        })->values();

        $diff = $target - (int) $days->sum('total_sales');
        $last = $days->keys()->reverse()->first(fn ($i) => $days[$i]['total_sales'] > 0);

        if ($diff !== 0 && $last !== null) {
            $day = $days[$last];
            $key = collect($inSales)->sortByDesc(fn ($k) => $day[$k])->first();
            $day[$key] += $diff;
            $day['total_sales'] += $diff;
            $days[$last] = $day;
        }

        return $days;
    }

    public function filename(): string
    {
        return str(__('nav.tax_filing'))->slug()
            ->append(sprintf('-%d-%02d.pdf', $this->report['year'], $this->report['month']))
            ->toString();
    }
}

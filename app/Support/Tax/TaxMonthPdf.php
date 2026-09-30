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
            'report' => $this->report,
            'lines' => TaxMonthExport::summaryLines($this->report['summary']),
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

    public function filename(): string
    {
        return str(__('nav.tax_filing'))->slug()
            ->append(sprintf('-%d-%02d.pdf', $this->report['year'], $this->report['month']))
            ->toString();
    }
}

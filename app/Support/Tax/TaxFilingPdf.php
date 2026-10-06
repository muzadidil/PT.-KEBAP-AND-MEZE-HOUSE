<?php

namespace App\Support\Tax;

use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Canvas;
use Dompdf\FontMetrics;

/** PDF Laporan Pajak: A4 mendatar karena kolomnya banyak; kop sama dengan PDF lain. */
class TaxFilingPdf
{
    /** @param  array{year: int, rows: \Illuminate\Support\Collection, totals: array<string, int>}  $report */
    public function __construct(protected array $report) {}

    public function render(): string
    {
        $pdf = Pdf::loadView('pdf.tax-filing', [
            'report' => $this->report,
            'columns' => TaxFilingExport::pdfColumns(),
            'letterhead' => config('zeytin.letterhead'),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('isFontSubsettingEnabled', true);

        $pdf->render();
        $pdf->getDomPDF()->getCanvas()->page_script(
            function (int $page, int $pages, Canvas $canvas, FontMetrics $metrics): void {
                $font = $metrics->getFont('DejaVu Sans');
                $text = __('zeytin.pdf.page_of', ['page' => $page, 'pages' => $pages]);
                $width = $metrics->getTextWidth($text, $font, 6.5);

                $canvas->text($canvas->get_width() - 40 - $width, $canvas->get_height() - 30, $text, $font, 6.5, [0.42, 0.45, 0.5]);
            },
        );

        return $pdf->output();
    }

    public function filename(): string
    {
        return str(__('nav.tax_filing'))->slug()->append('-'.$this->report['year'].'.pdf')->toString();
    }
}

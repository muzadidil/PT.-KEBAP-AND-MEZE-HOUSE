<?php

namespace App\Support\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Canvas;
use Dompdf\FontMetrics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * PDF laporan berbentuk daftar — Pengeluaran Tunai, Transfer Online, Pajak.
 *
 * Bentuknya sama dengan PDF Buku Besar: A4 tegak, kop perusahaan, baris
 * total bergaris ganda, dan nomor halaman di kaki. Isinya baris yang sedang
 * tampil di layar, dengan penyaring yang sama — alamatnya membawa rentang,
 * penyaring, dan pencarian yang sedang dipakai.
 */
class ListPdf
{
    /**
     * @param  Collection<int, object>  $rows
     * @param  array<int, array{label: string, value: callable, money?: bool, date?: bool}>  $columns
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        protected string $title,
        protected string $source,
        protected Collection $rows,
        protected array $columns,
        protected array $filters = [],
    ) {}

    public function render(): string
    {
        $pdf = Pdf::loadView('pdf.report-list', [
            'title' => $this->title,
            'source' => $this->source,
            'rows' => $this->rows,
            'columns' => $this->columns,
            'period' => $this->period(),
            'total' => $this->total(),
            'letterhead' => config('zeytin.letterhead'),
        ])
            ->setPaper('a4', 'portrait')
            // Hanya huruf yang terpakai yang ditanam; lihat PeriodPdf.
            ->setOption('isFontSubsettingEnabled', true);

        // "Halaman x dari y" baru bisa ditulis setelah semua halaman tersusun.
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
        $range = implode('_', array_filter([$this->filters['from'] ?? null, $this->filters['to'] ?? null]));

        return str($this->title)->slug()->append($range ? '-'.$range : '')->append('.pdf')->toString();
    }

    protected function total(): int
    {
        $total = 0;

        foreach ($this->columns as $column) {
            if ($column['money'] ?? false) {
                foreach ($this->rows as $row) {
                    $total += (int) ($column['value'])($row);
                }

                break;
            }
        }

        return $total;
    }

    /** "1 Agt 2026 – 31 Agt 2026", atau keterangan kalau rentangnya terbuka. */
    protected function period(): string
    {
        $from = $this->filters['from'] ?? null;
        $to = $this->filters['to'] ?? null;

        return match (true) {
            $from && $to => Carbon::parse($from)->translatedFormat('j M Y').' – '.Carbon::parse($to)->translatedFormat('j M Y'),
            (bool) $from => __('report.from').' '.Carbon::parse($from)->translatedFormat('j M Y'),
            (bool) $to => __('report.to').' '.Carbon::parse($to)->translatedFormat('j M Y'),
            default => __('report.all_dates'),
        };
    }
}

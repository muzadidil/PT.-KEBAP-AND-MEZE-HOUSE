<?php

namespace App\Support\Zeytin;

use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Canvas;
use Dompdf\FontMetrics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Laporan PDF satu periode, untuk dikirim ke pemilik.
 *
 * Sama seperti PeriodExport: dibuat dari laporan yang sedang tampil, bukan
 * dihitung ulang, jadi berkas yang dikirim tidak mungkin berbeda dari yang
 * dilihat stafnya. Bentuknya mengikuti PDF aplikasi Zeytin sebelumnya: kop
 * berwarna, ringkasan, lalu rincian per periode dengan baris total.
 */
class PeriodPdf
{
    /**
     * @param  array<string, mixed>  $report  hasil DailyLedger::periodReport()
     * @param  string  $grouping  daily | monthly | yearly
     */
    public function __construct(protected array $report, protected string $grouping = 'daily') {}

    /**
     * Baris rincian sesuai pengelompokan.
     *
     * Pada laporan harian, hari tanpa penjualan maupun belanja dibuang:
     * laporan untuk pemilik tidak perlu penuh baris nol. Barisnya hanya
     * disembunyikan dari rincian — total dan ringkasannya tetap dari laporan
     * yang sama, jadi tidak ada angka yang ikut berubah.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(): Collection
    {
        return match ($this->grouping) {
            'monthly' => DailyLedger::groupByMonth($this->report['rows']),
            'yearly' => DailyLedger::groupByYear($this->report['rows']),
            default => $this->report['rows']
                ->filter(fn (array $row) => $row['total_sales'] > 0 || $row['expense'] > 0)
                ->values(),
        };
    }

    /** Label kolom pertama; dipakai juga oleh halaman Buku Besar. */
    public static function label(array $row, string $grouping): string
    {
        return match ($grouping) {
            'monthly' => Carbon::parse($row['key'].'-01')->translatedFormat('F Y'),
            'yearly' => $row['key'],
            default => $row['date']->translatedFormat('D, d M Y'),
        };
    }

    public function render(): string
    {
        $pdf = Pdf::loadView('pdf.zeytin-period', [
            'report' => $this->report,
            'rows' => $this->rows(),
            'grouping' => $this->grouping,
            'letterhead' => config('zeytin.letterhead'),
        ])
            ->setPaper('a4', 'portrait')
            // Hanya huruf yang terpakai yang ditanam: tanpa ini tiap berkas
            // membawa seluruh huruf DejaVu dan beratnya hampir 1 MB.
            ->setOption('isFontSubsettingEnabled', true);

        // "Halaman x dari y" baru bisa ditulis setelah semua halaman tersusun:
        // HTML tidak tahu jumlah halamannya. Rata kanan, sejajar kaki halaman.
        $pdf->render();
        $pdf->getDomPDF()->getCanvas()->page_script(
            function (int $page, int $pages, Canvas $canvas, FontMetrics $metrics): void {
                $font = $metrics->getFont('DejaVu Sans');
                $text = __('zeytin.pdf.page_of', ['page' => $page, 'pages' => $pages]);
                $width = $metrics->getTextWidth($text, $font, 6.5);

                $canvas->text($canvas->get_width() - 40 - $width, $canvas->get_height() - 50, $text, $font, 6.5, [0.42, 0.45, 0.5]);
            },
        );

        return $pdf->output();
    }

    public function filename(): string
    {
        return 'zeytin-'.$this->grouping.'-'
            .$this->report['from']->toDateString().'_'.$this->report['to']->toDateString().'.pdf';
    }
}

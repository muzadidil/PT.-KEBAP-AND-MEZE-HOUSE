<?php

namespace App\Support\Reports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Unduhan Excel untuk laporan berbentuk daftar — Pengeluaran Tunai,
 * Transfer Online, Pajak.
 *
 * Isinya baris yang sedang tampil di layar, dengan penyaring dan pencarian
 * yang sama: berkas yang diunduh tidak mungkin berbeda dari yang dilihat.
 * Rentang dan penyaringnya ditulis di baris pertama supaya berkas yang sudah
 * terlanjur tersimpan di komputer orang masih bisa dikenali isinya.
 *
 * Angka uang ditulis sebagai angka dengan format sel, bukan teks
 * "Rp 45.000", supaya tetap bisa dijumlahkan penerimanya.
 */
class ListExport
{
    protected const MONEY_FORMAT = '#,##0;-#,##0';

    protected const HEADER_COLOR = 'FF4D7C0F';

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

    public function build(): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr($this->title, 0, 31));

        $sheet->fromArray([[$this->title], [$this->period()], [$this->source], []], null, 'A1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setBold(true);

        $header = 5;
        $sheet->fromArray([array_column($this->columns, 'label')], null, 'A'.$header);

        $lines = [];
        $total = 0;

        foreach ($this->rows as $row) {
            $line = [];

            foreach ($this->columns as $column) {
                $value = ($column['value'])($row);

                if ($column['money'] ?? false) {
                    $total += (int) $value;
                    $line[] = (int) $value;

                    continue;
                }

                $line[] = $value instanceof Carbon ? $value->toDateString() : $value;
            }

            $lines[] = $line;
        }

        if ($lines) {
            $sheet->fromArray($lines, null, 'A'.($header + 1));
        }

        $last = $header + count($lines);
        $this->styleHeader($sheet, $header, $last);
        $this->totalRow($sheet, $last + 1, $total);

        foreach ($this->columns as $index => $column) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);

            $sheet->getColumnDimension($letter)->setWidth(($column['money'] ?? false) ? 18 : 24);

            if ($column['money'] ?? false) {
                $sheet->getStyle($letter.($header + 1).':'.$letter.($last + 1))
                    ->getNumberFormat()->setFormatCode(static::MONEY_FORMAT);
                $sheet->getStyle($letter.($header + 1).':'.$letter.($last + 1))
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }

        $sheet->freezePane('A'.($header + 1));

        return $book;
    }

    public function filename(): string
    {
        $range = implode('_', array_filter([$this->filters['from'] ?? null, $this->filters['to'] ?? null]));

        return str($this->title)->slug()->append($range ? '-'.$range : '')->append('.xlsx')->toString();
    }

    protected function styleHeader(Worksheet $sheet, int $header, int $last): void
    {
        $columns = Coordinate::stringFromColumnIndex(count($this->columns));
        $range = 'A'.$header.':'.$columns.$header;

        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(static::HEADER_COLOR);
        $sheet->setAutoFilter('A'.$header.':'.$columns.max($last, $header));
    }

    protected function totalRow(Worksheet $sheet, int $row, int $total): void
    {
        $moneyIndex = null;

        foreach ($this->columns as $index => $column) {
            if ($column['money'] ?? false) {
                $moneyIndex = $index + 1;
                break;
            }
        }

        $sheet->setCellValue('A'.$row, __('report.total'));

        if ($moneyIndex) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($moneyIndex).$row, $total);
        }

        $columns = Coordinate::stringFromColumnIndex(count($this->columns));
        $sheet->getStyle('A'.$row.':'.$columns.$row)->getFont()->setBold(true);
    }

    protected function period(): string
    {
        $from = $this->filters['from'] ?? null;
        $to = $this->filters['to'] ?? null;

        $period = match (true) {
            $from && $to => Carbon::parse($from)->translatedFormat('j M Y').' – '.Carbon::parse($to)->translatedFormat('j M Y'),
            (bool) $from => __('report.from').' '.Carbon::parse($from)->translatedFormat('j M Y'),
            (bool) $to => __('report.to').' '.Carbon::parse($to)->translatedFormat('j M Y'),
            default => __('report.all_dates'),
        };

        // Penyaring lain ikut ditulis apa adanya: berkas tanpa keterangan ini
        // gampang dikira memuat seluruh baris, padahal sudah tersaring.
        $extra = collect($this->filters)
            ->except(['from', 'to'])
            ->map(fn ($value, $key) => $key.': '.$value)
            ->implode(' · ');

        return $period.($extra ? ' · '.$extra : '');
    }
}

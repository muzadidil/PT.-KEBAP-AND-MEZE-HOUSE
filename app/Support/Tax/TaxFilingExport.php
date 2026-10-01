<?php

namespace App\Support\Tax;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Unduhan Excel Laporan Pajak. Isinya persis yang tampil di layar, dengan
 * angka sebagai angka (bukan teks "Rp") supaya tetap bisa dijumlahkan.
 * Kolomnya dibagi dengan PDF lewat columns().
 */
class TaxFilingExport
{
    protected const MONEY_FORMAT = '#,##0;-#,##0';

    protected const HEADER_COLOR = 'FF4D7C0F';

    /** @param  array{year: int, rows: \Illuminate\Support\Collection, totals: array<string, int>}  $report */
    public function __construct(protected array $report) {}

    /** @return array<int, array{key: string, label: string, money: bool}> */
    public static function columns(): array
    {
        $money = fn (string $key) => ['key' => $key, 'label' => __('tax_filing.col.'.$key), 'money' => true];

        return [
            ['key' => 'label', 'label' => __('report.month'), 'money' => false],
            $money('system_revenue'),
            $money('revenue'),
            $money('investor_share'),
            $money('tax_base'),
            $money('final_due'),
            $money('paid_final'),
            $money('ppn_output'),
            $money('ppn_input'),
            $money('ppn_due'),
            $money('paid_ppn'),
            $money('outstanding'),
            ['key' => 'status', 'label' => __('tax_filing.col.status'), 'money' => false],
        ];
    }

    /** Nilai satu sel; kolom status ditulis sebagai kata. */
    public static function cell(array $row, string $key): mixed
    {
        return $key === 'status'
            ? ($row['is_reported'] ? __('tax_filing.reported') : __('tax_filing.not_reported'))
            : $row[$key];
    }

    public function build(): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr(__('nav.tax_filing'), 0, 31));

        $sheet->fromArray([
            [__('nav.tax_filing').' '.$this->report['year']],
            [__('tax_filing.source')],
            [],
        ], null, 'A1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $columns = static::columns();
        $header = 4;
        $sheet->fromArray([array_column($columns, 'label')], null, 'A'.$header);

        $lines = [];

        foreach ($this->report['rows'] as $row) {
            $lines[] = array_map(fn (array $c) => static::cell($row, $c['key']), $columns);
        }

        $totals = [];

        foreach ($columns as $index => $column) {
            $totals[] = match (true) {
                $index === 0 => __('report.grand_total'),
                $column['money'] => $this->report['totals'][$column['key']] ?? '',
                default => '',
            };
        }

        $lines[] = $totals;
        $sheet->fromArray($lines, null, 'A'.($header + 1));

        $last = $header + count($lines);
        $lastLetter = Coordinate::stringFromColumnIndex(count($columns));
        $range = 'A'.$header.':'.$lastLetter.$header;

        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(static::HEADER_COLOR);
        $sheet->getStyle('A'.$last.':'.$lastLetter.$last)->getFont()->setBold(true);

        foreach ($columns as $index => $column) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getColumnDimension($letter)->setWidth($column['money'] ? 18 : 20);

            if ($column['money']) {
                $cells = $sheet->getStyle($letter.($header + 1).':'.$letter.$last);
                $cells->getNumberFormat()->setFormatCode(static::MONEY_FORMAT);
                $cells->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }

        $sheet->freezePane('B'.($header + 1));

        return $book;
    }

    public function filename(): string
    {
        return str(__('nav.tax_filing'))->slug()->append('-'.$this->report['year'].'.xlsx')->toString();
    }
}

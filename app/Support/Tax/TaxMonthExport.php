<?php

namespace App\Support\Tax;

use App\Support\Zeytin\Channels;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Unduhan Excel Laporan Pajak satu bulan: ringkasan pajak di atas, penjualan
 * per hari di bawah. Angka ditulis sebagai angka supaya bisa dijumlahkan.
 */
class TaxMonthExport
{
    protected const MONEY_FORMAT = '#,##0;-#,##0';

    protected const HEADER_COLOR = 'FF4D7C0F';

    /** @param  array<string, mixed>  $report  hasil TaxFilingReport::month() */
    public function __construct(protected array $report) {}

    /**
     * Baris ringkasan: [label, nilai, apakah uang].
     *
     * @return array<int, array{0: string, 1: mixed, 2: bool}>
     */
    public static function summaryLines(array $summary): array
    {
        $rate = fn (float $value) => rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',').'%';

        return [
            [__('tax_filing.col.system_revenue'), $summary['system_revenue'], true],
            [__('tax_filing.col.revenue'), $summary['revenue'], true],
            [__('tax_filing.field.investor_share'), $rate($summary['investor_share_pct']), false],
            [__('tax_filing.col.investor_share'), $summary['investor_share'], true],
            [__('tax_filing.col.tax_base'), $summary['tax_base'], true],
            [__('tax_filing.field.final_rate'), $rate($summary['final_rate']), false],
            [__('tax_filing.col.final_due'), $summary['final_due'], true],
            [__('tax_filing.col.paid_final'), $summary['paid_final'], true],
            [__('tax_filing.field.ppn_rate'), $rate($summary['ppn_rate']).' · '.__('tax_filing.month.'.($summary['ppn_inclusive'] ? 'inclusive' : 'exclusive')), false],
            [__('tax_filing.col.ppn_output'), $summary['ppn_output'], true],
            [__('tax_filing.col.ppn_input'), $summary['ppn_input'], true],
            [__('tax_filing.col.ppn_due'), $summary['ppn_due'], true],
            [__('tax_filing.col.paid_ppn'), $summary['paid_ppn'], true],
            [__('tax_filing.col.outstanding'), $summary['outstanding'], true],
            [__('tax_filing.col.status'), $summary['is_reported'] ? __('tax_filing.reported') : __('tax_filing.not_reported'), false],
            [__('field.note'), $summary['note'] ?? '', false],
        ];
    }

    /**
     * Ringkasan untuk PDF: omzet tampil sebagai satu angka (dasar pajak),
     * tanpa baris omzet sistem, omzet dilaporkan, atau bagi hasil.
     *
     * @return array<int, array{0: string, 1: mixed, 2: bool}>
     */
    public static function pdfSummaryLines(array $summary): array
    {
        $hidden = [
            __('tax_filing.col.system_revenue'),
            __('tax_filing.col.revenue'),
            __('tax_filing.field.investor_share'),
            __('tax_filing.col.investor_share'),
        ];

        $lines = array_values(array_filter(
            static::summaryLines($summary),
            fn (array $line) => ! in_array($line[0], $hidden, true),
        ));

        return array_map(
            fn (array $line) => $line[0] === __('tax_filing.col.tax_base')
                ? [__('tax_filing.col.omzet'), $line[1], $line[2]]
                : $line,
            $lines,
        );
    }

    /** @return array<int, array{key: string, label: string}> */
    public static function dayColumns(): array
    {
        $columns = [['key' => 'date', 'label' => __('report.date')]];

        foreach (Channels::all() as $channel) {
            $columns[] = ['key' => $channel['key'], 'label' => $channel['label']];
        }

        $columns[] = ['key' => 'total_sales', 'label' => __('zeytin.col.total_sales')];

        return $columns;
    }

    public function build(): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr(__('nav.tax_filing'), 0, 31));

        $sheet->fromArray([[__('nav.tax_filing').' — '.$this->report['label']], [__('tax_filing.source')], []], null, 'A1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $row = 4;

        foreach (static::summaryLines($this->report['summary']) as [$label, $value, $money]) {
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('B'.$row, $value);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true);

            if ($money) {
                $sheet->getStyle('B'.$row)->getNumberFormat()->setFormatCode(static::MONEY_FORMAT);
            }

            $sheet->getStyle('B'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $row++;
        }

        $row += 1;
        $sheet->setCellValue('A'.$row, __('tax_filing.month.daily_title'));
        $sheet->getStyle('A'.$row)->getFont()->setBold(true);
        $row++;

        $columns = static::dayColumns();
        $header = $row;
        $sheet->fromArray([array_column($columns, 'label')], null, 'A'.$header);

        $lines = [];

        foreach ($this->report['days'] as $day) {
            $lines[] = array_map(
                fn (array $c) => $c['key'] === 'date' ? $day['date']->toDateString() : (int) $day[$c['key']],
                $columns,
            );
        }

        $totals = [__('report.grand_total')];

        foreach (array_slice($columns, 1) as $c) {
            $totals[] = (int) $this->report['days']->sum($c['key']);
        }

        $lines[] = $totals;
        $sheet->fromArray($lines, null, 'A'.($header + 1));

        $last = $header + count($lines);
        $lastLetter = Coordinate::stringFromColumnIndex(count($columns));
        $range = 'A'.$header.':'.$lastLetter.$header;

        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(static::HEADER_COLOR);
        $sheet->getStyle('A'.$last.':'.$lastLetter.$last)->getFont()->setBold(true);

        $money = $sheet->getStyle('B'.($header + 1).':'.$lastLetter.$last);
        $money->getNumberFormat()->setFormatCode(static::MONEY_FORMAT);
        $money->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        foreach (range(1, count($columns)) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setWidth($index === 1 ? 30 : 16);
        }

        return $book;
    }

    public function filename(): string
    {
        return str(__('nav.tax_filing'))->slug()
            ->append(sprintf('-%d-%02d.xlsx', $this->report['year'], $this->report['month']))
            ->toString();
    }
}

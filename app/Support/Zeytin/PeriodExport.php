<?php

namespace App\Support\Zeytin;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Unduhan Excel untuk laporan satu periode.
 *
 * Dibuat dari laporan yang sedang tampil di layar, bukan dihitung ulang
 * dengan cara lain. Berkas yang dikirim ke pemilik karena itu tidak mungkin
 * berbeda dari yang dilihat stafnya — kalau angka yang sama dihitung dua kali
 * lewat dua jalur, cepat atau lambat keduanya akan berselisih.
 */
class PeriodExport
{
    /**
     * Rupiah tanpa sen, pemisah ribuan titik — sama seperti yang dilihat di
     * layar lewat App\Support\Money. Ditulis sebagai format sel, bukan
     * sebagai teks "Rp 45.000", supaya angkanya tetap bisa dijumlahkan di
     * Excel oleh orang yang menerimanya.
     */
    protected const MONEY_FORMAT = '#,##0;-#,##0';

    protected const MONEY_COLUMNS = ['price', 'disc', 'tax', 'total', 'basic', 'bpjs', 'grand_total'];

    /** @param  array<string, mixed>  $report  hasil DailyLedger::periodReport() */
    public function __construct(protected array $report) {}

    public function build(): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);

        $this->summarySheet($book);
        $this->dailySheet($book);
        $this->rollUpSheet($book, __('zeytin.export.monthly'), DailyLedger::groupByMonth($this->report['rows']));
        $this->rollUpSheet($book, __('zeytin.export.yearly'), DailyLedger::groupByYear($this->report['rows']));
        $this->recordSheets($book);

        $book->setActiveSheetIndex(0);

        return $book;
    }

    public function save(string $path): string
    {
        (new Xlsx($this->build()))->save($path);

        return $path;
    }

    public function filename(): string
    {
        return 'zeytin-'.$this->report['from']->toDateString().'_'.$this->report['to']->toDateString().'.xlsx';
    }

    protected function summarySheet(Spreadsheet $book): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle(__('zeytin.export.summary'));

        $rows = [
            [__('zeytin.card.sales'), $this->report['total_sales']],
            [__('zeytin.card.cashless'), $this->report['cashless']],
            [__('zeytin.card.cash_expense'), $this->report['cash_expense']],
            [__('zeytin.card.transfers'), $this->report['transfers']],
            [__('zeytin.card.payroll'), $this->report['payroll']],
            [__('zeytin.card.total_expenses'), $this->report['total_expenses']],
            [__('zeytin.card.outstanding'), $this->report['outstanding']],
            [__('zeytin.card.remaining_supplier_cash'), $this->report['remaining_supplier_cash']],
            [__('zeytin.card.profit'), $this->report['net_profit']],
            [__('zeytin.card.global_balance'), $this->report['global_balance']],
        ];

        $sheet->fromArray([
            [__('zeytin.export.period'), $this->report['from']->toDateString().' … '.$this->report['to']->toDateString()],
            [__('zeytin.export.recorded_days'), $this->report['recorded_days'].' / '.$this->report['days']],
            [],
            ...$rows,
        ], null, 'A1');

        $sheet->getStyle('A1:A2')->getFont()->setBold(true);
        $sheet->getStyle('B4:B'.(3 + count($rows)))->getNumberFormat()->setFormatCode(static::MONEY_FORMAT);
        $sheet->getColumnDimension('A')->setWidth(34);
        $sheet->getColumnDimension('B')->setWidth(20);
    }

    protected function dailySheet(Spreadsheet $book): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle(__('zeytin.export.daily'));

        $header = [__('report.date')];

        foreach (Channels::all() as $channel) {
            $header[] = $channel['label'];
        }

        $header = [
            ...$header,
            __('zeytin.col.total_sales'),
            __('zeytin.col.cashless'),
            __('zeytin.col.expense'),
            __('zeytin.col.supplier_cash'),
            __('zeytin.col.remaining_supplier_cash'),
        ];

        $lines = [];

        foreach ($this->report['rows'] as $row) {
            $line = [$row['date']->toDateString()];

            foreach (Channels::keys() as $key) {
                $line[] = $row[$key];
            }

            $lines[] = [
                ...$line,
                $row['total_sales'],
                $row['cashless'],
                $row['expense'],
                $row['supplier_cash'],
                $row['remaining_supplier_cash'],
            ];
        }

        $this->write($sheet, $header, $lines);
    }

    /** @param  Collection<int, array<string, mixed>>  $rows */
    protected function rollUpSheet(Spreadsheet $book, string $title, Collection $rows): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle($title);

        $header = [__('report.period'), __('report.days')];

        foreach (Channels::all() as $channel) {
            $header[] = $channel['label'];
        }

        $header = [...$header, __('zeytin.col.total_sales'), __('zeytin.col.cashless'), __('zeytin.col.expense')];

        $lines = [];

        foreach ($rows as $row) {
            $line = [$row['key'], $row['recorded_days'].' / '.$row['days']];

            foreach (Channels::keys() as $key) {
                $line[] = $row[$key];
            }

            $lines[] = [...$line, $row['total_sales'], $row['cashless'], $row['expense']];
        }

        $this->write($sheet, $header, $lines, moneyFrom: 'C');
    }

    /**
     * Catatan mentah di balik ringkasan, satu sheet per jenis.
     *
     * Barisnya diambil lewat DailyLedger, jalur yang sama dengan yang
     * dijumlahkan ringkasan, jadi baris "Total" tiap sheet sama dengan angka
     * di sheet Ringkasan. Penerima berkas bisa menelusuri tiap angka ke
     * belanja, transfer, gaji, atau tagihan yang membentuknya.
     */
    protected function recordSheets(Spreadsheet $book): void
    {
        $from = $this->report['from'];
        $to = $this->report['to'];

        $this->recordSheet($book, __('zeytin.nav.purchases'),
            DailyLedger::purchases($from, $to)->orderBy('date')->orderBy('id')->get(),
            ['date', 'vendor', 'item', 'qty', 'unit', 'price', 'disc', 'tax', 'total']);

        $this->recordSheet($book, __('zeytin.nav.transfers'),
            DailyLedger::transfers($from, $to)->orderBy('date')->orderBy('id')->get(),
            ['date', 'vendor', 'item', 'qty', 'unit', 'price', 'total', 'method', 'status']);

        // Gaji hanya totalnya per bulan, bukan per orang: unduhan ini milik
        // Admin, dan gaji per orang hanya boleh dilihat Super Admin.
        $this->recordSheet($book, __('zeytin.nav.payroll'),
            DailyLedger::payroll($from, $to)
                ->selectRaw('month, SUM(grand_total) as grand_total')
                ->groupBy('month')
                ->orderBy('month')
                ->get(),
            ['month', 'grand_total']);

        $this->recordSheet($book, __('zeytin.nav.outstanding'),
            DailyLedger::unsettledBills(),
            ['date', 'due_date', 'vendor', 'item', 'qty', 'unit', 'price', 'disc', 'tax', 'total', 'status']);
    }

    /**
     * @param  Collection<int, Model>  $records
     * @param  array<int, string>  $columns  nama kolom tabel; kolom uang terakhir dijumlahkan di baris Total
     */
    protected function recordSheet(Spreadsheet $book, string $title, Collection $records, array $columns): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle($title);

        $lines = $records->map(fn (Model $record) => array_map(
            fn (string $column) => static::cell($column, $record->getAttribute($column)),
            $columns,
        ))->all();

        $money = array_values(array_intersect($columns, static::MONEY_COLUMNS));
        $totalColumn = end($money);

        $footer = array_map(fn (string $column) => match ($column) {
            $columns[0] => __('report.grand_total'),
            $totalColumn => (int) $records->sum($totalColumn),
            default => null,
        }, $columns);

        $sheet->fromArray([
            array_map(fn (string $column) => __('zeytin.field.'.$column), $columns),
            ...$lines,
            $footer,
        ], null, 'A1');

        $last = $sheet->getHighestColumn();
        $footerRow = count($lines) + 2;

        $sheet->getStyle('A1:'.$last.'1')->getFont()->setBold(true);
        $sheet->getStyle('A'.$footerRow.':'.$last.$footerRow)->getFont()->setBold(true);
        $sheet->freezePane('A2');

        foreach ($money as $column) {
            $letter = Coordinate::stringFromColumnIndex(array_search($column, $columns, true) + 1);

            $sheet->getStyle($letter.'2:'.$letter.$footerRow)
                ->getNumberFormat()
                ->setFormatCode(static::MONEY_FORMAT);
        }

        static::autoSize($sheet);
    }

    /** Tanggal ditulis sebagai teks tanggal, bulan gaji sebagai tahun-bulan. */
    protected static function cell(string $column, mixed $value): mixed
    {
        if ($value instanceof CarbonInterface) {
            return $column === 'month' ? $value->format('Y-m') : $value->toDateString();
        }

        return $value;
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<int, mixed>>  $lines
     */
    protected function write(Worksheet $sheet, array $header, array $lines, string $moneyFrom = 'B'): void
    {
        $sheet->fromArray([$header, ...$lines], null, 'A1');

        $last = $sheet->getHighestColumn();
        $sheet->getStyle('A1:'.$last.'1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        if ($lines) {
            $sheet->getStyle($moneyFrom.'2:'.$last.(count($lines) + 1))
                ->getNumberFormat()
                ->setFormatCode(static::MONEY_FORMAT);
        }

        static::autoSize($sheet);
    }

    /**
     * Lebar kolom mengikuti isinya.
     *
     * Lewat iterator kolom, bukan range('A', $last): begitu sebuah sheet
     * melewati kolom Z, range() menghasilkan urutan huruf yang bukan nama
     * kolom Excel dan lebarnya diterapkan ke kolom yang tidak ada.
     */
    protected static function autoSize(Worksheet $sheet): void
    {
        foreach ($sheet->getColumnIterator() as $column) {
            $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
        }
    }
}

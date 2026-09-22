<?php

namespace App\Support\Excel;

use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Tampilan yang sama di semua template Excel: judul kolom berwarna
 * aplikasi, sheet daftar pilihan yang disembunyikan, dan tabel contoh di
 * sheet petunjuk. Dipakai template bulanan (Zeytin) dan template per menu.
 */
trait StylesWorkbook
{
    /** Warna aplikasi untuk judul kolom. */
    public const COLOR_HEADER = 'FF0F6E6E';

    /** Dihitung ulang aplikasi, isinya tidak dibaca. */
    public const COLOR_COMPUTED = 'FFE2E8F0';

    /** Rumus awal yang dibaca dan boleh ditimpa. */
    public const COLOR_SUGGESTED = 'FFFEF3C7';

    /** Isian di luar rentang yang diterima. */
    public const COLOR_INVALID = 'FFFECACA';

    protected function styleHeader(Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $this->fill($sheet, $range, self::COLOR_HEADER);

        $sheet->getRowDimension((int) preg_replace('/\D/', '', explode(':', $range)[0]))->setRowHeight(30);
    }

    protected function fill(Worksheet $sheet, string $range, string $color): void
    {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($color);
    }

    /** Tanggal sebagai rumus Excel, dipakai aturan isian dan pewarnaan. */
    protected function excelDate(Carbon $date): string
    {
        return 'DATE('.$date->year.','.$date->month.','.$date->day.')';
    }

    /**
     * Rentang sel tiap daftar di sheet daftar. Daftar kosong tidak diberi
     * rentang, jadi kolomnya tetap bebas diisi, bukan terkunci ke daftar
     * tanpa isi.
     *
     * @param  array<string, array<int, string>>  $values
     * @return array<string, string>
     */
    protected function listRanges(string $title, array $values): array
    {
        $sheet = "'".str_replace("'", "''", $title)."'";
        $ranges = [];
        $index = 1;

        foreach ($values as $key => $items) {
            $col = Coordinate::stringFromColumnIndex($index++);

            if ($items) {
                $ranges[$key] = $sheet.'!$'.$col.'$2:$'.$col.'$'.(count($items) + 1);
            }
        }

        return $ranges;
    }

    /**
     * @param  array<string, array<int, string>>  $values
     * @param  array<string, string>  $labels  judul kolom tiap daftar
     */
    protected function addListSheet(Spreadsheet $book, string $title, array $values, array $labels): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle($title);

        $index = 1;

        foreach ($values as $key => $items) {
            $col = Coordinate::stringFromColumnIndex($index++);

            $sheet->setCellValue($col.'1', $labels[$key] ?? $key);
            $sheet->getColumnDimension($col)->setWidth(24);

            // Ditulis sebagai teks: nama yang kebetulan diawali "=" tidak
            // boleh berubah jadi rumus.
            foreach (array_values($items) as $n => $item) {
                $sheet->setCellValueExplicit($col.($n + 2), $item, DataType::TYPE_STRING);
            }
        }

        if ($values) {
            $this->styleHeader($sheet, 'A1:'.Coordinate::stringFromColumnIndex(count($values)).'1');
        }

        $sheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    }

    /**
     * Satu baris tabel contoh di sheet petunjuk.
     *
     * @param  array<int, mixed>  $values  berurutan sesuai kolom
     */
    protected function writeExampleRow(Worksheet $sheet, int $row, array $values): void
    {
        foreach ($values as $i => $value) {
            $cell = Coordinate::stringFromColumnIndex($i + 1).$row;

            if ($value instanceof Carbon) {
                $sheet->setCellValue($cell, ExcelDate::PHPToExcel($value));
                $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            } elseif (is_int($value)) {
                $sheet->setCellValue($cell, $value);
                $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0');
            } elseif ($value !== null && $value !== '') {
                $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
            }
        }
    }

    /** Judul bagian di sheet petunjuk. Mengembalikan baris berikutnya. */
    protected function guideHeading(Worksheet $sheet, int $row, string $text): int
    {
        $sheet->setCellValue('A'.$row, $text);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(12);

        return $row + 1;
    }

    /** Nama sheet petunjuk di semua bahasa: diabaikan saat mencari data. */
    public static function guideTitles(): array
    {
        $titles = [];

        foreach (['id', 'en'] as $locale) {
            $titles[] = mb_strtolower(__('zeytin.template.tab', [], $locale));
            $titles[] = mb_strtolower(__('excel.guide_tab', [], $locale));
        }

        return array_values(array_unique($titles));
    }
}

<?php

namespace App\Support\Zeytin\Workbook;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Berkas contoh berisi keenam sheet dengan nama dan judul kolom yang benar.
 *
 * Pengimpornya menolak sheet yang nama atau judul kolomnya tidak ia kenali,
 * tapi tanpa contoh berkas yang benar, satu-satunya cara tahu bentuk yang
 * diterima adalah mencoba lalu gagal.
 *
 * Sengaja tanpa baris contoh: baris yang lupa dihapus akan ikut terimpor
 * sebagai data sungguhan. Petunjuk pengisiannya ditaruh di sheet terpisah
 * yang namanya tidak dikenali pembaca, jadi ia diabaikan begitu saja.
 */
class TemplateBuilder
{
    public function build(): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);

        foreach (SheetSpec::all() as $spec) {
            $sheet = $book->createSheet();
            $sheet->setTitle($spec->sheet);
            $sheet->fromArray($spec->templateHeader(), null, 'A1');

            $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);
            $sheet->freezePane('A2');

            foreach ($sheet->getColumnIterator() as $column) {
                $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
            }
        }

        $this->addGuide($book);
        $book->setActiveSheetIndex(0);

        return $book;
    }

    public function save(string $path): string
    {
        (new Xlsx($this->build()))->save($path);

        return $path;
    }

    protected function addGuide(Spreadsheet $book): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle(__('zeytin.template.tab'));

        $lines = [
            [__('zeytin.template.title')],
            [],
            [__('zeytin.template.sheet')],
            [__('zeytin.template.header')],
            [__('zeytin.template.date')],
            [__('zeytin.template.money')],
            [__('zeytin.template.month')],
            [__('zeytin.template.extra')],
            [],
            [__('zeytin.template.sheet_list')],
        ];

        foreach (SheetSpec::all() as $spec) {
            $lines[] = [$spec->sheet, implode(' · ', $spec->templateHeader())];
        }

        $sheet->fromArray($lines, null, 'A1');
        $sheet->getStyle('A1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(34);
        $sheet->getColumnDimension('B')->setWidth(90);
    }
}

<?php

namespace App\Support\Excel;

use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Sesuatu yang punya template Excel dan bisa diimpor dari Excel — satu per
 * menu input. Tombol "Unduh template" dan "Impor Excel" di tiap halaman
 * hanya bicara lewat antarmuka ini (lihat App\Filament\Admin\Actions\ExcelActions).
 *
 * Dua pelaksananya:
 *   - ExcelSheet: menu data induk dan catatan biasa, kolomnya didaftar di
 *     resource-nya masing-masing.
 *   - Zeytin\Workbook\SpecSource: menu pembukuan bulanan, yang bentuk
 *     sheet-nya harus sama persis dengan berkas Excel bulanan klien.
 */
interface ExcelSource
{
    /** Nama menunya, untuk judul jendela impor dan nama berkas. */
    public function title(): string;

    /** Template dibatasi ke satu bulan (tanggalnya), jadi bulannya ditanyakan. */
    public function templateNeedsMonth(): bool;

    /** Berkasnya tidak menyebutkan bulannya sendiri (sheet Payroll). */
    public function importNeedsMonth(): bool;

    public function template(?Carbon $month = null): Spreadsheet;

    public function filename(?Carbon $month = null): string;

    public function import(string $path, ?Carbon $month = null): ImportReport;
}

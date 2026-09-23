<?php

namespace App\Support\Zeytin\Workbook;

use App\Support\Excel\ExcelSource;
use App\Support\Excel\ImportReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use RuntimeException;

/**
 * Tombol Excel di halaman pembukuan (Pemasukan Harian, Belanja Tunai,
 * Transfer Pemasok, Tagihan, Gaji): satu sheet dari template bulanan.
 *
 * Bentuk sheet-nya sengaja sama persis dengan sheet bernama sama di berkas
 * Excel bulanan klien, dan dibaca oleh pengimpor yang sama. Jadi satu sheet
 * bisa disalin dari berkas bulanan dan diunggah di halamannya sendiri, dan
 * aturan impornya — impor ulang tidak menggandakan, ketikan orang tidak
 * disentuh — sama di kedua tempat.
 */
class SpecSource implements ExcelSource
{
    public function __construct(protected string $sheet, protected string $title) {}

    public static function make(string $sheet, string $title): static
    {
        return new static($sheet, $title);
    }

    public function spec(): SheetSpec
    {
        foreach (SheetSpec::all() as $spec) {
            if ($spec->sheet === $this->sheet) {
                return $spec;
            }
        }

        throw new RuntimeException("Sheet {$this->sheet} tidak dikenal.");
    }

    public function title(): string
    {
        return $this->title;
    }

    public function templateNeedsMonth(): bool
    {
        return true;
    }

    /** Payroll dicatat untuk bulan itu; sheet bertanggal diperiksa tanggalnya terhadap bulan itu. */
    public function importNeedsMonth(): bool
    {
        return $this->spec()->needsMonth || $this->spec()->sameMonth;
    }

    public function template(?Carbon $month = null): Spreadsheet
    {
        return (new TemplateBuilder($month, [$this->sheet]))->build();
    }

    public function filename(?Carbon $month = null): string
    {
        return (new TemplateBuilder($month, [$this->sheet]))->filename();
    }

    public function import(string $path, ?Carbon $month = null): ImportReport
    {
        $result = DB::transaction(fn () => (new Importer($month))->importOne($path, $this->spec()));

        // Template yang belum diisi bukan kesalahan: tidak ada yang berubah.
        if ($result['error'] === 'empty') {
            return new ImportReport;
        }

        if ($result['error'] === 'out_of_month') {
            return ImportReport::failed(array_map(fn (array $row) => __('zeytin.import.error.row_out_of_month', [
                'row' => $row['line'],
                'date' => Carbon::parse($row['date'])->translatedFormat('j M Y'),
                'month' => $month?->translatedFormat('F Y'),
            ]), $result['rows']));
        }

        if ($result['error']) {
            return ImportReport::failed([__('zeytin.import.error.'.$result['error'])]);
        }

        /*
         * Di sini tidak ada layar untuk bertanya, jadi baris yang sama dengan
         * ketikan manual selalu dilewati — pilihan yang tidak mungkin
         * menggandakan total — dan nomor barisnya disebutkan. Untuk memilih
         * satu per satu, pakai halaman Impor Excel.
         */
        $notes = array_filter([
            $result['range'] ? __('excel.result.range', ['range' => $result['range']]) : null,
            $result['skipped'] ? __('zeytin.import.review.skipped_rows', [
                'rows' => implode(', ', array_column($result['duplicates'], 'line')),
            ]) : null,
        ]);

        return new ImportReport(
            counts: ['imported' => $result['imported'], 'replaced' => $result['replaced'], 'manual' => $result['skipped']],
            note: $notes ? implode(' ', $notes) : null,
        );
    }
}

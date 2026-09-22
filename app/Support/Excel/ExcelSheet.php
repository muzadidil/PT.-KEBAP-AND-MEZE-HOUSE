<?php

namespace App\Support\Excel;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Template dan impor Excel untuk satu menu, dideklarasikan di resource-nya
 * sendiri di sebelah formulirnya — mengubah formulir tanpa melirik daftar
 * kolom ini sulit terlewat.
 *
 * Baris yang diimpor dicocokkan ke data lama dengan `matchBy`:
 *
 *   - Data induk (pemasok, menu, karyawan…): baris yang namanya sudah ada
 *     diperbarui, bukan ditambah. Sel kosong tidak menghapus isi lama.
 *   - Catatan tanpa nama (pengeluaran, modal): tidak ada kunci. Baris yang
 *     isinya persis sama dengan yang sudah tercatat dilewati, jadi berkas
 *     yang sama aman diimpor dua kali. Dua baris kembar yang sah di berkas
 *     tetap jadi dua baris — yang dihitung jumlahnya, bukan cuma adanya.
 */
class ExcelSheet implements ExcelSource
{
    /** @var array<int, Column> */
    protected array $columns = [];

    /** @var array<int, array<int, string>> */
    protected array $matchBy = [];

    /** @var array<int, array<string, mixed>> */
    protected array $examples = [];

    /** @var (Closure(Builder): mixed)|null */
    protected ?Closure $scope = null;

    /**
     * @param  class-string<Model>  $model
     */
    public function __construct(public string $model, protected string $title) {}

    /** @param  class-string<Model>  $model */
    public static function make(string $model, string $title): static
    {
        return new static($model, $title);
    }

    /** @param  array<int, Column>  $columns */
    public function columns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Kunci pencocokan, dicoba berurutan: `matchBy(['sku'], ['name_en'])`
     * mencocokkan lewat kode dulu, lalu lewat nama kalau kodenya kosong.
     * Kunci pertama tiap set harus terisi supaya set itu dipakai.
     *
     * @param  array<int, string>  ...$sets
     */
    public function matchBy(array ...$sets): static
    {
        $this->matchBy = $sets;

        return $this;
    }

    /** @param  array<int, array<string, mixed>>  $rows  baris contoh, dikunci nama kolom */
    public function examples(array $rows): static
    {
        $this->examples = $rows;

        return $this;
    }

    /** Membatasi baris lama yang boleh dicocokkan (mis. tugas utama saja). */
    public function scope(Closure $scope): static
    {
        $this->scope = $scope;

        return $this;
    }

    /** @return array<int, Column> */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function column(string $name): ?Column
    {
        foreach ($this->columns as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }

        return null;
    }

    /** @return array<int, array<int, string>> */
    public function getMatchBy(): array
    {
        return $this->matchBy;
    }

    /** @return array<int, array<string, mixed>> */
    public function getExamples(): array
    {
        return $this->examples;
    }

    public function query(): Builder
    {
        $query = $this->model::query();

        if ($this->scope) {
            ($this->scope)($query);
        }

        return $query;
    }

    /** Nama sheet data: judul menunya, dipangkas ke batas Excel (31 huruf). */
    public function sheetName(): string
    {
        return mb_substr(str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $this->title), 0, 31);
    }

    /* ------------------------------------------------------ ExcelSource */

    public function title(): string
    {
        return $this->title;
    }

    public function templateNeedsMonth(): bool
    {
        return false;
    }

    public function importNeedsMonth(): bool
    {
        return false;
    }

    public function template(?Carbon $month = null): Spreadsheet
    {
        return (new SheetTemplate($this))->build();
    }

    public function filename(?Carbon $month = null): string
    {
        return 'template-'.Str::slug($this->title).'.xlsx';
    }

    public function import(string $path, ?Carbon $month = null): ImportReport
    {
        return (new SheetImporter($this))->import($path);
    }
}

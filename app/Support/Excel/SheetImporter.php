<?php

namespace App\Support\Excel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Calculation\Calculation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

/**
 * Membaca berkas Excel satu menu ke basis data.
 *
 * Semua atau tidak sama sekali: setiap sel diperiksa dulu, dan kalau ada
 * satu saja yang salah, tidak ada yang disimpan — yang dikembalikan daftar
 * kesalahannya, per baris dan per kolom. Separuh berkas yang terimpor lebih
 * sulit dibereskan daripada tidak ada yang terimpor, karena tidak ada yang
 * tahu separuh mana.
 *
 * Kolom dikenali dari judulnya, bukan posisinya: urutan kolom boleh diubah,
 * kolom tambahan diabaikan, dan kolom yang tidak ada di berkas tidak
 * menyentuh isi lama.
 */
class SheetImporter
{
    /** @var array<string, int|string> pilihan yang dibuat selama impor ini */
    protected array $created = [];

    public function __construct(protected ExcelSheet $sheet) {}

    public function import(string $path): ImportReport
    {
        $book = IOFactory::load($path);
        Calculation::getInstance($book)->setSuppressFormulaErrors(true);

        try {
            $located = $this->locate($book);
        } finally {
            $book->disconnectWorksheets();
        }

        if (! $located) {
            return ImportReport::failed([__('excel.error.no_header', ['columns' => $this->requiredTitles()])]);
        }

        [$grid, $header] = $located;
        [$rows, $errors] = $this->read($grid, $header);

        $errors = [...$errors, ...$this->duplicates($rows)];

        if ($errors) {
            return ImportReport::failed($errors);
        }

        try {
            return DB::transaction(fn () => $this->sheet->getMatchBy()
                ? $this->saveMatched($rows)
                : $this->saveCounted($rows));
        } catch (RuntimeException $e) {
            return ImportReport::failed([$e->getMessage()]);
        }
    }

    /* ------------------------------------------------------------ membaca */

    /**
     * Sheet dan baris judulnya. Sheet bernama sama dengan menunya dicoba
     * dulu, lalu sheet lain yang terlihat — kecuali sheet petunjuk, yang
     * memuat tabel contoh dengan judul kolom yang sama.
     *
     * @return array{0: array<int, array<int, mixed>>, 1: int}|null
     */
    protected function locate(Spreadsheet $book): ?array
    {
        $named = $book->getSheetByName($this->sheet->sheetName());
        $sheets = $named ? [$named] : [];

        foreach ($book->getWorksheetIterator() as $candidate) {
            if ($candidate === $named
                || $candidate->getSheetState() !== Worksheet::SHEETSTATE_VISIBLE
                || in_array(mb_strtolower($candidate->getTitle()), SheetTemplate::guideTitles(), true)) {
                continue;
            }

            $sheets[] = $candidate;
        }

        foreach ($sheets as $candidate) {
            // formatData mati: tanggal kembali sebagai serial angka, tidak
            // terpengaruh format tampilan yang dipilih di Excel.
            $grid = $candidate->toArray(null, true, false, false);

            foreach ($grid as $index => $cells) {
                if ($this->isHeader($cells)) {
                    return [$grid, $index];
                }
            }
        }

        return null;
    }

    /** @param  array<int, mixed>  $cells */
    protected function isHeader(array $cells): bool
    {
        $normalised = array_map([Column::class, 'norm'], $cells);

        foreach ($this->sheet->getColumns() as $column) {
            if ($column->required && ! array_intersect($column->aliases(), $normalised)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<int, mixed>>  $grid
     * @return array{0: array<int, array{line: int, values: array<string, mixed>}>, 1: array<int, string>}
     */
    protected function read(array $grid, int $header): array
    {
        $indexes = [];

        foreach ($this->sheet->getColumns() as $column) {
            foreach ($grid[$header] as $index => $cell) {
                if (in_array(Column::norm($cell), $column->aliases(), true)) {
                    $indexes[$column->name] = $index;
                    break;
                }
            }
        }

        $rows = [];
        $errors = [];

        for ($r = $header + 1, $count = count($grid); $r < $count; $r++) {
            $cells = $grid[$r];

            if (! $this->hasContent($cells, $indexes)) {
                continue;
            }

            $values = [];

            foreach ($this->sheet->getColumns() as $column) {
                if (! array_key_exists($column->name, $indexes)) {
                    continue;
                }

                [$value, $error] = $column->read($cells[$indexes[$column->name]] ?? null);

                if ($error) {
                    $errors[] = __('excel.error.row', ['row' => $r + 1, 'column' => __($column->label), 'message' => $error]);

                    continue;
                }

                $values[$column->name] = $value;
            }

            $rows[] = ['line' => $r + 1, 'values' => $values];
        }

        return [$rows, $errors];
    }

    /**
     * @param  array<int, mixed>  $cells
     * @param  array<string, int>  $indexes
     */
    protected function hasContent(array $cells, array $indexes): bool
    {
        foreach ($indexes as $index) {
            $cell = $cells[$index] ?? null;

            if ($cell !== null && trim((string) $cell) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Dua baris dengan kunci yang sama di satu berkas — mana yang benar?
     * Ditanyakan, bukan ditebak dengan memakai yang terakhir.
     *
     * @param  array<int, array{line: int, values: array<string, mixed>}>  $rows
     * @return array<int, string>
     */
    protected function duplicates(array $rows): array
    {
        $seen = [];
        $errors = [];

        foreach ($rows as $row) {
            $key = $this->matchKey($this->withDefaults($row['values']));

            if ($key === null) {
                continue;
            }

            if (isset($seen[$key])) {
                $errors[] = __('excel.error.duplicate', ['row' => $row['line'], 'first' => $seen[$key]]);

                continue;
            }

            $seen[$key] = $row['line'];
        }

        return $errors;
    }

    /** @param  array<string, mixed>  $values */
    protected function matchKey(array $values): ?string
    {
        foreach ($this->sheet->getMatchBy() as $n => $set) {
            if (($values[$set[0]] ?? null) === null) {
                continue;
            }

            return $n.'|'.implode('|', array_map(fn (string $key) => $this->plain($values[$key] ?? null), $set));
        }

        return null;
    }

    protected function plain(mixed $value): string
    {
        return match (true) {
            $value instanceof PendingChoice => Column::norm($value->label),
            is_bool($value) => $value ? '1' : '0',
            default => Column::norm($value),
        };
    }

    /* ---------------------------------------------------------- menyimpan */

    /**
     * Data induk: yang kuncinya sudah ada diperbarui, sisanya ditambah. Sel
     * kosong tidak menghapus isi lama.
     *
     * @param  array<int, array{line: int, values: array<string, mixed>}>  $rows
     */
    protected function saveMatched(array $rows): ImportReport
    {
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($rows as $row) {
            $this->guard($row['line'], function () use ($row, &$counts) {
                $values = $this->resolve($row['values']);

                // Nilai bawaan ikut dipakai mencari (jenis kosong = Pendapatan),
                // tapi tidak ditulis ke baris lama: sel Aktif yang kosong tidak
                // boleh mengaktifkan lagi pemasok yang sengaja dinonaktifkan.
                if ($existing = $this->findExisting($this->withDefaults($values))) {
                    $existing->forceFill(array_filter($values, fn ($value) => $value !== null));

                    if ($existing->isDirty()) {
                        $existing->save();
                        $counts['updated']++;
                    } else {
                        $counts['skipped']++;
                    }

                    return;
                }

                $this->create($values);
                $counts['created']++;
            });
        }

        return new ImportReport($counts);
    }

    /**
     * Catatan tanpa kunci: baris yang isinya persis sama dengan yang sudah
     * tercatat dilewati. Dihitung per isi — tiga baris kembar di berkas dan
     * satu yang sudah tercatat berarti dua baris baru.
     *
     * @param  array<int, array{line: int, values: array<string, mixed>}>  $rows
     */
    protected function saveCounted(array $rows): ImportReport
    {
        $groups = [];

        foreach ($rows as $row) {
            $values = $this->withDefaults($row['values']);
            $key = implode('|', array_map(fn ($value) => $this->plain($value), $values));

            $groups[$key] ??= ['line' => $row['line'], 'values' => $values, 'count' => 0];
            $groups[$key]['count']++;
        }

        $counts = ['created' => 0, 'skipped' => 0];

        foreach ($groups as $group) {
            $this->guard($group['line'], function () use ($group, &$counts) {
                $values = $this->resolve($group['values']);
                $new = max(0, $group['count'] - $this->sameContent($values)->count());

                for ($i = 0; $i < $new; $i++) {
                    $this->create($values);
                }

                $counts['created'] += $new;
                $counts['skipped'] += $group['count'] - $new;
            });
        }

        return new ImportReport($counts);
    }

    /** Galat basis data diberi nomor baris Excel-nya, lalu seluruh impor dibatalkan. */
    protected function guard(int $line, callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            $message = $e instanceof QueryException ? ($e->errorInfo[2] ?? $e->getMessage()) : $e->getMessage();

            throw new RuntimeException(__('excel.error.save', ['row' => $line, 'message' => $message]), 0, $e);
        }
    }

    /**
     * Nilai bawaan untuk sel yang kosong atau kolom yang tidak ada di berkas
     * — nilai yang sama yang akan dipakai kalau barisnya dibuat baru.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function withDefaults(array $values): array
    {
        foreach ($this->sheet->getColumns() as $column) {
            if (($values[$column->name] ?? null) === null && $column->default !== null) {
                $values[$column->name] = $column->default;
            }
        }

        return $values;
    }

    /**
     * Pilihan baru (mis. proyek yang belum ada) dibuat di sini, di dalam
     * transaksi impor, sekali per nama.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function resolve(array $values): array
    {
        foreach ($values as $name => $value) {
            if (! $value instanceof PendingChoice) {
                continue;
            }

            $key = $name.'|'.Column::norm($value->label);

            $values[$name] = $this->created[$key] ??= ($this->sheet->column($name)->creator)($value->label);
        }

        return $values;
    }

    /** @param  array<string, mixed>  $values */
    protected function findExisting(array $values): ?Model
    {
        foreach ($this->sheet->getMatchBy() as $set) {
            if (($values[$set[0]] ?? null) === null) {
                continue;
            }

            $query = $this->sheet->query();

            foreach ($set as $key) {
                $this->whereValue($query, $key, $values[$key] ?? null, caseless: true);
            }

            if ($found = $query->first()) {
                return $found;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $values */
    protected function sameContent(array $values): Builder
    {
        $query = $this->sheet->query();

        foreach ($values as $key => $value) {
            $this->whereValue($query, $key, $value);
        }

        return $query;
    }

    protected function whereValue(Builder $query, string $key, mixed $value, bool $caseless = false): void
    {
        $column = $query->getModel()->qualifyColumn($key);

        match (true) {
            $value === null => $query->whereNull($column),
            $this->sheet->column($key)?->type === Column::DATE => $query->whereDate($column, $value),
            $caseless && is_string($value) => $query->whereRaw(
                'LOWER('.$query->getQuery()->getGrammar()->wrap($column).') = ?',
                [mb_strtolower($value)],
            ),
            default => $query->where($column, $value),
        };
    }

    /** @param  array<string, mixed>  $values */
    protected function create(array $values): void
    {
        /** @var Model $model */
        $model = new ($this->sheet->model);

        foreach ($this->sheet->getColumns() as $column) {
            $value = $values[$column->name] ?? $column->default;

            if ($value !== null) {
                $model->setAttribute($column->name, $value);
            }
        }

        $model->save();
    }

    protected function requiredTitles(): string
    {
        return implode(', ', array_map(
            fn (Column $column) => __($column->label),
            array_filter($this->sheet->getColumns(), fn (Column $column) => $column->required),
        ));
    }
}

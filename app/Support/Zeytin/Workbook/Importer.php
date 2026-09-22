<?php

namespace App\Support\Zeytin\Workbook;

use App\Filament\Admin\Resources\Zeytin\Payrolls\PayrollResource;
use App\Models\Supplier;
use App\Support\Zeytin\Channels;
use App\Support\Zeytin\RecordSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Calculation\Calculation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Pengimpor berkas Excel bulanan.
 *
 * Berkas klien ditulis manusia untuk dibaca manusia, bukan untuk diurai
 * mesin, dan bentuknya tidak rapi:
 *
 *   - Tanggal hanya ditulis di baris pertama tiap kelompok; baris di
 *     bawahnya kosong dan harus mewarisi dari atas.
 *   - Baris header muncul lebih dari sekali (Outstanding INV punya dua
 *     blok), dengan judul kolom yang berbeda antar blok.
 *   - Tiap sheet mulai di baris berlainan: Income 7, Expense 4,
 *     Supplier Database 9, Payroll 19.
 *   - Blok rekap menyelip di samping data (Income kolom O–R).
 *
 * Karena itu pengimpor ini TIDAK menganggap "baris 1 header, sisanya data".
 * Ia mencari baris headernya berdasarkan nama kolom, dan kalau tidak ketemu,
 * ia MENOLAK sheet itu dan mengatakannya. Menebak posisi kolom berarti
 * memasukkan angka ke tempat yang salah tanpa ada yang sadar — cara tercepat
 * merusak laporan.
 */
class Importer
{
    /** Beberapa baris kosong berturut-turut menandai satu blok berakhir. */
    protected const BLANK_RUN = 8;

    /** @var array<int, string> */
    protected array $numeric;

    /** @var array<int, string> */
    protected array $text = [
        'vendor', 'item', 'unit', 'method', 'status', 'name', 'section',
        'contact_person', 'supplies', 'bank', 'bank_account', 'account_name',
        'payment_method', 'note',
    ];

    /**
     * @param  Carbon|null  $payrollMonth  bulan untuk sheet Payroll; sheet itu
     *                                     tidak menyebutkan bulannya sendiri.
     */
    public function __construct(protected ?Carbon $payrollMonth = null)
    {
        $this->numeric = [
            'qty', 'price', 'disc', 'tax', 'total',
            'basic', 'bpjs', 'grand_total', 'last_price',
            ...Channels::keys(),
        ];
    }

    /**
     * @return array<int, array{sheet: string, imported: int, replaced: int, range: string, error: string|null}>
     */
    public function import(string $path): array
    {
        $spreadsheet = IOFactory::load($path);

        // Rumus yang tidak dikenali tidak boleh menggagalkan seluruh impor;
        // kolom yang kita baca isinya angka yang diketik, bukan rumus.
        Calculation::getInstance($spreadsheet)->setSuppressFormulaErrors(true);

        $results = [];

        try {
            foreach (SheetSpec::all() as $spec) {
                $results[] = $this->importSheet($spreadsheet->getSheetByName($spec->sheet), $spec);
            }
        } finally {
            // Tanpa ini buku kerjanya tidak pernah dilepas dari memori.
            $spreadsheet->disconnectWorksheets();
        }

        return $results;
    }

    /**
     * Satu sheet saja — tombol Impor di halaman pembukuan masing-masing.
     *
     * Sheetnya dicari dari namanya dulu. Kalau tidak ada (sheetnya diganti
     * nama), dipakai sheet pertama yang punya baris judul yang cocok —
     * kecuali sheet petunjuk dan sheet tersembunyi: keduanya memuat tabel
     * contoh dan daftar pilihan, bukan data.
     *
     * @return array{sheet: string, imported: int, replaced: int, range: string, error: string|null}
     */
    public function importOne(string $path, SheetSpec $spec): array
    {
        $spreadsheet = IOFactory::load($path);
        Calculation::getInstance($spreadsheet)->setSuppressFormulaErrors(true);

        try {
            $sheet = $spreadsheet->getSheetByName($spec->sheet) ?? $this->sheetWithHeader($spreadsheet, $spec);

            return $this->importSheet($sheet, $spec);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    protected function sheetWithHeader(Spreadsheet $book, SheetSpec $spec): ?Worksheet
    {
        foreach ($book->getWorksheetIterator() as $sheet) {
            if ($sheet->getSheetState() !== Worksheet::SHEETSTATE_VISIBLE
                || in_array(mb_strtolower($sheet->getTitle()), TemplateBuilder::guideTitles(), true)) {
                continue;
            }

            try {
                $grid = $sheet->toArray(null, true, false, false);
            } catch (Throwable) {
                continue;
            }

            if ($this->findHeaderRows($grid, $spec->required)) {
                return $sheet;
            }
        }

        return null;
    }

    /**
     * @return array{sheet: string, imported: int, replaced: int, range: string, error: string|null}
     */
    protected function importSheet(?Worksheet $sheet, SheetSpec $spec): array
    {
        $blank = ['sheet' => $spec->sheet, 'imported' => 0, 'replaced' => 0, 'range' => '', 'error' => null];

        if (! $sheet) {
            return [...$blank, 'error' => 'sheet_missing'];
        }

        if ($spec->needsMonth && ! $this->payrollMonth) {
            return [...$blank, 'error' => 'month_missing'];
        }

        try {
            // formatData mati, jadi tanggal kembali sebagai serial angka dan
            // tidak terpengaruh format tampilan yang dipilih klien di Excel.
            $grid = $sheet->toArray(null, true, false, false);
        } catch (Throwable $e) {
            return [...$blank, 'error' => 'unreadable'];
        }

        $blocks = $this->findHeaderRows($grid, $spec->required);

        if (! $blocks) {
            return [...$blank, 'error' => 'header_missing'];
        }

        /*
         * Penghitung kemunculan dibagi seluruh blok di sheet ini: dua baris
         * belanja yang isinya benar-benar sama dalam satu hari tetap dua
         * baris, tapi urutannya sama tiap kali diimpor.
         */
        $seen = [];
        $records = [];

        foreach ($blocks as $headerRow) {
            $records = [...$records, ...$this->readBlock($grid, $headerRow, $spec, $seen)];
        }

        if (! $records) {
            return [...$blank, 'error' => 'empty'];
        }

        $replaced = $this->prune($spec, $records) + $this->persist($spec, $records);

        return [
            'sheet' => $spec->sheet,
            'imported' => count($records),
            'replaced' => $replaced,
            'range' => $this->dateRange($spec, $records),
            'error' => null,
        ];
    }

    /**
     * Semua baris yang tampak seperti baris header, bukan cuma yang pertama.
     * Outstanding INV punya dua blok dengan judul kolom berbeda, dan keduanya
     * berisi data yang sah.
     *
     * @param  array<int, array<int, mixed>>  $grid
     * @param  array<int, string>  $required
     * @return array<int, int>
     */
    protected function findHeaderRows(array $grid, array $required): array
    {
        $blocks = [];

        foreach ($grid as $index => $cells) {
            $normalised = array_map([Cells::class, 'norm'], $cells);

            if (! array_filter($normalised)) {
                continue;
            }

            if ($this->looksLikeHeader($normalised, $required)) {
                $blocks[] = $index;
            }
        }

        return $blocks;
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array<int, string>  $required
     */
    protected function looksLikeHeader(array $cells, array $required): bool
    {
        foreach ($required as $label) {
            if (! in_array($label, $cells, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<int, mixed>>  $grid
     * @param  array<string, int>  $seen
     * @return array<int, array<string, mixed>>
     */
    protected function readBlock(array $grid, int $headerRow, SheetSpec $spec, array &$seen): array
    {
        $header = array_map([Cells::class, 'norm'], $grid[$headerRow] ?? []);
        $columns = [];

        foreach ($spec->map as $field => $aliases) {
            foreach ($header as $index => $cell) {
                if (in_array($cell, $aliases, true)) {
                    $columns[$field] = $index;
                    break;
                }
            }
        }

        $records = [];
        $lastDate = null;
        $section = null;
        $blank = 0;
        $rowCount = count($grid);

        for ($r = $headerRow + 1; $r < $rowCount; $r++) {
            $cells = $grid[$r] ?? [];

            if (! $this->hasContent($cells)) {
                if (++$blank >= static::BLANK_RUN) {
                    break;
                }

                continue;
            }

            $blank = 0;
            $normalised = array_map([Cells::class, 'norm'], $cells);

            // Baris berikutnya adalah header lagi: blok ini selesai.
            if ($this->looksLikeHeader($normalised, $spec->required)) {
                break;
            }

            // Penanda bagian di sheet Payroll, ditulis sebagai baris teks biasa.
            // Kolom Section milik template tidak ikut diperiksa: baris gaji
            // yang bagiannya "Kitchen Staff" adalah data, bukan penanda.
            if ($spec->hasSections && $label = $this->sectionLabel(Arr::except($normalised, $columns['section'] ?? []))) {
                $section = $label;

                continue;
            }

            $row = [];

            foreach ($columns as $field => $index) {
                $row[$field] = $cells[$index] ?? null;
            }

            $row = $this->normaliseRow($row, $columns, $spec, $lastDate);

            if ($row['date'] ?? null) {
                $lastDate = $row['date'];
            }

            if ($spec->needsMonth) {
                $row['month'] = $this->payrollMonth->copy()->startOfMonth();
            }

            if ($spec->hasSections) {
                $row['section'] = $this->sectionName($row['section'] ?? '') ?? $section ?? 'Front Staff';
            }

            if (blank($row[$spec->gate] ?? null)) {
                continue;
            }

            if ($spec->skipWhenEmpty && ! array_filter(Arr::only($row, $spec->skipWhenEmpty))) {
                continue;
            }

            if (! $spec->uniqueBy) {
                $key = Cells::stableKey($spec->keyFrom, $row, $seen);

                if ($key === '') {
                    continue;
                }

                $row['import_key'] = $key;
            }

            $records[] = $row;
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $columns
     * @return array<string, mixed>
     */
    protected function normaliseRow(array $row, array $columns, SheetSpec $spec, ?Carbon $lastDate): array
    {
        if (array_key_exists('date', $columns)) {
            $parsed = Cells::toDate($row['date']);

            // Tanggal hanya ditulis di baris pertama tiap kelompok, jadi
            // baris di bawahnya mewarisi dari atas.
            $row['date'] = $spec->inheritDate ? ($parsed ?? $lastDate) : $parsed;
        }

        if (array_key_exists('due_date', $columns)) {
            $row['due_date'] = Cells::toDate($row['due_date']);
        }

        foreach ($this->numeric as $field) {
            if (array_key_exists($field, $row)) {
                $row[$field] = Cells::toNumber($row[$field]);
            }
        }

        foreach ($this->text as $field) {
            if (array_key_exists($field, $row)) {
                $row[$field] = Cells::toText($row[$field]);
            }
        }

        // Kolom catatan di basis data 200 karakter. Catatan yang lebih
        // panjang dipotong, bukan menggagalkan impor sebulan.
        if (array_key_exists('note', $row)) {
            $row['note'] = mb_substr($row['note'], 0, 200) ?: null;
        }

        return $row;
    }

    /** @param  array<int, mixed>  $cells */
    protected function hasContent(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== null && $cell !== '') {
                return true;
            }
        }

        return false;
    }

    /** @param  array<int, string>  $cells */
    protected function sectionLabel(array $cells): ?string
    {
        foreach ($cells as $cell) {
            if (in_array($cell, ['front staff', 'kitchen staff'], true)) {
                return Str::title($cell);
            }
        }

        return null;
    }

    /**
     * Isi kolom Section, dengan ejaan yang sama dipakai halaman Gaji
     * ("kitchen staff" jadi "Kitchen Staff"). Yang tidak dikenali disimpan
     * apa adanya supaya kelihatan, bukan diganti bagian lain diam-diam.
     */
    protected function sectionName(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        foreach (array_keys(PayrollResource::sections()) as $known) {
            if (Cells::norm($known) === Cells::norm($value)) {
                return $known;
            }
        }

        return $value;
    }

    /**
     * Membuang baris hasil impor lama yang sudah tidak ada di berkas ini.
     *
     * Tanpa ini, membetulkan satu sel di Excel lalu mengimpor ulang akan
     * meninggalkan baris versi lamanya — isinya berubah, jadi nomornya
     * berubah, jadi baris lamanya tidak tertimpa. Dua-duanya ikut terhitung.
     *
     * Yang dibuang hanya baris bertanda `source: import` dan hanya di dalam
     * rentang tanggal berkas yang sedang diimpor. Apa pun yang diketik lewat
     * halaman tidak pernah disentuh — berkas Excel berwenang atas baris yang
     * ia bawa sendiri, bukan atas seluruh isi basis data.
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    protected function prune(SheetSpec $spec, array $records): int
    {
        /** @var class-string<Model> $model */
        $model = $spec->model;

        $query = $model::query()->where('source', RecordSource::IMPORT);

        if ($column = $spec->dateColumn()) {
            $dates = $this->dateValues($spec, $records);

            if (! $dates) {
                return 0;
            }

            $query->whereBetween($column, [min($dates), max($dates)]);
        }

        if ($spec->uniqueBy) {
            $query->whereNotIn($spec->uniqueBy, array_map(function (array $row) use ($spec) {
                $value = $row[$spec->uniqueBy];

                return $value instanceof Carbon ? $value->toDateString() : $value;
            }, $records));
        } else {
            $query->whereNotIn('import_key', array_column($records, 'import_key'));
        }

        $stale = $query->get();

        foreach ($stale as $row) {
            $row->delete();
        }

        return $stale->count();
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return int berapa baris yang sebelumnya diketik orang lalu tertimpa
     */
    protected function persist(SheetSpec $spec, array $records): int
    {
        if ($spec->model === Supplier::class) {
            return $this->persistSuppliers($records);
        }

        /** @var class-string<Model> $model */
        $model = $spec->model;
        $overwritten = 0;

        foreach ($records as $record) {
            $match = $spec->uniqueBy
                ? [$spec->uniqueBy => $record[$spec->uniqueBy]]
                : ['import_key' => $record['import_key']];

            /*
             * Baris pemasukan harian dikenali dari tanggalnya, jadi berkas
             * Excel memang berwenang atas hari yang ia bawa — termasuk hari
             * yang sudah diketik orang. Itu tidak bisa dihindari tanpa
             * membuat satu hari punya dua baris. Yang bisa dilakukan adalah
             * tidak menyembunyikannya: jumlahnya dilaporkan di tabel hasil.
             */
            if ($spec->uniqueBy) {
                $overwritten += $model::query()
                    ->where($match)
                    ->where('source', RecordSource::MANUAL)
                    ->count();
            }

            $model::updateOrCreate($match, [...$record, 'source' => RecordSource::IMPORT]);
        }

        return $overwritten;
    }

    /**
     * Pemasok dari sheet Supplier Database.
     *
     * Pemasok yang namanya sudah ada tidak dibuat ulang dan tidak ditulis
     * ulang: yang diisi hanya kolom rekening dan cara bayar, yaitu keterangan
     * yang memang belum pernah ada di aplikasi ini. Nama, narahubung, telepon,
     * dan alamat yang sudah diketik tetap seperti apa adanya — berkas Excel
     * menambah keterangan bank, bukan mengambil alih daftar pemasok.
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    protected function persistSuppliers(array $records): int
    {
        $bankColumns = ['bank', 'bank_account', 'account_name', 'payment_method', 'last_price'];

        foreach ($records as $record) {
            $existing = Supplier::query()->where('import_key', $record['import_key'])->first()
                ?: Supplier::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($record['name'])])->first();

            if (! $existing) {
                Supplier::create([...$record, 'source' => RecordSource::IMPORT, 'active' => true]);

                continue;
            }

            $existing->fill(Arr::only($record, $bankColumns));

            // Baris yang sudah pernah diimpor ikut memperbarui nomornya,
            // supaya pembersihan berikutnya tidak menganggapnya usang. Baris
            // yang diketik orang tetap bertanda manual, jadi tidak pernah
            // ikut terhapus.
            if ($existing->source === RecordSource::IMPORT) {
                $existing->import_key = $record['import_key'];
            }

            $existing->save();
        }

        return 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, string>
     */
    protected function dateValues(SheetSpec $spec, array $records): array
    {
        $column = $spec->dateColumn();

        if (! $column) {
            return [];
        }

        $values = [];

        foreach ($records as $record) {
            $value = $record[$column] ?? null;

            if ($value instanceof Carbon) {
                $values[] = $value->toDateString();
            }
        }

        return $values;
    }

    /**
     * Rentang tanggal yang benar-benar terbaca dari sheet, ditampilkan apa
     * adanya di tabel hasil.
     *
     * Gunanya bukan hiasan: salah ketik tahun di satu sel — di berkas Agustus
     * 2026 ada satu baris tertulis "18/8/2028" — tidak mengubah jumlah baris
     * dan tidak memunculkan galat apa pun. Yang berubah cuma ujung rentangnya.
     * Ditampilkan begini, kekeliruan itu kelihatan sebelum datanya dipakai;
     * kalau disembunyikan, baru ketahuan saat laporan tahunan terlihat aneh.
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    protected function dateRange(SheetSpec $spec, array $records): string
    {
        $values = $this->dateValues($spec, $records);

        if (! $values) {
            return '';
        }

        sort($values);

        $first = reset($values);
        $last = end($values);

        return $first === $last ? $first : $first.' … '.$last;
    }
}

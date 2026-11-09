<?php

namespace App\Support\Moka;

use App\Models\MokaItemSale;
use App\Support\Excel\Column;
use App\Support\Excel\ImportReport;
use App\Support\Money;
use App\Support\Zeytin\DailyLedger;
use App\Support\Zeytin\Workbook\Cells;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Membaca berkas "Report Item Sales" hasil unduhan Moka.
 *
 * Berkas ini rekap satu periode per menu — bukan daftar transaksi — dan
 * **tidak memuat tanggalnya sendiri** (periodenya hanya ada di nama berkas).
 * Karena itu periode diberikan dari luar dan dipakai apa adanya. Itu satu-
 * satunya hal yang tidak bisa diperiksa dari isi berkas, jadi sisanya
 * diperiksa seketat mungkin:
 *
 *   - Net Sales tiap baris harus sama dengan Gross − Discount − Refund.
 *     Berkas laporan lain, atau kolom yang bergeser, hampir pasti melanggarnya.
 *   - Baris "Total" di kaki berkas, kalau terisi, harus sama dengan jumlah
 *     baris menu — menangkap berkas yang terpotong.
 *   - Dua baris dengan menu + varian + kategori yang sama ditolak.
 *
 * Semua atau tidak sama sekali, seperti pengimpor Excel lain di aplikasi ini.
 *
 * Mengimpor periode yang persis sama **mengganti** isinya, jadi berkas yang
 * sama aman diimpor dua kali. Periode yang tanggalnya bersinggungan tapi
 * tidak sama ditolak: menu yang sama akan terhitung dua kali.
 *
 * Datanya tidak masuk Total penjualan laporan mana pun — penjualan yang sama
 * sudah tercatat di Pemasukan Harian. Hasil impor hanya membandingkan
 * keduanya sebagai bahan periksa.
 */
class ItemSalesImport
{
    /** Discount dan Refund ditulis Moka sebagai angka minus. */
    protected const FLOOR = -1_000_000_000_000;

    protected const CEILING = 1_000_000_000_000;

    /** Baris judul dicari di sekian baris pertama saja. */
    protected const HEADER_SCAN = 30;

    /** Selisih satu rupiah dimaafkan: Moka menyimpan angka desimal. */
    protected const TOLERANCE = 1;

    public function __construct(protected Carbon $from, protected Carbon $to)
    {
        $this->from = $from->copy()->startOfDay();
        $this->to = $to->copy()->startOfDay();
    }

    /**
     * Periode dari nama berkas bawaan Moka, mis.
     * "Report Item Sales - 01-10-2026 - 31-10-2026 - ZEYTIN.xlsx".
     *
     * Hari dulu, seperti di seluruh aplikasi ini. Tanggal yang mustahil
     * (31-02) atau terbalik dianggap tidak terbaca: lebih baik kotak tanggal
     * dibiarkan kosong daripada terisi tebakan.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public static function periodFromFilename(string $filename): ?array
    {
        if (! preg_match('/(\d{1,2})-(\d{1,2})-(\d{4})\s*-\s*(\d{1,2})-(\d{1,2})-(\d{4})/', $filename, $m)) {
            return null;
        }

        [, $d1, $m1, $y1, $d2, $m2, $y2] = array_map('intval', $m);

        if (! checkdate($m1, $d1, $y1) || ! checkdate($m2, $d2, $y2)) {
            return null;
        }

        $from = Carbon::create($y1, $m1, $d1)->startOfDay();
        $to = Carbon::create($y2, $m2, $d2)->startOfDay();

        return $from->lte($to) ? [$from, $to] : null;
    }

    /** Judul kolom persis seperti di berkas Moka (Inggris), untuk pesan galat. */
    public static function headerTitle(Column $column): string
    {
        return __($column->label, [], 'en');
    }

    public function import(string $path): ImportReport
    {
        if ($error = $this->periodError()) {
            return ImportReport::failed([$error]);
        }

        $grid = $this->grid($path);
        $located = $this->locate($grid);

        if (! $located) {
            return ImportReport::failed([__('moka.error.no_header', ['columns' => $this->requiredTitles()])]);
        }

        [$header, $indexes] = $located;
        [$rows, $footer, $errors] = $this->read($grid, $header, $indexes);

        if (! $errors && ! $rows) {
            return ImportReport::failed([__('moka.error.empty')]);
        }

        $errors = [...$errors, ...$this->duplicates($rows), ...$this->arithmetic($rows), ...$this->footer($rows, $footer)];

        if ($errors) {
            return ImportReport::failed($errors);
        }

        try {
            return DB::transaction(fn () => $this->save($rows));
        } catch (RuntimeException $e) {
            return ImportReport::failed([$e->getMessage()]);
        }
    }

    /* ------------------------------------------------------------ periode */

    protected function periodError(): ?string
    {
        return match (true) {
            $this->from->gt($this->to) => __('moka.error.period_order'),
            $this->to->gt(now()->startOfDay()) => __('moka.error.period_future'),
            default => null,
        };
    }

    /* ------------------------------------------------------------ membaca */

    /** @return array<int, array<int, mixed>> */
    protected function grid(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        $book = $reader->load($path);

        try {
            // Hanya sheet pertama: Moka mengunduh satu sheet. Tanggal tidak
            // dipakai, jadi tidak perlu pemformatan sel.
            return $book->getSheet(0)->toArray(null, true, false, false);
        } finally {
            $book->disconnectWorksheets();
        }
    }

    /** @return array<int, Column> */
    public static function columns(): array
    {
        return [
            Column::text('item_name', 'moka.col.item_name')->required()->maxLength(120),
            Column::text('variant_name', 'moka.col.variant_name')->maxLength(120),
            Column::text('category_name', 'moka.col.category_name')->maxLength(80),
            Column::text('sku', 'moka.col.sku')->maxLength(60),
            Column::number('qty_sold', 'moka.col.qty_sold')->required()->between(0, 100_000_000),
            Column::number('qty_refunded', 'moka.col.qty_refunded')->between(0, 100_000_000),
            Column::money('gross_sales', 'moka.col.gross_sales')->required()->between(0, self::CEILING),
            Column::money('discount', 'moka.col.discount')->between(self::FLOOR, self::CEILING),
            Column::money('refund', 'moka.col.refund')->between(self::FLOOR, self::CEILING),
            Column::money('net_sales', 'moka.col.net_sales')->required()->between(self::FLOOR, self::CEILING),
        ];
    }

    protected function requiredTitles(): string
    {
        return implode(', ', array_map(
            fn (Column $column) => static::headerTitle($column),
            array_filter(static::columns(), fn (Column $column) => $column->required),
        ));
    }

    /**
     * Baris judul: baris pertama (dalam batas) yang memuat semua kolom
     * wajib. Kolom dikenali dari judulnya, bukan posisinya.
     *
     * @param  array<int, array<int, mixed>>  $grid
     * @return array{0: int, 1: array<string, int>}|null
     */
    protected function locate(array $grid): ?array
    {
        foreach (array_slice($grid, 0, self::HEADER_SCAN, true) as $row => $cells) {
            $normalised = array_map([Column::class, 'norm'], $cells);
            $indexes = [];

            foreach (static::columns() as $column) {
                foreach ($normalised as $index => $title) {
                    if (in_array($title, $column->aliases(), true)) {
                        $indexes[$column->name] = $index;
                        break;
                    }
                }

                if ($column->required && ! isset($indexes[$column->name])) {
                    continue 2;
                }
            }

            return [$row, $indexes];
        }

        return null;
    }

    /**
     * @param  array<int, array<int, mixed>>  $grid
     * @param  array<string, int>  $indexes
     * @return array{0: array<int, array{line: int, values: array<string, mixed>}>, 1: array{net_sales: int}|null, 2: array<int, string>}
     */
    protected function read(array $grid, int $header, array $indexes): array
    {
        $columns = static::columns();
        $rows = [];
        $errors = [];
        $footer = null;

        foreach (array_slice($grid, $header + 1, null, true) as $index => $cells) {
            $line = $index + 1;

            if (! $this->hasContent($cells, $indexes)) {
                continue;
            }

            // Baris "Total" di kaki berkas. Bukan menu, tapi berguna sebagai
            // pemeriksa: kalau terisi, jumlahnya harus cocok (lihat footer()).
            if (Cells::norm($cells[$indexes['item_name']] ?? null) === 'total') {
                $footer = $this->footerValues($cells, $indexes);

                continue;
            }

            $values = $this->values($columns, $indexes, $cells, $line, $errors);

            if ($values !== null) {
                $rows[] = ['line' => $line, 'values' => $values];
            }
        }

        return [$rows, $footer, $errors];
    }

    /**
     * Hanya Net Sales dari baris Total. Sel lainnya sengaja tidak dibaca:
     * di berkas Moka yang kami lihat seluruh selnya kosong, dan membacanya
     * seperti baris menu menghasilkan galat "wajib diisi" yang palsu.
     *
     * @param  array<int, mixed>  $cells
     * @param  array<string, int>  $indexes
     * @return array{net_sales: int}|null
     */
    protected function footerValues(array $cells, array $indexes): ?array
    {
        $raw = $cells[$indexes['net_sales']] ?? null;

        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }

        foreach (static::columns() as $column) {
            if ($column->name === 'net_sales') {
                [$net, $error] = $column->read($raw);

                return $error ? null : ['net_sales' => $net];
            }
        }

        return null;
    }

    /**
     * Satu baris dibaca jadi nilai siap simpan; kesalahan per sel
     * dikumpulkan ke $errors dengan nomor baris dan judul kolom aslinya.
     *
     * @param  array<int, Column>  $columns
     * @param  array<string, int>  $indexes
     * @param  array<int, mixed>  $cells
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null null kalau ada sel yang salah
     */
    protected function values(array $columns, array $indexes, array $cells, int $line, array &$errors): ?array
    {
        $values = [];
        $ok = true;

        foreach ($columns as $column) {
            if (! array_key_exists($column->name, $indexes)) {
                continue;
            }

            $raw = $cells[$indexes[$column->name]] ?? null;

            // Jumlah terjual pecahan (mis. per kilo) akan dibulatkan diam-
            // diam oleh pembaca angka dan membuat jumlahnya salah. Ditolak.
            if (in_array($column->name, ['qty_sold', 'qty_refunded'], true) && is_numeric($raw) && floor((float) $raw) != (float) $raw) {
                $errors[] = $this->cellError($line, $column, __('moka.error.fraction', ['value' => $raw]));
                $ok = false;

                continue;
            }

            [$value, $error] = $column->read($raw);

            if ($error) {
                $errors[] = $this->cellError($line, $column, $error);
                $ok = false;

                continue;
            }

            $values[$column->name] = $value;
        }

        return $ok ? $values : null;
    }

    protected function cellError(int $line, Column $column, string $message): string
    {
        return __('excel.error.row', ['row' => $line, 'column' => static::headerTitle($column), 'message' => $message]);
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

    /* ---------------------------------------------------------- memeriksa */

    /**
     * Menu + varian + kategori yang sama dua kali di satu berkas: mana yang
     * benar? Ditanyakan, bukan ditebak.
     *
     * Kategori ikut kuncinya. Di berkas nyata ada "Ice lemon tea"
     * (Uncategorized) dan "ice lemon tea" (Drink): huruf besar dan kategori
     * berbeda, dua baris yang sah. Tanpa kategori, keduanya dikira kembar.
     *
     * @param  array<int, array{line: int, values: array<string, mixed>}>  $rows
     * @return array<int, string>
     */
    protected function duplicates(array $rows): array
    {
        $seen = [];
        $errors = [];

        foreach ($rows as $row) {
            $v = $row['values'];
            $key = implode('|', [
                Cells::norm($v['item_name'] ?? null),
                Cells::norm($v['variant_name'] ?? null),
                Cells::norm($v['category_name'] ?? null),
            ]);

            if (isset($seen[$key])) {
                $errors[] = __('excel.error.duplicate', ['row' => $row['line'], 'first' => $seen[$key]]);

                continue;
            }

            $seen[$key] = $row['line'];
        }

        return $errors;
    }

    /**
     * Net = Gross − |Discount| − |Refund|. Tanda diabaikan: Moka menulis
     * potongan sebagai angka minus, dan berkas yang menulisnya positif
     * tetap berarti potongan.
     *
     * @param  array<int, array{line: int, values: array<string, mixed>}>  $rows
     * @return array<int, string>
     */
    protected function arithmetic(array $rows): array
    {
        $errors = [];

        foreach ($rows as $row) {
            $v = $row['values'];
            $expected = ($v['gross_sales'] ?? 0) - abs($v['discount'] ?? 0) - abs($v['refund'] ?? 0);

            if (abs($expected - ($v['net_sales'] ?? 0)) > self::TOLERANCE) {
                $errors[] = __('moka.error.net_mismatch', [
                    'row' => $row['line'],
                    'name' => $v['item_name'] ?? '',
                    'net' => Money::format($v['net_sales'] ?? 0),
                    'expected' => Money::format($expected),
                ]);
            }
        }

        return $errors;
    }

    /**
     * Baris Total di kaki berkas Moka. Di berkas yang kami lihat selnya
     * kosong, dan dilewati; kalau terisi, harus sama dengan jumlah menu.
     *
     * @param  array<int, array{line: int, values: array<string, mixed>}>  $rows
     * @param  array{net_sales: int}|null  $footer
     * @return array<int, string>
     */
    protected function footer(array $rows, ?array $footer): array
    {
        if (! $footer || ! isset($footer['net_sales'])) {
            return [];
        }

        $sum = array_sum(array_map(fn (array $row) => $row['values']['net_sales'] ?? 0, $rows));

        if (abs($sum - $footer['net_sales']) <= self::TOLERANCE) {
            return [];
        }

        return [__('moka.error.total_mismatch', [
            'file' => Money::format($footer['net_sales']),
            'sum' => Money::format($sum),
        ])];
    }

    /* ---------------------------------------------------------- menyimpan */

    /**
     * @param  array<int, array{line: int, values: array<string, mixed>}>  $rows
     */
    protected function save(array $rows): ImportReport
    {
        $start = $this->from->toDateString();
        $end = $this->to->toDateString();

        $overlapping = MokaItemSale::query()->overlapping($start, $end)
            ->select('period_start', 'period_end')->distinct()->orderBy('period_start')->get();

        if ($overlapping->isNotEmpty()) {
            throw new RuntimeException(__('moka.error.overlap', [
                'periods' => $overlapping->map(fn (MokaItemSale $row) => $row->periodLabel())->implode(', '),
            ]));
        }

        $replaced = MokaItemSale::query()->inPeriod($start, $end)->delete();
        $now = now();
        $userId = Auth::id();

        foreach (array_chunk($rows, 200) as $chunk) {
            MokaItemSale::insert(array_map(fn (array $row) => [
                'period_start' => $start,
                'period_end' => $end,
                'item_name' => $row['values']['item_name'],
                'variant_name' => $row['values']['variant_name'] ?? null,
                'category_name' => $row['values']['category_name'] ?? null,
                'sku' => $row['values']['sku'] ?? null,
                'qty_sold' => $row['values']['qty_sold'],
                'qty_refunded' => $row['values']['qty_refunded'] ?? 0,
                'gross_sales' => $row['values']['gross_sales'],
                'discount' => abs($row['values']['discount'] ?? 0),
                'refund' => abs($row['values']['refund'] ?? 0),
                'net_sales' => $row['values']['net_sales'],
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        return new ImportReport(
            counts: ['imported' => count($rows), 'replaced' => $replaced],
            note: $this->compare(array_sum(array_map(fn (array $row) => $row['values']['net_sales'], $rows))),
        );
    }

    /**
     * Penjualan bersih Moka dibandingkan dengan Pemasukan Harian untuk
     * periode yang sama. Hanya bahan periksa: keduanya beda sumber (pajak,
     * pembulatan, penjualan di luar Moka), jadi tidak harus sama persis.
     */
    protected function compare(int $moka): string
    {
        $books = DailyLedger::periodReport($this->from, $this->to);

        $line = $books['recorded_days'] > 0
            ? __('moka.note.compare', [
                'moka' => Money::format($moka),
                'books' => Money::format($books['total_sales']),
                'days' => $books['recorded_days'],
                'diff' => Money::format($books['total_sales'] - $moka),
            ])
            : __('moka.note.no_books', ['moka' => Money::format($moka)]);

        return $line.' '.__('moka.note.separate');
    }
}

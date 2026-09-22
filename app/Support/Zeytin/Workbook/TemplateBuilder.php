<?php

namespace App\Support\Zeytin\Workbook;

use App\Filament\Admin\Resources\Zeytin\OutstandingBills\OutstandingBillResource;
use App\Filament\Admin\Resources\Zeytin\Payrolls\PayrollResource;
use App\Filament\Admin\Resources\Zeytin\SupplierTransfers\SupplierTransferResource;
use App\Models\Employee;
use App\Models\PaymentMethodOption;
use App\Models\Payroll;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\SupplierTransfer;
use App\Support\Excel\StylesWorkbook;
use App\Support\Zeytin\Channels;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Template impor untuk satu bulan.
 *
 * Judul kolom yang benar sudah dijamin SheetSpec. Yang dikejar di sini
 * adalah berkas yang sulit diisi berantakan:
 *
 *   - Tanggal di sheet bulanan hanya menerima tanggal di bulan template.
 *     Salah ketik "18/8/2028" di berkas Agustus ditolak saat diketik, dan
 *     yang lolos lewat tempel diwarnai merah.
 *   - Pemasok, barang, satuan, dan cara bayar dipilih dari daftar yang sama
 *     dengan formulir aplikasi. Nama baru tetap boleh (cuma diingatkan),
 *     seperti di formulirnya, tapi beda ejaan yang tidak disengaja tidak
 *     lagi memecah satu pemasok jadi dua di laporan.
 *   - Status dan bagian karyawan dipilih dari daftar tertutup, karena
 *     halaman aplikasinya juga daftar tertutup.
 *   - Total terisi sendiri dengan rumus yang sama seperti aplikasi.
 *   - Nomor rekening disimpan sebagai teks, jadi nol di depannya tidak hilang.
 *
 * Sheet data sengaja tanpa baris contoh: baris yang lupa dihapus akan ikut
 * terimpor sebagai data sungguhan. Contohnya ditaruh di sheet Petunjuk, dan
 * daftar pilihannya di sheet tersembunyi. Nama kedua sheet itu tidak
 * dikenali pembaca, jadi keduanya diabaikan saat impor.
 */
class TemplateBuilder
{
    use StylesWorkbook;

    /** Baris yang disiapkan per sheet. Belanja sebulan sekitar 230 baris. */
    public const ROWS = 400;

    /** Sheet yang tanggalnya harus jatuh di bulan template. */
    protected const MONTHLY = ['Income', 'Expense', 'Supplier Transfer Payment'];

    /** Daftar tertutup, karena halaman aplikasinya juga pilihan tertutup. */
    protected const CLOSED = ['transfer_statuses', 'bill_statuses', 'sections'];

    protected const MONEY = ['price', 'disc', 'tax', 'total', 'total_sales', 'basic', 'bpjs', 'grand_total', 'last_price'];

    protected const WIDTHS = [
        'date' => 13, 'due_date' => 13, 'vendor' => 24, 'item' => 28, 'supplies' => 28,
        'name' => 26, 'note' => 34, 'method' => 16, 'payment_method' => 16, 'status' => 22,
        'section' => 16, 'unit' => 10, 'qty' => 9, 'bank' => 12, 'bank_account' => 20,
        'account_name' => 24, 'contact_person' => 20,
    ];

    protected Carbon $month;

    /** @var array<string, string> nama daftar => rentang selnya di sheet daftar */
    protected array $lists = [];

    /**
     * @param  array<int, string>|null  $only  nama sheet yang dibuat; null = keenamnya.
     *                                         Satu sheet saja untuk tombol template di
     *                                         halaman pembukuan masing-masing.
     */
    public function __construct(?Carbon $month = null, protected ?array $only = null)
    {
        $this->month = ($month ?? now())->copy()->startOfMonth();
    }

    public function build(): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);

        $values = $this->listValues();
        $this->lists = $this->listRanges(__('zeytin.template.lists_tab'), $values);

        $this->addGuide($book);

        foreach ($this->specs() as $spec) {
            $this->addSheet($book, $spec);
        }

        $labels = [];

        foreach (array_keys($values) as $key) {
            $labels[$key] = __('zeytin.template.list.'.$key);
        }

        $this->addListSheet($book, __('zeytin.template.lists_tab'), $values, $labels);

        // Berkas bulanan dibuka di Petunjuk; template satu menu langsung di
        // sheet datanya, seperti template menu lain.
        $book->setActiveSheetIndex(count($this->specs()) === 1 ? 1 : 0);

        return $book;
    }

    /** @return array<int, SheetSpec> */
    public function specs(): array
    {
        return array_values(array_filter(
            SheetSpec::all(),
            fn (SheetSpec $spec) => $this->only === null || in_array($spec->sheet, $this->only, true),
        ));
    }

    public function save(string $path): string
    {
        $book = $this->build();

        (new Xlsx($book))->save($path);

        // Buku kerja PhpSpreadsheet saling merujuk dengan sheet-sheetnya dan
        // tidak dilepas sendiri dari memori tanpa ini.
        $book->disconnectWorksheets();

        return $path;
    }

    public function filename(): string
    {
        if ($this->only !== null && count($this->specs()) === 1) {
            return 'zeytin-template-'.Str::slug($this->specs()[0]->sheet).'-'.$this->month->format('Y-m').'.xlsx';
        }

        return 'zeytin-template-'.$this->month->format('Y-m').'.xlsx';
    }

    /* ------------------------------------------------------------ sheet data */

    protected function addSheet(Spreadsheet $book, SheetSpec $spec): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle($spec->sheet);

        $columns = $spec->templateColumns();
        $letters = $this->letters($columns);

        // Sheet satu-baris-per-tanggal (Income) disiapkan persis sebulan.
        $daily = $spec->uniqueBy === 'date';
        $last = 1 + ($daily ? $this->month->daysInMonth : static::ROWS);

        $sheet->fromArray(array_values($columns), null, 'A1');
        $this->styleHeader($sheet, 'A1:'.end($letters).'1');

        foreach ($columns as $field => $title) {
            $this->prepareColumn($sheet, $spec, $field, $title, $letters, $last);
        }

        if ($daily) {
            for ($day = 0; $day < $this->month->daysInMonth; $day++) {
                $sheet->setCellValue($letters['date'].($day + 2), ExcelDate::PHPToExcel($this->month->copy()->addDays($day)));
            }
        }

        $sheet->freezePane('A2');
    }

    /** @param  array<string, string>  $letters */
    protected function prepareColumn(Worksheet $sheet, SheetSpec $spec, string $field, string $title, array $letters, int $last): void
    {
        $col = $letters[$field];
        $range = "{$col}2:{$col}{$last}";
        $kind = $this->kind($field);
        $read = isset($spec->map[$field]);

        $sheet->getColumnDimension($col)->setWidth(static::WIDTHS[$field] ?? ($kind === 'money' ? 14 : 16));

        $formula = $this->formula($field, $letters);

        if ($formula) {
            for ($r = 2; $r <= $last; $r++) {
                $sheet->setCellValue($col.$r, str_replace('{r}', (string) $r, $formula));
            }

            $this->fill($sheet, $range, $read ? static::COLOR_SUGGESTED : static::COLOR_COMPUTED);
        }

        $format = match (true) {
            $kind === 'date' => 'dd/mm/yyyy',
            $kind === 'money', $kind === 'qty' => '#,##0',
            $field === 'bank_account' => NumberFormat::FORMAT_TEXT,
            default => null,
        };

        // Format dipasang ke seluruh kolom, bukan ke sel yang disiapkan saja:
        // baris ke-401 yang diketik sendiri tetap berformat tanggal/rupiah,
        // dan berkasnya tidak perlu memuat ribuan sel kosong.
        if ($format) {
            $sheet->getStyle("{$col}:{$col}")->getNumberFormat()->setFormatCode($format);
        }

        if ($validation = $this->validation($spec, $field, $kind, $formula !== null)) {
            // Excel menolak judul lebih dari 32 huruf.
            $validation->setPromptTitle(mb_substr($title, 0, 32));
            $validation->setErrorTitle(mb_substr($title, 0, 32));
            $sheet->setDataValidation($range, $validation);
        }

        if ($kind === 'date' && $this->isMonthly($spec)) {
            $this->markOutsideMonth($sheet, $range, $col.'2');
        }
    }

    protected function kind(string $field): string
    {
        return match (true) {
            in_array($field, ['date', 'due_date'], true) => 'date',
            $field === 'qty' => 'qty',
            in_array($field, [...static::MONEY, ...Channels::keys()], true) => 'money',
            default => 'text',
        };
    }

    /**
     * Rumus satu kolom, dengan `{r}` sebagai nomor baris.
     *
     * Sama dengan hitungan aplikasi: Total Sales tanpa petty cash
     * (DailyLedger), total belanja qty × harga + pajak − potongan
     * (DailyLedger::lineTotal), transfer qty × harga, gaji pokok − BPJS.
     *
     * @param  array<string, string>  $c
     */
    protected function formula(string $field, array $c): ?string
    {
        $cell = fn (string $f) => $c[$f].'{r}';

        if ($field === 'total_sales') {
            $all = implode(',', array_map($cell, array_values(array_intersect(Channels::keys(), array_keys($c)))));
            $sales = implode(',', array_map($cell, array_values(array_intersect(Channels::inSales(), array_keys($c)))));

            return '=IF(COUNT('.$all.')=0,"",SUM('.$sales.'))';
        }

        if ($field === 'total' && isset($c['item'], $c['qty'], $c['price'])) {
            $line = $cell('qty').'*'.$cell('price');

            if (isset($c['tax'], $c['disc'])) {
                $line .= '+'.$cell('tax').'-'.$cell('disc');
            }

            return '=IF('.$cell('item').'="","",'.$line.')';
        }

        if ($field === 'grand_total' && isset($c['name'], $c['basic'], $c['bpjs'])) {
            return '=IF('.$cell('name').'="","",'.$cell('basic').'-'.$cell('bpjs').')';
        }

        return null;
    }

    /**
     * Aturan isian satu kolom, beserta pesan yang muncul saat selnya dipilih.
     */
    protected function validation(SheetSpec $spec, string $field, string $kind, bool $hasFormula): ?DataValidation
    {
        $rule = (new DataValidation)
            ->setAllowBlank(true)
            ->setShowInputMessage(true)
            ->setShowErrorMessage(true);

        $month = ['month' => $this->monthName()];

        // Dihitung ulang aplikasi, jadi tidak ada yang perlu diperiksa.
        if ($hasFormula && ! isset($spec->map[$field])) {
            return $rule->setType(DataValidation::TYPE_NONE)
                ->setPrompt(__('zeytin.template.prompt.computed'));
        }

        if ($kind === 'date') {
            $monthly = $this->isMonthly($spec);

            // Sheet tagihan memuat utang bulan-bulan lalu dan jatuh tempo
            // bulan depan, jadi rentangnya lebar dan hanya mengingatkan.
            [$from, $to] = $monthly
                ? [$this->month, $this->month->copy()->endOfMonth()]
                : [$this->month->copy()->subYear(), $this->month->copy()->addYear()->endOfMonth()];

            return $rule->setType(DataValidation::TYPE_DATE)
                ->setOperator(DataValidation::OPERATOR_BETWEEN)
                ->setFormula1($this->excelDate($from))
                ->setFormula2($this->excelDate($to))
                ->setErrorStyle($monthly ? DataValidation::STYLE_STOP : DataValidation::STYLE_WARNING)
                ->setError(__($monthly ? 'zeytin.template.error.date_month' : 'zeytin.template.error.date', $month))
                ->setPrompt(__(match (true) {
                    $monthly && $spec->inheritDate => 'zeytin.template.prompt.date_inherit',
                    $monthly => 'zeytin.template.prompt.date_month',
                    default => 'zeytin.template.prompt.date',
                }, $month));
        }

        if ($kind === 'money' || $kind === 'qty') {
            return $rule->setType(DataValidation::TYPE_WHOLE)
                ->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL)
                ->setFormula1('0')
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setError(__('zeytin.template.error.'.$kind))
                ->setPrompt(__('zeytin.template.prompt.'.($hasFormula ? 'suggested_'.$field : $kind)));
        }

        $list = $this->listFor($spec, $field);

        if ($list && isset($this->lists[$list])) {
            $closed = in_array($list, static::CLOSED, true);

            return $rule->setType(DataValidation::TYPE_LIST)
                ->setShowDropDown(true)
                ->setFormula1($this->lists[$list])
                ->setErrorStyle($closed ? DataValidation::STYLE_STOP : DataValidation::STYLE_WARNING)
                ->setError(__('zeytin.template.error.'.($closed ? 'closed_list' : 'open_list')))
                ->setPrompt(__('zeytin.template.prompt.'.($closed ? 'closed_list' : 'open_list')));
        }

        if (in_array($field, ['note', 'bank_account'], true)) {
            return $rule->setType(DataValidation::TYPE_NONE)
                ->setPrompt(__('zeytin.template.prompt.'.$field));
        }

        return null;
    }

    protected function listFor(SheetSpec $spec, string $field): ?string
    {
        return match ($field) {
            'vendor' => 'vendors',
            'item' => 'items',
            'unit' => 'units',
            'method', 'payment_method' => 'methods',
            'status' => $spec->model === SupplierTransfer::class ? 'transfer_statuses' : 'bill_statuses',
            'section' => 'sections',
            'name' => $spec->model === Payroll::class ? 'employees' : null,
            default => null,
        };
    }

    /**
     * Tanggal di luar bulan template diwarnai merah.
     *
     * Aturan isian Excel hanya berlaku saat sel diketik; tanggal yang
     * ditempel dari berkas lain lolos begitu saja. Warna ini yang
     * menangkapnya — termasuk "05/08" yang dibaca Excel berlokal Amerika
     * sebagai 8 Mei.
     */
    protected function markOutsideMonth(Worksheet $sheet, string $range, string $first): void
    {
        $from = $this->excelDate($this->month);
        $to = $this->excelDate($this->month->copy()->endOfMonth());

        $rule = (new Conditional)
            ->setConditionType(Conditional::CONDITION_EXPRESSION)
            ->addCondition("AND(ISNUMBER({$first}),OR({$first}<{$from},{$first}>{$to}))");

        $fill = $rule->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
        $fill->getStartColor()->setARGB(static::COLOR_INVALID);
        $fill->getEndColor()->setARGB(static::COLOR_INVALID);
        $rule->getStyle()->getFont()->getColor()->setARGB('FF991B1B');

        $sheet->setConditionalStyles($range, [$rule]);
    }

    protected function isMonthly(SheetSpec $spec): bool
    {
        return in_array($spec->sheet, static::MONTHLY, true);
    }

    /* ------------------------------------------------------- daftar pilihan */

    /**
     * Isi daftar pilihan, dari sumber yang sama dengan formulir aplikasi.
     *
     * Nama karyawan saja, tanpa gaji: template ini diunduh Admin, dan gaji
     * per orang hanya untuk Super Admin.
     *
     * @return array<string, array<int, string>>
     */
    protected function listValues(): array
    {
        return [
            'vendors' => $this->distinct(Supplier::query()->pluck('name')),
            'items' => $this->distinct(PurchaseItem::query()->pluck('name')),
            'units' => $this->distinct(PurchaseItem::query()->pluck('unit')),
            'methods' => $this->distinct(PaymentMethodOption::query()->where('active', true)->pluck('name')),
            'transfer_statuses' => array_keys(SupplierTransferResource::statuses()),
            'bill_statuses' => array_keys(OutstandingBillResource::statuses()),
            'sections' => array_keys(PayrollResource::sections()),
            'employees' => $this->distinct(Employee::query()->active()->pluck('name')),
        ];
    }

    /**
     * Dirapikan, tanpa kembaran yang cuma beda huruf besar-kecil, urut abjad.
     *
     * @param  iterable<int, mixed>  $names
     * @return array<int, string>
     */
    protected function distinct(iterable $names): array
    {
        return collect($names)
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique(fn (string $name) => mb_strtolower($name))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /* ------------------------------------------------------------- petunjuk */

    protected function addGuide(Spreadsheet $book): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle(__('zeytin.template.tab'));

        $month = ['month' => $this->monthName()];

        for ($i = 1; $i <= 11; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(15);
        }

        $sheet->setCellValue('A1', __('zeytin.template.title', $month));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB(static::COLOR_HEADER);
        $sheet->setCellValue('A2', __('zeytin.template.subtitle', $month));

        $row = $this->guideHeading($sheet, 4, __('zeytin.template.rules'));

        foreach ($this->rules() as $n => $key) {
            $sheet->setCellValue('A'.$row++, ($n + 1).'. '.__('zeytin.template.rule.'.$key, [...$month, 'rows' => static::ROWS]));
        }

        $row = $this->guideHeading($sheet, $row + 1, __('zeytin.template.legend'));

        $colors = [
            'header' => static::COLOR_HEADER,
            'computed' => static::COLOR_COMPUTED,
            'suggested' => static::COLOR_SUGGESTED,
            'invalid' => static::COLOR_INVALID,
        ];

        foreach ($colors as $key => $color) {
            $this->fill($sheet, 'A'.$row, $color);
            $sheet->setCellValue('B'.$row++, __('zeytin.template.color.'.$key));
        }

        $row = $this->guideHeading($sheet, $row + 1, __('zeytin.template.examples'));

        foreach ($this->specs() as $spec) {
            $row = $this->guideExample($sheet, $row, $spec) + 1;
        }
    }

    /**
     * Aturan yang berlaku untuk sheet yang ada di berkas ini saja. Template
     * Belanja Tunai tidak perlu membahas kolom Section milik Payroll.
     *
     * @return array<int, string>
     */
    protected function rules(): array
    {
        $specs = collect($this->specs());
        $sheets = $specs->pluck('sheet');

        return array_values(array_filter([
            'sheet',
            $specs->contains(fn (SheetSpec $spec) => $this->isMonthly($spec)) ? 'month' : null,
            $specs->contains(fn (SheetSpec $spec) => $spec->dateColumn() === 'date') ? 'date' : null,
            $specs->contains(fn (SheetSpec $spec) => $spec->inheritDate) ? 'inherit' : null,
            'money',
            $sheets->diff(['Income'])->isNotEmpty() ? 'list' : null,
            $sheets->contains('Income') ? 'income' : null,
            $sheets->contains('Payroll') ? 'payroll' : null,
            'extra',
            'rows',
        ]));
    }

    /** Satu tabel contoh: nama sheet, judul kolomnya, lalu baris contohnya. */
    protected function guideExample(Worksheet $sheet, int $row, SheetSpec $spec): int
    {
        $columns = $spec->templateColumns();
        $lastCol = Coordinate::stringFromColumnIndex(count($columns));

        $sheet->setCellValue('A'.$row, $spec->sheet);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true);
        $row++;

        $sheet->fromArray(array_values($columns), null, 'A'.$row);
        $this->styleHeader($sheet, "A{$row}:{$lastCol}{$row}");
        $row++;

        foreach ($this->examples($spec) as $example) {
            $this->writeExampleRow($sheet, $row++, array_map(fn (string $field) => $example[$field] ?? null, array_keys($columns)));
        }

        return $row;
    }

    /**
     * Baris contoh tiap sheet, dikunci nama kolomnya.
     *
     * Baris kedua Expense sengaja tanpa tanggal — begitulah baris kedua dan
     * seterusnya di hari yang sama ditulis — dan membawa catatan.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function examples(SheetSpec $spec): array
    {
        $day = fn (int $day) => $this->month->copy()->day($day);

        $income = [];

        foreach (Channels::keys() as $i => $key) {
            $income[$key] = [100_000, 2_500_000, 1_200_000, 850_000, 600_000, 150_000][$i] ?? 100_000;
        }

        return match ($spec->sheet) {
            'Income' => [[
                'date' => $day(1),
                ...$income,
                'total_sales' => array_sum(Arr::only($income, Channels::inSales())),
            ]],

            'Expense' => [
                ['date' => $day(5), 'vendor' => 'Pasar Kerobokan', 'item' => 'Tomat', 'qty' => 3, 'unit' => 'kg', 'price' => 18_000, 'disc' => 0, 'tax' => 0, 'total' => 54_000],
                ['vendor' => 'Toko Ani', 'item' => 'Gelas', 'qty' => 2, 'unit' => 'pcs', 'price' => 15_000, 'disc' => 0, 'tax' => 0, 'total' => 30_000, 'note' => __('zeytin.template.example_note')],
            ],

            'Supplier Transfer Payment' => [[
                'date' => $day(10), 'vendor' => 'CV Daging Bali', 'item' => 'Daging sapi', 'unit' => 'kg', 'qty' => 20,
                'price' => 120_000, 'total' => 2_400_000, 'method' => 'Transfer', 'status' => 'PT KEBAP PAID',
            ]],

            'Outstanding INV' => [[
                'date' => $day(12), 'due_date' => $day(26), 'vendor' => 'CV Sayur Segar', 'item' => 'Selada', 'unit' => 'kg',
                'qty' => 5, 'price' => 25_000, 'disc' => 0, 'tax' => 0, 'status' => 'Need the payment', 'total' => 125_000,
            ]],

            'Supplier Database' => [[
                'name' => 'CV Daging Bali', 'contact_person' => 'Pak Made', 'supplies' => 'Daging sapi', 'last_price' => 120_000,
                'bank' => 'BCA', 'bank_account' => '0123456789', 'account_name' => 'Made Wijaya', 'payment_method' => 'Transfer',
            ]],

            'Payroll' => [
                ['name' => 'Sinta', 'basic' => 3_000_000, 'bpjs' => 150_000, 'grand_total' => 2_850_000, 'section' => 'Front Staff'],
                ['name' => 'Budi', 'basic' => 3_200_000, 'bpjs' => 150_000, 'grand_total' => 3_050_000, 'section' => 'Kitchen Staff'],
            ],

            default => [],
        };
    }

    /* ---------------------------------------------------------------- bantu */

    /**
     * @param  array<string, string>  $columns
     * @return array<string, string> nama kolom => huruf kolom
     */
    protected function letters(array $columns): array
    {
        $letters = [];
        $index = 1;

        foreach (array_keys($columns) as $field) {
            $letters[$field] = Coordinate::stringFromColumnIndex($index++);
        }

        return $letters;
    }

    protected function monthName(): string
    {
        return $this->month->translatedFormat('F Y');
    }
}

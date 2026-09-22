<?php

namespace Tests\Feature\Zeytin;

use App\Filament\Admin\Pages\Zeytin\ImportExcel;
use App\Models\DailyIncome;
use App\Models\Employee;
use App\Models\PaymentMethodOption;
use App\Models\Payroll;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\SupplierTransfer;
use App\Support\Zeytin\Channels;
use App\Support\Zeytin\DailyLedger;
use App\Support\Zeytin\RecordSource;
use App\Support\Zeytin\Workbook\Importer;
use App\Support\Zeytin\Workbook\TemplateBuilder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Template impor per bulan: yang dijaga adalah bahwa berkas dari template
 * sulit diisi berantakan, dan bahwa template yang belum diisi penuh tidak
 * merusak apa pun saat diunggah.
 */
class ImportTemplateTest extends TestCase
{
    protected string $path;

    /** @var array<int, Spreadsheet> dilepas di tearDown; lihat TemplateBuilder::save() */
    protected array $books = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = tempnam(sys_get_temp_dir(), 'zeytin').'.xlsx';
    }

    protected function tearDown(): void
    {
        foreach ($this->books as $book) {
            $book->disconnectWorksheets();
        }

        @unlink($this->path);

        parent::tearDown();
    }

    protected function book(string $month = '2026-08-01'): Spreadsheet
    {
        return $this->books[] = (new TemplateBuilder(Carbon::parse($month)))->build();
    }

    /** Disimpan lalu dibaca ulang, seperti berkas yang benar-benar diunggah. */
    protected function reload(Spreadsheet $book): Spreadsheet
    {
        (new Xlsx($book))->save($this->path);

        return $this->books[] = IOFactory::load($this->path);
    }

    protected function import(Spreadsheet $book, string $month = '2026-08-01'): array
    {
        (new Xlsx($book))->save($this->path);

        return (new Importer(Carbon::parse($month)))->import($this->path);
    }

    /** Isi daftar pilihan yang dirujuk aturan isian satu sel. */
    protected function choices(Spreadsheet $book, Worksheet $sheet, string $cell): array
    {
        [$name, $range] = explode('!', $sheet->getDataValidation($cell)->getFormula1());

        return array_merge(...$book->getSheetByName(trim($name, "'"))->rangeToArray(str_replace('$', '', $range)));
    }

    /** Nomor baris Income untuk satu tanggal: tanggal 1 di baris 2. */
    protected function incomeRow(int $day): int
    {
        return $day + 1;
    }

    public function test_tanggal_income_terisi_sebulan_penuh(): void
    {
        $income = $this->reload($this->book())->getSheetByName('Income');

        $this->assertSame('2026-08-01', ExcelDate::excelToDateTimeObject($income->getCell('A2')->getValue())->format('Y-m-d'));
        $this->assertSame('2026-08-31', ExcelDate::excelToDateTimeObject($income->getCell('A32')->getValue())->format('Y-m-d'));
        $this->assertNull($income->getCell('A33')->getValue());
    }

    /**
     * Kejadian nyata: "18/8/2028" di berkas Agustus 2026. Di template,
     * tanggal di luar bulan ditolak saat diketik dan diwarnai merah kalau
     * lolos lewat tempel.
     */
    public function test_tanggal_sheet_bulanan_dibatasi_ke_bulan_template(): void
    {
        $book = $this->reload($this->book());

        foreach (['Income', 'Expense', 'Supplier Transfer Payment'] as $name) {
            $sheet = $book->getSheetByName($name);
            $rule = $sheet->getDataValidation('A2');

            $this->assertSame(DataValidation::TYPE_DATE, $rule->getType(), $name);
            $this->assertSame(DataValidation::STYLE_STOP, $rule->getErrorStyle(), $name);
            $this->assertSame('DATE(2026,8,1)', $rule->getFormula1(), $name);
            $this->assertSame('DATE(2026,8,31)', $rule->getFormula2(), $name);

            $this->assertNotEmpty($sheet->getConditionalStyles('A2'), $name);
        }

        // Tagihan boleh berasal dari bulan-bulan lalu: hanya diingatkan.
        $bills = $book->getSheetByName('Outstanding INV')->getDataValidation('A2');

        $this->assertSame(DataValidation::STYLE_WARNING, $bills->getErrorStyle());
    }

    public function test_pilihan_diambil_dari_data_aplikasi(): void
    {
        Supplier::create(['name' => 'CV Daging', 'active' => true]);
        Supplier::create(['name' => 'cv daging ', 'active' => true]);
        PurchaseItem::create(['name' => 'Ayam', 'unit' => 'kg', 'price' => 35_000, 'active' => true]);
        PaymentMethodOption::create(['name' => 'Transfer', 'active' => true]);
        PaymentMethodOption::create(['name' => 'Cek', 'active' => false]);

        $book = $this->reload($this->book());
        $expense = $book->getSheetByName('Expense');
        $transfer = $book->getSheetByName('Supplier Transfer Payment');

        // Kembaran yang cuma beda huruf besar-kecil ditulis sekali.
        $this->assertSame(['CV Daging'], $this->choices($book, $expense, 'B2'));
        $this->assertSame(['Ayam'], $this->choices($book, $expense, 'C2'));
        $this->assertSame(['kg'], $this->choices($book, $expense, 'E2'));
        $this->assertSame(['Transfer'], $this->choices($book, $transfer, 'H2'));

        // Nama baru tetap boleh, seperti di formulir — cuma diingatkan.
        $this->assertSame(DataValidation::STYLE_WARNING, $expense->getDataValidation('B2')->getErrorStyle());

        // Status transfer daftar tertutup, seperti di halaman aplikasinya.
        $this->assertSame(['PT KEBAP PAID', 'ASLAN PAID', '—'], $this->choices($book, $transfer, 'I2'));
        $this->assertSame(DataValidation::STYLE_STOP, $transfer->getDataValidation('I2')->getErrorStyle());

        $this->assertSame(Worksheet::SHEETSTATE_HIDDEN, $book->getSheetByName(__('zeytin.template.lists_tab'))->getSheetState());
    }

    public function test_daftar_karyawan_tanpa_gaji(): void
    {
        Employee::create(['name' => 'Sinta', 'basic_salary' => 4_500_000, 'active' => true]);

        $book = $this->reload($this->book());
        $lists = $book->getSheetByName(__('zeytin.template.lists_tab'));

        $this->assertSame(['Sinta'], $this->choices($book, $book->getSheetByName('Payroll'), 'A2'));

        // Template diunduh Admin; gaji per orang hanya untuk Super Admin.
        $values = array_merge(...$lists->toArray());
        $this->assertNotContains(4_500_000, $values);
        $this->assertNotContains('4500000', $values);
    }

    public function test_total_terhitung_dengan_rumus_yang_sama_dengan_aplikasi(): void
    {
        $book = $this->book();

        $expense = $book->getSheetByName('Expense');
        $expense->fromArray([null, 'Pak Budi', 'Ayam', 3, 'kg', 18_000, 500, 1_000], null, 'A2');

        $this->assertSame(DailyLedger::lineTotal(3, 18_000, 1_000, 500), (int) $expense->getCell('I2')->getCalculatedValue());

        // Total Sales tanpa petty cash, persis seperti rumus berkas aslinya.
        $income = $book->getSheetByName('Income');
        $income->fromArray([100_000, 2_000_000, 500_000, 0, 0, 0], null, 'B2');

        $this->assertSame(2_500_000, (int) $income->getCell('H2')->getCalculatedValue());

        // Baris yang belum diisi totalnya kosong, bukan nol.
        $this->assertSame('', $expense->getCell('I3')->getCalculatedValue());
    }

    public function test_nomor_rekening_disimpan_sebagai_teks(): void
    {
        $sheet = $this->reload($this->book())->getSheetByName('Supplier Database');

        $this->assertSame('@', $sheet->getStyle('F2')->getNumberFormat()->getFormatCode());
    }

    /**
     * Template yang diunggah sebelum selesai diisi tidak boleh menimpa apa
     * pun. Tanggal Income sudah tertulis sebulan penuh — tanpa aturan
     * lewati-baris-kosong, tiap hari yang belum diisi akan jadi pemasukan nol
     * dan menimpa hari yang sudah diketik di aplikasi.
     */
    public function test_template_kosong_tidak_mengimpor_dan_tidak_menimpa_apa_pun(): void
    {
        $typed = DailyIncome::create([
            'date' => '2026-08-05',
            ...array_fill_keys(Channels::keys(), 0),
            'cash' => 3_000_000,
            'source' => RecordSource::MANUAL,
        ]);

        foreach ($this->import($this->book()) as $result) {
            $this->assertSame(0, $result['imported'], $result['sheet']);
        }

        $this->assertSame(1, DailyIncome::count());
        $this->assertSame(3_000_000, $typed->fresh()->cash);

        // Contoh di sheet Petunjuk tidak ikut terimpor.
        $this->assertSame(0, Purchase::count());
        $this->assertSame(0, Payroll::count());
    }

    public function test_hanya_hari_income_yang_diisi_yang_terimpor(): void
    {
        $book = $this->book();
        $book->getSheetByName('Income')->fromArray([0, 1_500_000, 250_000], null, 'B'.$this->incomeRow(7));

        $this->import($book);

        $this->assertSame(1, DailyIncome::count());
        $this->assertSame('2026-08-07', DailyIncome::first()->date->toDateString());
        $this->assertSame(1_750_000, DailyIncome::first()->totalSales());
    }

    public function test_catatan_pengeluaran_ikut_terimpor(): void
    {
        $book = $this->book();
        $book->getSheetByName('Expense')->fromArray(
            ['05/08/2026', 'Toko Ani', 'Gelas', 2, 'pcs', 15_000, 0, 0, null, 'Ganti gelas pecah'],
            null,
            'A2',
        );
        $book->getSheetByName('Supplier Transfer Payment')->fromArray(
            ['05/08/2026', 'CV Daging', 'Daging sapi', 'kg', 20, 120_000, 2_400_000, 'Transfer', 'PT KEBAP PAID', 'Bayar dua minggu'],
            null,
            'A2',
        );

        $this->import($book);

        $this->assertSame('Ganti gelas pecah', Purchase::first()->note);
        $this->assertSame(30_000, Purchase::first()->total);
        $this->assertSame('Bayar dua minggu', SupplierTransfer::first()->note);
    }

    /**
     * Kolom Section di template. Baris gaji yang bagiannya "Kitchen Staff"
     * adalah data, bukan baris penanda bagian seperti di berkas klien.
     */
    public function test_bagian_karyawan_dibaca_dari_kolom_section(): void
    {
        $book = $this->book();
        $book->getSheetByName('Payroll')->fromArray([
            ['Sinta', 3_000_000, 150_000, 2_850_000, 'Front Staff'],
            ['Budi', 3_200_000, 150_000, 3_050_000, 'kitchen staff'],
            ['Rani', 3_000_000, 150_000, 2_850_000, null],
        ], null, 'A2');

        $this->import($book);

        $this->assertSame(3, Payroll::count());
        $this->assertSame('Front Staff', Payroll::where('name', 'Sinta')->value('section'));
        $this->assertSame('Kitchen Staff', Payroll::where('name', 'Budi')->value('section'));
        $this->assertSame('Front Staff', Payroll::where('name', 'Rani')->value('section'));
    }

    public function test_tombol_template_memakai_bulan_yang_dipilih(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ImportExcel::class)
            ->set('data.payroll_month', '2026-08-01')
            ->call('downloadTemplate')
            ->assertFileDownloaded('zeytin-template-2026-08.xlsx');
    }
}

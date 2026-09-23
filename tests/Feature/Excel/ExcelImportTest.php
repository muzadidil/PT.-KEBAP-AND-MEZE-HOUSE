<?php

namespace Tests\Feature\Excel;

use App\Filament\Admin\Pages\MeetingProgress;
use App\Filament\Admin\Resources\CapitalEntries\CapitalEntryResource;
use App\Filament\Admin\Resources\Employees\EmployeeResource;
use App\Filament\Admin\Resources\Expenses\ExpenseResource;
use App\Filament\Admin\Resources\PayComponents\PayComponentResource;
use App\Filament\Admin\Resources\Payslips\PayslipResource;
use App\Filament\Admin\Resources\Products\ProductResource;
use App\Filament\Admin\Resources\Suppliers\Pages\ManageSuppliers;
use App\Filament\Admin\Resources\Suppliers\SupplierResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Filament\Admin\Resources\Zeytin\Purchases\Pages\ManagePurchases;
use App\Filament\Admin\Resources\Zeytin\OutstandingBills\OutstandingBillResource;
use App\Filament\Admin\Resources\Zeytin\Purchases\PurchaseResource;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\MeetingProject;
use App\Models\MeetingTask;
use App\Models\Owner;
use App\Models\PayComponent;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Support\Excel\ExcelSheet;
use App\Support\Excel\ExcelSource;
use App\Support\Excel\ImportReport;
use App\Support\Zeytin\RecordSource;
use App\Support\Zeytin\Workbook\Cells;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Tombol "Unduh template" dan "Impor Excel" di tiap menu input.
 *
 * Yang dijaga: semua menu input punya keduanya, berkas yang salah satu
 * barisnya saja tidak menyimpan apa pun, data induk diperbarui bukan
 * digandakan, dan catatan yang diimpor ulang tidak terhitung dua kali.
 */
class ExcelImportTest extends TestCase
{
    protected string $path;

    /** Menu input yang sengaja tanpa impor Excel, beserta alasannya. */
    protected const WITHOUT_EXCEL = [
        // Akun masuk: kata sandi dan peran tidak diisi lewat berkas.
        UserResource::class,
        // Slip dibuat dari data karyawan dan komponen gaji, bernomor urut;
        // keduanya sudah bisa diimpor.
        PayslipResource::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->path = tempnam(sys_get_temp_dir(), 'excel').'.xlsx';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    /**
     * Template dari sumbernya, diisi baris uji di sheet datanya, lalu disimpan.
     *
     * @param  array<int, array<int, mixed>>  $rows
     */
    protected function fill(ExcelSource $source, array $rows, ?string $sheet = null, string $month = '2026-08-01'): string
    {
        $book = $source->template(Carbon::parse($month));
        $target = $sheet ? $book->getSheetByName($sheet) : $book->getSheet(0);
        $target->fromArray($rows, null, 'A2');

        (new Xlsx($book))->save($this->path);
        $book->disconnectWorksheets();

        return $this->path;
    }

    /** @param  array<int, array<int, mixed>>  $rows */
    protected function import(ExcelSource $source, array $rows, ?string $sheet = null): ImportReport
    {
        return $source->import($this->fill($source, $rows, $sheet), Carbon::parse('2026-08-01'));
    }

    protected function category(string $name = 'Kebab'): Category
    {
        return Category::create(['name_en' => $name, 'sort' => 1, 'active' => true]);
    }

    /* ------------------------------------------------------------ cakupan */

    /**
     * Menu input baru tanpa template gagal di sini, jadi tidak bisa lolos
     * tanpa diputuskan dulu: diberi impor Excel, atau masuk daftar
     * pengecualian beserta alasannya.
     */
    public function test_setiap_menu_input_punya_template_dan_impor(): void
    {
        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            if (in_array($resource, static::WITHOUT_EXCEL, true)) {
                continue;
            }

            $this->assertTrue(method_exists($resource, 'excel'), $resource);
            $this->assertInstanceOf(ExcelSource::class, $resource::excel(), $resource);
        }

        $this->assertInstanceOf(ExcelSource::class, MeetingProgress::excel());
    }

    public function test_template_menu_terunduh_dari_tombolnya(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ManageSuppliers::class)
            ->assertActionVisible('excelImport')
            ->callAction('excelTemplate')
            ->assertFileDownloaded('template-'.Str::slug(__('nav.suppliers')).'.xlsx');
    }

    /** Menu pembukuan menanyakan bulannya: tanggal di templatenya dibatasi ke bulan itu. */
    public function test_template_pembukuan_terunduh_untuk_bulan_yang_dipilih(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManagePurchases::class)
            ->callAction('excelTemplate', data: ['month' => '2026-08-01'])
            ->assertFileDownloaded('zeytin-template-expense-2026-08.xlsx');
    }

    public function test_impor_lewat_tombol_menyimpan_dan_memberi_tahu(): void
    {
        $this->fill(SupplierResource::excel(), [['CV Daging Bali', 'Daging sapi']]);

        Livewire::actingAs($this->superAdmin())
            ->test(ManageSuppliers::class)
            ->callAction('excelImport', data: [
                'file' => UploadedFile::fake()->createWithContent('pemasok.xlsx', file_get_contents($this->path)),
            ])
            ->assertHasNoActionErrors()
            ->assertNotified(__('excel.result.done'));

        $this->assertSame('Daging sapi', Supplier::where('name', 'CV Daging Bali')->value('supplies'));
    }

    /* ---------------------------------------------------------- data induk */

    public function test_data_induk_diperbarui_bukan_digandakan(): void
    {
        $existing = Supplier::create(['name' => 'CV Daging Bali', 'phone' => '0811', 'active' => false]);

        $report = $this->import(SupplierResource::excel(), [
            // Beda huruf besar-kecil tetap pemasok yang sama.
            ['cv daging bali', 'Daging sapi', null, null],
            ['Toko Ani', 'Gelas', 'Bu Ani', '081234567890'],
        ]);

        $this->assertSame(['created' => 1, 'updated' => 1, 'skipped' => 0], $report->counts);
        $this->assertSame(2, Supplier::count());

        $existing->refresh();
        $this->assertSame('Daging sapi', $existing->supplies);

        // Sel kosong tidak menghapus isi lama, dan kolom Aktif yang kosong
        // tidak mengaktifkan lagi pemasok yang sengaja dinonaktifkan.
        $this->assertSame('0811', $existing->phone);
        $this->assertFalse($existing->active);

        // Nomor telepon tetap teks, nol di depannya tidak hilang.
        $this->assertSame('081234567890', Supplier::where('name', 'Toko Ani')->value('phone'));
    }

    public function test_dua_baris_berkunci_sama_di_satu_berkas_ditolak(): void
    {
        $report = $this->import(SupplierResource::excel(), [
            ['Toko Ani', 'Gelas'],
            ['TOKO ANI', 'Piring'],
        ]);

        $this->assertFalse($report->ok());
        $this->assertSame([__('excel.error.duplicate', ['row' => 3, 'first' => 2])], $report->errors);
        $this->assertSame(0, Supplier::count());
    }

    /**
     * Semua atau tidak sama sekali: satu baris yang salah membatalkan
     * seluruh berkas, dan kesalahannya disebut per baris dan per kolom.
     */
    public function test_satu_baris_salah_tidak_ada_yang_disimpan(): void
    {
        $this->category('Kebab');

        $report = $this->import(ProductResource::excel(), [
            ['Kebab', 'KB-01', 'Chicken Kebab', null, 45_000],
            ['Kebab', 'KB-02', 'Beef Kebab', null, '45rb'],
            ['Minuman', 'MN-01', 'Ayran', null, 15_000],
        ]);

        $this->assertFalse($report->ok());
        $this->assertSame(0, Product::count());

        $this->assertSame([
            __('excel.error.row', ['row' => 3, 'column' => __('field.price'), 'message' => __('excel.error.number', ['value' => '45rb'])]),
            __('excel.error.row', ['row' => 4, 'column' => __('field.category'), 'message' => __('excel.error.choice', ['value' => 'Minuman'])]),
        ], $report->errors);
    }

    public function test_menu_dicocokkan_lewat_kode_lalu_nama_dan_kategorinya_lewat_nama(): void
    {
        $kebab = $this->category('Kebab');
        $drinks = $this->category('Drinks');
        $product = Product::create(['category_id' => $kebab->id, 'sku' => 'KB-01', 'name_en' => 'Chicken Kebab', 'price' => 40_000]);

        $this->import(ProductResource::excel(), [
            // Kodenya sama: namanya yang diganti.
            ['kebab', 'KB-01', 'Chicken Kebab Wrap', null, 45_000],
            ['Drinks', null, 'Ayran', 'Ayran', 'Rp 15.000'],
        ]);

        $this->assertSame(2, Product::count());
        $this->assertSame('Chicken Kebab Wrap', $product->fresh()->name_en);
        $this->assertSame(45_000, $product->fresh()->price);

        $ayran = Product::where('name_en', 'Ayran')->first();
        $this->assertSame($drinks->id, $ayran->category_id);
        $this->assertSame(15_000, $ayran->price);
    }

    public function test_karyawan_dicocokkan_lewat_nik_lalu_nama(): void
    {
        $sinta = Employee::create(['name' => 'Sinta', 'nik' => '00123', 'basic_salary' => 3_000_000]);
        $budi = Employee::create(['name' => 'Budi', 'basic_salary' => 3_000_000]);

        $this->import(EmployeeResource::excel(), [
            ['Sinta Dewi', '00123', 'Waiter', 'Front Staff', 3_200_000],
            ['budi', null, 'Cook', 'kitchen staff', 3_500_000],
        ]);

        $this->assertSame(2, Employee::count());
        $this->assertSame('Sinta Dewi', $sinta->fresh()->name);
        $this->assertSame('00123', $sinta->fresh()->nik);
        $this->assertSame(3_500_000, $budi->fresh()->basic_salary);
        $this->assertSame('Kitchen Staff', $budi->fresh()->section);
    }

    /** Nama boleh kembar asal jenisnya beda — kuncinya jenis dan nama. */
    public function test_komponen_gaji_dicocokkan_lewat_jenis_dan_nama(): void
    {
        $overtime = PayComponent::create(['type' => PayComponent::EARNING, 'name' => 'Lembur', 'default_amount' => 50_000]);

        $this->import(PayComponentResource::excel(), [
            [__('payroll.type.earning'), 'Lembur', 75_000],
            [__('payroll.type.deduction'), 'Lembur', 25_000],
        ]);

        $this->assertSame(2, PayComponent::count());
        $this->assertSame(75_000, $overtime->fresh()->default_amount);
        $this->assertSame(25_000, PayComponent::where('type', PayComponent::DEDUCTION)->value('default_amount'));
    }

    /* ------------------------------------------------------------- catatan */

    /**
     * Pengeluaran tidak punya nama untuk dicocokkan. Berkas yang sama
     * diimpor dua kali tidak menggandakan apa pun, sedangkan dua baris kembar
     * yang sah di berkas tetap jadi dua baris.
     */
    public function test_pengeluaran_yang_diimpor_ulang_tidak_terhitung_dua_kali(): void
    {
        $rows = [
            ['05/08/2026', 'Gas elpiji', 210_000, 'Operasional', 'Tunai'],
            ['05/08/2026', 'Es batu', 12_000, 'operational', 'cash'],
            ['05/08/2026', 'Es batu', 12_000, 'operational', 'cash'],
        ];

        $first = $this->import(ExpenseResource::excel(), $rows);
        $second = $this->import(ExpenseResource::excel(), $rows);

        $this->assertSame(['created' => 3, 'skipped' => 0], $first->counts);
        $this->assertSame(['created' => 0, 'skipped' => 3], $second->counts);
        $this->assertSame(3, Expense::count());
        $this->assertSame(234_000, (int) Expense::sum('amount'));
    }

    public function test_modal_pemilik_dicocokkan_ke_pemilik_lewat_namanya(): void
    {
        $owner = Owner::create(['name' => 'Aslan', 'share_percent' => 60, 'active' => true]);

        $report = $this->import(CapitalEntryResource::excel(), [
            ['01/08/2026', 'aslan', null, null, 10_000_000],
            ['01/08/2026', 'Orang lain', null, null, 5_000_000],
        ]);

        $this->assertFalse($report->ok());
        $this->assertCount(1, $report->errors);

        $this->import(CapitalEntryResource::excel(), [['01/08/2026', 'aslan', null, null, 10_000_000]]);

        $this->assertSame(10_000_000, (int) $owner->capitalEntries()->sum('amount'));
    }

    public function test_progres_rapat_membuat_proyek_baru_sekali_dan_memperbarui_tugas_lama(): void
    {
        $rows = [
            ['Cat ulang dapur', 'Renovasi', null, 'Tinggi'],
            ['Ganti lampu', 'renovasi', null, 'medium'],
        ];

        $this->import(MeetingProgress::excel(), $rows);

        $this->assertSame(1, MeetingProject::count());
        $this->assertSame(2, MeetingTask::where('project_id', MeetingProject::first()->id)->count());
        $this->assertSame('kerja', MeetingTask::where('text', 'Ganti lampu')->value('category'));

        // Diimpor lagi dengan prioritas lain: diperbarui, bukan ditambah.
        $this->import(MeetingProgress::excel(), [['Ganti lampu', 'Renovasi', null, 'Rendah']]);

        $this->assertSame(2, MeetingTask::count());
        $this->assertSame('low', MeetingTask::where('text', 'Ganti lampu')->value('priority'));
    }

    /* ------------------------------------------------------------ template */

    public function test_template_berisi_daftar_pilihan_dari_aplikasi(): void
    {
        $this->category('Kebab');
        $this->category('Drinks');

        $book = ProductResource::excel()->template();
        (new Xlsx($book))->save($this->path);
        $book->disconnectWorksheets();

        $book = IOFactory::load($this->path);
        $data = $book->getSheet(0);

        $category = $data->getDataValidation('A2');
        $this->assertSame(DataValidation::TYPE_LIST, $category->getType());
        $this->assertSame(DataValidation::STYLE_STOP, $category->getErrorStyle());

        [$sheet, $range] = explode('!', $category->getFormula1());
        $this->assertSame(['Drinks', 'Kebab'], array_merge(...$book->getSheetByName(trim($sheet, "'"))->rangeToArray(str_replace('$', '', $range))));

        // Kolom wajib ditandai bintang.
        $this->assertSame(__('field.name_en').' *', $data->getCell('C1')->getValue());

        $book->disconnectWorksheets();
    }

    /** Template yang diunduh dalam bahasa Inggris tetap terbaca dalam bahasa Indonesia. */
    public function test_judul_kolom_dikenali_di_kedua_bahasa(): void
    {
        app()->setLocale('en');
        $this->fill(SupplierResource::excel(), [['CV Daging Bali', 'Daging sapi']]);

        app()->setLocale('id');
        $report = SupplierResource::excel()->import($this->path);

        $this->assertTrue($report->ok(), implode(' / ', $report->errors));
        $this->assertSame(1, Supplier::count());
    }

    /**
     * Menu pembukuan memakai sheet yang sama dengan berkas bulanan klien,
     * dibaca pengimpor yang sama. Contoh di sheet Petunjuk tidak pernah ikut
     * terimpor, walau sheet datanya diganti nama.
     */
    public function test_template_pembukuan_satu_sheet_dan_contohnya_tidak_terimpor(): void
    {
        $source = PurchaseResource::excel();
        $book = $source->template(Carbon::parse('2026-08-01'));

        $names = array_map(fn ($sheet) => $sheet->getTitle(), iterator_to_array($book->getWorksheetIterator()));
        $this->assertSame([__('zeytin.template.tab'), 'Expense', __('zeytin.template.lists_tab')], $names);

        $book->getSheetByName('Expense')->setTitle('Belanja Agustus');
        $book->getSheetByName('Belanja Agustus')->fromArray(['05/08/2026', 'Toko Ani', 'Es batu', 2, 'pcs', 6_000], null, 'A2');
        (new Xlsx($book))->save($this->path);
        $book->disconnectWorksheets();

        $report = $source->import($this->path, Carbon::parse('2026-08-01'));

        $this->assertTrue($report->ok(), implode(' / ', $report->errors));
        $this->assertSame(1, $report->count('imported'));
        $this->assertSame(['Es batu'], Purchase::pluck('item')->all());
    }

    public function test_angka_bertitik_ribuan_dibaca_utuh(): void
    {
        $this->assertSame(35_000, Cells::parseNumber('Rp 35.000'));
        $this->assertSame(35_000, Cells::parseNumber('35,000'));
        $this->assertSame(1_250_000, Cells::parseNumber('1.250.000'));
        $this->assertSame(3, Cells::parseNumber('2.5'));
        $this->assertNull(Cells::parseNumber('35rb'));

        // Pembaca bulanan tetap longgar: huruf di sekitar angka dibuang.
        $this->assertSame(35_000, Cells::toNumber('Rp 35.000'));
        $this->assertSame(45_000, Cells::toNumber('45000 IDR'));
    }

    public function test_definisi_excel_adalah_excel_sheet_atau_sumber_pembukuan(): void
    {
        $this->assertInstanceOf(ExcelSheet::class, SupplierResource::excel());
        $this->assertTrue(PurchaseResource::excel()->templateNeedsMonth());
        // Tanggal belanja diperiksa terhadap bulan yang dipilih saat mengunggah.
        $this->assertTrue(PurchaseResource::excel()->importNeedsMonth());
        $this->assertFalse(OutstandingBillResource::excel()->importNeedsMonth());
        $this->assertFalse(SupplierResource::excel()->templateNeedsMonth());
    }

    /* ------------------------------------------- tombol impor pembukuan */

    public function test_tombol_impor_belanja_menolak_tanggal_di_luar_bulan(): void
    {
        $report = $this->import(PurchaseResource::excel(), [
            ['01/08/2026', 'Pak Budi', 'Ayam', 1, 'kg', 35_000, 0, 0, 35_000],
            ['18/8/2028', 'Pak Budi', 'Cabai', 1, 'kg', 50_000, 0, 0, 50_000],
        ], 'Expense');

        $this->assertFalse($report->ok());
        $this->assertSame([__('zeytin.import.error.row_out_of_month', [
            'row' => 3,
            'date' => Carbon::parse('2028-08-18')->translatedFormat('j M Y'),
            'month' => Carbon::parse('2026-08-01')->translatedFormat('F Y'),
        ])], $report->errors);
        $this->assertSame(0, Purchase::count());
    }

    /** Tidak ada layar untuk bertanya, jadi yang sama dengan ketikan dilewati dan disebutkan barisnya. */
    public function test_tombol_impor_belanja_melewati_yang_sudah_diketik(): void
    {
        Purchase::create([
            'date' => '2026-08-01', 'item' => 'Ayam', 'qty' => 10, 'price' => 35_000,
            'source' => RecordSource::MANUAL,
        ]);

        $report = $this->import(PurchaseResource::excel(), [
            ['01/08/2026', 'Pak Budi', 'Ayam', 10, 'kg', 35_000, 0, 0, 350_000],
            [null, 'Pak Budi', 'Cabai', 1, 'kg', 50_000, 0, 0, 50_000],
        ], 'Expense');

        $this->assertTrue($report->ok());
        $this->assertSame(1, $report->count('imported'));
        $this->assertSame(1, $report->count('manual'));
        $this->assertStringContainsString(__('zeytin.import.review.skipped_rows', ['rows' => '2']), (string) $report->note);
        $this->assertSame(2, Purchase::count());
    }
}

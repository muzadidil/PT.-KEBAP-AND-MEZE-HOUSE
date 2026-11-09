<?php

namespace Tests\Feature\Moka;

use App\Filament\Admin\Resources\Zeytin\DailyIncomes\Pages\ManageDailyIncomes;
use App\Filament\Admin\Resources\Zeytin\MokaItemSales\Pages\ManageMokaItemSales;
use App\Models\DailyIncome;
use App\Models\MokaItemSale;
use App\Support\Moka\MokaImport;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Satu tombol "Impor dari Moka" untuk semua laporan Moka: jenis laporannya
 * dikenali dari isi berkas, bukan dari halaman tempat tombolnya ditekan.
 *
 * Yang dijaga: laporan rekap per menu masuk ke Penjualan per Menu, laporan
 * per struk masuk ke Pemasukan Harian — di halaman mana pun diunggah — dan
 * berkas yang tidak dikenali tidak menyimpan apa pun.
 */
class MokaImportTest extends TestCase
{
    /** @var array<int, string> */
    protected array $files = [];

    protected const ITEM_SALES_HEADER = ['Item Name', 'Item Variant Name', 'Category Name', 'SKU', 'Item Sold', 'Item Refunded', 'Gross Sales', 'Discount', 'Refund', 'Net Sales', 'COGS', 'Gross Profit', 'Gross Margin'];

    protected const MOKA_NAME_ITEM_SALES = 'Report Item Sales - 01-11-2026 - 08-11-2026 - ZEYTIN.xlsx';

    protected const MOKA_NAME_TRANSACTION = 'Report Transaction - 01-11-2026 - 08-11-2026 - All_Outlet - 1086892 - 6af13f4a.xlsx';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    protected function save(Spreadsheet $book): string
    {
        $path = tempnam(sys_get_temp_dir(), 'moka').'.xlsx';
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $this->files[] = $path;
    }

    /** Report Item Sales: rekap per menu, dua menu. */
    protected function itemSalesFile(): string
    {
        $book = new Spreadsheet;
        $book->getSheet(0)->fromArray([
            self::ITEM_SALES_HEADER,
            ['Ayran', null, 'Drink', null, 10.0, 0.0, 450000.0, 0.0, 0.0, 450000.0, 0.0, 450000.0, '100%'],
            ['Adana kebab beff', null, 'Kebab', null, 5.0, 0.0, 675000.0, -6750.0, 0.0, 668250.0, 0.0, 668250.0, '100%'],
            ['Total'],
        ], null, 'A1');

        return $this->save($book);
    }

    /**
     * Report Transaction: per struk. Tanggal yang hari-bulannya bisa dibalik
     * (hari <= 12) tersimpan TERTUKAR seperti dari Moka (1 November menjadi
     * 11 Januari); yang tidak bisa dibalik tetap teks.
     *
     * @param  array<int, array{0: string, 1: string, 2: int}>  $receipts  [tanggal sebenarnya, cara bayar, net sales]
     */
    protected function transactionFile(array $receipts, bool $swapped = true): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getSheet(0);
        $sheet->fromArray([['Outlet', 'Date', 'Time', 'Gross Sales', 'Discounts', 'Refunds', 'Net Sales', 'Gratuity', 'Tax', 'Total Collected', 'Total Amount', 'Other Note (Optional)', 'Receipt Number', 'Collected By', 'Served By', 'Customer', 'Customer Phone', 'Items', 'Payment Method', 'Event Type', 'Reason of Refund']], null, 'A1');

        foreach ($receipts as $i => [$day, $method, $net]) {
            $date = Carbon::parse($day);

            $cell = match (true) {
                ! $swapped => ExcelDate::PHPToExcel($date),
                $date->day <= 12 => ExcelDate::PHPToExcel(Carbon::create($date->year, $date->day, $date->month)),
                default => $date->format('d/m/Y'),
            };

            $sheet->fromArray([['ZEYTIN', $cell, 0.5, $net, 0, 0, $net, 0, 0, $net, $net, null, 'R'.$i, 'Zeytin', null, null, null, 'Item', $method, 'Payment', null]], null, 'A'.($i + 2));
        }

        return $this->save($book);
    }

    /** @return array<int, array{0: string, 1: string, 2: int}> */
    protected function receipts(): array
    {
        return [
            ['2026-11-01', 'Cash', 100_000],
            ['2026-11-01', 'BNI', 200_000],
            ['2026-11-02', 'GrabFood', 50_000],
        ];
    }

    protected function unknownFile(): string
    {
        $book = new Spreadsheet;
        $book->getSheet(0)->fromArray([['Nama', 'Kelompok', 'Omzet'], ['Ayran', 'Drink', 450000]], null, 'A1');

        return $this->save($book);
    }

    /**
     * Berkas xlsx asli Moka menaruh `_rels/.rels` di urutan pertama zip, dan
     * jenis yang dilaporkan server menjadi octet-stream. Keduanya ditiru.
     */
    protected function upload(string $path, string $name): UploadedFile
    {
        $in = new \ZipArchive;
        $in->open($path);
        $moka = tempnam(sys_get_temp_dir(), 'moka').'.xlsx';
        $out = new \ZipArchive;
        $out->open($moka, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $out->addFromString('_rels/.rels', $in->getFromName('_rels/.rels'));

        for ($i = 0; $i < $in->numFiles; $i++) {
            if (($entry = $in->getNameIndex($i)) !== '_rels/.rels') {
                $out->addFromString($entry, $in->getFromIndex($i));
            }
        }

        $in->close();
        $out->close();
        $this->files[] = $moka;

        return UploadedFile::fake()->createWithContent($name, file_get_contents($moka))->mimeType('application/octet-stream');
    }

    /** @return array<int, string> */
    protected function incomeDates(): array
    {
        return DailyIncome::orderBy('date')->get()->map(fn ($row) => $row->date->toDateString())->all();
    }

    /* ------------------------------------------------------------ pengenal */

    public function test_jenis_laporan_dikenali_dari_judul_kolomnya(): void
    {
        $this->assertSame(MokaImport::ITEM_SALES, MokaImport::detect($this->itemSalesFile()));
        $this->assertSame(MokaImport::RECEIPTS, MokaImport::detect($this->transactionFile($this->receipts())));
        $this->assertNull(MokaImport::detect($this->unknownFile()));
    }

    public function test_csv_per_struk_dikenali_juga(): void
    {
        $csv = tempnam(sys_get_temp_dir(), 'moka').'.csv';
        file_put_contents($csv, "\xEF\xBB\xBFOutlet,Receipt Number,Date,Time,Net Sales,Payment Method\nZEYTIN,R1,30-09-2026,12:00:00,155000.0,Cash\n");
        $this->files[] = $csv;

        $this->assertSame(MokaImport::RECEIPTS, MokaImport::detect($csv));
    }

    /* ------------------------------------------- satu tombol, di halaman mana pun */

    public function test_rekap_per_menu_masuk_ke_penjualan_per_menu_walau_diunggah_di_pemasukan_harian(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageDailyIncomes::class)
            ->callAction('posSalesImport', data: [
                'file' => $this->upload($this->itemSalesFile(), self::MOKA_NAME_ITEM_SALES),
            ])
            ->assertHasNoActionErrors()
            ->assertNotified(__('excel.result.done'));

        $this->assertSame(2, MokaItemSale::count());
        $this->assertSame(0, DailyIncome::count());
        $this->assertSame('2026-11-01', MokaItemSale::first()->period_start->toDateString());
        $this->assertSame('2026-11-08', MokaItemSale::first()->period_end->toDateString());
    }

    /** Persis yang terjadi di produksi: Report Transaction diunggah di Penjualan per Menu. */
    public function test_laporan_per_struk_masuk_ke_pemasukan_harian_walau_diunggah_di_penjualan_per_menu(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageMokaItemSales::class)
            ->callAction('mokaImport', data: [
                'file' => $this->upload($this->transactionFile($this->receipts()), self::MOKA_NAME_TRANSACTION),
            ])
            ->assertHasNoActionErrors()
            ->assertNotified(__('excel.result.done'));

        $this->assertSame(0, MokaItemSale::count());
        // Tanggal tertukar di berkas dikembalikan ke periode nama berkas.
        $this->assertSame(['2026-11-01', '2026-11-02'], $this->incomeDates());
        $this->assertSame(100_000, DailyIncome::whereDate('date', '2026-11-01')->first()->cash);
        $this->assertSame(200_000, DailyIncome::whereDate('date', '2026-11-01')->first()->bni);
        $this->assertSame(50_000, DailyIncome::whereDate('date', '2026-11-02')->first()->grab_food);
    }

    public function test_tombol_yang_sama_di_kedua_halaman(): void
    {
        Livewire::actingAs($this->admin())->test(ManageDailyIncomes::class)->assertActionExists('posSalesImport');
        Livewire::actingAs($this->admin())->test(ManageMokaItemSales::class)->assertActionExists('mokaImport');
    }

    /* ------------------------------------------------------------- periode */

    public function test_kotak_dari_sampai_didahulukan_atas_nama_berkas(): void
    {
        // Nama tanpa periode; periodenya diisi sendiri di kotak.
        Livewire::actingAs($this->admin())
            ->test(ManageMokaItemSales::class)
            ->callAction('mokaImport', data: [
                'file' => $this->upload($this->transactionFile($this->receipts()), 'laporan.xlsx'),
                'from' => '2026-11-01',
                'until' => '2026-11-08',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified(__('excel.result.done'));

        $this->assertSame(['2026-11-01', '2026-11-02'], $this->incomeDates());
    }

    public function test_rekap_per_menu_tanpa_periode_ditolak_dengan_pesan_jelas(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageMokaItemSales::class)
            ->callAction('mokaImport', data: [
                'file' => $this->upload($this->itemSalesFile(), 'laporan.xlsx'),
            ])
            ->assertNotified(trans_choice('excel.result.failed', 1, ['count' => 1]));

        $this->assertSame(0, MokaItemSale::count());
    }

    public function test_laporan_per_struk_tanpa_periode_tetap_jalan_seperti_dulu(): void
    {
        // Tanpa periode, tanggal dipakai apa adanya (perilaku sebelum ada pengenal).
        Livewire::actingAs($this->admin())
            ->test(ManageDailyIncomes::class)
            ->callAction('posSalesImport', data: [
                'file' => $this->upload($this->transactionFile([['2026-11-05', 'Cash', 1_000]], swapped: false), 'laporan.xlsx'),
            ])
            ->assertHasNoActionErrors()
            ->assertNotified(__('excel.result.done'));

        $this->assertSame(['2026-11-05'], $this->incomeDates());
    }

    public function test_memilih_berkas_mengisi_periode_di_kedua_halaman(): void
    {
        foreach ([[ManageDailyIncomes::class, 'posSalesImport'], [ManageMokaItemSales::class, 'mokaImport']] as [$page, $action]) {
            Livewire::actingAs($this->admin())
                ->test($page)
                ->mountAction($action)
                ->set('mountedActions.0.data.file', $this->upload($this->transactionFile($this->receipts()), self::MOKA_NAME_TRANSACTION))
                ->assertActionDataSet(['from' => '2026-11-01', 'until' => '2026-11-08']);
        }
    }

    /* -------------------------------------------------- yang tidak dikenali */

    public function test_berkas_yang_bukan_laporan_moka_ditolak_dan_tidak_menyimpan_apa_pun(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageMokaItemSales::class)
            ->callAction('mokaImport', data: [
                'file' => $this->upload($this->unknownFile(), 'Report Item Sales - 01-11-2026 - 08-11-2026.xlsx'),
            ])
            ->assertNotified(trans_choice('excel.result.failed', 1, ['count' => 1]));

        $this->assertSame(0, MokaItemSale::count());
        $this->assertSame(0, DailyIncome::count());
    }

    public function test_ekstensi_selain_csv_dan_excel_ditolak(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageMokaItemSales::class)
            ->callAction('mokaImport', data: [
                'file' => UploadedFile::fake()->createWithContent('laporan.pdf', 'bukan spreadsheet'),
            ])
            ->assertHasActionErrors(['file']);
    }

    public function test_tanggal_di_luar_periode_membatalkan_seluruh_berkas(): void
    {
        // 15 Oktober tidak termasuk periode 1–8 November.
        Livewire::actingAs($this->admin())
            ->test(ManageMokaItemSales::class)
            ->callAction('mokaImport', data: [
                'file' => $this->upload($this->transactionFile([...$this->receipts(), ['2026-10-15', 'Cash', 999]]), self::MOKA_NAME_TRANSACTION),
            ])
            ->assertNotified(trans_choice('excel.result.failed', 1, ['count' => 1]));

        $this->assertSame(0, DailyIncome::count());
    }
}

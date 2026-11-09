<?php

namespace Tests\Feature\Moka;

use App\Filament\Admin\Resources\Zeytin\MokaItemSales\Pages\ManageMokaItemSales;
use App\Models\DailyIncome;
use App\Models\MokaItemSale;
use App\Models\Sale;
use App\Support\Moka\ItemSalesImport;
use App\Support\Zeytin\Channels;
use App\Support\Zeytin\DailyLedger;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Impor "Report Item Sales" dari Moka.
 *
 * Berkas Moka rekap per menu untuk satu periode, tanpa tanggal di dalamnya.
 * Yang dijaga: angkanya tidak ikut laporan penjualan (kalau ikut, satu bulan
 * terhitung dua kali dengan Pemasukan Harian), berkas yang salah satu
 * barisnya saja tidak cocok tidak menyimpan apa pun, dan mengimpor periode
 * yang sama lagi mengganti, bukan menambah.
 */
class ItemSalesImportTest extends TestCase
{
    protected string $path;

    protected const HEADER = [
        'Item Name', 'Item Variant Name', 'Category Name', 'SKU', 'Item Sold', 'Item Refunded',
        'Gross Sales', 'Discount', 'Refund', 'Net Sales', 'COGS', 'Gross Profit', 'Gross Margin',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->path = tempnam(sys_get_temp_dir(), 'moka').'.xlsx';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    /**
     * Baris seperti di berkas nyata: angka berupa pecahan desimal, potongan
     * bernilai minus, kolom COGS/Gross Profit/Margin ikut ada.
     *
     * @return array<int, mixed>
     */
    protected function item(string $name, ?string $category, float $sold, float $refunded, float $gross, float $discount, float $refund, ?float $net = null, ?string $variant = null): array
    {
        $net ??= $gross + $discount + $refund;

        return [$name, $variant, $category, null, $sold, $refunded, $gross, $discount, $refund, $net, 0.0, $net, '100%'];
    }

    /** @return array<int, array<int, mixed>> */
    protected function october(): array
    {
        return [
            $this->item('Ayran', 'Drink', 209, 3, 9_405_000, -67_500, -135_000),
            $this->item('Adana kebab beff', 'Kebab', 53, 0, 7_155_000, -60_750, 0),
            $this->item('Ice lemon tea', 'Uncategorized', 3, 0, 90_000, -1_500, 0),
            $this->item('ice lemon tea', 'Drink', 3, 0, 90_000, 0, 0),
        ];
    }

    /**
     * @param  array<int, array<int, mixed>>  $items
     * @param  array<int, mixed>|null  $total  baris Total di kaki; kosong seperti di berkas Moka
     */
    protected function workbook(array $items, ?array $total = ['Total'], array $header = self::HEADER): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getSheet(0);
        $sheet->setTitle('Sheet1');
        $sheet->fromArray([$header], null, 'A1');

        if ($items) {
            $sheet->fromArray($items, null, 'A2');
        }

        if ($total) {
            $sheet->fromArray([$total], null, 'A'.(count($items) + 2));
        }

        (new Xlsx($book))->save($this->path);
        $book->disconnectWorksheets();

        return $this->path;
    }

    protected function import(array $items, string $from = '2026-10-01', string $to = '2026-10-31', ?array $total = ['Total'], array $header = self::HEADER)
    {
        $this->workbook($items, $total, $header);

        return (new ItemSalesImport(Carbon::parse($from), Carbon::parse($to)))->import($this->path);
    }

    /* ----------------------------------------------------------- menyimpan */

    public function test_menu_dari_berkas_moka_tersimpan_dengan_potongan_positif(): void
    {
        $report = $this->import($this->october());

        $this->assertTrue($report->ok(), implode(' / ', $report->errors));
        $this->assertSame(4, $report->count('imported'));
        $this->assertSame(4, MokaItemSale::count());

        $ayran = MokaItemSale::where('item_name', 'Ayran')->firstOrFail();

        $this->assertSame('2026-10-01', $ayran->period_start->toDateString());
        $this->assertSame('2026-10-31', $ayran->period_end->toDateString());
        $this->assertSame('Drink', $ayran->category_name);
        $this->assertSame(209, $ayran->qty_sold);
        $this->assertSame(3, $ayran->qty_refunded);
        $this->assertSame(9_405_000, $ayran->gross_sales);
        // Moka menulis potongan dengan tanda minus; disimpan sebagai besarnya.
        $this->assertSame(67_500, $ayran->discount);
        $this->assertSame(135_000, $ayran->refund);
        $this->assertSame(9_202_500, $ayran->net_sales);
        $this->assertSame($ayran->gross_sales - $ayran->discount - $ayran->refund, $ayran->net_sales);
    }

    public function test_pengimpor_dicatat_dan_baris_kosong_serta_total_tidak_jadi_menu(): void
    {
        $user = $this->admin();
        $this->actingAs($user);

        // Berkas nyata punya belasan baris kosong sesudah baris Total.
        $items = [...$this->october(), array_fill(0, 13, null), array_fill(0, 13, null)];

        $report = $this->import($items);

        $this->assertTrue($report->ok(), implode(' / ', $report->errors));
        $this->assertSame(4, MokaItemSale::count());
        $this->assertSame([$user->id], MokaItemSale::pluck('user_id')->unique()->all());
        $this->assertSame(0, MokaItemSale::where('item_name', 'Total')->count());
    }

    /**
     * Di berkas nyata ada "Ice lemon tea" (Uncategorized) dan "ice lemon tea"
     * (Drink): dua menu Moka yang sah. Kategori ikut kunci, jadi keduanya
     * tersimpan — dan MySQL tidak menganggapnya sama karena huruf besarnya.
     */
    public function test_nama_sama_beda_huruf_dan_kategori_adalah_dua_menu(): void
    {
        $report = $this->import($this->october());

        $this->assertTrue($report->ok(), implode(' / ', $report->errors));

        $rows = MokaItemSale::whereRaw('LOWER(item_name) = ?', ['ice lemon tea'])->orderBy('id')->get();

        $this->assertSame(
            ['Ice lemon tea' => 'Uncategorized', 'ice lemon tea' => 'Drink'],
            $rows->pluck('category_name', 'item_name')->all(),
        );
    }

    public function test_menu_kembar_dalam_satu_berkas_ditolak(): void
    {
        $report = $this->import([
            $this->item('Ayran', 'Drink', 10, 0, 450_000, 0, 0),
            $this->item('AYRAN', 'drink', 5, 0, 225_000, 0, 0),
        ]);

        $this->assertFalse($report->ok());
        $this->assertStringContainsString('3', $report->errors[0]);
        $this->assertSame(0, MokaItemSale::count());
    }

    public function test_varian_yang_berbeda_adalah_menu_yang_berbeda(): void
    {
        $report = $this->import([
            $this->item('Juice', 'Drink', 10, 0, 450_000, 0, 0, null, 'Orange'),
            $this->item('Juice', 'Drink', 5, 0, 225_000, 0, 0, null, 'Apple'),
        ]);

        $this->assertTrue($report->ok(), implode(' / ', $report->errors));
        $this->assertSame(2, MokaItemSale::count());
    }

    /* ------------------------------------------------------- ganti & periode */

    public function test_mengimpor_periode_yang_sama_mengganti_bukan_menambah(): void
    {
        $this->import($this->october());

        $items = $this->october();
        $items[0] = $this->item('Ayran', 'Drink', 210, 3, 9_450_000, -67_500, -135_000);

        $report = $this->import($items);

        $this->assertTrue($report->ok(), implode(' / ', $report->errors));
        $this->assertSame(4, $report->count('imported'));
        $this->assertSame(4, $report->count('replaced'));
        $this->assertSame(4, MokaItemSale::count());
        $this->assertSame(210, MokaItemSale::where('item_name', 'Ayran')->value('qty_sold'));
    }

    public function test_periode_bersinggungan_ditolak_dan_tidak_menyentuh_data_lama(): void
    {
        $this->import($this->october());

        $report = $this->import($this->october(), '2026-10-15', '2026-11-05');

        $this->assertFalse($report->ok());
        $this->assertStringContainsString('01/10/2026', $report->errors[0]);
        $this->assertSame(4, MokaItemSale::count());
        $this->assertSame(['2026-10-01'], MokaItemSale::pluck('period_start')->map->toDateString()->unique()->values()->all());
    }

    public function test_periode_yang_bersebelahan_boleh(): void
    {
        $this->import($this->october());

        $report = $this->import($this->october(), '2026-11-01', '2026-11-08');

        $this->assertTrue($report->ok(), implode(' / ', $report->errors));
        $this->assertSame(8, MokaItemSale::count());
    }

    public function test_periode_terbalik_atau_di_masa_depan_ditolak(): void
    {
        $reversed = $this->import($this->october(), '2026-10-31', '2026-10-01');
        $this->assertFalse($reversed->ok());

        $future = $this->import($this->october(), '2026-10-01', now()->addDay()->toDateString());
        $this->assertFalse($future->ok());

        $this->assertSame(0, MokaItemSale::count());
    }

    public function test_periode_dibaca_dari_nama_berkas_hari_dulu(): void
    {
        [$from, $to] = ItemSalesImport::periodFromFilename('Report Item Sales - 01-10-2026 - 31-10-2026 - ZEYTIN.xlsx');

        $this->assertSame('2026-10-01', $from->toDateString());
        $this->assertSame('2026-10-31', $to->toDateString());

        // Hari dulu: 05-06 adalah 5 Juni, bukan 6 Mei.
        [$from] = ItemSalesImport::periodFromFilename('Report Item Sales - 05-06-2026 - 30-06-2026.xlsx');
        $this->assertSame('2026-06-05', $from->toDateString());
    }

    public function test_nama_berkas_yang_tidak_terbaca_dibiarkan_kosong_bukan_ditebak(): void
    {
        $this->assertNull(ItemSalesImport::periodFromFilename('laporan.xlsx'));
        $this->assertNull(ItemSalesImport::periodFromFilename('Report - 31-02-2026 - 28-02-2026.xlsx'));
        $this->assertNull(ItemSalesImport::periodFromFilename('Report - 31-10-2026 - 01-10-2026.xlsx'));
    }

    /* ------------------------------------------------------------ pemeriksaan */

    public function test_satu_baris_yang_net_salesnya_tidak_cocok_membatalkan_seluruh_berkas(): void
    {
        $items = $this->october();
        // Net seharusnya 7.094.250.
        $items[1] = $this->item('Adana kebab beff', 'Kebab', 53, 0, 7_155_000, -60_750, 0, 7_000_000);

        $report = $this->import($items);

        $this->assertFalse($report->ok());
        $this->assertCount(1, $report->errors);
        $this->assertStringContainsString('3', $report->errors[0]);
        $this->assertStringContainsString('Adana kebab beff', $report->errors[0]);
        $this->assertSame(0, MokaItemSale::count());
    }

    /** Moka menyimpan angka desimal; selisih satu rupiah dimaafkan, dua tidak. */
    public function test_selisih_satu_rupiah_dimaafkan_dua_rupiah_tidak(): void
    {
        $off1 = $this->import([$this->item('Juice', 'Drink', 3, 0, 100_000, 0, 0, 99_999)]);
        $this->assertTrue($off1->ok(), implode(' / ', $off1->errors));

        MokaItemSale::query()->delete();

        $off2 = $this->import([$this->item('Juice', 'Drink', 3, 0, 100_000, 0, 0, 99_998)]);
        $this->assertFalse($off2->ok());
    }

    public function test_potongan_yang_ditulis_positif_tetap_berarti_potongan(): void
    {
        $report = $this->import([$this->item('Ayran', 'Drink', 10, 1, 450_000, 5_000, 45_000, 400_000)]);

        $this->assertTrue($report->ok(), implode(' / ', $report->errors));
        $this->assertSame(5_000, MokaItemSale::firstOrFail()->discount);
    }

    public function test_baris_total_yang_terisi_harus_cocok_dengan_jumlah_menu(): void
    {
        $items = $this->october();
        $sum = 9_202_500 + 7_094_250 + 88_500 + 90_000;

        $ok = $this->import($items, total: ['Total', null, null, null, null, null, null, null, null, $sum]);
        $this->assertTrue($ok->ok(), implode(' / ', $ok->errors));

        MokaItemSale::query()->delete();

        // Berkas terpotong: totalnya lebih besar dari menu yang ada.
        $truncated = $this->import($items, total: ['Total', null, null, null, null, null, null, null, null, $sum + 5_000_000]);
        $this->assertFalse($truncated->ok());
        $this->assertSame(0, MokaItemSale::count());
    }

    public function test_judul_kolom_yang_tidak_dikenal_dilaporkan_bukan_ditebak(): void
    {
        $report = $this->import(
            [['Ayran', 'Drink', 10, 450_000]],
            header: ['Nama', 'Kelompok', 'Qty', 'Omzet'],
        );

        $this->assertFalse($report->ok());
        $this->assertStringContainsString('Item Name', $report->errors[0]);
        $this->assertStringContainsString('Net Sales', $report->errors[0]);
        $this->assertSame(0, MokaItemSale::count());
    }

    public function test_berkas_tanpa_baris_menu_ditolak(): void
    {
        $report = $this->import([]);

        $this->assertFalse($report->ok());
        $this->assertSame(0, MokaItemSale::count());
    }

    public function test_jumlah_terjual_pecahan_ditolak_bukan_dibulatkan_diam_diam(): void
    {
        $report = $this->import([$this->item('Lamb shank', 'Kebab', 1.5, 0, 450_000, 0, 0)]);

        $this->assertFalse($report->ok());
        $this->assertStringContainsString('Item Sold', $report->errors[0]);
        $this->assertSame(0, MokaItemSale::count());
    }

    public function test_angka_yang_bukan_angka_dilaporkan_per_baris_dan_kolom(): void
    {
        $items = $this->october();
        $items[2][6] = 'sembilan puluh ribu';

        $report = $this->import($items);

        $this->assertFalse($report->ok());
        $this->assertStringContainsString('Gross Sales', $report->errors[0]);
        $this->assertSame(0, MokaItemSale::count());
    }

    /* ---------------------------------------------- tidak ikut laporan penjualan */

    /**
     * Penjualan yang sama sudah tercatat di Pemasukan Harian. Kalau impor
     * Moka ikut Total penjualan, satu bulan terhitung dua kali — dan itu
     * jenis galat yang tidak terlihat sampai ada yang mencocokkan dengan
     * rekening.
     */
    public function test_impor_tidak_mengubah_total_penjualan_di_laporan_mana_pun(): void
    {
        DailyIncome::create([
            'date' => '2026-10-05',
            ...array_fill_keys(Channels::keys(), 0),
            'cash' => 3_000_000,
            'bni' => 2_000_000,
        ]);

        $before = DailyLedger::periodReport(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

        $report = $this->import($this->october());

        $after = DailyLedger::periodReport(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

        $this->assertTrue($report->ok(), implode(' / ', $report->errors));
        $this->assertSame(5_000_000, $before['total_sales']);
        $this->assertSame($before['total_sales'], $after['total_sales']);
        $this->assertSame($before['by_channel'], $after['by_channel']);
        $this->assertSame(0, Sale::count());
    }

    public function test_hasil_impor_membandingkan_dengan_pemasukan_harian_periode_yang_sama(): void
    {
        DailyIncome::create([
            'date' => '2026-10-05',
            ...array_fill_keys(Channels::keys(), 0),
            'cash' => 20_000_000,
        ]);

        $report = $this->import($this->october());

        // Net Moka: 9.202.500 + 7.094.250 + 88.500 + 90.000 = 16.475.250.
        $this->assertStringContainsString('Rp 16.475.250', $report->note);
        $this->assertStringContainsString('Rp 20.000.000', $report->note);
        $this->assertStringContainsString('Rp 3.524.750', $report->note);
    }

    public function test_tanpa_pemasukan_harian_hasil_impor_bilang_belum_ada_yang_dibandingkan(): void
    {
        $report = $this->import($this->october());

        $this->assertStringStartsWith(__('moka.note.no_books', ['moka' => 'Rp 16.475.250']), $report->note);
        $this->assertStringContainsString(__('moka.note.separate'), $report->note);
    }

    /* ------------------------------------------------------------- halaman */

    public function test_tombol_impor_di_halaman_menyimpan_dan_memberi_tahu(): void
    {
        $this->workbook($this->october());

        Livewire::actingAs($this->admin())
            ->test(ManageMokaItemSales::class)
            ->assertActionVisible('mokaImport')
            ->callAction('mokaImport', data: [
                'file' => UploadedFile::fake()->createWithContent(
                    'Report Item Sales - 01-10-2026 - 31-10-2026 - ZEYTIN.xlsx',
                    file_get_contents($this->path),
                ),
                'from' => '2026-10-01',
                'until' => '2026-10-31',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified(__('excel.result.done'));

        $this->assertSame(4, MokaItemSale::count());
    }

    public function test_berkas_yang_salah_dari_halaman_tidak_menyimpan_apa_pun(): void
    {
        $items = $this->october();
        $items[0][9] = 1;

        $this->workbook($items);

        Livewire::actingAs($this->admin())
            ->test(ManageMokaItemSales::class)
            ->callAction('mokaImport', data: [
                'file' => UploadedFile::fake()->createWithContent('moka.xlsx', file_get_contents($this->path)),
                'from' => '2026-10-01',
                'until' => '2026-10-31',
            ])
            ->assertNotified(trans_choice('excel.result.failed', 1, ['count' => 1]));

        $this->assertSame(0, MokaItemSale::count());
    }

    public function test_tabel_menampilkan_menu_hasil_impor(): void
    {
        $this->import($this->october());

        Livewire::actingAs($this->admin())
            ->test(ManageMokaItemSales::class)
            ->assertCanSeeTableRecords(MokaItemSale::all())
            ->searchTable('Ayran')
            ->assertCountTableRecords(1);
    }

    public function test_filter_periode_dan_kategori_menyaring_tabel(): void
    {
        $this->import($this->october());
        $this->import($this->october(), '2026-11-01', '2026-11-08');

        $page = Livewire::actingAs($this->admin())->test(ManageMokaItemSales::class);

        $page->assertCountTableRecords(8)
            ->filterTable('period', '2026-11-01|2026-11-08')
            ->assertCountTableRecords(4)
            ->assertCanSeeTableRecords(MokaItemSale::whereDate('period_start', '2026-11-01')->get())
            ->assertCanNotSeeTableRecords(MokaItemSale::whereDate('period_start', '2026-10-01')->get());

        $page->removeTableFilter('period')
            ->filterTable('category_name', 'Kebab')
            ->assertCountTableRecords(2);
    }

    /**
     * Berkas Moka tidak memuat tanggal, jadi kotak Dari/Sampai diisi dari
     * nama berkas begitu berkasnya dipilih — dan dibiarkan kosong kalau
     * namanya tidak terbaca.
     */
    public function test_memilih_berkas_mengisi_periode_dari_namanya(): void
    {
        $this->workbook($this->october());
        $content = file_get_contents($this->path);

        Livewire::actingAs($this->admin())
            ->test(ManageMokaItemSales::class)
            ->mountAction('mokaImport')
            ->set('mountedActions.0.data.file', UploadedFile::fake()->createWithContent(
                'Report Item Sales - 01-10-2026 - 31-10-2026 - ZEYTIN.xlsx',
                $content,
            ))
            // Yang dibandingkan state yang sampai ke aksi (tanggal saja);
            // state mentah kotak tanggal Filament membawa jam saat ini.
            ->assertActionDataSet(['from' => '2026-10-01', 'until' => '2026-10-31']);

        Livewire::actingAs($this->admin())
            ->test(ManageMokaItemSales::class)
            ->mountAction('mokaImport')
            ->set('mountedActions.0.data.file', UploadedFile::fake()->createWithContent('laporan.xlsx', $content))
            ->assertActionDataSet(['from' => null, 'until' => null]);
    }

    public function test_halaman_tidak_menyediakan_tambah_manual(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageMokaItemSales::class)
            ->assertActionDoesNotExist('create');
    }
}

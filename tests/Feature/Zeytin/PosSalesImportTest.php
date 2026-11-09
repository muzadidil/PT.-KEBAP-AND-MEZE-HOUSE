<?php

namespace Tests\Feature\Zeytin;

use App\Filament\Admin\Resources\Zeytin\DailyIncomes\Pages\ManageDailyIncomes;
use App\Models\DailyIncome;
use App\Support\Zeytin\PosSalesImporter;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Impor laporan kasir ("Report Item Details") ke Pemasukan Harian.
 * Yang dijaga: jumlahnya per tanggal dan cara bayar benar, tidak ada yang
 * ditimpa, dan satu kesalahan berarti tidak ada yang tersimpan.
 */
class PosSalesImportTest extends TestCase
{
    protected string $path;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->path = tempnam(sys_get_temp_dir(), 'pos').'.csv';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    /** @param  array<int, array{0: string, 1: string, 2: string|int}>  $rows  [tanggal, cara bayar, net sales] */
    protected function csv(array $rows, string $header = 'Outlet,Receipt Number,Date,Time,Category,Items,Quantity,Gross Sales,Net Sales,Gratuity,Tax,Payment Method'): string
    {
        $lines = [$header];

        foreach ($rows as $i => [$date, $method, $net]) {
            $lines[] = "ZEYTIN,R{$i},{$date},12:00:00,Kebab,Item,1,{$net},{$net},5000.0,10500.0,{$method}";
        }

        file_put_contents($this->path, implode("\n", $lines)."\n");

        return $this->path;
    }

    public function test_net_sales_dijumlahkan_per_tanggal_dan_cara_bayar(): void
    {
        $report = (new PosSalesImporter)->import($this->csv([
            ['30-09-2026', 'Cash', '155000.0'],
            ['30-09-2026', 'Cash', '195000.0'],
            ['30-09-2026', 'GrabFood', '150000.0'],
            ['30-09-2026', 'BNI', '60000.0'],
            ['29-09-2026', 'BNI', '100000.0'],
        ]));

        $this->assertTrue($report->ok());
        $this->assertSame(2, $report->count('created'));

        $day = DailyIncome::whereDate('date', '2026-09-30')->first();
        $this->assertSame(350_000, $day->cash);
        $this->assertSame(150_000, $day->grab_food);
        $this->assertSame(60_000, $day->bni);
        // Gratuity dan Tax tidak ikut: hanya Net Sales.
        $this->assertSame(560_000, $day->totalSales());
        $this->assertSame(100_000, DailyIncome::whereDate('date', '2026-09-29')->first()->bni);
    }

    public function test_tanggal_yang_sudah_terisi_dilewati_dan_tidak_ditimpa(): void
    {
        DailyIncome::create(['date' => '2026-09-30', 'cash' => 999]);

        $report = (new PosSalesImporter)->import($this->csv([
            ['30-09-2026', 'Cash', '155000.0'],
            ['01-10-2026', 'Cash', '50000.0'],
        ]));

        $this->assertTrue($report->ok());
        $this->assertSame(1, $report->count('created'));
        $this->assertSame(1, $report->count('skipped'));
        $this->assertSame(999, DailyIncome::whereDate('date', '2026-09-30')->first()->cash);
        $this->assertStringContainsString('30', (string) $report->note);
    }

    public function test_mengimpor_berkas_yang_sama_dua_kali_tidak_menggandakan(): void
    {
        $path = $this->csv([['30-09-2026', 'Cash', '155000.0']]);

        (new PosSalesImporter)->import($path);
        (new PosSalesImporter)->import($path);

        $this->assertSame(1, DailyIncome::count());
        $this->assertSame(155_000, DailyIncome::first()->cash);
    }

    public function test_cara_bayar_tidak_dikenal_membatalkan_semuanya(): void
    {
        $report = (new PosSalesImporter)->import($this->csv([
            ['30-09-2026', 'Cash', '155000.0'],
            ['30-09-2026', 'Shopee Pay', '50000.0'],
        ]));

        $this->assertFalse($report->ok());
        $this->assertStringContainsString('Shopee Pay', $report->errors[0]);
        $this->assertSame(0, DailyIncome::count());
    }

    public function test_tanggal_atau_angka_salah_membatalkan_semuanya(): void
    {
        $report = (new PosSalesImporter)->import($this->csv([
            ['30-09-2026', 'Cash', '155000.0'],
            ['31-02-2026', 'Cash', '50000.0'],
            ['01-10-2026', 'Cash', 'abc'],
        ]));

        $this->assertCount(2, $report->errors);
        $this->assertStringContainsString('3', $report->errors[0]);
        $this->assertSame(0, DailyIncome::count());
    }

    public function test_kolom_wajib_harus_ada(): void
    {
        $report = (new PosSalesImporter)->import($this->csv([['30-09-2026', 'Cash', '1000']], 'Outlet,Receipt Number,Date,Time,Category,Items,Quantity,Gross Sales,Discounts,Gratuity,Tax,Payment Method'));

        $this->assertFalse($report->ok());
        $this->assertStringContainsString('Net Sales', $report->errors[0]);
    }

    /* ------------------------------------------------- periode dari nama berkas */

    /** Periode seperti dari nama berkas "… - 01-11-2026 - 15-11-2026 - …". */
    protected function period(string $from, string $to): array
    {
        return [Carbon::parse($from), Carbon::parse($to)];
    }

    /**
     * Berkas "Report Transaction" seperti dari Moka. Tanggal yang hari-bulannya
     * bisa dibalik (hari <= 12) disimpan Excel sebagai tanggal asli yang
     * TERTUKAR — 1 November jadi 11 Januari — dan yang tidak bisa dibalik
     * tetap teks hari-bulan. Satu berkas memuat keduanya.
     *
     * @param  array<int, array{0: string, 1: string, 2: int}>  $receipts  [tanggal sebenarnya, cara bayar, net sales]
     */
    protected function transactionXlsx(array $receipts, bool $swapped = true): string
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

        $path = tempnam(sys_get_temp_dir(), 'tx').'.xlsx';
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    public function test_tanggal_tertukar_dikembalikan_ke_periode_nama_berkas(): void
    {
        $path = $this->transactionXlsx([
            ['2026-11-01', 'Cash', 100_000],
            ['2026-11-01', 'BNI', 200_000],
            ['2026-11-02', 'GrabFood', 50_000],
            ['2026-11-05', 'BNI', 70_000],
            ['2026-11-05', 'BNI', 30_000],
            // Hari > 12 tidak bisa tertukar: tetap teks dan terbaca benar.
            ['2026-11-13', 'Cash', 999],
        ]);

        $report = (new PosSalesImporter($this->period('2026-11-01', '2026-11-15')))->import($path);

        $this->assertTrue($report->ok(), implode(' / ', $report->errors));
        $this->assertSame(4, $report->count('created'));
        $this->assertSame(
            ['2026-11-01', '2026-11-02', '2026-11-05', '2026-11-13'],
            DailyIncome::orderBy('date')->get()->map(fn ($row) => $row->date->toDateString())->all(),
        );
        $this->assertSame(0, DailyIncome::whereDate('date', '<', '2026-11-01')->count(), 'penjualan mendarat di Januari–Juli');

        $first = DailyIncome::whereDate('date', '2026-11-01')->first();
        $this->assertSame(100_000, $first->cash);
        $this->assertSame(200_000, $first->bni);
        $this->assertSame(50_000, DailyIncome::whereDate('date', '2026-11-02')->first()->grab_food);
        $this->assertSame(100_000, DailyIncome::whereDate('date', '2026-11-05')->first()->bni);
        $this->assertSame(999, DailyIncome::whereDate('date', '2026-11-13')->first()->cash);

        $this->assertStringContainsString('01/11/2026 – 15/11/2026', $report->note);
        $this->assertStringContainsString(__('excel.pos.swapped_note', ['count' => 5]), $report->note);

        @unlink($path);
    }

    public function test_tanggal_asli_excel_yang_sudah_benar_tidak_ditukar(): void
    {
        $path = $this->transactionXlsx([['2026-11-05', 'Cash', 1_000]], swapped: false);

        $report = (new PosSalesImporter($this->period('2026-11-01', '2026-11-08')))->import($path);

        $this->assertTrue($report->ok(), implode(' / ', $report->errors));
        $this->assertSame('2026-11-05', DailyIncome::firstOrFail()->date->toDateString());
        $this->assertStringNotContainsString(__('excel.pos.swapped_note', ['count' => 1]), $report->note);

        @unlink($path);
    }

    public function test_tanggal_ketikan_teks_dibaca_hari_dulu_dan_tidak_pernah_ditukar(): void
    {
        // "05-11-2026" adalah 5 November; tafsiran 11 Mei tidak ikut dipertimbangkan.
        $report = (new PosSalesImporter($this->period('2026-05-01', '2026-11-30')))->import($this->csv([
            ['05-11-2026', 'Cash', '1000.0'],
        ]));

        $this->assertTrue($report->ok(), implode(' / ', $report->errors));
        $this->assertSame('2026-11-05', DailyIncome::firstOrFail()->date->toDateString());
    }

    public function test_tanggal_di_luar_periode_menolak_seluruh_berkas(): void
    {
        $path = $this->transactionXlsx([
            ['2026-11-01', 'Cash', 100_000],
            // 20/10 (teks) dan 5/10 (tertukar jadi 10 Mei): keduanya bukan di 1–8 November.
            ['2026-10-20', 'Cash', 1],
            ['2026-10-05', 'Cash', 2],
        ]);

        $report = (new PosSalesImporter($this->period('2026-11-01', '2026-11-08')))->import($path);

        $this->assertFalse($report->ok());
        $this->assertCount(2, $report->errors);
        $this->assertStringContainsString('01/11/2026 – 08/11/2026', $report->errors[0]);
        $this->assertSame(0, DailyIncome::count());

        @unlink($path);
    }

    public function test_tanggal_yang_bisa_dibaca_dua_cara_di_dalam_periode_ditolak_bukan_ditebak(): void
    {
        // 11 Oktober tersimpan sebagai 10 November; periode Oktober–November memuat kedua tafsiran.
        $path = $this->transactionXlsx([['2026-10-11', 'Cash', 1_000]]);

        $report = (new PosSalesImporter($this->period('2026-10-01', '2026-11-30')))->import($path);

        $this->assertFalse($report->ok());
        $this->assertSame(0, DailyIncome::count());

        @unlink($path);
    }

    public function test_tombol_menerima_xlsx_moka_dan_mengisi_dari_periode_nama_berkas(): void
    {
        $path = $this->transactionXlsx([
            ['2026-11-01', 'Cash', 100_000],
            ['2026-11-01', 'BNI', 200_000],
            ['2026-11-02', 'GrabFood', 50_000],
        ]);
        $moka = $this->mokaOrdered($path);

        Livewire::actingAs($this->admin())
            ->test(ManageDailyIncomes::class)
            ->callAction('posSalesImport', data: [
                // Nama bawaan Moka; jenis yang dilaporkan dipaksa seperti libmagic di
                // server (isi zip-nya berurutan ala Moka, jadi octet-stream).
                'file' => UploadedFile::fake()->createWithContent(
                    'Report Transaction - 01-11-2026 - 08-11-2026 - All_Outlet - 1086892 - 6af13f4a.xlsx',
                    file_get_contents($moka),
                )->mimeType('application/octet-stream'),
            ])
            ->assertHasNoActionErrors()
            ->assertNotified(__('excel.result.done'));

        $this->assertSame(['2026-11-01', '2026-11-02'], DailyIncome::orderBy('date')->get()->map(fn ($row) => $row->date->toDateString())->all());
        $this->assertSame(0, DailyIncome::whereDate('date', '<', '2026-11-01')->count());

        @unlink($path);
        @unlink($moka);
    }

    public function test_tombol_menolak_ekstensi_selain_csv_dan_excel(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageDailyIncomes::class)
            ->callAction('posSalesImport', data: [
                'file' => UploadedFile::fake()->createWithContent('laporan.pdf', 'bukan spreadsheet'),
            ])
            ->assertHasActionErrors(['file']);

        $this->assertSame(0, DailyIncome::count());
    }

    /** Salinan xlsx dengan `_rels/.rels` di entri pertama, urutan yang dipakai Moka. */
    protected function mokaOrdered(string $source): string
    {
        $target = tempnam(sys_get_temp_dir(), 'moka').'.xlsx';

        $in = new \ZipArchive;
        $in->open($source);
        $out = new \ZipArchive;
        $out->open($target, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $out->addFromString('_rels/.rels', $in->getFromName('_rels/.rels'));

        for ($i = 0; $i < $in->numFiles; $i++) {
            if (($name = $in->getNameIndex($i)) !== '_rels/.rels') {
                $out->addFromString($name, $in->getFromIndex($i));
            }
        }

        $in->close();
        $out->close();

        return $target;
    }

    public function test_tombol_ada_di_pemasukan_harian_untuk_admin(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageDailyIncomes::class)
            ->assertActionExists('posSalesImport');
    }
}

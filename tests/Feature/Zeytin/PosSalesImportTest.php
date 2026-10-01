<?php

namespace Tests\Feature\Zeytin;

use App\Filament\Admin\Resources\Zeytin\DailyIncomes\Pages\ManageDailyIncomes;
use App\Models\DailyIncome;
use App\Support\Zeytin\PosSalesImporter;
use Filament\Facades\Filament;
use Livewire\Livewire;
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

    public function test_tombol_ada_di_pemasukan_harian_untuk_admin(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageDailyIncomes::class)
            ->assertActionExists('posSalesImport');
    }
}

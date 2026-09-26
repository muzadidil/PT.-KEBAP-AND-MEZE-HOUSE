<?php

namespace Tests\Feature\Reports;

use App\Filament\Admin\Pages\Reports\CashExpenses;
use App\Filament\Admin\Pages\Reports\OnlineTransfers;
use App\Models\Purchase;
use App\Models\SupplierTransfer;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pencarian dan pintasan rentang di laporan berbentuk daftar.
 *
 * Yang dijaga: kotak pencarian menerima tanggal — "15/08/2026" menyaring ke
 * hari itu dan "08/2026" ke satu bulan — dan pintasan periode mengisi isian
 * tanggal yang tampil di atas tabel, bukan menyaring dengan caranya sendiri.
 */
class ExpenseFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Carbon::setTestNow('2026-09-26 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function purchase(string $date, string $item, ?string $vendor = null): Purchase
    {
        return Purchase::create([
            'date' => $date, 'item' => $item, 'qty' => 1, 'unit' => 'kg',
            'price' => 100_000, 'vendor' => $vendor,
        ]);
    }

    /* ---------------------------------------------------------- pencarian */

    public function test_mencari_tanggal_menyaring_ke_hari_itu(): void
    {
        $wanted = $this->purchase('2026-08-15', 'Ayam');
        $other = $this->purchase('2026-08-16', 'Cabai');

        Livewire::actingAs($this->admin())
            ->test(CashExpenses::class)
            ->set('tableSearch', '15/08/2026')
            ->assertCanSeeTableRecords([$wanted])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_mencari_bulan_menyaring_sebulan_penuh(): void
    {
        $august = $this->purchase('2026-08-15', 'Ayam');
        $september = $this->purchase('2026-09-02', 'Cabai');

        Livewire::actingAs($this->admin())
            ->test(CashExpenses::class)
            ->set('tableSearch', '08/2026')
            ->assertCanSeeTableRecords([$august])
            ->assertCanNotSeeTableRecords([$september]);
    }

    /** Yang dicari orang paling sering nama barang; itu tidak boleh berubah. */
    public function test_mencari_barang_dan_pemasok_tetap_jalan(): void
    {
        $chicken = $this->purchase('2026-08-15', 'Ayam', 'Pak Budi');
        $chili = $this->purchase('2026-08-16', 'Cabai', 'Bu Sari');

        $page = Livewire::actingAs($this->admin())->test(CashExpenses::class);

        $page->set('tableSearch', 'Ayam')
            ->assertCanSeeTableRecords([$chicken])
            ->assertCanNotSeeTableRecords([$chili]);

        $page->set('tableSearch', 'Bu Sari')
            ->assertCanSeeTableRecords([$chili])
            ->assertCanNotSeeTableRecords([$chicken]);
    }

    /** Kata yang bukan tanggal dan bukan isi baris mana pun tidak mencocoki semuanya. */
    public function test_pencarian_tanpa_hasil_tidak_menampilkan_semua(): void
    {
        $this->purchase('2026-08-15', 'Ayam');

        Livewire::actingAs($this->admin())
            ->test(CashExpenses::class)
            ->set('tableSearch', 'tidak ada barang ini')
            ->assertCountTableRecords(0);
    }

    public function test_pencarian_tanggal_juga_dipakai_pdf(): void
    {
        $this->purchase('2026-08-15', 'Ayam');
        $this->purchase('2026-08-16', 'Cabai');

        $page = app(CashExpenses::class);

        $this->assertSame(1, $page->filteredQuery(['search' => '15/08/2026'])->count());
        $this->assertSame(2, $page->filteredQuery(['search' => '08/2026'])->count());
    }

    public function test_tanggal_dibaca_hari_dulu(): void
    {
        // 5/8 berarti 5 Agustus, bukan 8 Mei — sama seperti yang tampil di kolom.
        $this->assertSame('2026-08-05', CashExpenses::searchDate('5/8/2026')?->toDateString());
        $this->assertSame('2026-08-05', CashExpenses::searchDate('05/08/2026')?->toDateString());
        $this->assertSame('2026-08-05', CashExpenses::searchDate('2026-08-05')?->toDateString());
        $this->assertNull(CashExpenses::searchDate('Ayam'));

        $this->assertSame('2026-08-01', CashExpenses::searchMonth('08/2026')?->toDateString());
        $this->assertNull(CashExpenses::searchMonth('Ayam'));
    }

    /* --------------------------------------------------- pintasan periode */

    public function test_pintasan_periode_mengisi_isian_tanggal(): void
    {
        $page = Livewire::actingAs($this->admin())->test(CashExpenses::class);

        $page->call('applyPeriod', 'today');
        $this->assertSame(
            ['from' => '2026-09-26', 'until' => '2026-09-26'],
            $page->get('tableFilters')['period'],
        );

        $page->call('applyPeriod', 'last_month');
        $this->assertSame(
            ['from' => '2026-08-01', 'until' => '2026-08-31'],
            $page->get('tableFilters')['period'],
        );

        // "Semua" mengosongkan rentangnya, bukan memasang rentang selebar-lebarnya.
        $page->call('applyPeriod', 'all');
        $this->assertSame(['from' => null, 'until' => null], $page->get('tableFilters')['period']);
    }

    public function test_pintasan_periode_benar_benar_menyaring_tabel(): void
    {
        $today = $this->purchase('2026-09-26', 'Ayam hari ini');
        $lastMonth = $this->purchase('2026-08-15', 'Ayam bulan lalu');

        $page = Livewire::actingAs($this->admin())->test(CashExpenses::class);

        $page->call('applyPeriod', 'today')
            ->assertCanSeeTableRecords([$today])
            ->assertCanNotSeeTableRecords([$lastMonth]);

        $page->call('applyPeriod', 'last_month')
            ->assertCanSeeTableRecords([$lastMonth])
            ->assertCanNotSeeTableRecords([$today]);
    }

    /** Ketiganya memakai induk yang sama; Transfer Online ikut diperiksa. */
    public function test_transfer_online_punya_pencarian_dan_pintasan_yang_sama(): void
    {
        $august = SupplierTransfer::create([
            'date' => '2026-08-15', 'item' => 'Daging', 'qty' => 1, 'price' => 1_000_000, 'total' => 1_000_000,
        ]);
        $september = SupplierTransfer::create([
            'date' => '2026-09-02', 'item' => 'Keju', 'qty' => 1, 'price' => 500_000, 'total' => 500_000,
        ]);

        Livewire::actingAs($this->admin())
            ->test(OnlineTransfers::class)
            ->set('tableSearch', '15/08/2026')
            ->assertCanSeeTableRecords([$august])
            ->assertCanNotSeeTableRecords([$september])
            ->set('tableSearch', '')
            ->call('applyPeriod', 'this_month')
            ->assertCanSeeTableRecords([$september])
            ->assertCanNotSeeTableRecords([$august]);
    }

    /** Isian tanggal tampil di atas tabel, tidak tersembunyi di balik ikon corong. */
    public function test_isian_tanggal_tampil_di_atas_tabel(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CashExpenses::class)
            ->assertSee(__('report.from'))
            ->assertSee(__('report.to'))
            ->assertSee(__('report.preset.today'));
    }
}

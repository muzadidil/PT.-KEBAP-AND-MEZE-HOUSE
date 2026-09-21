<?php

namespace Tests\Feature\Zeytin;

use App\Filament\Admin\Pages\Reports\CashExpenses;
use App\Filament\Admin\Pages\Reports\DailySales;
use App\Filament\Admin\Pages\Reports\MonthlySales;
use App\Filament\Admin\Pages\Reports\OnlineTransfers;
use App\Filament\Admin\Pages\Reports\Salary;
use App\Filament\Admin\Pages\Reports\WeeklySales;
use App\Filament\Admin\Pages\Reports\YearlySales;
use App\Filament\Admin\Pages\Zeytin\MonthlyLedger;
use App\Models\DailyIncome;
use App\Models\Payroll;
use App\Models\Purchase;
use App\Models\SupplierTransfer;
use App\Support\Money;
use App\Support\Zeytin\Channels;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Laporan di menu Laporan membaca pembukuan yang sama dengan Buku Besar.
 *
 * Keluhan yang memicunya: Penjualan Tahunan menampilkan 95 juta, Buku Besar
 * 280 juta, untuk Agustus yang sama — karena laporan membaca transaksi kasir
 * dan Buku Besar membaca Pemasukan Harian. Tes ini menjaga supaya dua
 * halaman tidak pernah lagi menunjukkan dua angka untuk hal yang sama.
 */
class SalesReportPagesTest extends TestCase
{
    protected const RANGE = ['from' => '2026-08-01', 'to' => '2026-08-31'];

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    protected function seedAugust(): void
    {
        foreach ([['2026-08-03', 5_000_000, 1_000_000], ['2026-08-15', 3_000_000, 2_500_000], ['2026-08-31', 800_000, 0]] as [$date, $cash, $bni]) {
            DailyIncome::create(['date' => $date, ...array_fill_keys(Channels::keys(), 0), 'cash' => $cash, 'bni' => $bni]);
        }

        Purchase::create(['date' => '2026-08-03', 'item' => 'Ayam', 'qty' => 10, 'price' => 35_000]);
        SupplierTransfer::create(['date' => '2026-08-15', 'item' => 'Daging', 'qty' => 1, 'price' => 2_000_000, 'status' => 'ASLAN PAID']);
        Payroll::create(['month' => '2026-08-01', 'name' => 'Sinta', 'basic' => 3_000_000, 'bpjs' => 200_000]);
        Payroll::create(['month' => '2026-08-01', 'name' => 'Ayu', 'basic' => 2_500_000, 'bpjs' => 100_000]);
    }

    public function test_keempat_laporan_penjualan_sama_dengan_buku_besar(): void
    {
        $this->seedAugust();

        $ledger = Livewire::actingAs($this->admin())
            ->test(MonthlyLedger::class, self::RANGE)
            ->instance()
            ->report;

        $this->assertSame(12_300_000, $ledger['total_sales']);

        foreach ([DailySales::class, WeeklySales::class, MonthlySales::class, YearlySales::class] as $page) {
            $instance = Livewire::actingAs($this->admin())->test($page, self::RANGE)->instance();

            $this->assertSame($ledger['total_sales'], $instance->report['total_sales'], class_basename($page));
            $this->assertSame($ledger['total_expenses'], $instance->report['total_expenses'], class_basename($page));
            $this->assertSame($ledger['net_profit'], $instance->report['net_profit'], class_basename($page));

            // Baris per periode berjumlah sama dengan totalnya.
            $this->assertSame($ledger['total_sales'], $instance->rows->sum('total_sales'), class_basename($page));
        }
    }

    public function test_halaman_menyebut_sumber_angkanya(): void
    {
        $this->seedAugust();

        Livewire::actingAs($this->admin())
            ->test(YearlySales::class, self::RANGE)
            ->assertSee(__('report.source_bookkeeping'))
            ->assertSee(Money::format(12_300_000));
    }

    /**
     * Penjualan Tahunan membuka lima tahun. Dibagi seluruh hari, 280 juta
     * tampil sebagai "Rp 162.888 per hari"; yang dibagi harus hari yang
     * benar-benar tercatat.
     */
    public function test_rata_rata_dihitung_dari_hari_yang_tercatat(): void
    {
        $this->seedAugust();

        $page = Livewire::actingAs($this->admin())
            ->test(YearlySales::class, ['from' => '2022-01-01', 'to' => '2026-09-21'])
            ->instance();

        $this->assertSame(3, $page->report['recorded_days']);
        $this->assertSame(4_100_000, $page->averagePerDay());
    }

    public function test_laporan_pengeluaran_sama_dengan_kartu_buku_besar(): void
    {
        $this->seedAugust();

        $filter = ['from' => '2026-08-01', 'until' => '2026-08-31'];

        Livewire::actingAs($this->admin())
            ->test(CashExpenses::class)
            ->filterTable('period', $filter)
            ->assertSee(__('report.source.cash_expenses'))
            ->assertSee(Money::format(350_000))
            ->assertSee('Ayam');

        Livewire::actingAs($this->admin())
            ->test(OnlineTransfers::class)
            ->filterTable('period', $filter)
            ->assertSee(Money::format(2_000_000))
            ->assertSee('ASLAN PAID');
    }

    /** Gaji per orang hanya untuk Super Admin; Admin melihat totalnya. */
    public function test_laporan_gaji_hanya_menampilkan_total_per_bulan(): void
    {
        $this->seedAugust();

        Livewire::actingAs($this->admin())
            ->test(Salary::class, self::RANGE)
            ->assertSee(Money::format(5_200_000))
            ->assertDontSee('Sinta')
            ->assertDontSee('Ayu');
    }
}

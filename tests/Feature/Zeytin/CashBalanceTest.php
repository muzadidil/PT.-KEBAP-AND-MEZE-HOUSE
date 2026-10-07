<?php

namespace Tests\Feature\Zeytin;

use App\Filament\Admin\Pages\Zeytin\CashBalancePage;
use App\Models\DailyIncome;
use App\Models\Purchase;
use App\Support\Zeytin\CashBalance;
use App\Support\Zeytin\Channels;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** Sisa uang cash di kasir: saldo awal + penjualan cash − belanja tunai. */
class CashBalanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Carbon::setTestNow('2026-10-07 10:00:00');

        $income = fn (string $date, array $channels) => DailyIncome::create(['date' => $date, ...array_fill_keys(Channels::keys(), 0), ...$channels]);

        $income('2026-08-31', ['cash' => 999_999]);                 // sebelum tanggal mulai: tidak dihitung
        $income('2026-09-29', ['cash' => 100_000, 'bni' => 700_000, 'petty_cash' => 55_000]);
        $income('2026-09-30', ['cash' => 300_000, 'grab_food' => 90_000]);
        Purchase::create(['date' => '2026-09-29', 'item' => 'Ayam', 'qty' => 1, 'unit' => 'kg', 'price' => 50_000]);
        Purchase::create(['date' => '2026-08-31', 'item' => 'Lama', 'qty' => 1, 'unit' => 'kg', 'price' => 11_111]);

        CashBalance::setOpening(1_000_000, '2026-09-01');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_saldo_hanya_dari_cash_dan_belanja_tunai_sejak_tanggal_mulai(): void
    {
        $report = CashBalance::report(Carbon::parse('2026-09-01'), Carbon::parse('2026-10-05'));

        // 1.000.000 + (100.000 + 300.000) − 50.000. BNI, Grab, petty cash dan
        // hari sebelum tanggal mulai tidak ikut.
        $this->assertSame(1_350_000, $report['end_balance']);
        $this->assertSame(400_000, $report['cash_in']);
        $this->assertSame(50_000, $report['cash_out']);
    }

    public function test_rentang_yang_dimulai_kemudian_membawa_saldo_sebelumnya(): void
    {
        $report = CashBalance::report(Carbon::parse('2026-09-30'), Carbon::parse('2026-10-05'));

        $this->assertSame(1_050_000, $report['start_balance']);   // 1.000.000 + 100.000 − 50.000
        $this->assertSame(1_350_000, $report['end_balance']);

        $day = $report['rows']->first(fn ($row) => $row['date']->toDateString() === '2026-09-30');
        $this->assertSame(1_050_000, $day['opening']);
        $this->assertSame(300_000, $day['cash_in']);
        $this->assertSame(1_350_000, $day['balance']);
    }

    public function test_hari_sebelum_tanggal_mulai_tidak_dihitung(): void
    {
        $report = CashBalance::report(Carbon::parse('2026-08-30'), Carbon::parse('2026-09-02'));

        $before = $report['rows']->first(fn ($row) => $row['date']->toDateString() === '2026-08-31');
        $this->assertFalse($before['counted']);
        $this->assertSame(1_000_000, $report['end_balance']);
    }

    public function test_tanpa_tanggal_mulai_semua_catatan_dihitung_dari_nol(): void
    {
        CashBalance::setOpening(0, null);

        $report = CashBalance::report(Carbon::parse('2026-08-30'), Carbon::parse('2026-10-05'));

        $this->assertSame(999_999 + 100_000 + 300_000 - 11_111 - 50_000, $report['end_balance']);
    }

    public function test_halaman_menampilkan_sisa_dan_menyimpan_saldo_awal(): void
    {
        $page = Livewire::actingAs($this->admin())
            ->test(CashBalancePage::class, ['from' => '2026-09-01', 'to' => '2026-10-05'])
            ->assertSee('Rp 1.350.000');

        $page->callAction('opening', ['amount' => 2_000_000, 'date' => '2026-09-01'])
            ->assertHasNoActionErrors()
            ->assertSee('Rp 2.350.000');
    }
}

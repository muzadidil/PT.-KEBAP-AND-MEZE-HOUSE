<?php

namespace Tests\Feature\Zeytin;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Filament\Admin\Pages\Zeytin\BankBalancePage;
use App\Models\DailyIncome;
use App\Models\Expense;
use App\Models\SupplierTransfer;
use App\Support\Zeytin\BankBalance;
use App\Support\Zeytin\Channels;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** Perkiraan sisa uang di rekening: saldo awal + penjualan non-tunai − pembayaran lewat rekening. */
class BankBalanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Carbon::setTestNow('2026-10-07 10:00:00');

        $income = fn (string $date, array $channels) => DailyIncome::create(['date' => $date, ...array_fill_keys(Channels::keys(), 0), ...$channels]);

        $income('2026-08-31', ['bni' => 999_999]);                                   // sebelum tanggal mulai
        $income('2026-09-29', ['cash' => 100_000, 'bni' => 700_000, 'grab_food' => 90_000, 'petty_cash' => 55_000]);
        $income('2026-09-30', ['go_food' => 30_000, 'go_pay' => 10_000, 'cash' => 400_000]);

        SupplierTransfer::create(['date' => '2026-09-29', 'vendor' => 'Serdar', 'item' => 'Yogurt', 'qty' => 1, 'price' => 300_000]);

        BankBalance::setOpening(5_000_000, '2026-09-01');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function expense(array $attributes): Expense
    {
        return Expense::create([
            'spent_on' => '2026-09-30', 'category' => ExpenseCategory::Operational, 'description' => 'x',
            'method' => PaymentMethod::Transfer, 'amount' => 20_000, 'is_paid' => true, ...$attributes,
        ]);
    }

    public function test_hanya_penjualan_non_tunai_masuk_dan_transfer_pemasok_keluar(): void
    {
        $report = BankBalance::report(Carbon::parse('2026-09-01'), Carbon::parse('2026-10-05'));

        // Masuk: bni 700.000 + grab 90.000 + go food 30.000 + go pay 10.000.
        // Cash, petty cash, dan hari sebelum tanggal mulai tidak ikut.
        $this->assertSame(830_000, $report['money_in']);
        $this->assertSame(300_000, $report['money_out']);
        $this->assertSame(5_000_000 + 830_000 - 300_000, $report['end_balance']);
    }

    public function test_pengeluaran_lewat_rekening_mengurangi_yang_lain_tidak(): void
    {
        [$aslan] = $this->owners();

        $this->expense(['amount' => 20_000, 'category' => ExpenseCategory::Salary]);        // gaji lewat bank: ikut
        $this->expense(['amount' => 10_000, 'method' => PaymentMethod::Cash]);              // tunai: tidak
        $this->expense(['amount' => 70_000, 'is_paid' => false]);                           // belum dibayar: tidak
        $this->expense(['amount' => 80_000, 'paid_by_owner_id' => $aslan->id]);             // talangan pemilik: tidak

        $report = BankBalance::report(Carbon::parse('2026-09-01'), Carbon::parse('2026-10-05'));

        $this->assertSame(320_000, $report['money_out']);
        $this->assertSame(5_000_000 + 830_000 - 320_000, $report['end_balance']);
    }

    public function test_rentang_yang_dimulai_kemudian_membawa_saldo_sebelumnya(): void
    {
        $report = BankBalance::report(Carbon::parse('2026-09-30'), Carbon::parse('2026-10-05'));

        // 5.000.000 + (700.000 + 90.000) − 300.000 sebelum 30 September.
        $this->assertSame(5_490_000, $report['start_balance']);
        $this->assertSame(5_530_000, $report['end_balance']);
    }

    public function test_halaman_menampilkan_saldo_dan_menyimpan_saldo_awal(): void
    {
        Livewire::actingAs($this->admin())
            ->test(BankBalancePage::class, ['from' => '2026-09-01', 'to' => '2026-10-05'])
            ->assertSee('Rp 5.530.000')
            ->callAction('opening', ['amount' => 6_000_000, 'date' => '2026-09-01'])
            ->assertHasNoActionErrors()
            ->assertSee('Rp 6.530.000');
    }
}

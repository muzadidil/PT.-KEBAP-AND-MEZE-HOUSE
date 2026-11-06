<?php

namespace Tests\Feature\Zeytin;

use App\Filament\Admin\Resources\Zeytin\Payrolls\Pages\ManagePayrolls;
use App\Models\Payroll;
use App\Support\Zeytin\BankBalance;
use App\Support\Zeytin\CashBalance;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Gaji yang dicatat sudah dibayar mengurangi Saldo Bank (atau Sisa Cash) pada
 * tanggal bayarnya. Yang belum dicatat tidak mengurangi apa pun.
 */
class PayrollPaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Carbon::setTestNow('2026-11-06 10:00:00');

        BankBalance::setOpening(100_000_000, '2026-10-08');
        CashBalance::setOpening(1_000_000, '2026-10-08');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function payroll(string $name, int $basic, string $month = '2026-10-01', array $extra = []): Payroll
    {
        return Payroll::create(['month' => $month, 'name' => $name, 'basic' => $basic, 'bpjs' => 0, ...$extra]);
    }

    protected function bankOut(string $from = '2026-10-08', string $to = '2026-11-06'): int
    {
        return BankBalance::report(Carbon::parse($from), Carbon::parse($to))['money_out'];
    }

    public function test_gaji_belum_dicatat_dibayar_tidak_mengurangi_saldo(): void
    {
        $this->payroll('Ani', 5_000_000);

        $this->assertSame(0, $this->bankOut());
    }

    public function test_gaji_dibayar_lewat_bank_mengurangi_saldo_bank_pada_tanggal_bayar(): void
    {
        $this->payroll('Ani', 5_000_000, extra: ['paid_on' => '2026-11-03', 'paid_via' => 'bank']);
        $this->payroll('Budi', 4_000_000, extra: ['paid_on' => '2026-11-03', 'paid_via' => 'bank']);

        $this->assertSame(9_000_000, $this->bankOut());

        $report = BankBalance::report(Carbon::parse('2026-11-03'), Carbon::parse('2026-11-03'));
        $this->assertSame(9_000_000, $report['rows']->first()['money_out']);
        $this->assertSame(91_000_000, $report['end_balance']);
    }

    public function test_tanggal_bayar_di_luar_rentang_atau_sebelum_saldo_awal_tidak_ikut(): void
    {
        $this->payroll('Ani', 5_000_000, extra: ['paid_on' => '2026-10-01', 'paid_via' => 'bank']);

        $this->assertSame(0, $this->bankOut());
        $this->assertSame(0, $this->bankOut('2026-11-04', '2026-11-06'));
    }

    public function test_gaji_tunai_mengurangi_sisa_cash_bukan_saldo_bank(): void
    {
        $this->payroll('Ani', 2_000_000, extra: ['paid_on' => '2026-11-03', 'paid_via' => 'cash']);

        $this->assertSame(0, $this->bankOut());

        $report = CashBalance::report(Carbon::parse('2026-11-03'), Carbon::parse('2026-11-03'));
        $this->assertSame(2_000_000, $report['cash_out']);
    }

    public function test_tombol_catat_pembayaran_satu_bulan(): void
    {
        $this->payroll('Ani', 5_000_000);
        $this->payroll('Budi', 4_000_000);
        $this->payroll('Citra', 3_000_000, '2026-09-01');

        Livewire::actingAs($this->superAdmin())
            ->test(ManagePayrolls::class)
            ->assertActionExists('payMonth')
            ->callAction('payMonth', ['month' => '2026-10-01', 'paid_on' => '2026-11-03', 'paid_via' => 'bank'])
            ->assertNotified(__('payroll_paid.marked'));

        $this->assertSame(2, Payroll::whereNotNull('paid_on')->count());
        $this->assertNull(Payroll::where('name', 'Citra')->first()->paid_on);
        $this->assertSame(9_000_000, $this->bankOut());
    }

    public function test_mencatat_dua_kali_tidak_menggandakan_pengurangan(): void
    {
        $this->payroll('Ani', 5_000_000);

        $component = Livewire::actingAs($this->superAdmin())->test(ManagePayrolls::class);
        $component->callAction('payMonth', ['month' => '2026-10-01', 'paid_on' => '2026-11-03', 'paid_via' => 'bank']);
        $component->callAction('payMonth', ['month' => '2026-10-01', 'paid_on' => '2026-11-05', 'paid_via' => 'bank'])
            ->assertNotified(__('payroll_paid.nothing'));

        $this->assertSame(5_000_000, $this->bankOut());
        $this->assertSame('2026-11-03', Payroll::first()->paid_on->toDateString());
    }

    public function test_catat_dibayar_untuk_baris_terpilih_dan_batalkan(): void
    {
        $ani = $this->payroll('Ani', 5_000_000);
        $budi = $this->payroll('Budi', 4_000_000);

        $component = Livewire::actingAs($this->superAdmin())->test(ManagePayrolls::class);

        $component->callTableBulkAction('markPaid', [$ani], ['paid_on' => '2026-11-03', 'paid_via' => 'bank']);

        $this->assertNotNull($ani->refresh()->paid_on);
        $this->assertNull($budi->refresh()->paid_on);
        $this->assertSame(5_000_000, $this->bankOut());

        $component->callTableBulkAction('markUnpaid', [$ani]);

        $this->assertNull($ani->refresh()->paid_on);
        $this->assertSame(0, $this->bankOut());
    }

    public function test_hanya_super_admin_bisa_membuka_gaji(): void
    {
        $this->actingAs($this->admin())
            ->get(\App\Filament\Admin\Resources\Zeytin\Payrolls\PayrollResource::getUrl())
            ->assertForbidden();
    }
}

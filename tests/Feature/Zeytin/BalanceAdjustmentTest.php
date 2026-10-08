<?php

namespace Tests\Feature\Zeytin;

use App\Filament\Admin\Resources\Zeytin\BalanceAdjustments\Pages\ManageBalanceAdjustments;
use App\Models\BalanceAdjustment;
use App\Models\DailyIncome;
use App\Support\Zeytin\BankBalance;
use App\Support\Zeytin\CashBalance;
use App\Support\Zeytin\Channels;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** Koreksi saldo: menggeser Sisa Cash atau Saldo Bank tanpa mengubah catatan lama. */
class BalanceAdjustmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Carbon::setTestNow('2026-10-10 10:00:00');

        DailyIncome::create(['date' => '2026-10-08', ...array_fill_keys(Channels::keys(), 0), 'cash' => 100_000, 'bni' => 200_000]);

        CashBalance::setOpening(826_493, '2026-10-08');
        BankBalance::setOpening(66_168_183, '2026-10-08');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_koreksi_bank_mengurangi_saldo_bank_bukan_cash(): void
    {
        $before = BankBalance::report(Carbon::parse('2026-10-08'), Carbon::parse('2026-10-12'));
        $cashBefore = CashBalance::report(Carbon::parse('2026-10-08'), Carbon::parse('2026-10-12'));

        BalanceAdjustment::create(['date' => '2026-10-08', 'account' => 'bank', 'direction' => 'minus', 'amount' => 15_000, 'reason' => 'Biaya BI-FAST']);

        $after = BankBalance::report(Carbon::parse('2026-10-08'), Carbon::parse('2026-10-12'));

        $this->assertSame($before['end_balance'] - 15_000, $after['end_balance']);
        $this->assertSame(-15_000, $after['adjustment']);
        $this->assertSame($cashBefore['end_balance'], CashBalance::report(Carbon::parse('2026-10-08'), Carbon::parse('2026-10-12'))['end_balance']);
    }

    public function test_koreksi_tambah_dan_kurang_pada_cash(): void
    {
        BalanceAdjustment::create(['date' => '2026-10-08', 'account' => 'cash', 'direction' => 'plus', 'amount' => 5_000, 'reason' => 'Kembalian lebih']);
        BalanceAdjustment::create(['date' => '2026-10-09', 'account' => 'cash', 'direction' => 'minus', 'amount' => 20_000, 'reason' => 'Selisih laci']);

        $report = CashBalance::report(Carbon::parse('2026-10-08'), Carbon::parse('2026-10-12'));

        $this->assertSame(826_493 + 100_000 + 5_000 - 20_000, $report['end_balance']);
        $this->assertSame(-15_000, $report['adjustment']);
    }

    public function test_koreksi_sebelum_rentang_membawa_ke_saldo_awal_rentang(): void
    {
        BalanceAdjustment::create(['date' => '2026-10-08', 'account' => 'bank', 'direction' => 'minus', 'amount' => 15_000, 'reason' => 'Biaya bank']);

        $report = BankBalance::report(Carbon::parse('2026-10-10'), Carbon::parse('2026-10-12'));

        $this->assertSame(66_168_183 + 200_000 - 15_000, $report['start_balance']);
    }

    public function test_koreksi_sebelum_tanggal_mulai_tidak_dihitung(): void
    {
        BalanceAdjustment::create(['date' => '2026-10-01', 'account' => 'bank', 'direction' => 'minus', 'amount' => 999_999, 'reason' => 'Sebelum mulai']);

        $report = BankBalance::report(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-12'));

        $this->assertSame(0, $report['adjustment']);
        $this->assertSame(66_168_183 + 200_000, $report['end_balance']);
    }

    public function test_admin_mencatat_koreksi_dari_halaman_dan_alasan_wajib(): void
    {
        $page = Livewire::actingAs($this->admin())->test(ManageBalanceAdjustments::class);

        $page->callAction(CreateAction::class, [
            'date' => '2026-10-08', 'account' => 'bank', 'direction' => 'minus', 'amount' => 15_000, 'reason' => '',
        ])->assertHasActionErrors(['reason' => 'required']);

        Livewire::actingAs($this->admin())->test(ManageBalanceAdjustments::class)
            ->callAction(CreateAction::class, [
                'date' => '2026-10-08', 'account' => 'bank', 'direction' => 'minus', 'amount' => 15_000, 'reason' => 'Biaya BI-FAST',
            ])->assertHasNoActionErrors();

        $row = BalanceAdjustment::first();
        $this->assertSame(-15_000, $row->signed());
        $this->assertNotNull($row->user_id);
    }
}

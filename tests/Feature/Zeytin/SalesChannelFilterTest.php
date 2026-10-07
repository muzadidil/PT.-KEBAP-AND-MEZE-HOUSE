<?php

namespace Tests\Feature\Zeytin;

use App\Filament\Admin\Pages\Reports\DailySales;
use App\Filament\Admin\Pages\Reports\MonthlySales;
use App\Filament\Admin\Pages\Reports\WeeklySales;
use App\Filament\Admin\Pages\Reports\YearlySales;
use App\Models\DailyIncome;
use App\Support\Zeytin\Channels;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** Laporan penjualan: pilihan cara bayar (Cash, BNI, Grab Food, ...) di keempat periode. */
class SalesChannelFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Carbon::setTestNow('2026-09-30 10:00:00');

        DailyIncome::create(['date' => '2026-09-29', ...array_fill_keys(Channels::keys(), 0), 'cash' => 100_000, 'bni' => 200_000, 'grab_food' => 50_000]);
        DailyIncome::create(['date' => '2026-09-30', ...array_fill_keys(Channels::keys(), 0), 'cash' => 300_000, 'bni' => 400_000, 'grab_food' => 70_000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public static function pages(): array
    {
        return [
            'harian' => [DailySales::class],
            'mingguan' => [WeeklySales::class],
            'bulanan' => [MonthlySales::class],
            'tahunan' => [YearlySales::class],
        ];
    }

    /** @dataProvider pages */
    public function test_setiap_periode_bisa_menampilkan_satu_cara_bayar(string $page): void
    {
        $expected = ['cash' => 400_000, 'bni' => 600_000, 'grab_food' => 120_000];

        foreach ($expected as $channel => $total) {
            $component = Livewire::actingAs($this->admin())
                ->test($page, ['from' => '2026-01-01', 'to' => '2026-10-15'])
                ->set('channel', $channel);

            $this->assertSame($total, $component->instance()->totalAmount(), "$page / $channel");
            $this->assertSame($total, (int) $component->instance()->rows->sum($channel));
        }

        // Tanpa pilihan: Total Penjualan seluruhnya.
        $all = Livewire::actingAs($this->admin())
            ->test($page, ['from' => '2026-01-01', 'to' => '2026-10-15']);
        $this->assertSame(1_120_000, $all->instance()->totalAmount());
    }

    public function test_cara_bayar_tidak_dikenal_dianggap_semua(): void
    {
        $component = Livewire::actingAs($this->admin())
            ->test(MonthlySales::class, ['from' => '2026-09-01', 'to' => '2026-10-15'])
            ->set('channel', 'petty_cash');

        $this->assertNull($component->instance()->activeChannel());
        $this->assertSame(1_120_000, $component->instance()->totalAmount());
    }

    public function test_tampilan_cash_menyembunyikan_laba_dan_kolom_lain(): void
    {
        Livewire::actingAs($this->admin())
            ->test(MonthlySales::class, ['from' => '2026-09-01', 'to' => '2026-10-15'])
            ->set('channel', 'cash')
            ->assertSee('Rp 400.000')
            ->assertDontSee(__('zeytin.card.profit'))
            ->assertDontSee(__('zeytin.col.total_sales'));
    }

    public function test_rata_rata_hanya_menghitung_hari_yang_ada_penjualannya_di_cara_bayar_itu(): void
    {
        DailyIncome::create(['date' => '2026-09-28', ...array_fill_keys(Channels::keys(), 0), 'bni' => 10_000]);

        $component = Livewire::actingAs($this->admin())
            ->test(MonthlySales::class, ['from' => '2026-09-01', 'to' => '2026-10-15'])
            ->set('channel', 'cash');

        // Cash hanya ada di 2 hari (29 dan 30), bukan 3.
        $this->assertSame(2, $component->instance()->averageDays());
        $this->assertSame(200_000, $component->instance()->averagePerDay());
    }
}

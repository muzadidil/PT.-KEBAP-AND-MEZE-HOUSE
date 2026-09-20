<?php

namespace Tests\Feature;

use App\Enums\SaleSource;
use App\Filament\Cashier\Pages\DailyEntry;
use App\Models\Sale;
use App\Support\Ledger;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class DailyEntryTest extends TestCase
{
    public function test_halaman_terbuka(): void
    {
        $this->actingAs($this->cashier())
            ->get('/daily-entry')
            ->assertOk();
    }

    public function test_rekap_harian_menyimpan_satu_baris_per_channel_yang_terisi(): void
    {
        Livewire::actingAs($this->cashier())
            ->test(DailyEntry::class)
            ->fillForm([
                'date' => '2026-09-18',
                'cash' => 1_500_000,
                'cashless' => 900_000,
                'grab' => 0,
            ])
            ->call('save')
            ->assertHasNoErrors();

        $sales = Sale::where('source', SaleSource::Quick)->get();

        // Grab nol tidak menghasilkan baris: nol bukan penjualan.
        $this->assertCount(2, $sales);
        $this->assertSame(1_500_000, (int) $sales->firstWhere('channel', 'cash')->total);
        $this->assertSame(900_000, (int) $sales->firstWhere('channel', 'cashless')->total);
        $this->assertNull($sales->first()->code);
    }

    public function test_menyimpan_tanggal_yang_sama_mengganti_bukan_menambah(): void
    {
        $cashier = $this->cashier();

        Livewire::actingAs($cashier)
            ->test(DailyEntry::class)
            ->fillForm(['date' => '2026-09-18', 'cash' => 1_000_000, 'cashless' => 0, 'grab' => 0])
            ->call('save');

        Livewire::actingAs($cashier)
            ->test(DailyEntry::class)
            ->fillForm(['date' => '2026-09-18', 'cash' => 1_200_000, 'cashless' => 0, 'grab' => 0])
            ->call('save');

        $this->assertSame(1, Sale::where('source', SaleSource::Quick)->count());
        $this->assertSame(1_200_000, (int) Sale::where('source', SaleSource::Quick)->sum('total'));
    }

    public function test_rekap_tidak_menyentuh_transaksi_kasir_di_tanggal_yang_sama(): void
    {
        $cashier = $this->cashier();

        Sale::create([
            'code' => 'S-20260918-0001',
            'sold_on' => '2026-09-18',
            'channel' => 'cash',
            'source' => SaleSource::Pos,
            'subtotal' => 250_000,
            'total' => 250_000,
            'paid' => 250_000,
            'user_id' => $cashier->id,
        ]);

        Livewire::actingAs($cashier)
            ->test(DailyEntry::class)
            ->fillForm(['date' => '2026-09-18', 'cash' => 750_000, 'cashless' => 0, 'grab' => 0])
            ->call('save');

        $this->assertSame(1, Sale::where('source', SaleSource::Pos)->count());

        // Laporan menjumlahkan keduanya: rekap harian adalah tambahan atas
        // yang sudah tercatat di kasir, bukan penggantinya.
        $day = Carbon::parse('2026-09-18');
        $this->assertSame(1_000_000, Ledger::salesSummary($day, $day)['cash']);
    }

    public function test_membuka_tanggal_yang_sudah_diisi_menampilkan_angkanya(): void
    {
        $cashier = $this->cashier();

        Livewire::actingAs($cashier)
            ->test(DailyEntry::class)
            ->fillForm(['date' => '2026-09-18', 'cash' => 640_000, 'cashless' => 310_000, 'grab' => 0])
            ->call('save');

        Livewire::actingAs($cashier)
            ->test(DailyEntry::class)
            ->fillForm(['date' => '2026-09-18'])
            ->assertSet('data.cash', 640_000)
            ->assertSet('data.cashless', 310_000)
            ->assertSet('data.grab', 0);
    }
}

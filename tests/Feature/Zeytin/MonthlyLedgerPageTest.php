<?php

namespace Tests\Feature\Zeytin;

use App\Filament\Admin\Pages\Zeytin\MonthlyLedger;
use App\Models\DailyIncome;
use App\Support\Zeytin\Channels;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Halaman buku besar bulanan.
 *
 * Harian, bulanan, dan tahunan adalah satu laporan yang digulung berbeda,
 * jadi yang diuji di sini bukan angkanya lagi — itu sudah dijaga
 * DailyLedgerTest — melainkan bahwa halamannya benar-benar memakai laporan
 * yang sama, dan bahwa isian yang aneh tidak menghasilkan layar kosong tanpa
 * penjelasan.
 */
class MonthlyLedgerPageTest extends TestCase
{
    protected function income(string $date, array $amounts = []): DailyIncome
    {
        return DailyIncome::create([
            'date' => $date,
            ...array_fill_keys(Channels::keys(), 0),
            ...$amounts,
        ]);
    }

    public function test_halaman_menampilkan_angka_periode_yang_dipilih(): void
    {
        $this->income('2026-08-10', ['cash' => 5_000_000, 'bni' => 1_000_000]);

        Livewire::actingAs($this->admin())
            ->test(MonthlyLedger::class, ['from' => '2026-08-01', 'to' => '2026-08-31'])
            ->assertSee('Rp 6.000.000');
    }

    /**
     * Rentang terbalik diperlakukan sebagai satu hari, bukan tabel kosong.
     *
     * Tabel kosong tanpa penjelasan adalah jawaban terburuk: orang akan
     * mengira datanya hilang, bukan mengira tanggalnya tertukar.
     */
    public function test_rentang_terbalik_tidak_menghasilkan_tabel_kosong(): void
    {
        $this->income('2026-08-10', ['cash' => 5_000_000]);

        $page = Livewire::actingAs($this->admin())
            ->test(MonthlyLedger::class, ['from' => '2026-08-10', 'to' => '2026-08-01']);

        $this->assertSame('2026-08-10', $page->instance()->toDate()->toDateString());
        $this->assertCount(1, $page->instance()->report['rows']);
    }

    public function test_ketiga_pengelompokan_menjumlah_angka_yang_sama(): void
    {
        $this->income('2026-08-31', ['cash' => 1_000_000]);
        $this->income('2026-09-01', ['cash' => 2_000_000]);

        $page = Livewire::actingAs($this->admin())
            ->test(MonthlyLedger::class, ['from' => '2026-08-01', 'to' => '2026-09-30']);

        $totals = [];

        foreach (['daily', 'monthly', 'yearly'] as $grouping) {
            $page->call('setGrouping', $grouping);
            $totals[$grouping] = $page->instance()->rows->sum('total_sales');
        }

        $this->assertSame([3_000_000, 3_000_000, 3_000_000], array_values($totals));
    }

    public function test_pengelompokan_yang_tidak_dikenal_kembali_ke_harian(): void
    {
        $page = Livewire::actingAs($this->admin())->test(MonthlyLedger::class);

        $page->call('setGrouping', 'mingguan');

        $this->assertSame('daily', $page->instance()->grouping);
    }

    public function test_unduhan_excel_dibuat_dari_laporan_yang_tampil(): void
    {
        $this->income('2026-08-10', ['cash' => 5_000_000]);

        Livewire::actingAs($this->admin())
            ->test(MonthlyLedger::class, ['from' => '2026-08-01', 'to' => '2026-08-31'])
            ->call('exportExcel')
            ->assertFileDownloaded('zeytin-2026-08-01_2026-08-31.xlsx');
    }

    public function test_petty_cash_tampil_terpisah_dan_ditandai_tidak_ikut(): void
    {
        $this->income('2026-08-10', ['cash' => 5_000_000, 'petty_cash' => 100_000]);

        $page = Livewire::actingAs($this->admin())
            ->test(MonthlyLedger::class, ['from' => '2026-08-01', 'to' => '2026-08-31']);

        $summary = $page->instance()->channelSummary;
        $excluded = array_values(array_filter($summary, fn (array $row) => $row['excluded']));

        $this->assertCount(1, $excluded);
        $this->assertSame(100_000, $excluded[0]['amount']);

        // Tunai 5jt, non-tunai 0 — petty cash tidak ikut keduanya.
        $this->assertSame(5_000_000, $summary[0]['amount']);
        $this->assertSame(0, $summary[1]['amount']);
    }
}

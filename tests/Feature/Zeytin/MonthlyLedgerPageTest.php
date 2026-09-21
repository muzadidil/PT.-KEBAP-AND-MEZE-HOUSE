<?php

namespace Tests\Feature\Zeytin;

use App\Filament\Admin\Pages\Zeytin\MonthlyLedger;
use App\Models\DailyIncome;
use App\Models\OutstandingBill;
use App\Models\Payroll;
use App\Models\Purchase;
use App\Models\SupplierTransfer;
use App\Support\Money;
use App\Support\Zeytin\Channels;
use App\Support\Zeytin\DailyLedger;
use App\Support\Zeytin\PeriodExport;
use App\Support\Zeytin\PeriodPdf;
use Illuminate\Support\Carbon;
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

    /**
     * PDF dibuka di tab baru (inline), bukan diunduh diam-diam: tombol
     * unduhan lama tidak menampilkan apa pun di layar, dan terlihat seperti
     * tidak bekerja.
     */
    public function test_pdf_dibuka_di_tab_baru_dari_rentang_yang_tampil(): void
    {
        $this->income('2026-08-10', ['cash' => 5_000_000]);

        $page = Livewire::actingAs($this->admin())
            ->test(MonthlyLedger::class, ['from' => '2026-08-01', 'to' => '2026-08-31', 'grouping' => 'monthly']);

        $url = $page->instance()->pdfUrl();

        $this->assertStringContainsString('from=2026-08-01', $url);
        $this->assertStringContainsString('grouping=monthly', $url);

        $response = $this->actingAs($this->admin())->get($url)->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('zeytin-monthly-2026-08-01_2026-08-31.pdf', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_pdf_buku_besar_hanya_untuk_admin(): void
    {
        $url = route('filament.admin.pdf.ledger', ['from' => '2026-08-01', 'to' => '2026-08-31']);

        $this->actingAs($this->superAdmin())->get($url)->assertForbidden();
    }

    public function test_pdf_buku_besar_meminta_masuk_dulu(): void
    {
        $this->get(route('filament.admin.pdf.ledger'))->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_pdf_memuat_angka_ringkasan_yang_sama_dengan_layar(): void
    {
        $this->income('2026-08-10', ['cash' => 5_000_000, 'bni' => 1_000_000]);
        Purchase::create(['date' => '2026-08-10', 'item' => 'Ayam', 'qty' => 10, 'price' => 35_000]);

        $report = DailyLedger::periodReport(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));
        $pdf = new PeriodPdf($report, 'daily');

        $html = view('pdf.zeytin-period', [
            'report' => $report,
            'rows' => $pdf->rows(),
            'grouping' => 'daily',
            'letterhead' => config('zeytin.letterhead'),
        ])->render();

        $this->assertStringContainsString(Money::format($report['total_sales']), $html);
        $this->assertStringContainsString(Money::format($report['global_balance']), $html);
        $this->assertStringContainsString(config('zeytin.letterhead.address'), $html);

        // Berkasnya benar-benar PDF, bukan halaman galat yang diberi nama .pdf.
        $this->assertStringStartsWith('%PDF-', $pdf->render());
    }

    /**
     * Laporan untuk pemilik tidak penuh baris nol. Hanya rinciannya yang
     * disaring; totalnya tetap dari laporan yang sama.
     */
    public function test_pdf_harian_membuang_hari_kosong_tanpa_mengubah_total(): void
    {
        $this->income('2026-08-10', ['cash' => 5_000_000]);
        $this->income('2026-08-20', ['cash' => 3_000_000]);

        $report = DailyLedger::periodReport(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));
        $rows = (new PeriodPdf($report, 'daily'))->rows();

        $this->assertCount(2, $rows);
        $this->assertSame($report['total_sales'], $rows->sum('total_sales'));
    }

    /**
     * Tiap angka di ringkasan bisa ditelusuri ke baris yang membentuknya:
     * baris Total di sheet catatan mentah sama dengan angka di ringkasan.
     */
    public function test_unduhan_excel_memuat_catatan_mentah_yang_berjumlah_sama(): void
    {
        Purchase::create(['date' => '2026-08-10', 'item' => 'Ayam', 'qty' => 10, 'price' => 35_000]);
        SupplierTransfer::create(['date' => '2026-08-11', 'item' => 'Daging', 'qty' => 1, 'price' => 2_000_000]);
        Payroll::create(['month' => '2026-08-01', 'name' => 'Sinta', 'basic' => 3_000_000, 'bpjs' => 200_000]);
        OutstandingBill::create(['date' => '2026-07-15', 'item' => 'Minyak', 'qty' => 1, 'price' => 500_000, 'status' => 'Need the payment']);
        OutstandingBill::create(['date' => '2026-08-01', 'item' => 'Tepung', 'qty' => 1, 'price' => 100_000, 'status' => 'PAID']);

        // Di luar rentang: tidak boleh ikut sheet mana pun.
        Purchase::create(['date' => '2026-09-01', 'item' => 'Ayam', 'qty' => 1, 'price' => 35_000]);

        $report = DailyLedger::periodReport(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));
        $book = (new PeriodExport($report))->build();

        $expected = [
            __('zeytin.nav.purchases') => [1, $report['cash_expense']],
            __('zeytin.nav.transfers') => [1, $report['transfers']],
            __('zeytin.nav.payroll') => [1, $report['payroll']],
            // Yang sudah lunas tidak ikut, sama seperti di ringkasan.
            __('zeytin.nav.outstanding') => [1, $report['outstanding']],
        ];

        foreach ($expected as $title => [$count, $total]) {
            $sheet = $book->getSheetByName($title);
            $this->assertNotNull($sheet, "Sheet {$title} tidak ada.");

            $rows = $sheet->toArray(null, false, false);
            $footer = end($rows);

            $this->assertCount($count + 2, $rows, "Jumlah baris sheet {$title}.");
            $this->assertSame(__('report.grand_total'), $footer[0]);
            // Satu-satunya angka di baris Total adalah jumlahnya.
            $numbers = array_values(array_filter($footer, 'is_numeric'));
            $this->assertEquals([$total], $numbers, "Total sheet {$title}.");
        }

        // Unduhan ini milik Admin; gaji per orang hanya untuk Super Admin.
        $payroll = $book->getSheetByName(__('zeytin.nav.payroll'))->toArray();
        $this->assertStringNotContainsString('Sinta', json_encode($payroll));
    }

    public function test_preset_hari_ini_dan_tahun_lalu(): void
    {
        Carbon::setTestNow('2026-09-21');

        $page = Livewire::actingAs($this->admin())->test(MonthlyLedger::class);

        $page->call('applyPreset', 'today')
            ->assertSet('from', '2026-09-21')
            ->assertSet('to', '2026-09-21');

        $page->call('applyPreset', 'last_year')
            ->assertSet('from', '2025-01-01')
            ->assertSet('to', '2025-12-31');

        Carbon::setTestNow();
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

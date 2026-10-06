<?php

namespace Tests\Feature\Reports;

use App\Filament\Admin\Pages\Reports\TaxFilings;
use App\Models\DailyIncome;
use App\Models\TaxFilingLog;
use App\Support\Tax\TaxFilingExport;
use App\Support\Tax\TaxFilingReport;
use App\Support\Zeytin\Channels;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Laporan Pajak: angka ditarik dari pembukuan, boleh dikoreksi, dan koreksi
 * tidak pernah mengubah data asli.
 */
class TaxFilingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Carbon::setTestNow('2026-09-24 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function sales(string $date, int $cash): DailyIncome
    {
        return DailyIncome::create(['date' => $date, ...array_fill_keys(Channels::keys(), 0), 'cash' => $cash]);
    }

    public function test_omzet_ditarik_dari_pembukuan_dan_pph_final_setengah_persen(): void
    {
        $this->sales('2026-08-05', 10_000_000);
        $this->sales('2026-08-20', 5_000_000);

        $august = TaxFilingReport::year(2026)['rows']->firstWhere('month', 8);

        $this->assertSame(15_000_000, $august['revenue']);
        $this->assertSame(75_000, $august['final_due']);
        $this->assertFalse($august['adjusted']);
    }

    public function test_ppn_dipisah_dari_harga_jika_sudah_termasuk(): void
    {
        $this->assertSame(1_100_000, TaxFilingReport::outputTax(10_000_000, 11, false));
        $this->assertSame(990_991, TaxFilingReport::outputTax(10_000_000, 11, true));
        $this->assertSame(0, TaxFilingReport::outputTax(0, 11, false));
    }

    public function test_koreksi_tersimpan_terpisah_dan_data_asli_tidak_berubah(): void
    {
        $income = $this->sales('2026-08-05', 10_000_000);

        Livewire::actingAs($this->admin())
            ->test(TaxFilings::class)
            ->set('year', 2026)
            ->callAction('editMonth', [
                'revenue_override' => 8_000_000,
                'final_rate' => null,
                'ppn_rate' => null,
                'ppn_inclusive' => 'default',
                'ppn_input' => 0,
                'paid_final' => 20_000,
                'paid_ppn' => 0,
                'is_reported' => true,
                'note' => 'disesuaikan',
            ], ['month' => 8])
            ->assertHasNoActionErrors();

        $august = TaxFilingReport::year(2026)['rows']->firstWhere('month', 8);

        $this->assertSame(8_000_000, $august['revenue']);
        $this->assertSame(10_000_000, $august['system_revenue']);
        $this->assertTrue($august['adjusted']);
        $this->assertSame(40_000, $august['final_due']);
        $this->assertSame(20_000, $august['paid_final']);
        $this->assertTrue($august['is_reported']);

        // Data asli tetap utuh.
        $this->assertSame(10_000_000, $income->fresh()->totalSales());

        $log = TaxFilingLog::where('field', 'revenue_override')->first();
        $this->assertNull($log->old_value);
        $this->assertSame('8000000', $log->new_value);
        $this->assertNotNull($log->user_id);
    }

    public function test_menyimpan_tanpa_perubahan_tidak_menulis_riwayat(): void
    {
        Livewire::actingAs($this->admin())
            ->test(TaxFilings::class)
            ->set('year', 2026)
            ->call('saveMonth', 3, [
                'revenue_override' => null, 'final_rate' => null, 'ppn_rate' => null,
                'ppn_inclusive' => 'default', 'ppn_input' => 0, 'paid_final' => 0,
                'paid_ppn' => 0, 'is_reported' => false, 'note' => null,
            ]);

        $this->assertSame(0, TaxFilingLog::count());
    }

    public function test_halaman_hanya_terbuka_untuk_admin(): void
    {
        $this->actingAs($this->admin())->get(route('filament.admin.pages.tax-filings'))->assertOk();

        $this->flushSession();

        $this->actingAs($this->superAdmin())->get(route('filament.admin.pages.tax-filings'))->assertForbidden();
    }

    public function test_pdf_dibuka_untuk_admin_dan_tertutup_untuk_super_admin(): void
    {
        $this->sales('2026-08-05', 10_000_000);

        $pdf = $this->actingAs($this->admin())
            ->get(route('filament.admin.pdf.tax-filing', ['year' => 2026]))
            ->assertOk();

        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    public function test_pdf_tertutup_untuk_super_admin(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('filament.admin.pdf.tax-filing', ['year' => 2026]))
            ->assertForbidden();
    }

    public function test_excel_memuat_angka_dan_total_yang_sama_dengan_layar(): void
    {
        $this->sales('2026-08-05', 10_000_000);

        $report = TaxFilingReport::year(2026);
        $book = (new TaxFilingExport($report))->build();
        $path = tempnam(sys_get_temp_dir(), 'tax').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);

        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        // Baris terakhir = total; kolom C = omzet dilaporkan.
        $this->assertSame(10_000_000, (int) $sheet->getCell('C17')->getValue());
        $this->assertSame($report['totals']['final_due'], (int) $sheet->getCell('F17')->getValue());
    }

    public function test_laporan_bulanan_sama_dengan_baris_bulan_di_tabel_tahunan(): void
    {
        $this->sales('2026-08-05', 10_000_000);
        $this->sales('2026-08-20', 5_000_000);
        $this->sales('2026-09-01', 2_000_000);

        $month = TaxFilingReport::month(2026, 8);
        $row = TaxFilingReport::year(2026)['rows']->firstWhere('month', 8);

        $this->assertSame($row, $month['summary']);
        $this->assertSame(31, $month['days']->count());
        $this->assertSame(15_000_000, $month['sales']);
        $this->assertSame($month['summary']['system_revenue'], $month['sales']);
    }

    public function test_halaman_bulan_menampilkan_rincian_dan_bisa_dibuka_dari_alamat(): void
    {
        $this->sales('2026-08-05', 10_000_000);

        Livewire::actingAs($this->admin())
            ->test(TaxFilings::class, ['year' => 2026, 'month' => 8])
            ->assertSet('month', 8)
            ->assertSee(__('tax_filing.month.daily_title'))
            ->call('openMonth', 0)
            ->assertSet('month', 0);
    }

    public function test_pdf_dan_excel_bulanan(): void
    {
        $this->sales('2026-08-05', 10_000_000);

        $pdf = $this->actingAs($this->admin())
            ->get(route('filament.admin.pdf.tax-filing', ['year' => 2026, 'month' => 8]))
            ->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());

        $book = (new \App\Support\Tax\TaxMonthExport(TaxFilingReport::month(2026, 8)))->build();
        $path = tempnam(sys_get_temp_dir(), 'taxm').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);
        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        $values = collect($sheet->toArray(null, true, false))->flatten()->filter()->all();
        $this->assertContains(10_000_000, array_map(fn ($v) => is_numeric($v) ? (int) $v : $v, $values));
    }

    public function test_bagi_hasil_investor_tampil_terpisah_dan_persennya_bisa_diatur(): void
    {
        $this->sales('2026-08-05', 10_000_000);
        \App\Models\Setting::put('tax.investor_share', 20);

        $august = TaxFilingReport::year(2026)['rows']->firstWhere('month', 8);

        $this->assertSame(10_000_000, $august['revenue']);
        $this->assertSame(2_000_000, $august['investor_share']);
        $this->assertSame(8_000_000, $august['tax_base']);
        $this->assertSame(40_000, $august['final_due']);

        // Satu bulan boleh memakai persen sendiri.
        Livewire::actingAs($this->admin())
            ->test(TaxFilings::class)
            ->set('year', 2026)
            ->call('saveMonth', 8, [
                'revenue_override' => null, 'investor_share' => 15, 'final_rate' => null, 'ppn_rate' => null,
                'ppn_inclusive' => 'default', 'ppn_input' => 0, 'paid_final' => 0,
                'paid_ppn' => 0, 'is_reported' => false, 'note' => null,
            ]);

        $august = TaxFilingReport::year(2026)['rows']->firstWhere('month', 8);
        $this->assertSame(1_500_000, $august['investor_share']);
        $this->assertSame(8_500_000, $august['tax_base']);
        $this->assertSame(1, TaxFilingLog::where('field', 'investor_share')->count());
    }

    public function test_persen_potongan_pilihan_di_pdf_menimpa_semua_bulan(): void
    {
        $this->sales('2026-08-05', 10_000_000);

        $august = TaxFilingReport::year(2026, 25)['rows']->firstWhere('month', 8);
        $this->assertSame(2_500_000, $august['investor_share']);
        $this->assertSame(7_500_000, $august['tax_base']);
        $this->assertSame(37_500, $august['final_due']);

        $this->assertSame(7_500_000, TaxFilingReport::month(2026, 8, 25)['summary']['tax_base']);

        $pdf = $this->actingAs($this->admin())
            ->get(route('filament.admin.pdf.tax-filing', ['year' => 2026, 'month' => 8, 'share' => 25]))
            ->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());

        $url = Livewire::actingAs($this->admin())
            ->test(TaxFilings::class, ['year' => 2026, 'month' => 8])
            ->set('pdfShare', '30')
            ->instance()->pdfUrl();
        $this->assertStringContainsString('share=30', $url);
    }

    public function test_halaman_punya_formulir_pdf_dengan_kotak_persen(): void
    {
        $this->actingAs($this->admin())
            ->get(route('filament.admin.pages.tax-filings', ['year' => 2026, 'month' => 8]))
            ->assertOk()
            ->assertSee('name="share"', false)
            ->assertSee('name="month" value="8"', false);
    }

    public function test_pdf_hanya_menampilkan_omzet_setelah_potongan_tanpa_keterangan(): void
    {
        $this->sales('2026-08-05', 10_000_000);

        $month = function (?float $share) {
            $r = TaxFilingReport::month(2026, 8, $share);

            return view('pdf.tax-month', [
                'report' => $r,
                'lines' => \App\Support\Tax\TaxMonthExport::pdfSummaryLines($r['summary']),
                'columns' => \App\Support\Tax\TaxMonthExport::dayColumns(),
                'showDays' => $r['summary']['tax_base'] === $r['sales'],
                'letterhead' => config('zeytin.letterhead'),
            ])->render();
        };

        $cut = $month(40);
        $this->assertStringContainsString('6.000.000', $cut);
        $this->assertStringNotContainsString('40%', $cut);
        $this->assertStringNotContainsString('10.000.000', $cut);
        $this->assertStringNotContainsString(__('tax_filing.col.investor_share'), $cut);
        $this->assertStringNotContainsString(__('tax_filing.col.system_revenue'), $cut);
        $this->assertStringNotContainsString(__('tax_filing.month.daily_title'), $cut);

        // Tanpa potongan, rincian harian tetap dicetak.
        $this->assertStringContainsString(__('tax_filing.month.daily_title'), $month(0));

        $year = view('pdf.tax-filing', [
            'report' => TaxFilingReport::year(2026, 40),
            'columns' => \App\Support\Tax\TaxFilingExport::pdfColumns(),
            'letterhead' => config('zeytin.letterhead'),
        ])->render();

        $this->assertStringContainsString('6.000.000', $year);
        $this->assertStringNotContainsString('40%', $year);
        $this->assertStringNotContainsString('10.000.000', $year);
        $this->assertStringNotContainsString(__('tax_filing.col.investor_share'), $year);
    }
}

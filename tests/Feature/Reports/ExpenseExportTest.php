<?php

namespace Tests\Feature\Reports;

use App\Enums\ExpenseCategory;
use App\Filament\Admin\Pages\Reports\CashExpenses;
use App\Filament\Admin\Pages\Reports\OnlineTransfers;
use App\Filament\Admin\Pages\Reports\Tax;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\SupplierTransfer;
use App\Support\Money;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Unduhan Excel, PDF, dan cetak di laporan berbentuk daftar: Pengeluaran
 * Tunai, Transfer Online, Pajak.
 *
 * Yang dijaga: isi berkas mengikuti penyaring yang sedang dipakai di layar,
 * dan totalnya sama dengan yang tampil di kaki tabel.
 */
class ExpenseExportTest extends TestCase
{
    protected string $path;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Carbon::setTestNow('2026-09-24 10:00:00');
        $this->path = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        @unlink($this->path);

        parent::tearDown();
    }

    protected function purchase(string $date, string $item, int $price, ?string $vendor = null): Purchase
    {
        return Purchase::create([
            'date' => $date, 'item' => $item, 'qty' => 1, 'unit' => 'kg',
            'price' => $price, 'vendor' => $vendor,
        ]);
    }

    /** @return array<int, array<int, mixed>> baris sheet yang terunduh */
    protected function sheetOf($response): array
    {
        ob_start();
        $response->sendContent();
        file_put_contents($this->path, ob_get_clean());

        $book = IOFactory::load($this->path);
        // Tanpa formatData: angka uang kembali sebagai angka, bukan teks
        // "400,000" — yang diperiksa memang bisa-tidaknya dijumlahkan.
        $rows = $book->getActiveSheet()->toArray(null, true, false, false);
        $book->disconnectWorksheets();

        return $rows;
    }

    /* -------------------------------------------------------------- excel */

    public function test_unduhan_excel_memuat_baris_dan_totalnya(): void
    {
        $this->purchase('2026-08-10', 'Ayam', 350_000, 'Pak Budi');
        $this->purchase('2026-08-12', 'Cabai', 50_000);

        $response = Livewire::actingAs($this->admin())
            ->test(CashExpenses::class)
            ->call('exportExcel')
            ->assertFileDownloaded()
            ->instance()
            ->exportExcel();

        $rows = $this->sheetOf($response);
        $flat = collect($rows)->map(fn (array $row) => implode('|', array_map(fn ($cell) => (string) $cell, $row)));

        $this->assertTrue($flat->contains(fn (string $line) => str_contains($line, 'Ayam') && str_contains($line, 'Pak Budi')));
        $this->assertTrue($flat->contains(fn (string $line) => str_contains($line, 'Cabai')));

        // Baris total di bawah, sebagai angka yang masih bisa dijumlahkan.
        $total = collect($rows)->last(fn (array $row) => ($row[0] ?? null) === __('report.total'));
        $this->assertNotNull($total);
        $this->assertSame(400_000, (int) collect($total)->first(fn ($cell) => is_numeric($cell) && (int) $cell === 400_000));
    }

    /** Berkasnya mengikuti layar: yang tersaring keluar dari tabel juga tidak ikut terunduh. */
    public function test_unduhan_excel_mengikuti_penyaring_periode(): void
    {
        $this->purchase('2026-08-10', 'Ayam', 350_000);
        $this->purchase('2026-09-10', 'Cabai', 50_000);

        $response = Livewire::actingAs($this->admin())
            ->test(CashExpenses::class)
            ->set('tableFilters.period.from', '2026-08-01')
            ->set('tableFilters.period.until', '2026-08-31')
            ->call('exportExcel')
            ->assertFileDownloaded()
            ->instance()
            ->exportExcel();

        $text = collect($this->sheetOf($response))
            ->map(fn (array $row) => implode('|', array_map(fn ($cell) => (string) $cell, $row)))
            ->implode("\n");

        $this->assertStringContainsString('Ayam', $text);
        $this->assertStringNotContainsString('Cabai', $text);
        $this->assertStringContainsString(Carbon::parse('2026-08-01')->translatedFormat('j M Y'), $text);
    }

    /* ---------------------------------------------------------------- pdf */

    public function test_pdf_dibuka_di_tab_baru_dengan_penyaring_dari_alamat(): void
    {
        $this->purchase('2026-08-10', 'Ayam', 350_000);
        $this->purchase('2026-09-10', 'Cabai', 50_000);

        $response = $this->actingAs($this->admin())
            ->get(route('filament.admin.pdf.expenses', [
                'report' => CashExpenses::reportKey(),
                'from' => '2026-08-01',
                'to' => '2026-08-31',
            ]))
            ->assertOk();

        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_alamat_pdf_membawa_penyaring_yang_sedang_dipakai(): void
    {
        $url = Livewire::actingAs($this->admin())
            ->test(OnlineTransfers::class)
            ->set('tableFilters.period.from', '2026-08-01')
            ->set('tableFilters.status.value', 'PAID')
            ->instance()
            ->pdfUrl();

        $this->assertStringContainsString(OnlineTransfers::reportKey(), $url);
        $this->assertStringContainsString('from=2026-08-01', $url);
        $this->assertStringContainsString('status=PAID', $url);
    }

    public function test_laporan_yang_tidak_dikenal_ditolak(): void
    {
        $this->actingAs($this->admin())
            ->get(route('filament.admin.pdf.expenses', ['report' => 'entah-apa']))
            ->assertNotFound();
    }

    public function test_pdf_laporan_tertutup_untuk_super_admin(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('filament.admin.pdf.expenses', ['report' => Tax::reportKey()]))
            ->assertForbidden();
    }

    /* ------------------------------------------------------- ketiga laporan */

    public function test_ketiga_laporan_punya_tombol_dan_kolom_unduhan(): void
    {
        SupplierTransfer::create([
            'date' => '2026-08-10', 'item' => 'Daging', 'qty' => 1, 'price' => 1_000_000,
            'total' => 1_000_000, 'status' => 'PAID',
        ]);

        Expense::create([
            'description' => 'PB1 Agustus', 'category' => ExpenseCategory::Tax, 'method' => 'transfer',
            'amount' => 2_000_000, 'spent_on' => '2026-08-31', 'is_paid' => true,
        ]);

        $this->purchase('2026-08-10', 'Ayam', 350_000);

        foreach ([CashExpenses::class, OnlineTransfers::class, Tax::class] as $page) {
            Livewire::actingAs($this->admin())
                ->test($page)
                ->assertSee(__('report.export_excel'))
                ->assertSee(__('report.pdf'))
                ->assertSee(__('report.print'));

            $columns = app($page)->exportColumns();

            // Selalu ada tanggal di depan dan satu kolom uang.
            $this->assertSame(__('field.date'), $columns[0]['label']);
            $this->assertTrue(collect($columns)->contains(fn (array $column) => $column['money'] ?? false));
        }
    }

    /** Total di PDF dan Excel dihitung dari kolom uang yang sama dengan kaki tabel. */
    public function test_total_pdf_sama_dengan_isi_tabel(): void
    {
        $this->purchase('2026-08-10', 'Ayam', 350_000);
        $this->purchase('2026-08-12', 'Cabai', 50_000);

        $page = app(CashExpenses::class);
        $rows = $page->filteredQuery(['from' => '2026-08-01', 'to' => '2026-08-31'])->get();

        $this->assertSame(2, $rows->count());
        $this->assertSame(Money::format(400_000), Money::format((int) $rows->sum('total')));
    }
}

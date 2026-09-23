<?php

namespace Tests\Feature\Zeytin;

use App\Filament\Admin\Pages\Zeytin\ImportExcel;
use App\Models\Purchase;
use App\Support\Zeytin\RecordSource;
use App\Support\Zeytin\Workbook\TemplateBuilder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Halaman Impor Excel: berkas diperiksa dulu sebelum ada yang disimpan.
 *
 * Tanggal di luar bulan berkas menghentikan impor. Baris yang sama dengan
 * ketikan manual ditunjukkan dulu; pengguna memilih mana yang tetap
 * dimasukkan, sisanya dilewati.
 */
class ImportReviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    /** @param  array<int, array<int, mixed>>  $expense */
    protected function upload(array $expense): UploadedFile
    {
        $book = (new TemplateBuilder(Carbon::parse('2026-08-01')))->build();
        $book->getSheetByName('Expense')->fromArray($expense, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'zeytin').'.xlsx';
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return UploadedFile::fake()->createWithContent('agustus.xlsx', file_get_contents($path));
    }

    protected function page(UploadedFile $file)
    {
        return Livewire::actingAs($this->admin())
            ->test(ImportExcel::class)
            ->fillForm(['file' => $file, 'payroll_month' => '2026-08-01']);
    }

    protected function typed(string $item, int $qty, int $price): Purchase
    {
        return Purchase::create([
            'date' => '2026-08-01', 'item' => $item, 'qty' => $qty, 'price' => $price,
            'source' => RecordSource::MANUAL,
        ]);
    }

    public function test_kemungkinan_dobel_ditunjukkan_dulu_dan_belum_ada_yang_tersimpan(): void
    {
        $this->typed('Ayam', 10, 35_000);

        $page = $this->page($this->upload([
            ['01/08/2026', 'Pak Budi', 'Ayam', 10, 'kg', 35_000, 0, 0, 350_000],
            [null, 'Pak Budi', 'Cabai', 1, 'kg', 50_000, 0, 0, 50_000],
        ]))->call('import');

        $review = $page->get('review');

        $this->assertCount(1, $review);
        $this->assertSame('Expense', $review[0]['sheet']);
        $this->assertSame(2, $review[0]['line']);
        $page->assertSee(__('zeytin.import.review.title'))->assertSee('Ayam');

        // Belum ada yang masuk sampai pengguna memutuskan.
        $this->assertSame(1, Purchase::count());
    }

    public function test_yang_tidak_dicentang_dilewati(): void
    {
        $this->typed('Ayam', 10, 35_000);

        $this->page($this->upload([
            ['01/08/2026', 'Pak Budi', 'Ayam', 10, 'kg', 35_000, 0, 0, 350_000],
            [null, 'Pak Budi', 'Cabai', 1, 'kg', 50_000, 0, 0, 50_000],
        ]))
            ->call('import')
            ->call('continueImport')
            ->assertSet('review', []);

        // Ketikan Ayam + Cabai dari berkas; Ayam dari berkas dilewati.
        $this->assertSame(2, Purchase::count());
        $this->assertSame(1, Purchase::where('item', 'Ayam')->count());
    }

    public function test_yang_dicentang_tetap_dimasukkan(): void
    {
        $this->typed('Ayam', 10, 35_000);

        $page = $this->page($this->upload([
            ['01/08/2026', 'Pak Budi', 'Ayam', 10, 'kg', 35_000, 0, 0, 350_000],
        ]))->call('import');

        $page->set('include', [$page->get('review')[0]['id']])->call('continueImport');

        $this->assertSame(2, Purchase::where('item', 'Ayam')->count());
    }

    public function test_batal_tidak_menyimpan_apa_pun(): void
    {
        $this->typed('Ayam', 10, 35_000);

        $this->page($this->upload([
            ['01/08/2026', 'Pak Budi', 'Ayam', 10, 'kg', 35_000, 0, 0, 350_000],
            [null, 'Pak Budi', 'Cabai', 1, 'kg', 50_000, 0, 0, 50_000],
        ]))
            ->call('import')
            ->call('cancelImport')
            ->assertSet('review', []);

        $this->assertSame(1, Purchase::count());
    }

    public function test_tanpa_dobel_langsung_diimpor(): void
    {
        $this->page($this->upload([
            ['01/08/2026', 'Pak Budi', 'Cabai', 1, 'kg', 50_000, 0, 0, 50_000],
        ]))
            ->call('import')
            ->assertSet('review', []);

        $this->assertSame(1, Purchase::count());
    }

    public function test_tanggal_di_luar_bulan_menghentikan_seluruh_impor(): void
    {
        $page = $this->page($this->upload([
            ['01/08/2026', 'Pak Budi', 'Ayam', 1, 'kg', 35_000, 0, 0, 35_000],
            ['18/8/2028', 'Pak Budi', 'Cabai', 1, 'kg', 50_000, 0, 0, 50_000],
        ]))->call('import');

        $this->assertSame(0, Purchase::count());

        $page->assertSee(__('zeytin.import.error.row_out_of_month', [
            'row' => 3,
            'date' => Carbon::parse('2028-08-18')->translatedFormat('j M Y'),
            'month' => Carbon::parse('2026-08-01')->translatedFormat('F Y'),
        ]));
    }
}

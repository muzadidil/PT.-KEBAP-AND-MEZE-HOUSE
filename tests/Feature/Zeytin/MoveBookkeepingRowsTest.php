<?php

namespace Tests\Feature\Zeytin;

use App\Models\DailyIncome;
use App\Models\Payroll;
use App\Models\Purchase;
use App\Support\Zeytin\Workbook\Importer;
use App\Support\Zeytin\Workbook\TemplateBuilder;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * `zeytin:pindah` — membetulkan baris yang jatuh di tanggal yang salah.
 *
 * Yang dijaga: hasilnya harus sama dengan mengimpor berkas yang selnya
 * sudah dibetulkan. Mengimpor berkas yang benar sesudahnya tidak boleh
 * menggandakan baris yang sudah dipindah.
 */
class MoveBookkeepingRowsTest extends TestCase
{
    protected string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = tempnam(sys_get_temp_dir(), 'zeytin').'.xlsx';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    /** @param  array<string, array<int, array<int, mixed>>>  $sheets */
    protected function importBook(array $sheets, string $month = '2026-08-01'): void
    {
        $book = (new TemplateBuilder)->build();

        foreach ($sheets as $name => $rows) {
            $book->getSheetByName($name)->fromArray($rows, null, 'A2');
        }

        (new Xlsx($book))->save($this->path);

        (new Importer(Carbon::parse($month)))->import($this->path);
    }

    /** Kejadian nyata di berkas Agustus: "18/8/2028" di sheet Expense. */
    public function test_belanja_salah_tahun_dipindah_lalu_berkas_yang_benar_tidak_menggandakan(): void
    {
        $rows = fn (string $date) => [
            [$date, '', 'Aqua Galon', 1, '', 66_000, 0, 0, 66_000],
            [null, '', 'Es Batu', 1, '', 12_000, 0, 0, 12_000],
        ];

        $this->importBook(['Expense' => $rows('18/8/2028')]);

        $this->artisan('zeytin:pindah', ['jenis' => 'belanja', 'dari' => '2028-08-18', 'ke' => '2026-08-18', '--paksa' => true])
            ->assertSuccessful();

        $this->assertSame(2, Purchase::whereDate('date', '2026-08-18')->count());
        $this->assertSame(0, Purchase::whereYear('date', 2028)->count());

        // Selnya dibetulkan di Excel, lalu berkasnya diimpor lagi.
        $this->importBook(['Expense' => $rows('18/8/2026')]);

        $this->assertSame(2, Purchase::count());
        $this->assertSame(78_000, (int) Purchase::sum('total'));
    }

    /** Kejadian nyata: bulan gaji dipilih Agustus 2025, bukan 2026. */
    public function test_gaji_salah_bulan_dipindah_lalu_impor_bulan_yang_benar_tidak_menggandakan(): void
    {
        $this->importBook(['Payroll' => [['Sinta', 3_000_000, 200_000, 2_800_000]]], month: '2025-08-01');

        $this->artisan('zeytin:pindah', ['jenis' => 'gaji', 'dari' => '2025-08', 'ke' => '2026-08', '--paksa' => true])
            ->assertSuccessful();

        $this->assertSame('2026-08-01', Payroll::sole()->month->toDateString());

        $this->importBook(['Payroll' => [['Sinta', 3_000_000, 200_000, 2_800_000]]], month: '2026-08-01');

        $this->assertSame(1, Payroll::count());
    }

    /**
     * Dua belanja yang isinya sama persis di hari tujuan tetap dua baris,
     * dengan nomor impor berbeda — sama seperti pengimpor memperlakukan
     * baris kembar dalam satu berkas.
     */
    public function test_baris_kembar_di_tanggal_tujuan_tetap_dua_baris(): void
    {
        $this->importBook(['Expense' => [
            ['18/8/2026', '', 'Aqua Galon', 1, '', 66_000, 0, 0, 66_000],
            ['18/8/2028', '', 'Aqua Galon', 1, '', 66_000, 0, 0, 66_000],
        ]]);

        $this->artisan('zeytin:pindah', ['jenis' => 'belanja', 'dari' => '2028-08-18', 'ke' => '2026-08-18', '--paksa' => true])
            ->assertSuccessful();

        $this->assertSame(2, Purchase::whereDate('date', '2026-08-18')->distinct()->count('import_key'));

        $this->importBook(['Expense' => [
            ['18/8/2026', '', 'Aqua Galon', 1, '', 66_000, 0, 0, 66_000],
            [null, '', 'Aqua Galon', 1, '', 66_000, 0, 0, 66_000],
        ]]);

        $this->assertSame(2, Purchase::count());
    }

    public function test_pemasukan_ke_tanggal_yang_sudah_terisi_ditolak(): void
    {
        DailyIncome::create(['date' => '2026-08-01', 'cash' => 1_000_000]);
        DailyIncome::create(['date' => '2026-08-02', 'cash' => 2_000_000]);

        $this->artisan('zeytin:pindah', ['jenis' => 'pemasukan', 'dari' => '2026-08-02', 'ke' => '2026-08-01', '--paksa' => true])
            ->assertFailed();

        $this->assertSame(2_000_000, (int) DailyIncome::whereDate('date', '2026-08-02')->value('cash'));
    }

    public function test_tidak_ada_baris_berarti_tidak_ada_yang_diubah(): void
    {
        $this->artisan('zeytin:pindah', ['jenis' => 'belanja', 'dari' => '2030-01-01', 'ke' => '2026-01-01', '--paksa' => true])
            ->assertSuccessful();

        $this->artisan('zeytin:pindah', ['jenis' => 'kasir', 'dari' => '2026-01-01', 'ke' => '2026-01-02'])
            ->assertFailed();
    }
}

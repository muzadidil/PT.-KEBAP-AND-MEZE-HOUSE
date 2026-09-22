<?php

namespace Tests\Feature\Zeytin;

use App\Models\DailyIncome;
use App\Models\OutstandingBill;
use App\Models\Payroll;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierTransfer;
use App\Support\Zeytin\RecordSource;
use App\Support\Zeytin\Workbook\Cells;
use App\Support\Zeytin\Workbook\Importer;
use App\Support\Zeytin\Workbook\SheetSpec;
use App\Support\Zeytin\Workbook\TemplateBuilder;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Pengimpor berkas Excel bulanan.
 *
 * Berkas ujinya dibangun dari template yang sama ditawarkan ke pengguna,
 * bukan dari berkas contoh yang disimpan di repo. Jadi kalau suatu saat
 * judul kolom di SheetSpec berubah tanpa templatenya ikut berubah, tes ini
 * yang gagal — bukan penggunanya yang menemukan saat mengunggah.
 */
class WorkbookImportTest extends TestCase
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

    /** Template kosong, siap diisi baris uji. */
    protected function book(): Spreadsheet
    {
        return (new TemplateBuilder)->build();
    }

    /** @param  array<string, array<int, array<int, mixed>>>  $sheets */
    protected function fill(array $sheets): string
    {
        $book = $this->book();

        foreach ($sheets as $name => $rows) {
            $book->getSheetByName($name)->fromArray($rows, null, 'A2');
        }

        (new Xlsx($book))->save($this->path);
        $book->disconnectWorksheets();

        return $this->path;
    }

    /** @return array<int, array<string, mixed>> */
    protected function import(?string $month = '2026-08-01'): array
    {
        return (new Importer($month ? Carbon::parse($month) : null))->import($this->path);
    }

    /** @return array<string, mixed> */
    protected function sheetResult(array $results, string $sheet): array
    {
        foreach ($results as $result) {
            if ($result['sheet'] === $sheet) {
                return $result;
            }
        }

        $this->fail("Sheet {$sheet} tidak ada di hasil impor.");
    }

    /* ------------------------------------------------------------ tanggal */

    public function test_serial_tanggal_excel_dibaca_benar(): void
    {
        // 46235 adalah 1 Agustus 2026 di penanggalan Excel, yang menghitung
        // dari 30 Desember 1899.
        $this->assertSame('2026-08-01', Cells::toDate(46235)->toDateString());
    }

    public function test_tanggal_ketikan_tangan_dibaca_hari_dulu(): void
    {
        // Bukan 8 Mei: urutan hari-dulu yang dipakai staf di berkas klien.
        $this->assertSame('2026-08-05', Cells::toDate('05/08/2026')->toDateString());
        $this->assertSame('2026-08-31', Cells::toDate('31/08/2026')->toDateString());
    }

    public function test_tanggal_mustahil_ditolak_bukan_digeser(): void
    {
        // Bukan 31 Januari 2027, yang dilakukan pengurai tanggal pada umumnya.
        $this->assertNull(Cells::toDate('31/13/2026'));

        // Februari tidak punya tanggal 31.
        $this->assertNull(Cells::toDate('31/02/2026'));
    }

    public function test_baris_tanpa_tanggal_mewarisi_tanggal_di_atasnya(): void
    {
        $this->fill(['Expense' => [
            ['01/08/2026', 'Pak Budi', 'Ayam', 10, 'kg', 35_000, 0, 0, 350_000],
            [null, 'Pak Budi', 'Cabai', 2, 'kg', 50_000, 0, 0, 100_000],
            ['02/08/2026', 'Bu Sari', 'Aqua', 3, 'galon', 20_000, 0, 0, 60_000],
        ]]);

        $this->import();

        $this->assertSame(3, Purchase::count());
        $this->assertSame(2, Purchase::whereDate('date', '2026-08-01')->count());
        $this->assertSame(1, Purchase::whereDate('date', '2026-08-02')->count());
    }

    /**
     * Rentang tanggal yang terbaca dilaporkan apa adanya. Salah ketik tahun
     * di satu sel tidak mengubah jumlah baris dan tidak memunculkan galat —
     * yang berubah cuma ujung rentangnya, dan itulah satu-satunya tanda.
     */
    public function test_rentang_tanggal_dilaporkan_sehingga_salah_ketik_tahun_kelihatan(): void
    {
        $this->fill(['Expense' => [
            ['01/08/2026', 'Pak Budi', 'Ayam', 1, 'kg', 35_000, 0, 0, 35_000],
            ['18/8/2028', 'Pak Budi', 'Cabai', 1, 'kg', 50_000, 0, 0, 50_000],
        ]]);

        $result = $this->sheetResult($this->import(), 'Expense');

        $this->assertSame(2, $result['imported']);
        $this->assertSame('2026-08-01 … 2028-08-18', $result['range']);
    }

    /* ----------------------------------------------------------- idempoten */

    public function test_mengimpor_berkas_yang_sama_dua_kali_tidak_menggandakan(): void
    {
        $this->fill([
            'Income' => [['01/08/2026', 100_000, 5_000_000, 1_000_000, 0, 0, 0, 6_000_000]],
            'Expense' => [['01/08/2026', 'Pak Budi', 'Ayam', 10, 'kg', 35_000, 0, 0, 350_000]],
        ]);

        $this->import();
        $this->import();
        $this->import();

        $this->assertSame(1, DailyIncome::count());
        $this->assertSame(1, Purchase::count());
        $this->assertSame(350_000, (int) Purchase::sum('total'));
    }

    public function test_dua_baris_kembar_dalam_satu_hari_tetap_dua_baris(): void
    {
        // Dua kali beli Aqua Galon di hari dan harga yang sama memang dua
        // baris yang sah, bukan satu baris yang tercatat dua kali.
        $this->fill(['Expense' => [
            ['01/08/2026', 'Bu Sari', 'Aqua Galon', 1, 'galon', 20_000, 0, 0, 20_000],
            [null, 'Bu Sari', 'Aqua Galon', 1, 'galon', 20_000, 0, 0, 20_000],
        ]]);

        $this->import();
        $this->import();

        $this->assertSame(2, Purchase::count());
    }

    public function test_membetulkan_satu_sel_lalu_impor_ulang_membuang_baris_lamanya(): void
    {
        $this->fill(['Expense' => [
            ['01/08/2026', 'Pak Budi', 'Ayam', 10, 'kg', 35_000, 0, 0, 350_000],
        ]]);

        $this->import();

        // Harganya dibetulkan di Excel, lalu berkasnya diimpor ulang.
        $this->fill(['Expense' => [
            ['01/08/2026', 'Pak Budi', 'Ayam', 10, 'kg', 40_000, 0, 0, 400_000],
        ]]);

        $result = $this->sheetResult($this->import(), 'Expense');

        // Satu baris, bukan dua yang berdampingan dengan angka berbeda.
        $this->assertSame(1, Purchase::count());
        $this->assertSame(400_000, (int) Purchase::sum('total'));
        $this->assertSame(1, $result['replaced']);
    }

    public function test_baris_yang_diketik_orang_tidak_pernah_disentuh_impor(): void
    {
        $typed = Purchase::create([
            'date' => '2026-08-01',
            'item' => 'Gas elpiji',
            'qty' => 1,
            'price' => 25_000,
            'source' => RecordSource::MANUAL,
        ]);

        $this->fill(['Expense' => [
            ['01/08/2026', 'Pak Budi', 'Ayam', 10, 'kg', 35_000, 0, 0, 350_000],
        ]]);

        $this->import();

        // Berkas Excel berwenang atas baris yang ia bawa sendiri, bukan atas
        // seluruh isi basis data.
        $this->assertDatabaseHas('purchases', ['id' => $typed->id, 'item' => 'Gas elpiji']);
        $this->assertSame(2, Purchase::count());
    }

    /* ------------------------------------------------------- sheet & header */

    public function test_sheet_yang_hilang_dilaporkan_bukan_didiamkan(): void
    {
        $book = $this->book();
        $book->removeSheetByIndex($book->getIndex($book->getSheetByName('Payroll')));
        (new Xlsx($book))->save($this->path);

        $this->assertSame('sheet_missing', $this->sheetResult($this->import(), 'Payroll')['error']);
    }

    public function test_sheet_yang_judul_kolomnya_tidak_dikenali_ditolak(): void
    {
        $book = $this->book();

        // Judul kolomnya diganti jadi sesuatu yang tidak dikenali pembaca.
        $book->getSheetByName('Expense')->fromArray([['Tanggal', 'Toko', 'Barang']], null, 'A1');
        (new Xlsx($book))->save($this->path);

        $result = $this->sheetResult($this->import(), 'Expense');

        // Ditolak, bukan ditebak posisi kolomnya.
        $this->assertSame('header_missing', $result['error']);
        $this->assertSame(0, $result['imported']);
        $this->assertSame(0, Purchase::count());
    }

    public function test_bulan_payroll_ditanyakan_bukan_ditebak(): void
    {
        $this->fill(['Payroll' => [['Sinta', 3_000_000, 200_000, 2_800_000]]]);

        $this->assertSame('month_missing', $this->sheetResult($this->import(month: null), 'Payroll')['error']);

        $this->import('2026-08-01');

        $this->assertSame('2026-08-01', Payroll::first()->month->toDateString());
    }

    /* --------------------------------------------------------------- isi */

    public function test_template_kosong_terbaca_utuh_oleh_pembacanya(): void
    {
        // Bolak-balik: template dibangun, diisi, lalu dibaca ulang. Keenam
        // sheet harus dikenali headernya — tidak boleh ada satu pun yang
        // gagal karena judul kolomnya meleset dari yang dicari.
        $this->fill([
            'Income' => [['01/08/2026', 100_000, 5_000_000, 1_000_000, 500_000, 250_000, 250_000, 7_000_000]],
            'Expense' => [['01/08/2026', 'Pak Budi', 'Ayam', 10, 'kg', 35_000, 0, 0, 350_000]],
            'Supplier Transfer Payment' => [['01/08/2026', 'CV Daging', 'Daging sapi', 'kg', 20, 120_000, 2_400_000, 'Transfer', 'PT KEBAP PAID']],
            'Outstanding INV' => [['01/08/2026', '10/08/2026', 'CV Sayur', 'Bayam', 'ikat', 50, 5_000, 0, 0, 'Need the payment']],
            'Supplier Database' => [['CV Daging', 'Pak Anto', 'Daging sapi', 120_000, 'BCA', '1234567890', 'Anto Wijaya', 'Transfer']],
            'Payroll' => [['Sinta', 3_000_000, 200_000, 2_800_000]],
        ]);

        foreach ($this->import() as $result) {
            $this->assertNull($result['error'], $result['sheet']);
            $this->assertSame(1, $result['imported'], $result['sheet']);
        }

        $this->assertSame(7_000_000, DailyIncome::first()->totalSales());
        $this->assertSame(350_000, Purchase::first()->total);
        $this->assertSame(2_400_000, SupplierTransfer::first()->total);
        $this->assertSame(250_000, OutstandingBill::first()->total);
        $this->assertSame('1234567890', Supplier::first()->bank_account);
        $this->assertSame(2_800_000, Payroll::first()->grand_total);
    }

    public function test_judul_kolom_template_sama_dengan_yang_dicari_pembacanya(): void
    {
        $book = IOFactory::load((new TemplateBuilder)->save($this->path));

        foreach (SheetSpec::all() as $spec) {
            $header = array_map(
                [Cells::class, 'norm'],
                $book->getSheetByName($spec->sheet)->toArray(null, true, false, false)[0],
            );

            foreach ($spec->required as $label) {
                $this->assertContains($label, $header, "{$spec->sheet}: {$label}");
            }
        }
    }

    public function test_pemasok_yang_sudah_ada_diisi_rekeningnya_bukan_diduplikasi(): void
    {
        $existing = Supplier::create([
            'name' => 'CV Daging',
            'contact_person' => 'Pak Anto',
            'phone' => '08123456789',
            'active' => true,
        ]);

        $this->fill(['Supplier Database' => [
            ['cv daging', 'Orang lain', 'Daging sapi', 120_000, 'BCA', '1234567890', 'Anto Wijaya', 'Transfer'],
        ]]);

        $this->import();

        $existing->refresh();

        $this->assertSame(1, Supplier::count());

        // Kolom rekening diisi …
        $this->assertSame('BCA', $existing->bank);
        $this->assertSame('1234567890', $existing->bank_account);

        // … tapi yang sudah diketik tidak diambil alih berkas Excel.
        $this->assertSame('Pak Anto', $existing->contact_person);
        $this->assertSame('08123456789', $existing->phone);
        $this->assertSame(RecordSource::MANUAL, $existing->source);
    }

    public function test_baris_impor_ditandai_asalnya(): void
    {
        $this->fill(['Expense' => [
            ['01/08/2026', 'Pak Budi', 'Ayam', 10, 'kg', 35_000, 0, 0, 350_000],
        ]]);

        $this->import();

        $purchase = Purchase::first();

        $this->assertSame(RecordSource::IMPORT, $purchase->source);
        $this->assertNotEmpty($purchase->import_key);
        $this->assertTrue($purchase->isImported());
    }
}

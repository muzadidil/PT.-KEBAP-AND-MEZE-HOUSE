<?php

namespace Tests\Feature\Zeytin;

use App\Models\DailyIncome;
use App\Models\OutstandingBill;
use App\Models\Payroll;
use App\Models\Purchase;
use App\Models\SupplierTransfer;
use App\Support\Zeytin\Channels;
use App\Support\Zeytin\DailyLedger;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Yang diuji di sini bukan "halaman terbuka", tapi hal-hal yang membuat
 * laporan salah kalau rusak — definisi kolom yang diambil dari rumus di
 * dalam berkas Excel klien.
 */
class DailyLedgerTest extends TestCase
{
    protected function income(string $date, array $amounts = []): DailyIncome
    {
        return DailyIncome::create([
            'date' => $date,
            ...array_fill_keys(Channels::keys(), 0),
            ...$amounts,
        ]);
    }

    protected function report(string $from = '2026-08-01', string $to = '2026-08-31'): array
    {
        return DailyLedger::periodReport(Carbon::parse($from), Carbon::parse($to));
    }

    public function test_total_sales_melewati_petty_cash(): void
    {
        $this->income('2026-08-01', [
            'petty_cash' => 100_000,
            'cash' => 5_000_000,
            'bni' => 1_000_000,
            'grab_food' => 500_000,
            'go_food' => 250_000,
            'go_pay' => 250_000,
        ]);

        $report = $this->report();

        // 7.000.000, bukan 7.100.000: rumus aslinya =SUM(D8:H8) memang mulai
        // dari kolom D dan melewati C.
        $this->assertSame(7_000_000, $report['total_sales']);
        $this->assertSame(100_000, $report['by_channel']['petty_cash']);
    }

    public function test_supplier_cash_mengikuti_rumus_aslinya(): void
    {
        $this->income('2026-08-01', ['cash' => 5_000_000]);

        $opening = (int) config('zeytin.supplier_cash_opening');
        $row = $this->report()['rows']->firstWhere('date.year', 2026);

        // =456560+D8
        $this->assertSame($opening + 5_000_000, $row['supplier_cash']);
    }

    public function test_sisa_titipan_adalah_titipan_dikurangi_belanja_hari_itu(): void
    {
        $this->income('2026-08-01', ['cash' => 5_000_000]);

        Purchase::create([
            'date' => '2026-08-01',
            'item' => 'Ayam',
            'qty' => 10,
            'price' => 35_000,
        ]);

        $opening = (int) config('zeytin.supplier_cash_opening');

        $this->assertSame($opening + 5_000_000 - 350_000, $this->report()['remaining_supplier_cash']);
    }

    /**
     * Pembagian tunai/nontunai mengikuti penanda `is_cash` di config, bukan
     * nama channel yang di-hardcode. Kalau suatu saat ada channel tunai
     * kedua, menandainya di config harus cukup.
     */
    public function test_nontunai_dihitung_dari_penanda_is_cash_bukan_nama_channel(): void
    {
        $this->income('2026-08-01', [
            'cash' => 4_000_000,
            'bni' => 1_000_000,
            'go_pay' => 1_000_000,
        ]);

        $this->assertSame(['cash'], Channels::cash());
        $this->assertSame(2_000_000, $this->report()['cashless']);
    }

    public function test_sisa_titipan_adalah_keadaan_hari_terakhir_bukan_jumlah_antar_hari(): void
    {
        $this->income('2026-08-01', ['cash' => 5_000_000]);
        $this->income('2026-08-02', ['cash' => 3_000_000]);

        $opening = (int) config('zeytin.supplier_cash_opening');

        // Bukan (opening + 5jt) + (opening + 3jt): menjumlahkan saldo tiap
        // hari tidak menghasilkan angka yang berarti apa pun.
        $this->assertSame($opening + 3_000_000, $this->report()['remaining_supplier_cash']);
    }

    public function test_hari_tanpa_penjualan_tetap_muncul_sebagai_baris_nol(): void
    {
        $this->income('2026-08-03', ['cash' => 1_000_000]);

        $report = $this->report('2026-08-01', '2026-08-05');

        $this->assertCount(5, $report['rows']);
        $this->assertSame(0, $report['rows'][0]['total_sales']);
        $this->assertSame(1, $report['recorded_days']);
    }

    public function test_transfer_dan_gaji_ikut_mengurangi_laba(): void
    {
        $this->income('2026-08-01', ['cash' => 10_000_000]);

        SupplierTransfer::create([
            'date' => '2026-08-01',
            'item' => 'Daging',
            'qty' => 1,
            'price' => 2_000_000,
        ]);

        Payroll::create([
            'month' => '2026-08-01',
            'name' => 'Sinta',
            'basic' => 3_000_000,
            'bpjs' => 200_000,
        ]);

        $report = $this->report();

        $this->assertSame(2_000_000, $report['transfers']);
        $this->assertSame(2_800_000, $report['payroll']);
        $this->assertSame(4_800_000, $report['total_expenses']);
        $this->assertSame(5_200_000, $report['net_profit']);
    }

    public function test_gaji_ikut_walau_rentangnya_tidak_mulai_dari_tanggal_satu(): void
    {
        Payroll::create([
            'month' => '2026-08-01',
            'name' => 'Sinta',
            'basic' => 3_000_000,
            'bpjs' => 0,
        ]);

        // Gaji dibayarkan sekali untuk sebulan, bukan per tanggal, jadi
        // membuka 5–20 Agustus tetap memuat payroll Agustus.
        $this->assertSame(3_000_000, $this->report('2026-08-05', '2026-08-20')['payroll']);
    }

    public function test_tagihan_yang_sudah_lunas_tidak_dihitung_sebagai_utang(): void
    {
        foreach (['PAID', 'Sudah lunas', 'settled', 'paid in full'] as $index => $status) {
            OutstandingBill::create([
                'date' => '2026-08-01',
                'item' => 'Tepung '.$index,
                'qty' => 1,
                'price' => 100_000,
                'status' => $status,
            ]);
        }

        OutstandingBill::create([
            'date' => '2026-08-01',
            'item' => 'Minyak',
            'qty' => 1,
            'price' => 500_000,
            'status' => 'Need the payment',
        ]);

        $this->assertSame(500_000, $this->report()['outstanding']);
    }

    /** Utang bulan lalu tetap utang hari ini, jadi tidak dibatasi rentang. */
    public function test_tagihan_di_luar_rentang_tetap_dihitung(): void
    {
        OutstandingBill::create([
            'date' => '2026-07-15',
            'item' => 'Minyak',
            'qty' => 1,
            'price' => 500_000,
            'status' => 'Waiting the payment',
        ]);

        $this->assertSame(500_000, $this->report('2026-08-01', '2026-08-31')['outstanding']);
    }

    /**
     * Pemasukan tunai 5 juta pernah muncul sebagai saldo global 10,4 juta,
     * karena sisa titipan belanja ikut ditambahkan — padahal rumusnya
     * `456.560 + tunai − belanja`, jadi uang tunai yang sama sudah terhitung
     * di Total Sales.
     */
    public function test_saldo_global_tidak_menghitung_uang_tunai_dua_kali(): void
    {
        $this->income('2026-08-01', ['cash' => 5_000_000]);

        $report = $this->report();

        $this->assertSame(5_000_000, $report['global_balance']);
        $this->assertGreaterThan(0, $report['remaining_supplier_cash']);
    }

    public function test_saldo_global_dikurangi_tagihan_yang_belum_dibayar(): void
    {
        $this->income('2026-08-01', ['cash' => 5_000_000]);

        OutstandingBill::create([
            'date' => '2026-08-01',
            'item' => 'Minyak',
            'qty' => 1,
            'price' => 1_000_000,
            'status' => 'Need the payment',
        ]);

        $this->assertSame(4_000_000, $this->report()['global_balance']);
    }

    public function test_rekap_bulanan_dan_tahunan_berjumlah_sama_dengan_harian(): void
    {
        $this->income('2026-08-31', ['cash' => 1_000_000, 'bni' => 500_000]);
        $this->income('2026-09-01', ['cash' => 2_000_000, 'go_pay' => 250_000]);

        $report = $this->report('2026-08-01', '2026-09-30');

        $monthly = DailyLedger::groupByMonth($report['rows']);
        $yearly = DailyLedger::groupByYear($report['rows']);

        $this->assertCount(2, $monthly);
        $this->assertCount(1, $yearly);

        $this->assertSame($report['total_sales'], $monthly->sum('total_sales'));
        $this->assertSame($report['total_sales'], $yearly->sum('total_sales'));

        // Per channel juga, bukan cuma totalnya.
        foreach (Channels::keys() as $key) {
            $this->assertSame($report['by_channel'][$key], $monthly->sum($key), $key);
        }

        $this->assertSame('2026-08', $monthly[0]['key']);
        $this->assertSame('2026', $yearly[0]['key']);
    }

    public function test_total_baris_belanja_mengikuti_rumus_excel(): void
    {
        // =((qty * price) + tax - disc)
        $this->assertSame(115_000, DailyLedger::lineTotal(10, 10_000, 20_000, 5_000));

        $purchase = Purchase::create([
            'date' => '2026-08-01',
            'item' => 'Ayam',
            'qty' => 10,
            'price' => 10_000,
            'tax' => 20_000,
            'disc' => 5_000,
        ]);

        // Dihitung di model, jadi total yang dikirim formulir pun tidak bisa
        // berbeda dari rumusnya.
        $this->assertSame(115_000, $purchase->fresh()->total);
    }

    public function test_status_lunas_dikenali_apa_pun_ejaannya(): void
    {
        foreach (['PAID', 'paid', 'Sudah Lunas', 'settled'] as $status) {
            $this->assertTrue(DailyLedger::isSettled($status), $status);
        }

        foreach (['Need the payment', 'Waiting the payment', '', null] as $status) {
            $this->assertFalse(DailyLedger::isSettled($status), var_export($status, true));
        }
    }
}

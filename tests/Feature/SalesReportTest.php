<?php

namespace Tests\Feature;

use App\Enums\SaleSource;
use App\Models\Sale;
use App\Support\Ledger;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Invarian laporan penjualan: keempat laporan berkala menjumlahkan agregat
 * harian yang sama, jadi untuk rentang yang sama totalnya wajib identik.
 * Kalau tes ini gagal, ada laporan yang menghitung sendiri di luar Ledger.
 */
class SalesReportTest extends TestCase
{
    /** Membuat penjualan tanpa lewat halaman kasir, untuk menyiapkan data. */
    protected function sell(string $date, string $channel, int $amount, SaleSource $source = SaleSource::Pos): Sale
    {
        return Sale::create([
            'code' => $source === SaleSource::Pos ? Sale::nextCode(Carbon::parse($date)) : null,
            'sold_on' => $date,
            'channel' => $channel,
            'source' => $source,
            'subtotal' => $amount,
            'total' => $amount,
            'paid' => $amount,
        ]);
    }

    public function test_penjualan_harian_dipecah_per_channel(): void
    {
        $this->sell('2026-09-18', 'cash', 500_000);
        $this->sell('2026-09-18', 'cash', 250_000);
        $this->sell('2026-09-18', 'cashless', 300_000);
        $this->sell('2026-09-18', 'grab', 150_000);

        $day = Carbon::parse('2026-09-18');
        $rows = Ledger::daily($day, $day);

        $this->assertCount(1, $rows);
        $this->assertSame(750_000, $rows[0]['cash']);
        $this->assertSame(300_000, $rows[0]['cashless']);
        $this->assertSame(150_000, $rows[0]['grab']);
        $this->assertSame(1_200_000, $rows[0]['total']);
        $this->assertSame(4, $rows[0]['transactions']);
    }

    public function test_hari_tanpa_penjualan_tetap_muncul_sebagai_nol(): void
    {
        $this->sell('2026-09-18', 'cash', 500_000);

        $rows = Ledger::daily(Carbon::parse('2026-09-16'), Carbon::parse('2026-09-20'));

        // Hari yang tutup dan hari yang lupa dicatat harus sama-sama terlihat.
        $this->assertCount(5, $rows);
        $this->assertSame(0, $rows[0]['total']);
        $this->assertSame(500_000, $rows[2]['total']);
        $this->assertSame(0, $rows[4]['total']);
    }

    public function test_rekap_harian_dan_transaksi_kasir_sama_sama_terhitung(): void
    {
        $this->sell('2026-09-18', 'cash', 400_000, SaleSource::Pos);
        $this->sell('2026-09-18', 'cash', 600_000, SaleSource::Quick);

        $day = Carbon::parse('2026-09-18');

        $this->assertSame(1_000_000, Ledger::salesSummary($day, $day)['cash']);
    }

    public function test_keempat_laporan_berkala_menghasilkan_total_yang_sama(): void
    {
        // Sebar penjualan ke beberapa bulan dan dua tahun sekaligus, supaya
        // pengelompokan minggu, bulan, dan tahun benar-benar teruji.
        $data = [
            ['2025-12-30', 'cash', 111_000],
            ['2025-12-31', 'grab', 222_000],
            ['2026-01-01', 'cashless', 333_000],
            ['2026-01-15', 'cash', 444_000],
            ['2026-02-28', 'grab', 555_000],
            ['2026-06-10', 'cash', 666_000],
            ['2026-09-18', 'cashless', 777_000],
            ['2026-09-20', 'cash', 888_000],
        ];

        foreach ($data as [$date, $channel, $amount]) {
            $this->sell($date, $channel, $amount);
        }

        $from = Carbon::parse('2025-01-01');
        $to = Carbon::parse('2026-12-31');
        $expected = array_sum(array_column($data, 2));

        foreach (['daily', 'weekly', 'monthly', 'yearly'] as $grain) {
            $rows = Ledger::$grain($from, $to);

            $this->assertSame(
                $expected,
                (int) $rows->sum('total'),
                "Laporan {$grain} tidak berjumlah sama dengan yang lain",
            );

            // Tiap channel juga harus cocok, bukan hanya totalnya.
            foreach (['cash', 'cashless', 'grab'] as $channel) {
                $this->assertSame(
                    array_sum(array_map(
                        fn (array $row) => $row[1] === $channel ? $row[2] : 0,
                        $data,
                    )),
                    (int) $rows->sum($channel),
                    "Channel {$channel} tidak cocok di laporan {$grain}",
                );
            }
        }
    }

    public function test_pengelompokan_bulanan_memisahkan_bulan_dengan_benar(): void
    {
        $this->sell('2026-01-31', 'cash', 100_000);
        $this->sell('2026-02-01', 'cash', 200_000);

        $rows = Ledger::monthly(Carbon::parse('2026-01-01'), Carbon::parse('2026-02-28'));

        $this->assertCount(2, $rows);
        $this->assertSame('2026-01', $rows[0]['key']);
        $this->assertSame(100_000, $rows[0]['total']);
        $this->assertSame('2026-02', $rows[1]['key']);
        $this->assertSame(200_000, $rows[1]['total']);
    }

    public function test_pengelompokan_mingguan_memakai_minggu_iso(): void
    {
        // 2026-01-01 jatuh di minggu ISO terakhir tahun 2025.
        $this->sell('2026-01-01', 'cash', 100_000);

        $rows = Ledger::weekly(Carbon::parse('2025-12-01'), Carbon::parse('2026-01-31'));

        $this->assertCount(1, $rows);
        $this->assertSame(100_000, $rows[0]['total']);
        $this->assertTrue($rows[0]['start']->lte(Carbon::parse('2026-01-01')));
        $this->assertTrue($rows[0]['end']->gte(Carbon::parse('2026-01-01')));
    }

    public function test_penjualan_di_luar_rentang_tidak_ikut_terhitung(): void
    {
        $this->sell('2026-09-17', 'cash', 999_000);
        $this->sell('2026-09-18', 'cash', 100_000);
        $this->sell('2026-09-19', 'cash', 999_000);

        $day = Carbon::parse('2026-09-18');

        $this->assertSame(100_000, Ledger::salesSummary($day, $day)['total']);
    }
}

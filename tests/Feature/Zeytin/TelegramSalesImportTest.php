<?php

namespace Tests\Feature\Zeytin;

use App\Models\DailyIncome;
use App\Support\Zeytin\TelegramSalesImporter;
use Tests\TestCase;

/**
 * Impor penjualan harian dari pesan Telegram. Yang dijaga: angka masuk ke
 * channel yang benar, tidak ada yang ditimpa, dan satu kesalahan berarti
 * tidak ada yang tersimpan.
 */
class TelegramSalesImportTest extends TestCase
{
    protected const MESSAGE = "Penjualan 26 Sep 2026\nPetty Cash: 0\nCash: 1.545.390\nBNI: 9.320.850\nGrab Food: 207.900\nGo Food: 0\nGo Pay: 0\nTotal: 11.074.140";

    protected function day(string $date): ?DailyIncome
    {
        return DailyIncome::query()->whereDate('date', $date)->first();
    }

    public function test_pesan_masuk_ke_channel_yang_benar(): void
    {
        $report = (new TelegramSalesImporter)->import(self::MESSAGE);

        $this->assertTrue($report->ok());
        $this->assertSame(1, $report->counts['created']);

        $row = $this->day('2026-09-26');
        $this->assertSame(1545390, (int) $row->cash);
        $this->assertSame(9320850, (int) $row->bni);
        $this->assertSame(207900, (int) $row->grab_food);
        $this->assertSame(0, (int) $row->go_food);
    }

    public function test_beberapa_hari_dan_obrolan_biasa_diabaikan(): void
    {
        $text = "Halo semua\n".self::MESSAGE."\n\nok makasih\nPenjualan 27-09-2026\nCash: 98.175\nBNI: 5.270.265\nGrab Food: 594.825";

        $report = (new TelegramSalesImporter)->import($text);

        $this->assertTrue($report->ok());
        $this->assertSame(2, $report->counts['created']);
        $this->assertSame(98175, (int) $this->day('2026-09-27')->cash);
        $this->assertSame(0, (int) $this->day('2026-09-27')->go_pay);
    }

    public function test_tanggal_yang_sudah_ada_dilewati_dan_tidak_ditimpa(): void
    {
        DailyIncome::create(['date' => '2026-09-26', 'cash' => 111]);

        $report = (new TelegramSalesImporter)->import(self::MESSAGE);

        $this->assertTrue($report->ok());
        $this->assertSame(1, $report->counts['skipped']);
        $this->assertSame(111, (int) $this->day('2026-09-26')->cash);
        $this->assertSame(1, DailyIncome::count());
    }

    public function test_mengimpor_dua_kali_tidak_menggandakan(): void
    {
        (new TelegramSalesImporter)->import(self::MESSAGE);
        (new TelegramSalesImporter)->import(self::MESSAGE);

        $this->assertSame(1, DailyIncome::count());
    }

    public function test_nama_channel_tidak_dikenal_membatalkan_semuanya(): void
    {
        $report = (new TelegramSalesImporter)->import("Penjualan 26 Sep 2026\nCash: 1.000\nShopeeFood: 5.000");

        $this->assertFalse($report->ok());
        $this->assertStringContainsString('ShopeeFood', $report->errors[0]);
        $this->assertSame(0, DailyIncome::count());
    }

    public function test_satu_hari_salah_membatalkan_hari_lain(): void
    {
        $report = (new TelegramSalesImporter)->import(self::MESSAGE."\nPenjualan 27 Sep 2026\nCash: 1.5");

        $this->assertFalse($report->ok());
        $this->assertSame(0, DailyIncome::count());
    }

    public function test_total_yang_tidak_cocok_ditolak(): void
    {
        $report = (new TelegramSalesImporter)->import("Penjualan 26 Sep 2026\nCash: 1.000\nBNI: 2.000\nTotal: 9.999");

        $this->assertFalse($report->ok());
        $this->assertSame(0, DailyIncome::count());
    }

    public function test_petty_cash_tidak_ikut_total(): void
    {
        $report = (new TelegramSalesImporter)->import("Penjualan 26 Sep 2026\nPetty Cash: 500.000\nCash: 1.000\nTotal: 1.000");

        $this->assertTrue($report->ok());
        $this->assertSame(500000, (int) $this->day('2026-09-26')->petty_cash);
    }

    public function test_format_angka_rp_dan_koma_ribuan(): void
    {
        $report = (new TelegramSalesImporter)->import("Penjualan 26 September 2026\nCash: Rp 1,545,390\nBNI = Rp. 2.000\nGo Pay: 3000");

        $this->assertTrue($report->ok());
        $row = $this->day('2026-09-26');
        $this->assertSame(1545390, (int) $row->cash);
        $this->assertSame(2000, (int) $row->bni);
        $this->assertSame(3000, (int) $row->go_pay);
    }

    public function test_pesan_dikirim_dua_kali_pesan_terakhir_dipakai(): void
    {
        $report = (new TelegramSalesImporter)->import("Penjualan 26 Sep 2026\nCash: 1.000\nPenjualan 26 Sep 2026\nCash: 2.000");

        $this->assertTrue($report->ok());
        $this->assertSame(2000, (int) $this->day('2026-09-26')->cash);
        $this->assertSame(1, DailyIncome::count());
    }

    public function test_berkas_json_ekspor_telegram(): void
    {
        $json = json_encode(['name' => 'Laporan', 'messages' => [
            ['id' => 1, 'type' => 'message', 'text' => 'Selamat pagi'],
            ['id' => 2, 'type' => 'message', 'text' => self::MESSAGE],
            ['id' => 3, 'type' => 'message', 'text' => [
                "Penjualan 27 Sep 2026\n",
                ['type' => 'bold', 'text' => "Cash: 98.175\n"],
                'BNI: 5.270.265',
            ]],
        ]]);

        $report = (new TelegramSalesImporter)->import($json);

        $this->assertTrue($report->ok());
        $this->assertSame(2, $report->counts['created']);
        $this->assertSame(5270265, (int) $this->day('2026-09-27')->bni);
    }

    public function test_tanpa_pesan_penjualan_gagal(): void
    {
        $report = (new TelegramSalesImporter)->import('Halo, tidak ada laporan di sini.');

        $this->assertFalse($report->ok());
    }

    public function test_json_rusak_ditolak(): void
    {
        $report = (new TelegramSalesImporter)->import('{"messages": [');

        $this->assertFalse($report->ok());
    }
}

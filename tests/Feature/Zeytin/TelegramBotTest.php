<?php

namespace Tests\Feature\Zeytin;

use App\Models\DailyIncome;
use App\Models\Setting;
use App\Support\Telegram\TelegramBot;
use App\Support\Telegram\TelegramSalesPoller;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Bot Telegram: hanya grup yang diizinkan yang dibaca, tiap pesan diproses
 * sendiri, balasan dikirim, dan pesan tidak dibaca dua kali.
 */
class TelegramBotTest extends TestCase
{
    protected const REPORT = "Penjualan 26 Sep 2026\nCash: 1.000\nBNI: 2.000";

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'telegram.token' => 'TESTTOKEN',
            'telegram.chat_ids' => ['-100'],
            'telegram.user_ids' => [],
            'telegram.reply' => true,
        ]);
    }

    /** @param  array<int, array<string, mixed>>  $messages */
    protected function updates(array $messages): void
    {
        $result = [];

        foreach ($messages as $i => $m) {
            $result[] = ['update_id' => 500 + $i, 'message' => $m + ['message_id' => 10 + $i, 'from' => ['id' => 7]]];
        }

        Http::fake([
            '*/getUpdates*' => Http::response(['ok' => true, 'result' => $result]),
            '*/sendMessage' => Http::response(['ok' => true]),
        ]);
    }

    protected function run_(bool $discover = false): array
    {
        return (new TelegramSalesPoller(new TelegramBot))->run($discover);
    }

    protected function sent(): array
    {
        return Http::recorded()
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'sendMessage'))
            ->map(fn ($pair) => $pair[0]->data()['text'])
            ->values()
            ->all();
    }

    public function test_laporan_dari_grup_yang_diizinkan_dicatat_dan_dibalas(): void
    {
        $this->updates([['chat' => ['id' => -100], 'text' => self::REPORT]]);

        $result = $this->run_();

        $this->assertSame(1, $result['recorded']);
        $this->assertSame(1000, (int) DailyIncome::query()->whereDate('date', '2026-09-26')->first()->cash);
        $this->assertStringContainsString('✅', $this->sent()[0]);
    }

    public function test_grup_lain_diabaikan(): void
    {
        $this->updates([['chat' => ['id' => -999], 'text' => self::REPORT]]);

        $result = $this->run_();

        $this->assertSame(1, $result['ignored']);
        $this->assertSame(0, DailyIncome::count());
        $this->assertSame([], $this->sent());
    }

    public function test_daftar_grup_kosong_tidak_membaca_apa_pun(): void
    {
        config(['telegram.chat_ids' => []]);
        $this->updates([['chat' => ['id' => -100], 'text' => self::REPORT]]);

        $this->run_();

        $this->assertSame(0, DailyIncome::count());
    }

    public function test_pengirim_di_luar_daftar_user_diabaikan(): void
    {
        config(['telegram.user_ids' => ['999']]);
        $this->updates([['chat' => ['id' => -100], 'text' => self::REPORT]]);

        $this->run_();

        $this->assertSame(0, DailyIncome::count());
    }

    public function test_obrolan_biasa_diabaikan_tanpa_balasan(): void
    {
        $this->updates([['chat' => ['id' => -100], 'text' => 'selamat pagi semua']]);

        $result = $this->run_();

        $this->assertSame(1, $result['ignored']);
        $this->assertSame([], $this->sent());
    }

    public function test_pesan_salah_dibalas_alasan_dan_tidak_menahan_pesan_lain(): void
    {
        $this->updates([
            ['chat' => ['id' => -100], 'text' => "Penjualan 25 Sep 2026\nShopeeFood: 5.000"],
            ['chat' => ['id' => -100], 'text' => self::REPORT],
        ]);

        $result = $this->run_();

        $this->assertSame(1, $result['failed']);
        $this->assertSame(1, $result['recorded']);
        $this->assertSame(1, DailyIncome::count());
        $this->assertStringContainsString('❌', $this->sent()[0]);
        $this->assertStringContainsString('ShopeeFood', $this->sent()[0]);
    }

    public function test_tanggal_yang_sudah_ada_dilewati_dengan_peringatan(): void
    {
        DailyIncome::create(['date' => '2026-09-26', 'cash' => 111]);
        $this->updates([['chat' => ['id' => -100], 'text' => self::REPORT]]);

        $result = $this->run_();

        $this->assertSame(1, $result['skipped']);
        $this->assertSame(111, (int) DailyIncome::first()->cash);
        $this->assertStringContainsString('⚠️', $this->sent()[0]);
    }

    public function test_offset_maju_dan_dipakai_pada_permintaan_berikutnya(): void
    {
        $this->updates([['chat' => ['id' => -100], 'text' => self::REPORT]]);
        $this->run_();

        $this->assertSame(501, (int) Setting::get('telegram.offset'));

        $this->run_();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'getUpdates') && ($r->data()['offset'] ?? null) === 501);
    }

    public function test_mode_cari_id_grup_tidak_mencatat_dan_tidak_memajukan_offset(): void
    {
        $this->updates([['chat' => ['id' => -100, 'title' => 'Laporan Zeytin'], 'text' => self::REPORT]]);

        $result = $this->run_(true);

        $this->assertSame(['-100' => 'Laporan Zeytin'], $result['chats']);
        $this->assertSame(0, DailyIncome::count());
        $this->assertNull(Setting::get('telegram.offset'));
    }

    public function test_balasan_bisa_dimatikan(): void
    {
        config(['telegram.reply' => false]);
        $this->updates([['chat' => ['id' => -100], 'text' => self::REPORT]]);

        $this->run_();

        $this->assertSame(1, DailyIncome::count());
        $this->assertSame([], $this->sent());
    }

    public function test_token_tidak_ikut_tercetak_saat_koneksi_gagal(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error for https://api.telegram.org/botTESTTOKEN/getUpdates'));

        try {
            $this->run_();
            $this->fail('Seharusnya melempar.');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('TESTTOKEN', $e->getMessage());
        }
    }

    public function test_perintah_menolak_jalan_tanpa_token_atau_tanpa_grup(): void
    {
        config(['telegram.token' => null]);
        $this->artisan('telegram:fetch')->assertFailed();

        config(['telegram.token' => 'X', 'telegram.chat_ids' => []]);
        $this->artisan('telegram:fetch')->assertFailed();
    }
}

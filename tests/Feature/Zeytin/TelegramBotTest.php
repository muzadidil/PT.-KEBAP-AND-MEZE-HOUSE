<?php

namespace Tests\Feature\Zeytin;

use App\Filament\Admin\Resources\Zeytin\TelegramReports\Pages\ManageTelegramReports;
use App\Models\DailyIncome;
use App\Models\TelegramReport;
use App\Models\Setting;
use App\Support\Telegram\TelegramBot;
use App\Support\Telegram\TelegramSalesPoller;
use Illuminate\Http\Client\Request;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Bot Telegram: hanya grup yang diizinkan yang dibaca, tiap pesan diproses
 * sendiri, laporan masuk ANTREAN (tidak langsung ke pembukuan), balasan
 * dikirim, dan pesan tidak dibaca dua kali.
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

    protected const PLAIN = "1. Cash: Rp158.975\n2. BNI: Rp4.802.400\n3. Grab: Rp1.518.825";

    protected function ts(string $utc): int
    {
        return \Illuminate\Support\Carbon::parse($utc, 'UTC')->timestamp;
    }

    protected function pending(): TelegramReport
    {
        return TelegramReport::query()->firstOrFail();
    }

    public function test_laporan_masuk_antrean_dan_belum_ke_pembukuan(): void
    {
        $this->updates([['chat' => ['id' => -100], 'text' => self::REPORT]]);

        $result = $this->run_();

        $this->assertSame(1, $result['queued']);
        $this->assertSame(0, DailyIncome::count());

        $report = $this->pending();
        $this->assertSame(TelegramReport::PENDING, $report->status);
        $this->assertSame('2026-09-26', $report->report_date->toDateString());
        $this->assertSame(1000, $report->amounts['cash']);
        $this->assertSame(3000, $report->total());
        $this->assertStringContainsString('📥', $this->sent()[0]);
    }

    public function test_pesan_tanpa_tanggal_masuk_ke_hari_pesan_dikirim(): void
    {
        // 17 Okt 2026 23:30 WITA = 15:30 UTC
        $this->updates([['chat' => ['id' => -100], 'date' => $this->ts('2026-10-17 15:30:00'), 'text' => self::PLAIN]]);

        $this->run_();

        $report = $this->pending();
        $this->assertSame('2026-10-17', $report->report_date->toDateString());
        $this->assertSame(158975, $report->amounts['cash']);
        $this->assertSame(4802400, $report->amounts['bni']);
        $this->assertSame(1518825, $report->amounts['grab_food']);
    }

    public function test_lewat_tengah_malam_waktu_setempat_masuk_hari_berikutnya(): void
    {
        // 16:30 UTC = 00:30 WITA tanggal 18
        $this->updates([['chat' => ['id' => -100], 'date' => $this->ts('2026-10-17 16:30:00'), 'text' => self::PLAIN]]);

        $this->run_();

        $this->assertSame('2026-10-18', $this->pending()->report_date->toDateString());
    }

    public function test_judul_tanggal_mengalahkan_tanggal_kirim(): void
    {
        $this->updates([['chat' => ['id' => -100], 'date' => $this->ts('2026-10-17 05:00:00'), 'text' => self::REPORT]]);

        $this->run_();

        $this->assertSame('2026-09-26', $this->pending()->report_date->toDateString());
    }

    public function test_pesan_salah_dibalas_alasan_dan_tidak_menahan_pesan_lain(): void
    {
        $this->updates([
            ['chat' => ['id' => -100], 'text' => "Penjualan 25 Sep 2026\nShopeeFood: 5.000"],
            ['chat' => ['id' => -100], 'text' => self::REPORT],
        ]);

        $result = $this->run_();

        $this->assertSame(1, $result['failed']);
        $this->assertSame(1, $result['queued']);
        $this->assertSame(1, TelegramReport::count());
        $this->assertStringContainsString('❌', $this->sent()[0]);
        $this->assertStringContainsString('ShopeeFood', $this->sent()[0]);
    }

    public function test_pesan_tanpa_tanggal_dengan_nama_salah_ditolak_dan_obrolan_dengan_titik_dua_diabaikan(): void
    {
        $this->updates([
            ['chat' => ['id' => -100], 'date' => time(), 'text' => "Cash: 1.000\nShopeeFood: 5.000"],
            ['chat' => ['id' => -100], 'date' => time(), 'text' => 'ok: 5'],
            ['chat' => ['id' => -100], 'date' => time(), 'text' => 'Jam buka: 10'],
        ]);

        $result = $this->run_();

        $this->assertSame(1, $result['failed']);
        $this->assertSame(2, $result['ignored']);
        $this->assertSame(0, TelegramReport::count());
    }

    public function test_tanggal_yang_sudah_ada_tetap_masuk_antrean_dengan_peringatan(): void
    {
        DailyIncome::create(['date' => '2026-09-26', 'cash' => 111]);
        $this->updates([['chat' => ['id' => -100], 'text' => self::REPORT]]);

        $this->run_();

        $this->assertTrue($this->pending()->dateTaken());
        $this->assertStringContainsString('⚠️', $this->sent()[0]);
        $this->assertSame(111, (int) DailyIncome::first()->cash);
    }

    public function test_grup_lain_diabaikan(): void
    {
        $this->updates([['chat' => ['id' => -999], 'text' => self::REPORT]]);

        $result = $this->run_();

        $this->assertSame(1, $result['ignored']);
        $this->assertSame(0, TelegramReport::count());
        $this->assertSame([], $this->sent());
    }

    public function test_daftar_grup_kosong_tidak_membaca_apa_pun(): void
    {
        config(['telegram.chat_ids' => []]);
        $this->updates([['chat' => ['id' => -100], 'text' => self::REPORT]]);

        $this->run_();

        $this->assertSame(0, TelegramReport::count());
    }

    public function test_pengirim_di_luar_daftar_user_diabaikan(): void
    {
        config(['telegram.user_ids' => ['999']]);
        $this->updates([['chat' => ['id' => -100], 'text' => self::REPORT]]);

        $this->run_();

        $this->assertSame(0, TelegramReport::count());
    }

    public function test_obrolan_biasa_diabaikan_tanpa_balasan(): void
    {
        $this->updates([['chat' => ['id' => -100], 'text' => 'selamat pagi semua']]);

        $result = $this->run_();

        $this->assertSame(1, $result['ignored']);
        $this->assertSame([], $this->sent());
    }

    public function test_offset_maju_dan_pesan_yang_sama_tidak_masuk_dua_kali(): void
    {
        $this->updates([['chat' => ['id' => -100], 'text' => self::REPORT]]);
        $this->run_();

        $this->assertSame(501, (int) Setting::get('telegram.offset'));

        $this->run_();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'getUpdates') && ($r->data()['offset'] ?? null) === 501);
        $this->assertSame(1, TelegramReport::count());
    }

    public function test_mode_cari_id_grup_tidak_menerima_dan_tidak_memajukan_offset(): void
    {
        $this->updates([['chat' => ['id' => -100, 'title' => 'Laporan Zeytin'], 'text' => self::REPORT]]);

        $result = $this->run_(true);

        $this->assertSame(['-100' => 'Laporan Zeytin'], $result['chats']);
        $this->assertSame(0, TelegramReport::count());
        $this->assertNull(Setting::get('telegram.offset'));
    }

    public function test_balasan_bisa_dimatikan(): void
    {
        config(['telegram.reply' => false]);
        $this->updates([['chat' => ['id' => -100], 'text' => self::REPORT]]);

        $this->run_();

        $this->assertSame(1, TelegramReport::count());
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

    public function test_dua_proses_bersamaan_tidak_membaca_dua_kali(): void
    {
        $lock = Cache::lock('telegram-poll', 120);
        $this->assertTrue($lock->get());
        $this->updates([['chat' => ['id' => -100], 'text' => self::REPORT]]);

        try {
            $this->run_();
            $this->fail('Seharusnya menolak karena sedang dibaca proses lain.');
        } catch (\RuntimeException) {
            $this->assertSame(0, TelegramReport::count());
        } finally {
            $lock->release();
        }
    }

    // — Menu konfirmasi (Antrean Telegram) —

    protected function queued(): TelegramReport
    {
        $this->updates([['chat' => ['id' => -100], 'message_id' => 42, 'sender_x' => 1, 'date' => $this->ts('2026-10-17 05:00:00'), 'text' => self::PLAIN, 'from' => ['id' => 7, 'first_name' => 'Kasir']]]);
        $this->run_();

        return $this->pending();
    }

    public function test_tombol_tarik_memasukkan_ke_antrean(): void
    {
        Filament::setCurrentPanel('admin');
        $this->updates([['chat' => ['id' => -100], 'date' => time(), 'text' => self::PLAIN]]);

        Livewire::actingAs($this->admin())
            ->test(ManageTelegramReports::class)
            ->assertActionExists('telegramPull')
            ->callAction('telegramPull')
            ->assertNotified(__('telegram.pull.done'));

        $this->assertSame(1, TelegramReport::count());
        $this->assertSame(0, DailyIncome::count());
    }

    public function test_tombol_tarik_tanpa_id_grup_hanya_menampilkan_id(): void
    {
        Filament::setCurrentPanel('admin');
        config(['telegram.chat_ids' => []]);
        $this->updates([['chat' => ['id' => -100, 'title' => 'Laporan Zeytin'], 'date' => time(), 'text' => self::PLAIN]]);

        Livewire::actingAs($this->admin())
            ->test(ManageTelegramReports::class)
            ->callAction('telegramPull')
            ->assertNotified(__('telegram.pull.no_chat_ids'));

        $this->assertSame(0, TelegramReport::count());
    }

    public function test_tombol_tarik_tanpa_token_memberi_tahu(): void
    {
        Filament::setCurrentPanel('admin');
        config(['telegram.token' => null]);

        Livewire::actingAs($this->admin())
            ->test(ManageTelegramReports::class)
            ->callAction('telegramPull')
            ->assertNotified(__('telegram.pull.failed'));
    }

    public function test_konfirmasi_mencatat_ke_pemasukan_harian_dan_membalas_di_grup(): void
    {
        Filament::setCurrentPanel('admin');
        $report = $this->queued();

        Livewire::actingAs($this->admin())
            ->test(ManageTelegramReports::class)
            ->callTableAction('record', $report, [
                'date' => '2026-10-17',
                'amt_petty_cash' => 0,
                'amt_cash' => 158975,
                'amt_bni' => 4802400,
                'amt_grab_food' => 1518825,
                'amt_go_food' => 0,
                'amt_go_pay' => 0,
            ])
            ->assertNotified(__('telegram.action.recorded'));

        $row = DailyIncome::query()->whereDate('date', '2026-10-17')->firstOrFail();
        $this->assertSame(158975, (int) $row->cash);
        $this->assertSame(1518825, (int) $row->grab_food);
        $this->assertSame(TelegramReport::RECORDED, $report->refresh()->status);
        $this->assertStringContainsString('✅', collect($this->sent())->last());
    }

    public function test_konfirmasi_boleh_mengubah_tanggal_dan_angka(): void
    {
        Filament::setCurrentPanel('admin');
        $report = $this->queued();

        Livewire::actingAs($this->admin())
            ->test(ManageTelegramReports::class)
            ->callTableAction('record', $report, [
                'date' => '2026-10-16',
                'amt_petty_cash' => 0,
                'amt_cash' => 200000,
                'amt_bni' => 0,
                'amt_grab_food' => 0,
                'amt_go_food' => 0,
                'amt_go_pay' => 0,
            ]);

        $this->assertNull(DailyIncome::query()->whereDate('date', '2026-10-17')->first());
        $this->assertSame(200000, (int) DailyIncome::query()->whereDate('date', '2026-10-16')->first()->cash);
        $this->assertSame('2026-10-16', $report->refresh()->report_date->toDateString());
    }

    public function test_konfirmasi_ditolak_kalau_tanggal_sudah_terisi_dan_tidak_menimpa(): void
    {
        Filament::setCurrentPanel('admin');
        $report = $this->queued();
        DailyIncome::create(['date' => '2026-10-17', 'cash' => 111]);

        Livewire::actingAs($this->admin())
            ->test(ManageTelegramReports::class)
            ->callTableAction('record', $report, [
                'date' => '2026-10-17',
                'amt_petty_cash' => 0,
                'amt_cash' => 999,
                'amt_bni' => 0,
                'amt_grab_food' => 0,
                'amt_go_food' => 0,
                'amt_go_pay' => 0,
            ])
            ->assertNotified(__('telegram.action.taken'));

        $this->assertSame(111, (int) DailyIncome::first()->cash);
        $this->assertSame(1, DailyIncome::count());
        $this->assertSame(TelegramReport::PENDING, $report->refresh()->status);
    }

    public function test_tolak_tidak_mencatat_dan_memberi_tahu_pengirim(): void
    {
        Filament::setCurrentPanel('admin');
        $report = $this->queued();

        Livewire::actingAs($this->admin())
            ->test(ManageTelegramReports::class)
            ->callTableAction('ignore', $report);

        $this->assertSame(TelegramReport::IGNORED, $report->refresh()->status);
        $this->assertSame(0, DailyIncome::count());
        $this->assertStringContainsString('🚫', collect($this->sent())->last());
    }

    public function test_kasir_tidak_bisa_membuka_antrean(): void
    {
        Filament::setCurrentPanel('admin');

        $this->actingAs($this->cashier())
            ->get(\App\Filament\Admin\Resources\Zeytin\TelegramReports\TelegramReportResource::getUrl())
            ->assertForbidden();
    }
}

<?php

namespace App\Console\Commands;

use App\Support\Telegram\TelegramBot;
use App\Support\Telegram\TelegramSalesPoller;
use Illuminate\Console\Command;

/** Baca pesan penjualan harian dari grup Telegram ke antrean konfirmasi. Dijadwalkan lewat cron. */
class FetchTelegramSales extends Command
{
    protected $signature = 'telegram:fetch {--chats : hanya tampilkan id grup yang terlihat (tidak mencatat apa pun)}';

    protected $description = 'Baca pesan penjualan harian di grup Telegram dan masukkan ke Antrean Telegram';

    public function handle(): int
    {
        $bot = new TelegramBot;

        if (! $bot->configured()) {
            $this->error('TELEGRAM_BOT_TOKEN belum diisi di .env.');

            return self::FAILURE;
        }

        $discover = (bool) $this->option('chats');

        if (! $discover && ! config('telegram.chat_ids')) {
            $this->error('TELEGRAM_CHAT_IDS belum diisi di .env. Jalankan dulu: php artisan telegram:fetch --chats');

            return self::FAILURE;
        }

        try {
            $result = (new TelegramSalesPoller($bot))->run($discover);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($discover) {
            $this->info('Grup/chat yang terlihat dari pesan terakhir (isi id-nya ke TELEGRAM_CHAT_IDS):');

            foreach ($result['chats'] as $id => $title) {
                $this->line("  {$id}  {$title}");
            }

            if (! $result['chats']) {
                $this->warn('Belum ada pesan. Kirim satu pesan apa saja ke grup, lalu jalankan lagi.');
            }

            return self::SUCCESS;
        }

        $this->info("Dicek {$result['checked']} pesan: {$result['queued']} masuk antrean (menunggu konfirmasi), {$result['failed']} salah format, {$result['ignored']} diabaikan (bukan laporan penjualan).");

        return self::SUCCESS;
    }
}

<?php

namespace App\Support\Telegram;

use App\Models\Setting;
use App\Support\Zeytin\TelegramSalesImporter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Membaca pesan baru di grup Telegram dan memasukkan laporan penjualan harian
 * ke antrean (TelegramQueue). Pembacaan memakai TelegramSalesImporter, jadi
 * format dan pengamannya sama dengan impor manual; tidak ada yang langsung
 * masuk ke pembukuan, Admin yang mengonfirmasi.
 *
 * Pengaman tambahan khusus bot:
 *   - Hanya chat di TELEGRAM_CHAT_IDS yang dibaca. Daftar kosong = tidak ada
 *     yang dibaca, karena bot bisa dimasukkan orang asing ke grup lain.
 *   - Kalau TELEGRAM_USER_IDS diisi, hanya pengirim itu yang dipercaya.
 *   - Tiap pesan diproses sendiri-sendiri: satu pesan salah tidak menahan
 *     pesan lain, dan bot membalas dengan alasannya.
 *   - Penanda "sudah dibaca sampai mana" (offset) maju setelah tiap pesan,
 *     jadi pesan tidak pernah dibaca dua kali.
 */
class TelegramSalesPoller
{
    protected const OFFSET_KEY = 'telegram.offset';

    public function __construct(protected TelegramBot $bot) {}

    /**
     * @return array{checked: int, queued: int, failed: int, ignored: int, chats: array<string, string>}
     */
    public function run(bool $discoverOnly = false): array
    {
        // Cron dan tombol di layar bisa berjalan bersamaan; satu saja yang boleh
        // membaca, supaya balasan tidak ganda dan offset tidak saling timpa.
        $lock = Cache::lock('telegram-poll', 120);

        if (! $lock->get()) {
            throw new \RuntimeException(__('telegram.pull.busy'));
        }

        try {
            return $this->poll($discoverOnly);
        } finally {
            $lock->release();
        }
    }

    /** @return array{checked: int, queued: int, failed: int, ignored: int, chats: array<string, string>} */
    protected function poll(bool $discoverOnly): array
    {
        $result = ['checked' => 0, 'queued' => 0, 'failed' => 0, 'ignored' => 0, 'chats' => []];
        $offset = (int) Setting::get(self::OFFSET_KEY, 0);

        foreach ($this->bot->updates($offset) as $update) {
            $result['checked']++;
            $message = $update['message'] ?? $update['channel_post'] ?? null;

            if (is_array($message)) {
                $chat = $message['chat'] ?? [];
                $result['chats'][(string) ($chat['id'] ?? '?')] = (string) ($chat['title'] ?? $chat['username'] ?? $chat['first_name'] ?? '');

                if (! $discoverOnly) {
                    $this->handle($message, $result);
                }
            }

            // Mode "cari id grup" tidak boleh menghabiskan pesan yang belum diproses.
            if (! $discoverOnly) {
                Setting::put(self::OFFSET_KEY, ((int) $update['update_id']) + 1);
            }
        }

        return $result;
    }

    /** @param  array<string, mixed>  $message */
    protected function handle(array $message, array &$result): void
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        $text = (string) ($message['text'] ?? '');
        $userId = (string) ($message['from']['id'] ?? '');
        $allowedUsers = config('telegram.user_ids', []);

        if (! in_array($chatId, config('telegram.chat_ids', []), true)
            || ($allowedUsers && ! in_array($userId, $allowedUsers, true))
            || ! TelegramSalesImporter::hasReport($text, allowUndated: true)) {
            $result['ignored']++;

            return;
        }

        // Tanpa judul tanggal, hari ini (waktu setempat) saat pesan dikirim yang dipakai.
        $sentAt = Carbon::createFromTimestamp((int) ($message['date'] ?? time()), config('telegram.timezone'));
        $parsed = (new TelegramSalesImporter)->parse($text, $sentAt);
        $messageId = (int) ($message['message_id'] ?? 0);
        $replyTo = $messageId ?: null;

        if ($parsed['errors'] || ! $parsed['days']) {
            $result['failed']++;
            $this->reply($chatId, $replyTo, "❌ Tidak diterima.\n".implode("\n", array_slice($parsed['errors'] ?: [__('excel.telegram.nothing_found')], 0, 3))."\nBetulkan pesannya lalu kirim ulang.");

            return;
        }

        $sender = trim(($message['from']['first_name'] ?? '').' '.($message['from']['last_name'] ?? '')) ?: ($message['from']['username'] ?? null);
        $lines = [];

        foreach ($parsed['days'] as $date => $amounts) {
            ['report' => $report, 'created' => $created] = TelegramQueue::stage($chatId, $messageId, $sender, $sentAt, $date, $amounts, $text);

            if (! $created) {
                continue;
            }

            $result['queued']++;
            $lines[] = $report->report_date->translatedFormat('j M Y').' · Total Rp '.number_format($report->total(), 0, ',', '.')
                .($report->dateTaken() ? ' (⚠️ tanggal ini sudah ada datanya)' : '');
        }

        if ($lines) {
            $this->reply($chatId, $replyTo, "📥 Diterima, menunggu konfirmasi admin.\n".implode("\n", $lines));
        }
    }

    protected function reply(string $chatId, ?int $replyTo, string $text): void
    {
        if (config('telegram.reply')) {
            $this->bot->send($chatId, $text, $replyTo);
        }
    }
}

<?php

namespace App\Support\Telegram;

use App\Models\Setting;
use App\Support\Zeytin\TelegramSalesImporter;
use Illuminate\Support\Carbon;

/**
 * Membaca pesan baru di grup Telegram dan mencatat laporan penjualan harian
 * lewat TelegramSalesImporter (aturan dan pengamannya sama dengan impor manual).
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
     * @return array{checked: int, recorded: int, skipped: int, failed: int, ignored: int, chats: array<string, string>}
     */
    public function run(bool $discoverOnly = false): array
    {
        $result = ['checked' => 0, 'recorded' => 0, 'skipped' => 0, 'failed' => 0, 'ignored' => 0, 'chats' => []];
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
        $report = (new TelegramSalesImporter)->import($text, $sentAt);
        $replyTo = (int) ($message['message_id'] ?? 0) ?: null;

        if (! $report->ok()) {
            $result['failed']++;
            $this->reply($chatId, $replyTo, "❌ Tidak disimpan.\n".implode("\n", array_slice($report->errors, 0, 3))."\nBetulkan pesannya lalu kirim ulang.");

            return;
        }

        if (($report->counts['created'] ?? 0) > 0) {
            $result['recorded']++;
            $this->reply($chatId, $replyTo, '✅ Tercatat. '.$report->note);

            return;
        }

        $result['skipped']++;
        $this->reply($chatId, $replyTo, '⚠️ Tidak ada yang disimpan. '.$report->note."\nTanggal itu sudah ada datanya. Kalau perlu diubah, edit di menu Pemasukan Harian.");
    }

    protected function reply(string $chatId, ?int $replyTo, string $text): void
    {
        if (config('telegram.reply')) {
            $this->bot->send($chatId, $text, $replyTo);
        }
    }
}

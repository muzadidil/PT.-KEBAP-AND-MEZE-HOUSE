<?php

namespace App\Support\Telegram;

use Illuminate\Support\Facades\Http;

/**
 * Klien tipis untuk Bot API Telegram: ambil pesan baru (getUpdates) dan
 * kirim balasan. Pakai HTTP bawaan Laravel, jadi tidak ada paket tambahan.
 */
class TelegramBot
{
    public function __construct(protected ?string $token = null)
    {
        $this->token ??= config('telegram.token');
    }

    public function configured(): bool
    {
        return filled($this->token);
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws \RuntimeException
     */
    public function updates(int $offset): array
    {
        try {
            $response = Http::timeout(config('telegram.timeout', 20))
                ->get($this->url('getUpdates'), [
                    'offset' => $offset,
                    'limit' => 100,
                    'timeout' => 0,
                    'allowed_updates' => json_encode(['message', 'channel_post']),
                ]);
        } catch (\Throwable $e) {
            // Alamat permintaan memuat token; jangan sampai ikut tercetak.
            throw new \RuntimeException(str_replace((string) $this->token, '***', $e->getMessage()));
        }

        if (! $response->ok() || ! $response->json('ok')) {
            // Pesan Telegram dipakai apa adanya (tanpa token) supaya penyebabnya jelas.
            throw new \RuntimeException('Telegram menolak permintaan: '.($response->json('description') ?? 'HTTP '.$response->status()));
        }

        return $response->json('result') ?? [];
    }

    /** Gagal kirim tidak boleh menghentikan pencatatan, jadi tidak melempar. */
    public function send(int|string $chatId, string $text, ?int $replyTo = null): bool
    {
        try {
            return Http::timeout(config('telegram.timeout', 20))
                ->post($this->url('sendMessage'), array_filter([
                    'chat_id' => $chatId,
                    'text' => $text,
                    'reply_to_message_id' => $replyTo,
                    'allow_sending_without_reply' => true,
                ], fn ($v) => $v !== null))
                ->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    protected function url(string $method): string
    {
        return 'https://api.telegram.org/bot'.$this->token.'/'.$method;
    }
}

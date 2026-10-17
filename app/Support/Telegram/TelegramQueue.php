<?php

namespace App\Support\Telegram;

use App\Models\DailyIncome;
use App\Models\TelegramReport;
use App\Support\Zeytin\DailyIncomeWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Antrean laporan Telegram: memasukkan hasil bacaan bot, dan mencatat satu
 * laporan yang sudah dikonfirmasi ke Pemasukan Harian.
 *
 * Tidak ada yang dicatat ke pembukuan tanpa konfirmasi Admin. Tanggal yang
 * sudah punya baris di Pemasukan Harian tidak pernah ditimpa: konfirmasi
 * ditolak sampai tanggalnya diubah atau laporan itu ditolak.
 */
class TelegramQueue
{
    /**
     * @param  array<string, int>  $amounts
     * @return array{report: TelegramReport, created: bool}
     */
    public static function stage(string $chatId, int $messageId, ?string $sender, Carbon $sentAt, string $date, array $amounts, string $rawText): array
    {
        // whereDate: tidak bergantung pada bentuk tanggal yang tersimpan di kolom.
        $report = TelegramReport::query()
            ->where('chat_id', $chatId)
            ->where('message_id', $messageId)
            ->whereDate('report_date', $date)
            ->first();

        if ($report) {
            return ['report' => $report, 'created' => false];
        }

        $report = TelegramReport::create([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'report_date' => $date,
            'sender' => $sender,
            'sent_at' => $sentAt->copy()->utc(),
            'amounts' => $amounts,
            'raw_text' => $rawText,
            'status' => TelegramReport::PENDING,
        ]);

        return ['report' => $report, 'created' => true];
    }

    /**
     * Mencatat ke Pemasukan Harian dengan tanggal dan angka yang sudah
     * diperiksa Admin. false = tanggal itu sudah terisi (tidak ada yang berubah).
     *
     * @param  array<string, int>  $amounts
     */
    public static function record(TelegramReport $report, string $date, array $amounts): bool
    {
        if ($report->status !== TelegramReport::PENDING) {
            return false;
        }

        return DB::transaction(function () use ($report, $date, $amounts) {
            if (DailyIncome::query()->whereDate('date', $date)->exists()) {
                return false;
            }

            DailyIncomeWriter::save([$date => $amounts]);

            $report->update(['status' => TelegramReport::RECORDED, 'report_date' => $date, 'amounts' => $amounts]);

            return true;
        });
    }

    public static function ignore(TelegramReport $report): void
    {
        if ($report->status === TelegramReport::PENDING) {
            $report->update(['status' => TelegramReport::IGNORED]);
        }
    }

    /** Memberi tahu pengirim di grup. Gagal kirim tidak boleh menggagalkan apa pun. */
    public static function tell(TelegramReport $report, string $text): void
    {
        if (config('telegram.reply') && filled(config('telegram.token'))) {
            (new TelegramBot)->send($report->chat_id, $text, $report->message_id);
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Support\Bank\BankMailbox;
use Illuminate\Console\Command;

/** Ambil notifikasi bank dari email ke antrean Transaksi Bank. Dijadwalkan lewat cron. */
class FetchBankEmail extends Command
{
    protected $signature = 'bank:fetch-email {--debug : tampilkan bentuk tiap email (angka disamarkan)} {--from= : dari tanggal (YYYY-MM-DD)} {--to= : sampai tanggal (YYYY-MM-DD)}';

    protected $description = 'Baca email notifikasi bank dan masukkan ke antrean Transaksi Bank';

    public function handle(): int
    {
        try {
            $inspect = $this->option('debug') ? function (int $uid, $message, string $outcome) {
                $text = preg_replace('/\s+/', ' ', trim($message->text));
                // Angka disamarkan: yang dicari adalah bentuk email, bukan datanya.
                $shape = mb_substr(preg_replace('/\d/', '#', $text), 0, 160);

                $this->line(sprintf('[%s] %s | %s | %d huruf | %s', $outcome, $message->header('date'), $message->header('subject'), mb_strlen($text), $shape));
                $this->line('    judul isian: '.implode(' | ', \App\Support\Bank\BniNotification::labels($message->text)));
            } : null;

            $from = $this->option('from') ? \Illuminate\Support\Carbon::parse($this->option('from')) : null;
            $to = $this->option('to') ? \Illuminate\Support\Carbon::parse($this->option('to')) : null;

            if ($from && $to && $to->lt($from)) {
                $this->error('Tanggal "sampai" tidak boleh sebelum "dari".');

                return self::FAILURE;
            }

            $result = BankMailbox::fetch(null, $inspect, $from, $to);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Dicek {$result['checked']} email: {$result['added']} baru, {$result['duplicate']} sudah ada, {$result['skipped']} bukan notifikasi transaksi, {$result['other_format']} transaksi berformat lain (belum dibaca), {$result['rejected']} ditolak (gagal verifikasi pengirim).");

        foreach ($result['problems'] as $problem) {
            $this->warn($problem);
        }

        return self::SUCCESS;
    }
}

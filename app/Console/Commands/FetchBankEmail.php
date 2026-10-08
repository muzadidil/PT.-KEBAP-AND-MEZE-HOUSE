<?php

namespace App\Console\Commands;

use App\Support\Bank\BankMailbox;
use Illuminate\Console\Command;

/** Ambil notifikasi bank dari email ke antrean Transaksi Bank. Dijadwalkan lewat cron. */
class FetchBankEmail extends Command
{
    protected $signature = 'bank:fetch-email';

    protected $description = 'Baca email notifikasi bank dan masukkan ke antrean Transaksi Bank';

    public function handle(): int
    {
        try {
            $result = BankMailbox::fetch();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Dicek {$result['checked']} email: {$result['added']} baru, {$result['duplicate']} sudah ada, {$result['rejected']} ditolak (bukan dari bank/gagal verifikasi).");

        foreach ($result['problems'] as $problem) {
            $this->warn($problem);
        }

        return self::SUCCESS;
    }
}

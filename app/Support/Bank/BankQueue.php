<?php

namespace App\Support\Bank;

use App\Models\BankTransaction;
use App\Models\SupplierTransfer;
use App\Support\Zeytin\RecordSource;
use Illuminate\Support\Facades\DB;

/**
 * Antrean notifikasi bank: memasukkan hasil bacaan email, dan mencatat satu
 * notifikasi yang sudah disetujui sebagai Transfer Pemasok.
 *
 * Tidak ada yang dicatat ke pembukuan tanpa persetujuan. Nomor referensi
 * menjaga supaya notifikasi yang sama (atau email yang ditempel dua kali)
 * tidak masuk dua kali, baik di antrean maupun di Transfer Pemasok.
 */
class BankQueue
{
    /**
     * @return array{added: int, duplicate: int, problems: array<int, string>}
     */
    public static function addFromText(string $text, string $source = 'paste'): array
    {
        $result = BniNotification::parse($text);
        $added = 0;
        $duplicate = 0;

        foreach ($result['parsed'] as $row) {
            $transaction = BankTransaction::query()->firstOrCreate(
                ['reference' => $row['reference']],
                [...$row, 'source' => $source, 'status' => BankTransaction::PENDING],
            );

            $transaction->wasRecentlyCreated ? $added++ : $duplicate++;
        }

        return ['added' => $added, 'duplicate' => $duplicate, 'problems' => $result['problems']];
    }

    /** Mencatat sebagai Transfer Pemasok. Hanya untuk transfer keluar yang masih menunggu. */
    public static function record(BankTransaction $transaction): ?SupplierTransfer
    {
        if (! $transaction->isOutgoing() || $transaction->status !== BankTransaction::PENDING) {
            return null;
        }

        return DB::transaction(function () use ($transaction) {
            $key = 'bni:'.$transaction->reference;

            $transfer = SupplierTransfer::query()->where('import_key', $key)->first()
                ?? SupplierTransfer::create([
                    'date' => $transaction->occurred_at->toDateString(),
                    'vendor' => $transaction->beneficiary,
                    'item' => $transaction->remark ?: ($transaction->type ?: 'Transfer BNI'),
                    'unit' => null,
                    'qty' => 1,
                    'price' => $transaction->amount,
                    'method' => 'BNI',
                    'status' => 'PT KEBAP PAID',
                    // Dianggap ketikan: impor Excel tidak boleh menggantinya.
                    'source' => RecordSource::MANUAL,
                    'import_key' => $key,
                ]);

            $transaction->update([
                'status' => BankTransaction::RECORDED,
                'supplier_transfer_id' => $transfer->id,
            ]);

            return $transfer;
        });
    }

    public static function ignore(BankTransaction $transaction): void
    {
        if ($transaction->status === BankTransaction::PENDING) {
            $transaction->update(['status' => BankTransaction::IGNORED]);
        }
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BookkeepingRecord;
use App\Support\Zeytin\RecordSource;
use Illuminate\Database\Eloquent\Model;

/**
 * Pembayaran ke pemasok lewat rekening — sheet Supplier Transfer Payment.
 *
 * Di berkas Excel aslinya angka ini tidak pernah ikut hitungan laba. Di sini
 * ikut sebagai pengeluaran, atas persetujuan klien.
 */
class SupplierTransfer extends Model
{
    use BookkeepingRecord;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'qty' => 'integer',
            'price' => 'integer',
            'total' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        /*
         * Baris yang diketik orang totalnya dihitung dari qty × harga, supaya
         * tidak ada salah ketik yang lolos.
         *
         * Baris hasil impor TIDAK dihitung ulang: sheet Transfer punya kolom
         * "Total Expense" sendiri yang kadang sudah disesuaikan tangan (ongkos
         * kirim, pembulatan pembayaran). Menghitung ulang berarti menimpa
         * angka yang benar-benar dibayarkan dengan angka yang seharusnya —
         * dan selisihnya tidak akan pernah kelihatan di mana pun.
         */
        static::saving(function (self $transfer) {
            if ($transfer->source !== RecordSource::IMPORT) {
                $transfer->total = (int) $transfer->qty * (int) $transfer->price;
            }
        });
    }
}

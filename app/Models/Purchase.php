<?php

namespace App\Models;

use App\Models\Concerns\BookkeepingRecord;
use App\Support\Zeytin\DailyLedger;
use Illuminate\Database\Eloquent\Model;

/**
 * Belanja harian yang dibayar tunai dari titipan pemasok — sheet Expense.
 *
 * Totalnya dihitung ulang setiap kali disimpan, bukan dipercayakan pada
 * formulir. Rumusnya milik berkas Excel (`=((qty*price)+tax-disc)`), dan
 * satu-satunya cara memastikan halaman, pengimpor, dan seeder tidak berbeda
 * pendapat adalah menghitungnya di sini.
 */
class Purchase extends Model
{
    use BookkeepingRecord;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'qty' => 'integer',
            'price' => 'integer',
            'disc' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $purchase) {
            $purchase->total = DailyLedger::lineTotal(
                $purchase->qty,
                $purchase->price,
                $purchase->tax,
                $purchase->disc,
            );
        });
    }
}

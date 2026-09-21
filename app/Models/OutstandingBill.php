<?php

namespace App\Models;

use App\Models\Concerns\BookkeepingRecord;
use App\Support\Zeytin\DailyLedger;
use Illuminate\Database\Eloquent\Model;

/**
 * Tagihan yang sudah diterima tapi belum tentu dibayar — sheet Outstanding INV.
 *
 * Yang belum beres mengurangi saldo global, dan tidak dibatasi rentang
 * tanggal: utang bulan lalu tetap utang hari ini.
 */
class OutstandingBill extends Model
{
    use BookkeepingRecord;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'due_date' => 'date',
            'qty' => 'integer',
            'price' => 'integer',
            'disc' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $bill) {
            $bill->total = DailyLedger::lineTotal($bill->qty, $bill->price, $bill->tax, $bill->disc);
        });
    }

    /**
     * Sudah lunas, apa pun ejaan statusnya.
     *
     * Dipakai lewat PHP dan bukan lewat `where` di SQL, karena statusnya teks
     * bebas dari berkas Excel — "PAID", "Sudah lunas", dan "settled"
     * semuanya berarti beres, dan daftar `where not in` akan ketinggalan
     * ejaan berikutnya yang belum pernah terlihat.
     */
    public function isSettled(): bool
    {
        return DailyLedger::isSettled($this->status);
    }
}

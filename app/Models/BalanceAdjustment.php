<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Koreksi saldo cash atau bank; lihat migrasi balance_adjustments.
 * Jumlahnya selalu positif; arahnya (tambah/kurangi) disimpan terpisah.
 */
class BalanceAdjustment extends Model
{
    public const CASH = 'cash';

    public const BANK = 'bank';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $row) => $row->user_id ??= Auth::id());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Jumlah bertanda: plus menambah, minus mengurangi saldo. */
    public function signed(): int
    {
        return $this->direction === 'minus' ? -$this->amount : $this->amount;
    }

    /**
     * Koreksi satu rekening per tanggal (Y-m-d => jumlah bertanda).
     *
     * @return array<string, int>
     */
    public static function byDate(string $account, Carbon $from, Carbon $to): array
    {
        $totals = [];

        static::query()
            ->where('account', $account)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->get()
            ->each(function (self $row) use (&$totals) {
                $key = $row->date->toDateString();
                $totals[$key] = ($totals[$key] ?? 0) + $row->signed();
            });

        return $totals;
    }
}

<?php

namespace App\Models;

use App\Enums\SalesChannel;
use App\Enums\SaleSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Satu penerimaan penjualan. Transaksi kasir (source `pos`) punya rincian
 * item; rekap harian (source `quick`) tidak. Seluruh laporan penjualan
 * membaca tabel ini dan tidak ada yang lain, jadi tidak ada angka yang bisa
 * berbeda antara satu laporan dan laporan lain.
 */
class Sale extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sold_on' => 'date',
            'channel' => SalesChannel::class,
            'source' => SaleSource::class,
            'subtotal' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'paid' => 'integer',
            'change' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeBetween(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereBetween('sold_on', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * Nomor struk harian: S-YYYYMMDD-NNNN. Dihitung dari nomor terakhir di
     * tanggal yang sama, jadi urutannya ikut terbaca oleh manusia.
     */
    public static function nextCode(Carbon $date): string
    {
        $prefix = 'S-'.$date->format('Ymd').'-';

        $last = static::where('code', 'like', $prefix.'%')
            ->orderByDesc('code')
            ->value('code');

        $number = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}

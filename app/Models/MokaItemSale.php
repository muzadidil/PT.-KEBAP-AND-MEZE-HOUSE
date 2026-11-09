<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu menu dalam satu periode laporan "Report Item Sales" Moka.
 *
 * Bukan transaksi: tidak ada tanggal per struk, dan tidak ada channel
 * pembayaran. Tabelnya tidak dibaca laporan penjualan mana pun — penjualan
 * yang sama sudah masuk lewat Pemasukan Harian, dan menjumlahkan keduanya
 * membuat satu bulan terhitung dua kali. Gunanya melihat apa yang terjual.
 */
class MokaItemSale extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'qty_sold' => 'integer',
            'qty_refunded' => 'integer',
            'gross_sales' => 'integer',
            'discount' => 'integer',
            'refund' => 'integer',
            'net_sales' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "01/10/2026 – 31/10/2026" */
    public function periodLabel(): string
    {
        return static::label($this->period_start->toDateString(), $this->period_end->toDateString());
    }

    public static function label(string $start, string $end): string
    {
        return date('d/m/Y', strtotime($start)).' – '.date('d/m/Y', strtotime($end));
    }

    /**
     * Periode yang ada, terbaru dulu. Kuncinya "awal|akhir" supaya satu
     * pilihan cukup untuk menyaring kedua tanggal sekaligus.
     *
     * @return array<string, string>
     */
    public static function periodOptions(): array
    {
        return static::query()
            ->select('period_start', 'period_end')
            ->distinct()
            ->orderByDesc('period_start')
            ->get()
            ->mapWithKeys(fn (self $row) => [
                $row->period_start->toDateString().'|'.$row->period_end->toDateString() => $row->periodLabel(),
            ])
            ->all();
    }

    public function scopeInPeriod(Builder $query, string $start, string $end): Builder
    {
        return $query->whereDate('period_start', $start)->whereDate('period_end', $end);
    }

    /** Periode lain yang tanggalnya bersinggungan, tapi bukan periode yang persis sama. */
    public function scopeOverlapping(Builder $query, string $start, string $end): Builder
    {
        return $query
            ->whereDate('period_start', '<=', $end)
            ->whereDate('period_end', '>=', $start)
            ->where(fn (Builder $q) => $q
                ->whereDate('period_start', '!=', $start)
                ->orWhereDate('period_end', '!=', $end));
    }
}

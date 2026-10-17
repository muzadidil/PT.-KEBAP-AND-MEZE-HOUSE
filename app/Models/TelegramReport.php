<?php

namespace App\Models;

use App\Support\Zeytin\Channels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Satu laporan penjualan harian dari Telegram yang menunggu konfirmasi; lihat migrasi telegram_reports. */
class TelegramReport extends Model
{
    public const PENDING = 'pending';

    public const RECORDED = 'recorded';

    public const IGNORED = 'ignored';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'report_date' => 'date:Y-m-d',
            'amounts' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $row) => $row->user_id ??= Auth::id());
    }

    /** Total penjualan: hanya channel yang ikut Total Sales (petty cash tidak). */
    public function total(): int
    {
        return (int) array_sum(array_map(fn (string $key) => (int) ($this->amounts[$key] ?? 0), Channels::inSales()));
    }

    /** Tanggal laporan ini sudah punya baris di Pemasukan Harian? */
    public function dateTaken(): bool
    {
        return DailyIncome::query()->whereDate('date', $this->report_date->toDateString())->exists();
    }
}

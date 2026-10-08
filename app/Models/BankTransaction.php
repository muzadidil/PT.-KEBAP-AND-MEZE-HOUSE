<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/** Satu notifikasi bank yang menunggu keputusan; lihat migrasi bank_transactions. */
class BankTransaction extends Model
{
    public const PENDING = 'pending';

    public const RECORDED = 'recorded';

    public const IGNORED = 'ignored';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $row) => $row->user_id ??= Auth::id());
    }

    public function supplierTransfer(): BelongsTo
    {
        return $this->belongsTo(SupplierTransfer::class);
    }

    /** Transfer keluar dari rekening PT, yaitu pembayaran ke pihak lain. */
    public function isOutgoing(): bool
    {
        return $this->direction === 'out';
    }
}

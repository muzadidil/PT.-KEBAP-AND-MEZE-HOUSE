<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Gaji satu pemilik untuk satu bulan. Bulannya disimpan sebagai tanggal 1,
 * seperti Payroll, supaya bisa diurutkan dan disaring seperti tanggal biasa.
 */
class OwnerSalary extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $salary) => $salary->user_id ??= Auth::id());
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

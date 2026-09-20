<?php

namespace App\Models;

use App\Enums\CapitalDirection;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class CapitalEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'entry_on' => 'date',
            'direction' => CapitalDirection::class,
            'method' => PaymentMethod::class,
            'amount' => 'integer',
        ];
    }

    /** Pencatat diisi sendiri, bukan ditanyakan lewat formulir. */
    protected static function booted(): void
    {
        static::creating(function (self $entry) {
            $entry->user_id ??= Auth::id();
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeBetween(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereBetween('entry_on', [$from->toDateString(), $to->toDateString()]);
    }
}

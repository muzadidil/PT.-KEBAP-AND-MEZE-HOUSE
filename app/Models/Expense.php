<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class Expense extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'spent_on' => 'date',
            'due_on' => 'date',
            'category' => ExpenseCategory::class,
            'method' => PaymentMethod::class,
            'amount' => 'integer',
            'is_paid' => 'boolean',
        ];
    }

    /**
     * Pengeluaran yang ditalangi pemilik selalu berstatus lunas: uangnya
     * sudah keluar dari kantong pemilik, jadi tidak mungkin masih berupa
     * tagihan yang belum dibayar. Aturannya dipasang di sini, bukan hanya di
     * formulir, supaya impor atau seeder pun tidak bisa menembusnya —
     * kombinasi "ditalangi tapi belum dibayar" akan merusak keseimbangan
     * neraca. Lihat App\Support\Ledger::balanceSheet().
     */
    protected static function booted(): void
    {
        static::saving(function (self $expense) {
            if ($expense->paid_by_owner_id !== null) {
                $expense->is_paid = true;
            }
        });

        // Pencatat diisi sendiri, bukan ditanyakan lewat formulir.
        static::creating(function (self $expense) {
            $expense->user_id ??= Auth::id();
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function paidByOwner(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'paid_by_owner_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeBetween(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()]);
    }

    /** Sudah keluar uangnya; yang belum adalah utang, bukan pengurang kas. */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('is_paid', true);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('is_paid', false);
    }

    /** Ditalangi pemilik dengan uang pribadi, jadi ikut pembagian 60/40. */
    public function scopeOwnerPaid(Builder $query): Builder
    {
        return $query->whereNotNull('paid_by_owner_id');
    }
}

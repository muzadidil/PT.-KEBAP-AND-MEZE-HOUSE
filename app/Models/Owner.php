<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pemilik usaha. `share_percent` adalah porsi tanggungan atas pengeluaran
 * yang ditalangi pemilik (Aslan 60, Leo 40) dan dipakai untuk menghitung
 * siapa berutang kepada siapa di laporan Monthly Expenses by Owner.
 */
class Owner extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'share_percent' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'paid_by_owner_id');
    }

    public function capitalEntries(): HasMany
    {
        return $this->hasMany(CapitalEntry::class);
    }
}

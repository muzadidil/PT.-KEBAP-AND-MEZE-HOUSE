<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        // `last_price` menampung harga terakhir dari sheet Supplier Database;
        // lihat migrasi 2026_09_21_100000.
        return ['active' => 'boolean', 'last_price' => 'integer'];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'sort' => 'integer'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Nama sesuai bahasa yang sedang dipakai; jatuh ke Inggris kalau kosong. */
    public function getDisplayNameAttribute(): string
    {
        return app()->getLocale() === 'id' && filled($this->name_id)
            ? $this->name_id
            : $this->name_en;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('name_en');
    }
}

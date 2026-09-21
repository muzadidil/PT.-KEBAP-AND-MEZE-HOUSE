<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Item pendapatan atau potongan yang bisa dipilih di slip gaji.
 *
 * Nominal bawaan hanya tawaran awal, kecuali item bertanda `fixed`: yang itu
 * nominalnya selalu nominal bawaan, apa pun yang diketik di formulir.
 */
class PayComponent extends Model
{
    public const EARNING = 'earning';

    public const DEDUCTION = 'deduction';

    protected $fillable = [
        'type',
        'name',
        'default_amount',
        'fixed',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'default_amount' => 'integer',
            'fixed' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Item aktif satu jenis yang namanya cocok dengan yang diketik.
     *
     * Dicocokkan tanpa memandang huruf besar-kecil dan spasi di ujung, karena
     * yang mengetik manusia. Tidak ketemu berarti baris itu item bebas.
     */
    public static function match(string $type, ?string $label): ?self
    {
        $label = mb_strtolower(trim((string) $label));

        if ($label === '') {
            return null;
        }

        return static::query()
            ->active()
            ->where('type', $type)
            ->get()
            ->first(fn (self $component) => mb_strtolower(trim($component->name)) === $label);
    }
}

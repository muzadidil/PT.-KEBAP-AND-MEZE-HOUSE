<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Koreksi pelaporan pajak untuk satu bulan; lihat migrasi tax_filings.
 * Hanya dibaca oleh Laporan Pajak — tidak pernah mengubah data asli.
 */
class TaxFiling extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'revenue_override' => 'integer',
            'investor_share' => 'float',
            'final_rate' => 'float',
            'ppn_rate' => 'float',
            'ppn_inclusive' => 'boolean',
            'ppn_input' => 'integer',
            'paid_final' => 'integer',
            'paid_ppn' => 'integer',
            'is_reported' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

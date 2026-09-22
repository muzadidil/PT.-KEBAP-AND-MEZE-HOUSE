<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Master pilihan Daily Report, dikelola dari halamannya sendiri.
 *
 *   condition  isi bagian jenis "choice" — Good / Need Attention / Problem
 *   status     status poin jenis "status" — Pending / Process / Finish
 *
 * Menghapus pilihan tidak menghapus catatan yang memakainya; catatannya
 * hanya jadi belum dipilih.
 */
class DailyReportOption extends Model
{
    public const GROUPS = ['condition', 'status'];

    protected $fillable = [
        'group',
        'label',
        'icon',
        'sort_order',
    ];

    /** @param  Builder<self>  $query */
    public function scopeInGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group)->orderBy('sort_order')->orderBy('id');
    }

    /** "🟢 Good" — ikon di depan kalau ada. */
    public function display(): string
    {
        return trim(($this->icon ? $this->icon.' ' : '').$this->label);
    }
}

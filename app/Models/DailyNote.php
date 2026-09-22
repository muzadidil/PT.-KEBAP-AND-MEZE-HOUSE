<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

/**
 * Satu baris Daily Report untuk satu tanggal: bagian utama (parent_id
 * kosong) atau sub-catatan di bawahnya, bertingkat tanpa batas — sama
 * seperti sub-tugas di Progres Rapat, tapi tanpa status selesai/belum.
 *
 * Nominal opsional, ditulis di sisi kanan teksnya (mis. "Total Sales" +
 * Rp 4.606.140), dan tidak wajib diisi untuk catatan yang bukan soal uang.
 */
class DailyNote extends Model
{
    protected $fillable = [
        'date',
        'parent_id',
        'text',
        'nominal',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'nominal' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $note) {
            $note->user_id ??= Auth::id();
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('id');
    }
}

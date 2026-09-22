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
 * Jenisnya menentukan isiannya:
 *
 *   text    teks bebas, dengan nominal Rupiah opsional
 *   choice  bagian yang isinya satu pilihan kondisi dari master
 *   number  angka biasa (bukan Rupiah)
 *   rating  angka 0–5, ditulis dengan bintang
 *   status  poin dengan status dari master
 */
class DailyNote extends Model
{
    public const KINDS = ['text', 'choice', 'number', 'rating', 'status'];

    /** Jenis yang bisa dipilih untuk bagian utama baru. */
    public const SECTION_KINDS = ['text', 'choice', 'number', 'status'];

    public const MAX_RATING = 5;

    protected $fillable = [
        'date',
        'parent_id',
        'text',
        'kind',
        'icon',
        'nominal',
        'value',
        'option_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'nominal' => 'integer',
            'value' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $note) {
            $note->user_id ??= Auth::id();
            $note->kind ??= 'text';
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

    public function option(): BelongsTo
    {
        return $this->belongsTo(DailyReportOption::class, 'option_id');
    }

    /**
     * Jenis sub-catatan baru di bawah catatan ini: poin di bawah bagian
     * angka ikut angka, di bawah bagian status ikut punya status. Di bawah
     * bagian pilihan, poin tambahannya catatan biasa (mis. "Main issue").
     */
    public function childKind(): string
    {
        return match ($this->kind) {
            'number', 'rating' => 'number',
            'status' => 'status',
            default => 'text',
        };
    }

    /** Grup master yang dipakai jenis ini, kalau ada. */
    public function optionGroup(): ?string
    {
        return match ($this->kind) {
            'choice' => 'condition',
            'status' => 'status',
            default => null,
        };
    }

    /** "4.9" untuk rating, "1.200" untuk angka biasa, null kalau belum diisi. */
    public function formattedValue(): ?string
    {
        if ($this->value === null) {
            return null;
        }

        if ($this->kind === 'rating') {
            return rtrim(rtrim(number_format($this->value, 1, '.', ''), '0'), '.');
        }

        return number_format($this->value, 0, ',', '.');
    }
}

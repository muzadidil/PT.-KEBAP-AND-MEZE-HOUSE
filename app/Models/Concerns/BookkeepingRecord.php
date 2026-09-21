<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Support\Zeytin\RecordSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Yang dimiliki bersama oleh seluruh catatan pembukuan bulanan.
 *
 * Intinya satu pembedaan: baris yang diketik orang lewat halaman, dan baris
 * yang dibawa berkas Excel. Pengimpor hanya boleh mengganti dan membuang
 * baris miliknya sendiri — berkas Excel berwenang atas baris yang ia bawa,
 * bukan atas seluruh isi basis data. Tanpa pembedaan itu, satu impor bisa
 * menghapus catatan yang diketik dengan susah payah tanpa ada yang tahu.
 */
trait BookkeepingRecord
{
    public static function bootBookkeepingRecord(): void
    {
        // Pencatat diisi sendiri, bukan ditanyakan lewat formulir.
        static::creating(function (Model $model) {
            $model->user_id ??= Auth::id();
        });
    }

    /** Kolom tanggal yang dipakai menyaring rentang. */
    public static function dateColumn(): string
    {
        return 'date';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeBetween(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereBetween(static::dateColumn(), [$from->toDateString(), $to->toDateString()]);
    }

    /** Dibawa berkas Excel; hanya baris inilah yang boleh diganti impor. */
    public function scopeImported(Builder $query): Builder
    {
        return $query->where('source', RecordSource::IMPORT);
    }

    /** Diketik orang lewat halaman; tidak pernah disentuh impor. */
    public function scopeManual(Builder $query): Builder
    {
        return $query->where('source', RecordSource::MANUAL);
    }

    public function isImported(): bool
    {
        return $this->source === RecordSource::IMPORT;
    }
}

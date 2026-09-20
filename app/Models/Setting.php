<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan kunci-nilai yang boleh diubah pemilik lewat halaman admin.
 *
 * Nilainya disimpan sebagai JSON, jadi satu kunci bisa memuat angka, teks,
 * atau seluruh daftar slide halaman masuk tanpa perlu tabel tersendiri.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected const CACHE_KEY = 'settings.all';

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::all_()[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => json_encode($value)]);

        static::forget();
    }

    /**
     * Seluruh pengaturan sekali baca, lalu disimpan di cache.
     *
     * Halaman masuk membaca beberapa kunci sekaligus dan halaman itu dibuka
     * setiap pagi oleh tiap kasir; tanpa cache, satu halaman berarti
     * beberapa kueri untuk data yang praktis tidak pernah berubah.
     *
     * @return array<string, mixed>
     */
    public static function all_(): array
    {
        return Cache::rememberForever(static::CACHE_KEY, function () {
            return static::query()
                ->pluck('value', 'key')
                ->map(fn (?string $value) => $value === null ? null : json_decode($value, true))
                ->all();
        });
    }

    public static function forget(): void
    {
        Cache::forget(static::CACHE_KEY);
    }

    protected static function booted(): void
    {
        // Diubah lewat jalur mana pun — halaman admin, tinker, seeder —
        // cache-nya ikut gugur. Kalau hanya dibersihkan di satu tempat,
        // perubahan lewat jalur lain akan terlihat baru setelah cache
        // kedaluwarsa, dan itu tidak pernah terjadi pada rememberForever.
        static::saved(fn () => static::forget());
        static::deleted(fn () => static::forget());
    }
}

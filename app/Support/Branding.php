<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Tampilan halaman masuk: logo, nama, dan slide latar.
 *
 * Semuanya boleh diubah pemilik lewat Admin → Tampilan, dan tersimpan di
 * tabel `settings`. Kalau belum pernah diatur, dipakai bawaan: logo berupa
 * inisial nama usaha, dan tiga slide latar bergambar vektor yang ikut
 * di-commit di public/img.
 *
 * Berkas unggahan disimpan di public/uploads/branding, bukan lewat
 * storage:link — shared hosting sering menolak symlink, dan latar halaman
 * masuk harus bisa dibuka tamu yang belum masuk sama sekali.
 */
class Branding
{
    public const DISK = 'branding';

    /** Slide bawaan kalau pemilik belum mengunggah apa pun. */
    public const DEFAULT_SLIDES = [
        ['image' => 'img/login-kebab.svg', 'key' => 'kebab'],
        ['image' => 'img/login-meze.svg', 'key' => 'meze'],
        ['image' => 'img/login-tea.svg', 'key' => 'tea'],
    ];

    public static function businessName(): string
    {
        return Setting::get('brand.name') ?: config('business.name');
    }

    public static function tagline(): string
    {
        return Setting::get('brand.tagline') ?: __('auth.default_tagline');
    }

    /** URL logo, atau null kalau pemilik belum mengunggahnya. */
    public static function logoUrl(): ?string
    {
        $path = Setting::get('brand.logo');

        if (! $path) {
            return null;
        }

        // Berkas bisa terhapus dari disk tanpa lewat aplikasi. Kalau itu
        // terjadi, halaman masuk jatuh ke inisial, bukan gambar rusak.
        return Storage::disk(static::DISK)->exists($path)
            ? Storage::disk(static::DISK)->url($path)
            : null;
    }

    /** Huruf awal nama usaha, dipakai kalau logo belum ada. */
    public static function initial(): string
    {
        return mb_strtoupper(mb_substr(static::businessName(), 0, 1));
    }

    /**
     * Slide latar yang siap dirender.
     *
     * @return array<int, array{image: ?string, eyebrow: ?string, title: ?string, text: ?string}>
     */
    public static function slides(): array
    {
        $stored = collect(Setting::get('brand.slides', []))
            ->map(fn (array $slide) => [
                'image' => static::slideUrl($slide['image'] ?? null),
                'eyebrow' => $slide['eyebrow'] ?? null,
                'title' => $slide['title'] ?? null,
                'text' => $slide['text'] ?? null,
            ])
            // Slide tanpa gambar maupun teks tidak menampilkan apa pun;
            // membiarkannya hanya menghasilkan layar kosong yang berganti.
            ->filter(fn (array $slide) => $slide['image'] || $slide['title'] || $slide['text'])
            ->values()
            ->all();

        return $stored !== [] ? $stored : static::defaultSlides();
    }

    /** @return array<int, array<string, ?string>> */
    protected static function defaultSlides(): array
    {
        return array_map(fn (array $slide) => [
            'image' => asset($slide['image']),
            'eyebrow' => __("auth.slides.{$slide['key']}.eyebrow"),
            'title' => __("auth.slides.{$slide['key']}.title"),
            'text' => __("auth.slides.{$slide['key']}.text"),
        ], static::DEFAULT_SLIDES);
    }

    protected static function slideUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk(static::DISK)->exists($path)
            ? Storage::disk(static::DISK)->url($path)
            : null;
    }
}

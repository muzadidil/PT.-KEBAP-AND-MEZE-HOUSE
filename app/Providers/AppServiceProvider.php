<?php

namespace App\Providers;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Menulis kolom yang tidak ada di $fillable harus gagal keras, bukan
        // diam-diam diabaikan; di pembukuan, kolom yang tidak tersimpan baru
        // ketahuan berbulan-bulan kemudian lewat laporan yang salah.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        /*
        | Gaya buatan sendiri disisipkan lewat tag <link> biasa, bukan lewat
        | pipeline aset. Berkasnya CSS tulisan tangan yang ikut di-commit,
        | jadi tidak ada langkah build yang perlu dijalankan di server.
        | Cap waktu berkas dipakai sebagai penanda versi supaya perubahan
        | gaya tidak tertahan cache peramban.
        */
        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn (): string => collect(['css/pos.css', 'css/auth.css'])
                ->map(function (string $path): string {
                    $file = public_path($path);
                    $version = File::exists($file) ? File::lastModified($file) : 0;

                    return '<link rel="stylesheet" href="'.asset($path).'?v='.$version.'">';
                })
                ->implode(''),
        );
    }
}

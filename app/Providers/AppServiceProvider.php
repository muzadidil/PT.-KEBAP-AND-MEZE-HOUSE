<?php

namespace App\Providers;

use App\Http\Responses\LoginResponse;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Tujuan setelah masuk mengikuti peran, bukan panel tempat masuk.
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
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

        /*
        | Mode gelap dilepas selama mencetak. Peramban membuang warna latar
        | saat mencetak, jadi tulisan terang milik mode gelap tertinggal di
        | kertas putih dan setiap laporan keluar sebagai halaman kosong.
        | Melepas kelas .dark sekaligus membalikkan warna Filament sendiri,
        | yang tidak bisa dijangkau dari pos.css.
        */
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => <<<'HTML'
                <script>
                    (() => {
                        const root = document.documentElement;
                        let wasDark = false;

                        window.addEventListener('beforeprint', () => {
                            wasDark = root.classList.contains('dark');
                            root.classList.remove('dark');
                        });

                        window.addEventListener('afterprint', () => {
                            if (wasDark) {
                                root.classList.add('dark');
                            }
                        });
                    })();
                </script>
                HTML,
        );
    }
}

<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Support\LanguageSwitcher;
use App\Http\Middleware\SetLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Backoffice pemilik: seluruh laporan dan seluruh data induk.
 * Kasir tidak bisa masuk ke sini; lihat User::canAccessPanel().
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->brandName(config('business.name'))
            ->colors([
                'primary' => Color::Amber,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make()->label(fn () => __('nav.group.sales')),
                NavigationGroup::make()->label(fn () => __('nav.group.expenses')),
                NavigationGroup::make()->label(fn () => __('nav.group.reports')),
                // Pembukuan bulanan gaya berkas Excel klien. Grupnya sendiri,
                // tidak menumpang yang sudah ada: isinya catatan yang ditulis
                // tangan per bulan, bukan turunan dari transaksi kasir.
                NavigationGroup::make()->label(fn () => __('zeytin.nav.group')),
                NavigationGroup::make()->label(fn () => __('nav.group.master')),
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->userMenuItems(LanguageSwitcher::menuItems())
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                // Harus sebelum middleware autentikasi Filament: kalau akses
                // ditolak, halaman 403 pun perlu tampil dalam bahasa penggunanya.
                // Rute panel tidak melewati grup `web`, jadi tidak cukup
                // mengandalkan pemasangan di bootstrap/app.php.
                SetLocale::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}

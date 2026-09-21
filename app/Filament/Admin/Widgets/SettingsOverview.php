<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\Products\ProductResource;
use App\Filament\Admin\Resources\Suppliers\SupplierResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Filament\Admin\Resources\Zeytin\PurchaseItems\PurchaseItemResource;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dasbor Super Admin. Perannya mengatur, bukan membaca angka penjualan,
 * jadi yang ditampilkan adalah isi pengaturannya: berapa akun per peran,
 * berapa menu yang dijual, berapa pemasok dan barang belanja yang terdaftar.
 */
class SettingsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -30;

    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() === true;
    }

    protected function getStats(): array
    {
        $users = User::query()->where('active', true)->get(['role'])->countBy(fn (User $user) => $user->role?->value);

        return [
            Stat::make(__('nav.users'), (string) $users->sum())
                ->description(collect(UserRole::cases())
                    ->map(fn (UserRole $role) => $role->getLabel().' '.($users[$role->value] ?? 0))
                    ->implode(' · '))
                ->url(UserResource::getUrl()),

            Stat::make(__('nav.products'), (string) Product::query()->where('active', true)->count())
                ->url(ProductResource::getUrl()),

            Stat::make(__('nav.suppliers'), (string) Supplier::query()->where('active', true)->count())
                ->url(SupplierResource::getUrl()),

            Stat::make(__('zeytin.nav.purchase_items'), (string) PurchaseItem::query()->where('active', true)->count())
                ->url(PurchaseItemResource::getUrl()),
        ];
    }
}

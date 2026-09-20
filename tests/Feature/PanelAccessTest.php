<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tiap halaman di kedua panel benar-benar terbuka, bukan hanya rutenya
 * terdaftar. Tes ini yang menangkap galat Blade dan kolom tabel yang salah
 * nama — jenis galat yang baru terlihat saat halamannya dibuka.
 */
class PanelAccessTest extends TestCase
{
    public static function adminPages(): array
    {
        return [
            'dashboard' => ['filament.admin.pages.dashboard'],
            'daily sales' => ['filament.admin.pages.daily-sales'],
            'weekly sales' => ['filament.admin.pages.weekly-sales'],
            'monthly sales' => ['filament.admin.pages.monthly-sales'],
            'yearly sales' => ['filament.admin.pages.yearly-sales'],
            'cash expenses' => ['filament.admin.pages.cash-expenses'],
            'online transfers' => ['filament.admin.pages.online-transfers'],
            'salary' => ['filament.admin.pages.salary'],
            'tax' => ['filament.admin.pages.tax'],
            'owner expenses' => ['filament.admin.pages.owner-expenses'],
            'balance sheet' => ['filament.admin.pages.balance-sheet'],
            'sales' => ['filament.admin.resources.sales.index'],
            'expenses' => ['filament.admin.resources.expenses.index'],
            'capital entries' => ['filament.admin.resources.capital-entries.index'],
            'products' => ['filament.admin.resources.products.index'],
            'categories' => ['filament.admin.resources.categories.index'],
            'suppliers' => ['filament.admin.resources.suppliers.index'],
            'owners' => ['filament.admin.resources.owners.index'],
            'users' => ['filament.admin.resources.users.index'],
        ];
    }

    #[DataProvider('adminPages')]
    public function test_halaman_admin_terbuka_untuk_admin(string $route): void
    {
        $this->owners();
        $this->product();

        $this->actingAs($this->admin())
            ->get(route($route))
            ->assertOk();
    }

    #[DataProvider('adminPages')]
    public function test_halaman_admin_tertutup_untuk_kasir(string $route): void
    {
        $this->actingAs($this->cashier())
            ->get(route($route))
            ->assertForbidden();
    }

    public function test_halaman_kasir_terbuka_untuk_kasir(): void
    {
        $this->product();
        $cashier = $this->cashier();

        $routes = [
            'filament.cashier.pages.register',
            'filament.cashier.pages.daily-entry',
            'filament.cashier.pages.my-sales',
        ];

        foreach ($routes as $route) {
            $this->actingAs($cashier)
                ->get(route($route))
                ->assertOk();
        }
    }

    /** Admin juga berdiri di kasir saat ramai, jadi panel kasir terbuka. */
    public function test_halaman_kasir_terbuka_untuk_admin(): void
    {
        $this->product();

        $this->actingAs($this->admin())
            ->get(route('filament.cashier.pages.register'))
            ->assertOk();
    }

    public function test_akun_nonaktif_tidak_bisa_masuk_ke_mana_pun(): void
    {
        $user = $this->cashier(['active' => false]);

        $this->actingAs($user)->get(route('filament.cashier.pages.register'))->assertForbidden();
        $this->actingAs($user)->get(route('filament.admin.pages.dashboard'))->assertForbidden();
    }

    public function test_semua_halaman_panel_ikut_diuji(): void
    {
        // Menambah halaman baru tanpa menambahkannya ke daftar di atas akan
        // menggagalkan tes ini, jadi halaman baru tidak bisa lolos tanpa uji.
        $registered = collect(Route::getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter(fn (?string $name) => $name
                && str_starts_with($name, 'filament.admin.')
                && (str_contains($name, '.pages.') || str_ends_with($name, '.index')))
            ->values()
            ->sort()
            ->all();

        $covered = collect(static::adminPages())->map(fn (array $row) => $row[0])->sort()->all();

        $this->assertSame(array_values($registered), array_values($covered));
    }
}

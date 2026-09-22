<?php

namespace Tests\Feature;

use App\Filament\Admin\Widgets\SettingsOverview;
use App\Filament\Admin\Widgets\ZeytinOverview;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tiap halaman di kedua panel benar-benar terbuka untuk perannya, dan
 * tertutup untuk peran lain — bukan hanya menunya yang disembunyikan.
 *
 *   Super Admin  pengaturan: pengguna, menu, pemasok, data induk, tampilan
 *   Admin        laporan: pengeluaran, pembukuan bulanan, seluruh laporan
 *   Kasir        penjualan: halaman kasir
 *
 * Tes ini juga yang menangkap galat Blade dan kolom tabel yang salah nama —
 * jenis galat yang baru terlihat saat halamannya dibuka.
 */
class PanelAccessTest extends TestCase
{
    public static function reportPages(): array
    {
        return [
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
            'expenses' => ['filament.admin.resources.expenses.index'],
            'capital entries' => ['filament.admin.resources.capital-entries.index'],

            // Pembukuan bulanan
            'monthly ledger' => ['filament.admin.pages.monthly-ledger'],
            'import excel' => ['filament.admin.pages.import-excel'],
            'daily incomes' => ['filament.admin.resources.zeytin.daily-incomes.index'],
            'purchases' => ['filament.admin.resources.zeytin.purchases.index'],
            'supplier transfers' => ['filament.admin.resources.zeytin.supplier-transfers.index'],
            'outstanding bills' => ['filament.admin.resources.zeytin.outstanding-bills.index'],
        ];
    }

    public static function settingsPages(): array
    {
        return [
            'products' => ['filament.admin.resources.products.index'],
            'categories' => ['filament.admin.resources.categories.index'],
            'suppliers' => ['filament.admin.resources.suppliers.index'],
            'purchase items' => ['filament.admin.resources.zeytin.purchase-items.index'],
            'payment methods' => ['filament.admin.resources.zeytin.payment-methods.index'],
            'owners' => ['filament.admin.resources.owners.index'],
            'users' => ['filament.admin.resources.users.index'],
            'appearance' => ['filament.admin.pages.appearance'],

            // Penggajian — gaji per orang hanya untuk Super Admin
            'employees' => ['filament.admin.resources.employees.index'],
            'pay components' => ['filament.admin.resources.pay-components.index'],
            'payslips' => ['filament.admin.resources.payslips.index'],
            'payrolls' => ['filament.admin.resources.zeytin.payrolls.index'],
        ];
    }

    /** Halaman yang dipakai kedua peran backoffice — Progres Rapat, Daily Report. */
    public static function sharedPages(): array
    {
        return [
            'meeting progress' => ['filament.admin.pages.meeting-progress'],
            'daily report' => ['filament.admin.pages.daily-report'],
        ];
    }

    #[DataProvider('sharedPages')]
    public function test_halaman_bersama_terbuka_untuk_admin_dan_super_admin(string $route): void
    {
        $this->actingAs($this->admin())->get(route($route))->assertOk();

        $this->flushSession();

        $this->actingAs($this->superAdmin())->get(route($route))->assertOk();
    }

    #[DataProvider('reportPages')]
    public function test_halaman_laporan_terbuka_untuk_admin(string $route): void
    {
        $this->owners();
        $this->product();

        $this->actingAs($this->admin())
            ->get(route($route))
            ->assertOk();
    }

    #[DataProvider('reportPages')]
    public function test_halaman_laporan_tertutup_untuk_super_admin(string $route): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route($route))
            ->assertForbidden();
    }

    #[DataProvider('settingsPages')]
    public function test_halaman_pengaturan_terbuka_untuk_super_admin(string $route): void
    {
        $this->owners();
        $this->product();

        $this->actingAs($this->superAdmin())
            ->get(route($route))
            ->assertOk();
    }

    #[DataProvider('settingsPages')]
    public function test_halaman_pengaturan_tertutup_untuk_admin(string $route): void
    {
        $this->actingAs($this->admin())
            ->get(route($route))
            ->assertForbidden();
    }

    #[DataProvider('reportPages')]
    #[DataProvider('settingsPages')]
    #[DataProvider('sharedPages')]
    public function test_panel_admin_tertutup_untuk_kasir(string $route): void
    {
        $this->actingAs($this->cashier())
            ->get(route($route))
            ->assertForbidden();
    }

    public function test_dasbor_terbuka_untuk_admin(): void
    {
        $this->actingAs($this->admin())->get(route('filament.admin.pages.dashboard'))->assertOk();
    }

    public function test_dasbor_terbuka_untuk_super_admin(): void
    {
        $this->actingAs($this->superAdmin())->get(route('filament.admin.pages.dashboard'))->assertOk();
    }

    public function test_dasbor_tertutup_untuk_kasir(): void
    {
        $this->actingAs($this->cashier())->get(route('filament.admin.pages.dashboard'))->assertForbidden();
    }

    /**
     * Admin melihat angka pembukuan saja — kartu transaksi kasir sudah
     * dihapus supaya tidak ada dua angka laba di satu halaman; Super Admin
     * melihat isi pengaturannya. Widget dasbor dimuat belakangan oleh Filament, jadi
     * tidak diperiksa lewat HTML halaman, melainkan lewat komponennya.
     */
    public function test_widget_dasbor_mengikuti_peran(): void
    {
        // Seperti saat dasbor benar-benar dibuka: panel admin yang aktif.
        Filament::setCurrentPanel('admin');

        $this->actingAs($this->admin());

        $this->assertTrue(ZeytinOverview::canView());
        $this->assertFalse(SettingsOverview::canView());

        Livewire::test(ZeytinOverview::class)
            ->assertSee(__('zeytin.card.global_balance'))
            ->assertSee(__('zeytin.card.remaining_supplier_cash'));

        $this->actingAs($this->superAdmin());

        $this->assertFalse(ZeytinOverview::canView());
        $this->assertTrue(SettingsOverview::canView());

        Livewire::test(SettingsOverview::class)
            ->assertSee(__('nav.users'))
            ->assertSee(__('zeytin.nav.purchase_items'));
    }

    /** Menu yang tidak boleh dibuka juga tidak boleh terlihat. */
    public function test_menu_admin_hanya_memuat_laporan(): void
    {
        $this->actingAs($this->admin())
            ->get(route('filament.admin.pages.dashboard'))
            ->assertSee(__('nav.group.reports'))
            ->assertSee(__('zeytin.nav.group'))
            ->assertDontSee(__('nav.group.master'))
            ->assertDontSee(__('nav.users'));
    }

    public function test_menu_super_admin_hanya_memuat_pengaturan(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('filament.admin.pages.dashboard'))
            ->assertSee(__('nav.group.master'))
            ->assertSee(__('nav.users'))
            ->assertDontSee(__('nav.group.reports'))
            ->assertDontSee(__('zeytin.nav.group'));
    }

    /** Penjualan dipegang kasir; panel admin tidak punya menu penjualan. */
    public function test_panel_admin_tidak_punya_menu_penjualan(): void
    {
        $this->assertFalse(Route::has('filament.admin.resources.sales.index'));
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

    public function test_halaman_kasir_tertutup_untuk_admin_dan_super_admin(): void
    {
        $this->product();

        foreach ([$this->admin(), $this->superAdmin()] as $user) {
            $this->actingAs($user)
                ->get(route('filament.cashier.pages.register'))
                ->assertForbidden();
        }
    }

    public function test_akun_nonaktif_tidak_bisa_masuk_ke_mana_pun(): void
    {
        foreach ([$this->cashier(['active' => false]), $this->superAdmin(['active' => false])] as $user) {
            $this->actingAs($user)->get(route('filament.cashier.pages.register'))->assertForbidden();
            $this->actingAs($user)->get(route('filament.admin.pages.dashboard'))->assertForbidden();
        }
    }

    public function test_semua_halaman_panel_ikut_diuji(): void
    {
        // Menambah halaman baru tanpa menambahkannya ke salah satu daftar di
        // atas akan menggagalkan tes ini, jadi halaman baru tidak bisa lolos
        // tanpa diputuskan dulu milik peran yang mana.
        $registered = collect(Route::getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter(fn (?string $name) => $name
                && str_starts_with($name, 'filament.admin.')
                && (str_contains($name, '.pages.') || str_ends_with($name, '.index')))
            ->values()
            ->sort()
            ->all();

        $covered = collect([...static::reportPages(), ...static::settingsPages(), ...static::sharedPages()])
            ->map(fn (array $row) => $row[0])
            ->push('filament.admin.pages.dashboard')
            ->sort()
            ->all();

        $this->assertSame(array_values($registered), array_values($covered));
    }
}

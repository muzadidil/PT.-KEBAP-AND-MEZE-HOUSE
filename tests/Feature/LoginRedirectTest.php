<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tiap orang dibawa ke panelnya sendiri setelah masuk, di halaman masuk mana
 * pun ia mengetik kata sandinya.
 *
 * Yang dijaga terutama: admin yang membuka alamat utama situs — halaman
 * masuk panel kasir — tidak boleh ditolak dengan "kredensial tidak cocok"
 * padahal kata sandinya benar.
 */
class LoginRedirectTest extends TestCase
{
    protected function signIn(string $panel, User $user, string $password = 'password'): Testable
    {
        Filament::setCurrentPanel($panel);

        return Livewire::test(Login::class)
            ->set('data.email', $user->email)
            ->set('data.password', $password)
            ->call('authenticate');
    }

    public function test_admin_yang_masuk_di_alamat_utama_dibawa_ke_backoffice(): void
    {
        $this->signIn('cashier', $this->admin())
            ->assertHasNoErrors()
            ->assertRedirect(route('filament.admin.pages.dashboard'));
    }

    public function test_super_admin_yang_masuk_di_alamat_utama_dibawa_ke_backoffice(): void
    {
        $this->signIn('cashier', $this->superAdmin())
            ->assertHasNoErrors()
            ->assertRedirect(route('filament.admin.pages.dashboard'));
    }

    public function test_kasir_yang_masuk_di_backoffice_dibawa_ke_halaman_kasir(): void
    {
        $this->signIn('admin', $this->cashier())
            ->assertHasNoErrors()
            ->assertRedirect(route('filament.cashier.pages.register'));
    }

    public function test_alamat_tujuan_dipakai_kalau_di_panel_yang_sama(): void
    {
        session()->put('url.intended', route('filament.admin.pages.monthly-ledger'));

        $this->signIn('admin', $this->admin())
            ->assertRedirect(route('filament.admin.pages.monthly-ledger'));
    }

    /** Kalau tidak, orangnya mendarat di halaman 403 tepat setelah masuk. */
    public function test_alamat_tujuan_di_panel_lain_diabaikan(): void
    {
        session()->put('url.intended', route('filament.admin.pages.monthly-ledger'));

        $this->signIn('admin', $this->cashier())
            ->assertRedirect(route('filament.cashier.pages.register'));
    }

    public function test_akun_nonaktif_dan_kata_sandi_salah_tetap_ditolak(): void
    {
        $this->signIn('cashier', $this->admin(['active' => false]))
            ->assertHasErrors('data.email');

        $this->signIn('admin', $this->admin(), 'salah')
            ->assertHasErrors('data.email');

        $this->assertGuest();
    }
}

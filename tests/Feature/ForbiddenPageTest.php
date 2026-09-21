<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Halaman 403 paling sering dilihat kasir yang membuka /admin, bukan
 * penyusup. Karena itu ia wajib menyebutkan siapa yang sedang masuk dan
 * memberi jalan keluar, bukan sekadar menuliskan "Forbidden".
 */
class ForbiddenPageTest extends TestCase
{
    public function test_kasir_yang_membuka_admin_diberi_penjelasan_dan_jalan_keluar(): void
    {
        $cashier = $this->cashier(['name' => 'Budi', 'locale' => 'id']);

        $response = $this->actingAs($cashier)
            ->get(route('filament.admin.pages.dashboard'))
            ->assertForbidden();

        $response->assertSee('Budi');
        $response->assertSee('Kasir');
        // Jalan keluarnya: kembali ke halaman yang boleh dibuka, atau ganti akun.
        $response->assertSee(__('error.forbidden.register'));
        $response->assertSee(route('filament.cashier.pages.register'));
        $response->assertSee(route('filament.cashier.auth.logout'));
    }

    /**
     * Bahasa harus sudah terpasang saat 403 dilempar. Middleware autentikasi
     * Filament berjalan lebih awal daripada kebanyakan middleware lain, jadi
     * SetLocale sengaja ditaruh tepat setelah StartSession di kedua panel.
     */
    public function test_halaman_403_memakai_bahasa_pengguna(): void
    {
        $this->actingAs($this->cashier(['locale' => 'en']))
            ->get(route('filament.admin.pages.dashboard'))
            ->assertForbidden()
            ->assertSee('No access to this page');

        $this->actingAs($this->cashier(['locale' => 'id']))
            ->get(route('filament.admin.pages.dashboard'))
            ->assertForbidden()
            ->assertSee('Tidak punya akses ke halaman ini');
    }

    public function test_admin_diarahkan_kembali_ke_backoffice(): void
    {
        // Admin bisa kena 403 di jalur lain; tombolnya harus menunjuk ke
        // tempat yang memang boleh dibukanya, bukan ke halaman kasir.
        $admin = $this->admin(['active' => false]);

        $this->actingAs($admin)
            ->get(route('filament.admin.pages.dashboard'))
            ->assertForbidden()
            ->assertSee(route('filament.admin.pages.dashboard'));
    }

    public function test_admin_yang_membuka_pengaturan_tidak_disebut_bukan_administrator(): void
    {
        // Pesan lama berbunyi "khusus administrator" — keliru begitu yang
        // ditolak adalah Admin yang membuka bagian milik Super Admin.
        $admin = $this->admin(['name' => 'Sari', 'locale' => 'id']);

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.users.index'))
            ->assertForbidden()
            ->assertSee('Sari')
            ->assertSee(__('error.forbidden.wrong_account', ['name' => 'Sari', 'role' => 'Administrator'], 'id'))
            ->assertSee(route('filament.admin.pages.dashboard'));
    }

    public function test_halaman_403_tidak_membuat_galat_baru_saat_tanpa_pengguna(): void
    {
        // Tanpa pengguna yang masuk, halaman tidak boleh mencoba membaca nama
        // atau perannya dan menimbulkan galat di atas galat.
        $response = $this->get(route('filament.admin.pages.dashboard'));

        // Tamu diarahkan ke halaman masuk, bukan ditolak.
        $response->assertRedirect(route('filament.admin.auth.login'));
    }
}

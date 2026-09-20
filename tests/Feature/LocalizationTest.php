<?php

namespace Tests\Feature;

use App\Filament\Cashier\Pages\Register;
use App\Models\Category;
use App\Models\Sale;
use App\Models\Product;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Dua bahasa, Inggris sebagai bawaan.
 *
 * Yang diuji: bahasa mengikuti pilihan pengguna yang sedang masuk, dan nama
 * menu ikut berganti — termasuk jatuh kembali ke bahasa Inggris kalau nama
 * Indonesianya belum diisi.
 */
class LocalizationTest extends TestCase
{
    public function test_bawaan_aplikasi_adalah_bahasa_inggris(): void
    {
        $this->assertSame('en', config('app.locale'));
        $this->assertSame('en', config('app.fallback_locale'));
    }

    public function test_pengguna_berbahasa_inggris_melihat_label_inggris(): void
    {
        $this->product();

        $this->actingAs($this->cashier(['locale' => 'en']))
            ->get(route('filament.cashier.pages.register'))
            ->assertOk()
            ->assertSee('Register')
            ->assertSee('Cashless');
    }

    public function test_pengguna_berbahasa_indonesia_melihat_label_indonesia(): void
    {
        $this->product();

        $this->actingAs($this->cashier(['locale' => 'id']))
            ->get(route('filament.cashier.pages.register'))
            ->assertOk()
            ->assertSee('Kasir')
            ->assertSee('Nontunai');
    }

    /** Menu dengan nama lengkap dua bahasa, dipakai beberapa tes di bawah. */
    protected function bilingualProduct(): Product
    {
        $category = Category::create(['name_en' => 'Drinks', 'name_id' => 'Minuman', 'active' => true]);

        return Product::create([
            'category_id' => $category->id,
            'name_en' => 'Turkish Tea',
            'name_id' => 'Teh Turki',
            'price' => 18000,
            'active' => true,
        ]);
    }

    public function test_nama_menu_tampil_dalam_bahasa_inggris(): void
    {
        $this->bilingualProduct();

        $this->actingAs($this->cashier(['locale' => 'en']))
            ->get(route('filament.cashier.pages.register'))
            ->assertSee('Turkish Tea')
            ->assertDontSee('Teh Turki');
    }

    public function test_nama_menu_tampil_dalam_bahasa_indonesia(): void
    {
        $this->bilingualProduct();

        $this->actingAs($this->cashier(['locale' => 'id']))
            ->get(route('filament.cashier.pages.register'))
            ->assertSee('Teh Turki')
            ->assertDontSee('Turkish Tea');
    }

    public function test_nama_indonesia_yang_kosong_jatuh_ke_nama_inggris(): void
    {
        $category = Category::create(['name_en' => 'Dessert', 'active' => true]);

        Product::create([
            'category_id' => $category->id,
            'name_en' => 'Baklava',
            'name_id' => null,
            'price' => 42000,
            'active' => true,
        ]);

        // Tanpa jatuhnya ke bahasa Inggris, menu ini akan tampil tanpa nama
        // di halaman kasir — kasir tidak akan tahu tombol mana yang mana.
        $this->actingAs($this->cashier(['locale' => 'id']))
            ->get(route('filament.cashier.pages.register'))
            ->assertOk()
            ->assertSee('Baklava');
    }

    /**
     * Ketukan tombol di halaman kasir adalah permintaan Livewire, dan
     * permintaan itu tidak melewati middleware panel. Tanpa SetLocale di
     * grup `web` (lihat bootstrap/app.php), halamannya tampil berbahasa
     * Indonesia tapi nama yang tersalin ke keranjang dan struk akan
     * berbahasa Inggris. Tes ini yang menjaganya tetap seragam.
     */
    public function test_nama_menu_di_keranjang_ikut_bahasa_pengguna(): void
    {
        $product = $this->bilingualProduct();
        $cashier = $this->cashier(['locale' => 'id']);

        $this->actingAs($cashier);
        app()->setLocale($cashier->locale);

        Livewire::actingAs($cashier)
            ->test(Register::class)
            ->call('addItem', $product->id)
            ->assertSet('cart.'.$product->id.'.name', 'Teh Turki')
            ->set('channel', 'cashless')
            ->call('charge');

        // Nama disalin apa adanya ke struk, jadi struk lama tetap terbaca
        // seperti saat dicetak walaupun bahasanya nanti diganti.
        $this->assertSame('Teh Turki', Sale::firstOrFail()->items->first()->name);
    }

    public function test_middleware_bahasa_terpasang_di_grup_web(): void
    {
        // Bukan sekadar memeriksa berkas konfigurasi: kalau baris ini hilang,
        // galatnya berupa nama menu yang tercampur bahasa di struk, dan itu
        // baru ketahuan setelah struk tercetak.
        $middleware = app(\Illuminate\Contracts\Http\Kernel::class)
            ->getMiddlewareGroups()['web'] ?? [];

        $this->assertContains(\App\Http\Middleware\SetLocale::class, $middleware);
    }
}

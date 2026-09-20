<?php

namespace Tests\Feature;

use App\Enums\SalesChannel;
use App\Enums\SaleSource;
use App\Filament\Cashier\Pages\Register;
use App\Models\Sale;
use Livewire\Livewire;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    public function test_halaman_kasir_ada_di_akar_situs(): void
    {
        $this->actingAs($this->cashier())
            ->get('/')
            ->assertOk()
            ->assertSee('Adana Kebab');
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->product(78000, 'Adana Kebab');
    }

    public function test_menambah_item_menghitung_subtotal_dan_total(): void
    {
        $product = $this->product(22000, 'Ayran');

        Livewire::actingAs($this->cashier())
            ->test(Register::class)
            ->call('addItem', $product->id)
            ->call('addItem', $product->id)
            ->assertSet('cart.'.$product->id.'.qty', 2)
            ->assertSeeHtml('44.000');
    }

    public function test_transaksi_tunai_tersimpan_dengan_item_dan_kembalian(): void
    {
        $kebab = $this->product(78000, 'Urfa Kebab');
        $ayran = $this->product(22000, 'Ayran');
        $cashier = $this->cashier();

        Livewire::actingAs($cashier)
            ->test(Register::class)
            ->call('addItem', $kebab->id)
            ->call('addItem', $ayran->id)
            ->call('addItem', $ayran->id)
            ->set('discount', '10.000')
            ->set('channel', 'cash')
            ->set('paid', '150000')
            ->call('charge')
            ->assertHasNoErrors();

        $sale = Sale::with('items')->firstOrFail();

        $this->assertSame(78000 + 44000, $sale->subtotal);
        $this->assertSame(10000, $sale->discount);
        $this->assertSame(112000, $sale->total);
        $this->assertSame(150000, $sale->paid);
        $this->assertSame(38000, $sale->change);
        $this->assertSame(SalesChannel::Cash, $sale->channel);
        $this->assertSame(SaleSource::Pos, $sale->source);
        $this->assertSame($cashier->id, $sale->user_id);
        $this->assertCount(2, $sale->items);
        $this->assertSame(44000, $sale->items->firstWhere('name', 'Ayran')->line_total);
    }

    public function test_nontunai_dianggap_dibayar_pas_tanpa_kembalian(): void
    {
        $product = $this->product(55000, 'Doner Wrap');

        Livewire::actingAs($this->cashier())
            ->test(Register::class)
            ->call('addItem', $product->id)
            ->set('channel', 'grab')
            ->call('charge');

        $sale = Sale::firstOrFail();

        $this->assertSame(55000, $sale->paid);
        $this->assertSame(0, $sale->change);
        $this->assertSame(SalesChannel::Grab, $sale->channel);
    }

    public function test_uang_tunai_kurang_dari_total_ditolak(): void
    {
        $product = $this->product(78000, 'Iskender');

        Livewire::actingAs($this->cashier())
            ->test(Register::class)
            ->call('addItem', $product->id)
            ->set('paid', '50000')
            ->call('charge');

        $this->assertSame(0, Sale::count());
    }

    public function test_keranjang_kosong_tidak_menghasilkan_transaksi(): void
    {
        Livewire::actingAs($this->cashier())
            ->test(Register::class)
            ->call('charge');

        $this->assertSame(0, Sale::count());
    }

    public function test_diskon_melebihi_subtotal_ditolak(): void
    {
        $product = $this->product(30000, 'Baklava');

        Livewire::actingAs($this->cashier())
            ->test(Register::class)
            ->call('addItem', $product->id)
            ->set('discount', '99000')
            ->set('paid', '99000')
            ->call('charge');

        $this->assertSame(0, Sale::count());
    }

    /**
     * Harga disalin ke baris transaksi saat item masuk keranjang, jadi
     * mengubah harga menu setelahnya tidak mengubah struk yang sudah jadi.
     */
    public function test_struk_lama_tidak_ikut_berubah_saat_harga_menu_diubah(): void
    {
        $product = $this->product(50000, 'Lahmacun');

        Livewire::actingAs($this->cashier())
            ->test(Register::class)
            ->call('addItem', $product->id)
            ->set('channel', 'cashless')
            ->call('charge');

        $product->update(['price' => 75000]);

        $this->assertSame(50000, Sale::firstOrFail()->total);
        $this->assertSame(50000, Sale::firstOrFail()->items->first()->unit_price);
    }

    public function test_nomor_struk_berurut_dalam_satu_hari(): void
    {
        $product = $this->product(20000, 'Turkish Tea');
        $cashier = $this->cashier();

        foreach (range(1, 3) as $ignored) {
            Livewire::actingAs($cashier)
                ->test(Register::class)
                ->call('addItem', $product->id)
                ->set('channel', 'cashless')
                ->call('charge');
        }

        $codes = Sale::orderBy('id')->pluck('code')->all();

        $this->assertSame('0001', substr($codes[0], -4));
        $this->assertSame('0002', substr($codes[1], -4));
        $this->assertSame('0003', substr($codes[2], -4));
    }

    public function test_keranjang_dikosongkan_setelah_pembayaran(): void
    {
        $product = $this->product(20000, 'Mineral Water');

        Livewire::actingAs($this->cashier())
            ->test(Register::class)
            ->call('addItem', $product->id)
            ->set('channel', 'cash')
            ->set('paid', '20000')
            ->call('charge')
            ->assertSet('cart', [])
            ->assertSet('discount', '')
            ->assertSet('paid', '');
    }
}

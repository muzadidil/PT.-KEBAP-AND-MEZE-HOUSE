<?php

namespace App\Filament\Cashier\Pages;

use App\Enums\SalesChannel;
use App\Enums\SaleSource;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Support\Money;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

/**
 * Halaman transaksi kasir — halaman utama aplikasi.
 *
 * Keranjang hidup di properti Livewire, bukan di basis data: pesanan yang
 * belum dibayar bukan penjualan, dan tidak boleh muncul di laporan mana pun.
 * Baris `sales` baru ditulis sekali, saat pembayaran diterima.
 */
class Register extends Page
{
    protected static ?int $navigationSort = -2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected string $view = 'filament.cashier.pages.register';

    public string $search = '';

    public ?int $categoryId = null;

    /**
     * Baris keranjang: product_id => [name, unit_price, qty].
     * Nama dan harga disalin saat item masuk keranjang, jadi mengubah harga
     * menu di tengah transaksi tidak diam-diam mengubah pesanan berjalan.
     *
     * @var array<int, array{name: string, unit_price: int, qty: int}>
     */
    public array $cart = [];

    public string $discount = '';

    public string $channel = 'cash';

    public string $paid = '';

    public string $note = '';

    /** Struk transaksi terakhir, untuk dicetak. Null sebelum ada transaksi. */
    public ?int $lastSaleId = null;

    /**
     * Halaman ini menempati akar panel, jadi membuka alamat situsnya
     * langsung mendarat di kasir. Caranya sama seperti Dashboard bawaan
     * Filament: slug-nya tetap "register" untuk nama rute dan penanda menu
     * aktif, hanya jalur rutenya yang dipendekkan.
     */
    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.register');
    }

    public function getTitle(): string
    {
        return __('pos.title');
    }

    /** @return Collection<int, Category> */
    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->active()->ordered()->get();
    }

    /** @return Collection<int, Product> */
    #[Computed]
    public function products(): Collection
    {
        return Product::query()
            ->active()
            ->with('category')
            ->when($this->categoryId, fn ($query) => $query->where('category_id', $this->categoryId))
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(fn ($q) => $q
                    ->where('name_en', 'like', $term)
                    ->orWhere('name_id', 'like', $term)
                    ->orWhere('sku', 'like', $term));
            })
            ->ordered()
            ->get();
    }

    #[Computed]
    public function subtotal(): int
    {
        return array_sum(array_map(
            fn (array $line) => $line['unit_price'] * $line['qty'],
            $this->cart
        ));
    }

    #[Computed]
    public function discountAmount(): int
    {
        return min(Money::parse($this->discount), $this->subtotal);
    }

    #[Computed]
    public function total(): int
    {
        return $this->subtotal - $this->discountAmount;
    }

    #[Computed]
    public function change(): int
    {
        // Hanya bermakna untuk tunai; nontunai selalu dibayar pas.
        return $this->isCash()
            ? max(0, Money::parse($this->paid) - $this->total)
            : 0;
    }

    public function isCash(): bool
    {
        return $this->channel === SalesChannel::Cash->value;
    }

    #[Computed]
    public function lastSale(): ?Sale
    {
        return $this->lastSaleId
            ? Sale::with('items', 'user')->find($this->lastSaleId)
            : null;
    }

    public function addItem(int $productId): void
    {
        $product = Product::active()->find($productId);

        if (! $product) {
            return;
        }

        if (isset($this->cart[$productId])) {
            $this->cart[$productId]['qty']++;
        } else {
            $this->cart[$productId] = [
                'name' => $product->display_name,
                'unit_price' => $product->price,
                'qty' => 1,
            ];
        }

        $this->afterCartChange();
    }

    public function setQty(int $productId, int $qty): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        if ($qty < 1) {
            unset($this->cart[$productId]);
        } else {
            $this->cart[$productId]['qty'] = min($qty, 999);
        }

        $this->afterCartChange();
    }

    public function increment(int $productId): void
    {
        $this->setQty($productId, ($this->cart[$productId]['qty'] ?? 0) + 1);
    }

    public function decrement(int $productId): void
    {
        $this->setQty($productId, ($this->cart[$productId]['qty'] ?? 0) - 1);
    }

    public function removeItem(int $productId): void
    {
        unset($this->cart[$productId]);
        $this->afterCartChange();
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->discount = '';
        $this->paid = '';
        $this->note = '';
        $this->afterCartChange();
    }

    /** Angka turunan dihitung ulang setiap keranjang berubah. */
    protected function afterCartChange(): void
    {
        unset($this->subtotal, $this->discountAmount, $this->total, $this->change);
    }

    public function updatedDiscount(): void
    {
        $this->afterCartChange();
    }

    public function updatedPaid(): void
    {
        unset($this->change);
    }

    public function updatedChannel(): void
    {
        unset($this->change);
    }

    public function charge(): void
    {
        if ($this->cart === []) {
            Notification::make()->danger()->title(__('pos.empty_error'))->send();

            return;
        }

        if (Money::parse($this->discount) > $this->subtotal) {
            Notification::make()->danger()->title(__('pos.discount_too_big'))->send();

            return;
        }

        $total = $this->total;
        $paid = $this->isCash() ? Money::parse($this->paid) : $total;

        if ($this->isCash() && $paid < $total) {
            Notification::make()->danger()->title(__('pos.not_enough'))->send();

            return;
        }

        $today = Carbon::today();

        // Satu transaksi: kepala struk dan seluruh barisnya ditulis bersama,
        // jadi tidak mungkin ada penjualan tanpa item atau sebaliknya.
        $sale = DB::transaction(function () use ($today, $total, $paid) {
            $sale = Sale::create([
                'code' => Sale::nextCode($today),
                'sold_on' => $today,
                'channel' => $this->channel,
                'source' => SaleSource::Pos,
                'subtotal' => $this->subtotal,
                'discount' => $this->discountAmount,
                'total' => $total,
                'paid' => $paid,
                'change' => $paid - $total,
                'note' => $this->note !== '' ? $this->note : null,
                'user_id' => Auth::id(),
            ]);

            foreach ($this->cart as $productId => $line) {
                $sale->items()->create([
                    'product_id' => $productId,
                    'name' => $line['name'],
                    'unit_price' => $line['unit_price'],
                    'qty' => $line['qty'],
                    'line_total' => $line['unit_price'] * $line['qty'],
                ]);
            }

            return $sale;
        });

        $this->lastSaleId = $sale->id;
        $this->clearCart();
        unset($this->lastSale);

        Notification::make()
            ->success()
            ->title(__('pos.saved'))
            ->body(__('pos.saved_body', ['code' => $sale->code, 'total' => Money::format($sale->total)]))
            ->send();
    }

    public function dismissReceipt(): void
    {
        $this->lastSaleId = null;
        unset($this->lastSale);
    }
}

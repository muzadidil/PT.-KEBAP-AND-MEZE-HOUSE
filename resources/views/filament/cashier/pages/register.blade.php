@php
    use App\Enums\SalesChannel;
    use App\Support\Money;
@endphp

<x-filament-panels::page>
    <div class="pos">
        {{-- Daftar menu --}}
        <div class="pos__panel">
            <div class="pos__filters">
                <input
                    type="search"
                    class="pos__search"
                    wire:model.live.debounce.250ms="search"
                    placeholder="{{ __('pos.search') }}"
                    aria-label="{{ __('pos.search') }}"
                >

                <div class="pos__chips">
                    <button
                        type="button"
                        class="pos__chip @if (! $this->categoryId) pos__chip--active @endif"
                        wire:click="$set('categoryId', null)"
                    >{{ __('pos.all_categories') }}</button>

                    @foreach ($this->categories as $category)
                        <button
                            type="button"
                            class="pos__chip @if ($this->categoryId === $category->id) pos__chip--active @endif"
                            wire:click="$set('categoryId', {{ $category->id }})"
                        >{{ $category->display_name }}</button>
                    @endforeach
                </div>
            </div>

            @if ($this->products->isEmpty())
                <p class="pos__empty">
                    {{ $this->categories->isEmpty() ? __('pos.no_products_yet') : __('pos.no_products') }}
                </p>
            @else
                <div class="pos__grid">
                    @foreach ($this->products as $product)
                        <button
                            type="button"
                            class="pos__product"
                            wire:click="addItem({{ $product->id }})"
                            wire:key="product-{{ $product->id }}"
                        >
                            <span>
                                <span class="pos__product-name">{{ $product->display_name }}</span>
                                <span class="pos__product-meta">{{ $product->category?->display_name }}</span>
                            </span>
                            <span class="pos__product-price">{{ Money::format($product->price) }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Keranjang --}}
        <div class="pos__panel pos__cart">
            <div class="pos__cart-head">
                <span>{{ __('pos.cart') }}</span>

                @if ($this->cart !== [])
                    <button type="button" class="pos__link" wire:click="clearCart">{{ __('pos.clear') }}</button>
                @endif
            </div>

            @if ($this->cart === [])
                <p class="pos__empty">{{ __('pos.cart_empty') }}</p>
            @else
                <div class="pos__lines">
                    @foreach ($this->cart as $productId => $line)
                        <div class="pos__line" wire:key="line-{{ $productId }}">
                            <span class="pos__line-name">{{ $line['name'] }}</span>
                            <span class="pos__line-total">{{ Money::format($line['unit_price'] * $line['qty']) }}</span>

                            <span class="pos__qty">
                                <button type="button" wire:click="decrement({{ $productId }})" aria-label="-">&minus;</button>
                                <span class="pos__qty-value">{{ $line['qty'] }}</span>
                                <button type="button" wire:click="increment({{ $productId }})" aria-label="+">+</button>
                            </span>
                            <span class="pos__line-unit">@ {{ Money::format($line['unit_price']) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="pos__totals">
                <div class="pos__row">
                    <span>{{ __('pos.subtotal') }}</span>
                    <span>{{ Money::format($this->subtotal) }}</span>
                </div>

                <div>
                    <label class="pos__label" for="pos-discount">{{ __('pos.discount') }}</label>
                    <input id="pos-discount" type="text" inputmode="numeric" class="pos__input" wire:model.live.debounce.400ms="discount" placeholder="0">
                </div>

                <div class="pos__row pos__row--grand">
                    <span>{{ __('pos.total') }}</span>
                    <span>{{ Money::format($this->total) }}</span>
                </div>

                <div>
                    <span class="pos__label">{{ __('pos.pay_with') }}</span>
                    <div class="pos__channels">
                        @foreach (SalesChannel::cases() as $option)
                            <button
                                type="button"
                                class="pos__channel @if ($this->channel === $option->value) pos__channel--active @endif"
                                wire:click="$set('channel', '{{ $option->value }}')"
                            >{{ $option->getLabel() }}</button>
                        @endforeach
                    </div>
                </div>

                @if ($this->isCash())
                    <div>
                        <label class="pos__label" for="pos-paid">{{ __('pos.received') }}</label>
                        <input id="pos-paid" type="text" inputmode="numeric" class="pos__input" wire:model.live.debounce.400ms="paid" placeholder="0">
                    </div>

                    <div class="pos__row pos__row--change">
                        <span>{{ __('pos.change') }}</span>
                        <span>{{ Money::format($this->change) }}</span>
                    </div>
                @endif

                <div>
                    <input type="text" class="pos__input pos__input--text" wire:model.blur="note" placeholder="{{ __('pos.note_placeholder') }}">
                </div>

                <button
                    type="button"
                    class="pos__charge"
                    wire:click="charge"
                    wire:loading.attr="disabled"
                    @disabled($this->cart === [])
                >
                    {{ __('pos.charge') }} &middot; {{ Money::format($this->total) }}
                </button>
            </div>
        </div>
    </div>

    {{-- Struk transaksi terakhir --}}
    @if ($sale = $this->lastSale)
        <x-filament::section class="fi-section-receipt">
            <x-slot name="heading">{{ __('pos.receipt') }} {{ $sale->code }}</x-slot>

            <x-slot name="headerEnd">
                <x-filament::button size="sm" color="gray" x-on:click="window.print()">
                    {{ __('pos.print') }}
                </x-filament::button>

                <x-filament::button size="sm" color="gray" wire:click="dismissReceipt">
                    {{ __('filament-actions::modal.actions.close.label') }}
                </x-filament::button>
            </x-slot>

            <div class="receipt">
                <div class="receipt__head">
                    <div class="receipt__business">{{ config('business.name') }}</div>
                    @if (config('business.address'))
                        <div class="receipt__muted">{{ config('business.address') }}</div>
                    @endif
                    @if (config('business.phone'))
                        <div class="receipt__muted">{{ config('business.phone') }}</div>
                    @endif
                </div>

                <hr class="receipt__rule">

                <div class="receipt__line">
                    <span>{{ $sale->code }}</span>
                    <span>{{ $sale->created_at->format('d/m/Y H:i') }}</span>
                </div>
                <div class="receipt__line receipt__muted">
                    <span>{{ __('pos.served_by') }}</span>
                    <span>{{ $sale->user?->name }}</span>
                </div>

                <hr class="receipt__rule">

                @foreach ($sale->items as $item)
                    <div class="receipt__line">
                        <span>{{ $item->qty }}&times; {{ $item->name }}</span>
                        <span>{{ Money::format($item->line_total) }}</span>
                    </div>
                @endforeach

                <hr class="receipt__rule">

                <div class="receipt__line">
                    <span>{{ __('pos.subtotal') }}</span>
                    <span>{{ Money::format($sale->subtotal) }}</span>
                </div>

                @if ($sale->discount > 0)
                    <div class="receipt__line">
                        <span>{{ __('pos.discount') }}</span>
                        <span>&minus;{{ Money::format($sale->discount) }}</span>
                    </div>
                @endif

                <div class="receipt__line receipt__line--total">
                    <span>{{ __('pos.total') }}</span>
                    <span>{{ Money::format($sale->total) }}</span>
                </div>

                <div class="receipt__line">
                    <span>{{ $sale->channel->getLabel() }}</span>
                    <span>{{ Money::format($sale->paid) }}</span>
                </div>

                @if ($sale->change > 0)
                    <div class="receipt__line">
                        <span>{{ __('pos.change') }}</span>
                        <span>{{ Money::format($sale->change) }}</span>
                    </div>
                @endif

                <hr class="receipt__rule">

                <div class="receipt__foot receipt__muted">{{ __('pos.thank_you') }}</div>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>

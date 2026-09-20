@php
    use App\Support\Money;

    $register = $this->registerSalesOnSelectedDate();
@endphp

<x-filament-panels::page>
    @if ($register['count'] > 0)
        <x-filament::section>
            <p class="pos__note" style="margin-top: 0;">
                {{ __('pos.daily_has_register', [
                    'count' => $register['count'],
                    'total' => Money::format($register['total']),
                ]) }}
            </p>
        </x-filament::section>
    @endif

    {{ $this->content }}
</x-filament-panels::page>

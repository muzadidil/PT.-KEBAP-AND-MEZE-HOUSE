@php
    $sheet = $this->sheet;
@endphp

<x-filament-panels::page>
    <div class="filters">
        <label class="filters__field">
            <span class="pos__label">{{ __('report.as_of') }}</span>
            <input type="date" wire:model.live="asOf">
        </label>

        <div class="filters__presets">
            <button type="button" class="pos__chip" onclick="window.print()">{{ __('report.print') }}</button>
        </div>
    </div>

    <div class="sheet">
        {{-- Aset --}}
        <div class="sheet__section">
            <div class="sheet__title">{{ __('report.balance.assets') }}</div>

            <div class="sheet__row">
                <span>{{ __('report.balance.cash_on_hand') }}</span>
                <span class="{{ $sheet['cash_on_hand'] < 0 ? 'report__negative' : '' }}">
                    {{ $this->money($sheet['cash_on_hand']) }}
                </span>
            </div>

            <div class="sheet__row">
                <span>{{ __('report.balance.bank') }}</span>
                <span class="{{ $sheet['bank'] < 0 ? 'report__negative' : '' }}">
                    {{ $this->money($sheet['bank']) }}
                </span>
            </div>

            <div class="sheet__row sheet__row--total">
                <span>{{ __('report.balance.total_assets') }}</span>
                <span>{{ $this->money($sheet['assets']) }}</span>
            </div>
        </div>

        {{-- Kewajiban & ekuitas --}}
        <div class="sheet__section">
            <div class="sheet__title">{{ __('report.balance.liabilities') }}</div>

            <div class="sheet__row">
                <span>{{ __('report.balance.supplier_payable') }}</span>
                <span>{{ $this->money($sheet['supplier_payable']) }}</span>
            </div>

            <div class="sheet__row">
                <span>{{ __('report.balance.tax_payable') }}</span>
                <span>{{ $this->money($sheet['tax_payable']) }}</span>
            </div>

            <div class="sheet__row">
                <span>{{ __('report.balance.other_payable') }}</span>
                <span>{{ $this->money($sheet['other_payable']) }}</span>
            </div>

            <div class="sheet__row sheet__row--total">
                <span>{{ __('report.balance.total_liabilities') }}</span>
                <span>{{ $this->money($sheet['liabilities']) }}</span>
            </div>

            <div class="sheet__title">{{ __('report.balance.equity') }}</div>

            @foreach ($sheet['owners'] as $owner)
                <div class="sheet__row">
                    <span>{{ $owner['name'] }}</span>
                    <span>{{ $this->money($owner['capital']) }}</span>
                </div>

                <div class="sheet__row sheet__row--sub">
                    <span>
                        {{ __('report.balance.invested') }} {{ $this->money($owner['invested']) }}
                        · {{ __('report.balance.withdrawn') }} {{ $this->money($owner['withdrawn']) }}
                        · {{ __('report.balance.advanced') }} {{ $this->money($owner['advanced']) }}
                    </span>
                    <span></span>
                </div>
            @endforeach

            <div class="sheet__row">
                <span>{{ __('report.balance.retained_earnings') }}</span>
                <span class="{{ $sheet['retained_earnings'] < 0 ? 'report__negative' : '' }}">
                    {{ $this->money($sheet['retained_earnings']) }}
                </span>
            </div>

            <div class="sheet__row sheet__row--total">
                <span>{{ __('report.balance.total_equity') }}</span>
                <span>{{ $this->money($sheet['equity']) }}</span>
            </div>

            <div class="sheet__row sheet__row--total">
                <span>{{ __('report.balance.liabilities_and_equity') }}</span>
                <span>{{ $this->money($sheet['liabilities'] + $sheet['equity']) }}</span>
            </div>
        </div>
    </div>

    {{-- Pembuktian keseimbangan. Ditampilkan apa adanya, termasuk kalau
         ternyata tidak nol, supaya galat tidak tersembunyi. --}}
    @if ($sheet['difference'] === 0)
        <div class="sheet__verdict sheet__verdict--ok">{{ __('report.balance.balanced') }}</div>
    @else
        <div class="sheet__verdict sheet__verdict--bad">
            {{ __('report.balance.not_balanced', ['amount' => $this->money($sheet['difference'])]) }}
        </div>
    @endif

    <p class="pos__note">{{ __('report.balance.note') }}</p>
</x-filament-panels::page>

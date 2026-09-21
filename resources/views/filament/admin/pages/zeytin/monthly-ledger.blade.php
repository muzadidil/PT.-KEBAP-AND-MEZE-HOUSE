@php
    $report = $this->report;
    $rows = $this->rows;
    $daily = $this->grouping === 'daily';
@endphp

<x-filament-panels::page>
    {{-- Saringan periode --}}
    <div class="filters">
        <label class="filters__field">
            <span class="pos__label">{{ __('report.from') }}</span>
            <input type="date" wire:model.live="from" max="{{ $this->to }}">
        </label>

        <label class="filters__field">
            <span class="pos__label">{{ __('report.to') }}</span>
            <input type="date" wire:model.live="to" min="{{ $this->from }}">
        </label>

        <div class="filters__presets">
            @foreach (['today', 'last_7', 'last_30', 'this_month', 'last_month', 'this_year', 'last_year'] as $preset)
                <button type="button" class="pos__chip" wire:click="applyPreset('{{ $preset }}')">
                    {{ __('report.preset.'.$preset) }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Harian, bulanan, tahunan: satu laporan digulung berbeda --}}
    <div class="filters">
        <div class="filters__presets">
            @foreach (['daily', 'monthly', 'yearly'] as $grouping)
                <button type="button"
                        class="pos__chip {{ $this->grouping === $grouping ? 'pos__chip--active' : '' }}"
                        wire:click="setGrouping('{{ $grouping }}')">
                    {{ __('zeytin.ledger.'.$grouping) }}
                </button>
            @endforeach
        </div>

        <div class="filters__presets">
            <button type="button" class="pos__chip" wire:click="exportExcel">
                {{ __('zeytin.export.download') }}
            </button>

            <button type="button" class="pos__chip" wire:click="exportPdf">
                {{ __('zeytin.pdf.download') }}
            </button>

            <button type="button" class="pos__chip" onclick="window.print()">
                {{ __('report.print') }}
            </button>
        </div>
    </div>

    {{-- Kartu ringkasan --}}
    <div class="stats">
        @foreach ($this->cards() as $card)
            <div class="stat">
                <div class="stat__label">{{ $card['label'] }}</div>
                <div class="stat__value {{ ($card['negative'] ?? false) ? 'report__negative' : '' }}">
                    {{ $this->money($card['value']) }}
                </div>
                @isset($card['hint'])
                    <div class="stat__hint">{{ $card['hint'] }}</div>
                @endisset
            </div>
        @endforeach
    </div>

    {{-- Saldo per channel: tunai, non-tunai, dan yang sengaja tidak ikut --}}
    <div class="report__wrap">
        <table class="report">
            <thead>
                <tr>
                    <th>{{ __('zeytin.ledger.channels') }}</th>
                    <th class="num">{{ __('report.total') }}</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($this->channelSummary as $channel)
                    <tr>
                        <td>
                            {{ $channel['label'] }}
                            @if ($channel['excluded'])
                                <span class="report__badge" title="{{ __('zeytin.hint.petty_cash') }}">
                                    {{ __('zeytin.ledger.excluded') }}
                                </span>
                            @endif
                        </td>
                        <td class="num">{{ $this->money($channel['amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>

            <tfoot>
                <tr>
                    <td>{{ __('zeytin.card.sales') }}</td>
                    <td class="num">{{ $this->money($report['total_sales']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Rincian --}}
    <div class="report__wrap">
        @if ($rows->isEmpty())
            <p class="report__empty">{{ __('report.no_data') }}</p>
        @else
            <table class="report">
                <thead>
                    <tr>
                        <th>{{ __('report.period') }}</th>

                        @unless ($daily)
                            <th class="num">{{ __('report.days') }}</th>
                        @endunless

                        @foreach (\App\Support\Zeytin\Channels::all() as $channel)
                            <th class="num">{{ $channel['label'] }}</th>
                        @endforeach

                        <th class="num">{{ __('zeytin.col.total_sales') }}</th>
                        <th class="num">{{ __('zeytin.col.cashless') }}</th>
                        <th class="num">{{ __('zeytin.col.expense') }}</th>

                        @if ($daily)
                            <th class="num">{{ __('zeytin.col.remaining_supplier_cash') }}</th>
                        @endif
                    </tr>
                </thead>

                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $this->periodLabel($row) }}</td>

                            @unless ($daily)
                                <td class="num">{{ $row['recorded_days'] }} / {{ $row['days'] }}</td>
                            @endunless

                            @foreach (\App\Support\Zeytin\Channels::keys() as $key)
                                <td class="num">{{ $this->money($row[$key]) }}</td>
                            @endforeach

                            <td class="num">{{ $this->money($row['total_sales']) }}</td>
                            <td class="num">{{ $this->money($row['cashless']) }}</td>
                            <td class="num">{{ $this->money($row['expense']) }}</td>

                            @if ($daily)
                                <td class="num {{ $row['remaining_supplier_cash'] < 0 ? 'report__negative' : '' }}">
                                    {{ $this->money($row['remaining_supplier_cash']) }}
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>

                <tfoot>
                    <tr>
                        <td>{{ __('report.grand_total') }}</td>

                        @unless ($daily)
                            <td class="num">{{ $report['recorded_days'] }} / {{ $report['days'] }}</td>
                        @endunless

                        @foreach (\App\Support\Zeytin\Channels::keys() as $key)
                            <td class="num">{{ $this->money($report['by_channel'][$key]) }}</td>
                        @endforeach

                        <td class="num">{{ $this->money($report['total_sales']) }}</td>
                        <td class="num">{{ $this->money($report['cashless']) }}</td>
                        <td class="num">{{ $this->money($report['cash_expense']) }}</td>

                        @if ($daily)
                            {{-- Sisa titipan adalah keadaan hari terakhir yang
                                 tercatat, bukan jumlah antar hari — menjumlahkan
                                 saldo tiap hari tidak menghasilkan angka apa pun. --}}
                            <td class="num">{{ $this->money($report['remaining_supplier_cash']) }}</td>
                        @endif
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>
</x-filament-panels::page>

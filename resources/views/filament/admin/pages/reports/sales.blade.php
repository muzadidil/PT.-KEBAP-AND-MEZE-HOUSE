@php
    $rows = $this->rows;
    $report = $this->report;
    $daily = $this->isDaily();
    $channels = \App\Support\Zeytin\Channels::all();
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

            <button type="button" class="pos__chip" wire:click="exportCsv">
                {{ __('report.export_csv') }}
            </button>

            <button type="button" class="pos__chip" onclick="window.print()">
                {{ __('report.print') }}
            </button>
        </div>
    </div>

    {{-- Asal angkanya ditulis di layar, supaya tidak ada yang mengira
         laporan ini dan Buku Besar menghitung dari tempat berbeda. --}}
    <p class="report__note">
        {{ __('report.source_bookkeeping') }}
        <a href="{{ $this->ledgerUrl() }}">{{ __('report.open_ledger') }}</a>
    </p>

    {{-- Ringkasan --}}
    <div class="stats">
        <div class="stat">
            <div class="stat__label">{{ __('zeytin.card.sales') }}</div>
            <div class="stat__value">{{ $this->money($report['total_sales']) }}</div>
            <div class="stat__hint">
                {{ __('zeytin.hint.recorded_days', ['recorded' => $report['recorded_days'], 'days' => $report['days']]) }}
            </div>
        </div>

        <div class="stat">
            <div class="stat__label">{{ __('zeytin.card.total_expenses') }}</div>
            <div class="stat__value">{{ $this->money($report['total_expenses']) }}</div>
        </div>

        <div class="stat">
            <div class="stat__label">{{ __('zeytin.card.profit') }}</div>
            <div class="stat__value {{ $report['net_profit'] < 0 ? 'report__negative' : '' }}">
                {{ $this->money($report['net_profit']) }}
            </div>
        </div>

        <div class="stat">
            <div class="stat__label">{{ __('report.average_per_day') }}</div>
            <div class="stat__value">{{ $this->money($this->averagePerDay()) }}</div>
        </div>
    </div>

    {{-- Tabel --}}
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

                        @foreach ($channels as $channel)
                            <th class="num">
                                {{ $channel['label'] }}
                                @unless ($channel['in_sales'])
                                    <span class="report__badge" title="{{ __('zeytin.hint.petty_cash') }}">{{ __('zeytin.ledger.excluded') }}</span>
                                @endunless
                            </th>
                        @endforeach

                        <th class="num">{{ __('zeytin.col.total_sales') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $this->periodLabel($row) }}</td>

                            @unless ($daily)
                                <td class="num">{{ $row['recorded_days'] }} / {{ $row['days'] }}</td>
                            @endunless

                            @foreach ($channels as $channel)
                                <td class="num">{{ $this->money($row[$channel['key']]) }}</td>
                            @endforeach

                            <td class="num">{{ $this->money($row['total_sales']) }}</td>
                        </tr>
                    @endforeach
                </tbody>

                <tfoot>
                    <tr>
                        <td>{{ __('report.grand_total') }}</td>

                        @unless ($daily)
                            <td class="num">{{ $report['recorded_days'] }} / {{ $report['days'] }}</td>
                        @endunless

                        @foreach ($channels as $channel)
                            <td class="num">{{ $this->money($report['by_channel'][$channel['key']]) }}</td>
                        @endforeach

                        <td class="num">{{ $this->money($report['total_sales']) }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>
</x-filament-panels::page>

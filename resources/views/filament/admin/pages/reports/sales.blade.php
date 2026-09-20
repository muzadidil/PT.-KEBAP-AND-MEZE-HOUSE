@php
    $rows = $this->rows;
    $totals = $this->totals;
    $summary = $this->summary;
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
            @foreach (['last_7', 'last_30', 'this_month', 'last_month', 'this_year'] as $preset)
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

    {{-- Ringkasan --}}
    <div class="stats">
        <div class="stat">
            <div class="stat__label">{{ __('report.summary.sales') }}</div>
            <div class="stat__value">{{ $this->money($totals['total']) }}</div>
            <div class="stat__hint">{{ $totals['transactions'] }} {{ __('report.transactions') }}</div>
        </div>

        <div class="stat">
            <div class="stat__label">{{ __('report.summary.expenses') }}</div>
            <div class="stat__value">{{ $this->money($summary['expenses']) }}</div>
        </div>

        <div class="stat">
            <div class="stat__label">{{ __('report.summary.profit') }}</div>
            <div class="stat__value {{ $summary['profit'] < 0 ? 'report__negative' : '' }}">
                {{ $this->money($summary['profit']) }}
            </div>
        </div>

        <div class="stat">
            <div class="stat__label">{{ __('report.average_per_day') }}</div>
            <div class="stat__value">{{ $this->money($this->averagePerDay) }}</div>
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
                        <th class="num">{{ __('enums.channel.cash') }}</th>
                        <th class="num">{{ __('enums.channel.cashless') }}</th>
                        <th class="num">{{ __('enums.channel.grab') }}</th>
                        <th class="num">{{ __('report.total') }}</th>
                        <th class="num">{{ __('report.transactions') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $this->periodLabel($row) }}</td>
                            <td class="num">{{ $this->money($row['cash']) }}</td>
                            <td class="num">{{ $this->money($row['cashless']) }}</td>
                            <td class="num">{{ $this->money($row['grab']) }}</td>
                            <td class="num">{{ $this->money($row['total']) }}</td>
                            <td class="num">{{ $row['transactions'] }}</td>
                        </tr>
                    @endforeach
                </tbody>

                <tfoot>
                    <tr>
                        <td>{{ __('report.grand_total') }}</td>
                        <td class="num">{{ $this->money($totals['cash']) }}</td>
                        <td class="num">{{ $this->money($totals['cashless']) }}</td>
                        <td class="num">{{ $this->money($totals['grab']) }}</td>
                        <td class="num">{{ $this->money($totals['total']) }}</td>
                        <td class="num">{{ $totals['transactions'] }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>
</x-filament-panels::page>

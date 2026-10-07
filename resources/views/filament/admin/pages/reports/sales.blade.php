@php
    $rows = $this->rows;
    $report = $this->report;
    $daily = $this->isDaily();
    $only = $this->activeChannel();
    $channels = array_values(array_filter(
        \App\Support\Zeytin\Channels::all(),
        fn ($c) => ! $only || $c['key'] === $only,
    ));
@endphp

<x-filament-panels::page>
    <x-report-period :from="$this->from" :to="$this->to">
        <button type="button" class="pos__chip" wire:click="exportCsv">
            {{ __('report.export_csv') }}
        </button>

        <button type="button" class="pos__chip" onclick="window.print()">
            {{ __('report.print') }}
        </button>
    </x-report-period>

    {{-- Pilihan cara bayar: semua, atau satu saja (Cash, BNI, Grab Food, ...) --}}
    <div class="filters__presets">
        <button type="button" class="pos__chip" wire:click="$set('channel', '')" @if (! $only) aria-current="true" @endif>
            {{ __('report.all_channels') }}
        </button>

        @foreach ($this->channelOptions() as $option)
            <button type="button" class="pos__chip" wire:click="$set('channel', '{{ $option['key'] }}')" @if ($only === $option['key']) aria-current="true" @endif>
                {{ $option['label'] }}
            </button>
        @endforeach
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
            <div class="stat__label">{{ $only ? __('report.sales_of', ['channel' => $this->activeChannelLabel()]) : __('zeytin.card.sales') }}</div>
            <div class="stat__value">{{ $this->money($this->totalAmount()) }}</div>
            <div class="stat__hint">
                {{ __('zeytin.hint.recorded_days', ['recorded' => $report['recorded_days'], 'days' => $report['days']]) }}
            </div>
        </div>

        {{-- Rinciannya ditulis di kartu: angka pengeluaran tanpa rincian
             selalu memancing pertanyaan "ini dari mana". --}}
        @unless ($only)
        <div class="stat">
            <div class="stat__label">{{ __('zeytin.card.total_expenses') }}</div>
            <div class="stat__value">{{ $this->money($report['total_expenses']) }}</div>
            <div class="stat__hint">
                {{ __('zeytin.card.cash_expense') }} {{ $this->money($report['cash_expense']) }}<br>
                {{ __('zeytin.card.transfers') }} {{ $this->money($report['transfers']) }}<br>
                {{ __('zeytin.card.payroll') }} {{ $this->money($report['payroll']) }}
            </div>
        </div>

        <div class="stat">
            <div class="stat__label">{{ __('zeytin.card.profit') }}</div>
            <div class="stat__value {{ $report['net_profit'] < 0 ? 'report__negative' : '' }}">
                {{ $this->money($report['net_profit']) }}
            </div>
        </div>
        @endunless

        <div class="stat">
            <div class="stat__label">{{ __('report.average_per_recorded_day') }}</div>
            <div class="stat__value">{{ $this->money($this->averagePerDay()) }}</div>
            <div class="stat__hint">{{ __('report.average_hint', ['days' => $this->averageDays()]) }}</div>
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

                        @unless ($only)
                            <th class="num">{{ __('zeytin.col.total_sales') }}</th>
                        @endunless
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

                            @unless ($only)
                                <td class="num">{{ $this->money($row['total_sales']) }}</td>
                            @endunless
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

                        @unless ($only)
                            <td class="num">{{ $this->money($report['total_sales']) }}</td>
                        @endunless
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>
</x-filament-panels::page>

@php
    $report = $this->report;
    $opening = $report['opening'];
@endphp

<x-filament-panels::page>
    <x-report-period :from="$this->from" :to="$this->to">
        <button type="button" class="pos__chip" onclick="window.print()">{{ __('report.print') }}</button>
    </x-report-period>

    <p class="report__note">{{ __('cash.formula') }}</p>

    <div class="stats">
        <div class="stat">
            <div class="stat__label">{{ __('cash.card.balance') }}</div>
            <div class="stat__value {{ $report['end_balance'] < 0 ? 'report__negative' : '' }}">{{ $this->money($report['end_balance']) }}</div>
            <div class="stat__hint">{{ __('cash.card.as_of', ['date' => $this->toDate()->translatedFormat('j M Y')]) }}</div>
        </div>

        <div class="stat">
            <div class="stat__label">{{ __('cash.card.start') }}</div>
            <div class="stat__value">{{ $this->money($report['start_balance']) }}</div>
            <div class="stat__hint">
                @if ($opening['date'])
                    {{ __('cash.card.opening', ['amount' => $this->money($opening['amount']), 'date' => \Illuminate\Support\Carbon::parse($opening['date'])->translatedFormat('j M Y')]) }}
                @else
                    {{ __('cash.card.no_opening') }}
                @endif
            </div>
        </div>

        <div class="stat">
            <div class="stat__label">{{ __('cash.col.cash_in') }}</div>
            <div class="stat__value">{{ $this->money($report['cash_in']) }}</div>
        </div>

        <div class="stat">
            <div class="stat__label">{{ __('cash.col.cash_out') }}</div>
            <div class="stat__value">{{ $this->money($report['cash_out']) }}</div>
        </div>
    </div>

    <div class="report__wrap">
        <table class="report">
            <thead>
                <tr>
                    <th>{{ __('report.date') }}</th>
                    <th class="num">{{ __('cash.col.opening') }}</th>
                    <th class="num">{{ __('cash.col.cash_in') }}</th>
                    <th class="num">{{ __('cash.col.cash_out') }}</th>
                    <th class="num">{{ __('balance_adjustment.col') }}</th>
                    <th class="num">{{ __('cash.col.balance') }}</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($report['rows'] as $row)
                    <tr>
                        <td>{{ $row['date']->translatedFormat('D, j M Y') }}</td>
                        @if ($row['counted'])
                            <td class="num">{{ $this->money($row['opening']) }}</td>
                            <td class="num">{{ $this->money($row['cash_in']) }}</td>
                            <td class="num">{{ $this->money($row['cash_out']) }}</td>
                            <td class="num">{{ $row['adjustment'] === 0 ? '–' : $this->money($row['adjustment']) }}</td>
                            <td class="num {{ $row['balance'] < 0 ? 'report__negative' : '' }}">{{ $this->money($row['balance']) }}</td>
                        @else
                            <td class="num" colspan="5">{{ __('cash.before_opening') }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>

            <tfoot>
                <tr>
                    <td>{{ __('report.grand_total') }}</td>
                    <td class="num">{{ $this->money($report['start_balance']) }}</td>
                    <td class="num">{{ $this->money($report['cash_in']) }}</td>
                    <td class="num">{{ $this->money($report['cash_out']) }}</td>
                    <td class="num">{{ $this->money($report['adjustment']) }}</td>
                    <td class="num">{{ $this->money($report['end_balance']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</x-filament-panels::page>

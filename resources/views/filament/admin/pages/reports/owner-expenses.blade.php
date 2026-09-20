@php
    $settlement = $this->settlement;
    $rows = $settlement['rows'];
    $expenses = $this->expenses;
    $shareTotal = $this->sharePercentTotal;
@endphp

<x-filament-panels::page>
    <div class="filters">
        <label class="filters__field">
            <span class="pos__label">{{ __('report.month') }}</span>
            <input type="month" wire:model.live="month">
        </label>

        <div class="filters__presets">
            <button type="button" class="pos__chip" onclick="window.print()">{{ __('report.print') }}</button>
        </div>
    </div>

    <p class="pos__note" style="margin-top: 0;">{{ __('report.owner_split.intro') }}</p>

    @if ($rows === [])
        <div class="report__wrap">
            <p class="report__empty">{{ __('report.owner_split.no_owners') }}</p>
        </div>
    @else
        @if ($shareTotal !== 100)
            <div class="sheet__verdict sheet__verdict--bad">
                {{ __('report.owner_split.share_warning', ['total' => $shareTotal]) }}
            </div>
        @endif

        <div class="stats">
            <div class="stat">
                <div class="stat__label">{{ __('report.owner_split.total_advanced') }}</div>
                <div class="stat__value">{{ $this->money($settlement['total']) }}</div>
                <div class="stat__hint">{{ $this->fromDate()->translatedFormat('F Y') }}</div>
            </div>

            @foreach ($rows as $row)
                <div class="stat">
                    <div class="stat__label">{{ $row['name'] }} · {{ $row['percent'] }}%</div>
                    <div class="stat__value {{ $row['balance'] < 0 ? 'report__negative' : ($row['balance'] > 0 ? 'report__positive' : '') }}">
                        {{ $this->money(abs($row['balance'])) }}
                    </div>
                    <div class="stat__hint">
                        @if ($row['balance'] > 0)
                            {{ __('report.owner_split.receives') }}
                        @elseif ($row['balance'] < 0)
                            {{ __('report.owner_split.owes') }}
                        @else
                            {{ __('report.owner_split.settled') }}
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="report__wrap">
            <table class="report">
                <thead>
                    <tr>
                        <th>{{ __('field.owner') }}</th>
                        <th class="num">{{ __('field.share_percent') }}</th>
                        <th class="num">{{ __('report.owner_split.paid') }}</th>
                        <th class="num">{{ __('report.owner_split.share') }}</th>
                        <th class="num">{{ __('report.owner_split.balance') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td class="num">{{ $row['percent'] }}%</td>
                            <td class="num">{{ $this->money($row['paid']) }}</td>
                            <td class="num">{{ $this->money($row['share']) }}</td>
                            <td class="num {{ $row['balance'] < 0 ? 'report__negative' : ($row['balance'] > 0 ? 'report__positive' : '') }}">
                                {{ $this->money($row['balance']) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>

                <tfoot>
                    <tr>
                        <td>{{ __('report.grand_total') }}</td>
                        <td class="num">{{ $shareTotal }}%</td>
                        <td class="num">{{ $this->money($settlement['total']) }}</td>
                        <td class="num">{{ $this->money($settlement['total']) }}</td>
                        <td class="num">{{ $this->money(0) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    <div class="report__wrap" style="margin-top: 1rem;">
        @if ($expenses->isEmpty())
            <p class="report__empty">{{ __('report.no_data') }}</p>
        @else
            <table class="report">
                <thead>
                    <tr>
                        <th>{{ __('field.date') }}</th>
                        <th>{{ __('field.description') }}</th>
                        <th>{{ __('field.category') }}</th>
                        <th>{{ __('field.method') }}</th>
                        <th>{{ __('field.paid_by_owner') }}</th>
                        <th class="num">{{ __('field.amount') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($expenses as $expense)
                        <tr>
                            <td>{{ $expense->spent_on->format('d/m/Y') }}</td>
                            <td>{{ $expense->description }}</td>
                            <td>{{ $expense->category->getLabel() }}</td>
                            <td>{{ $expense->method->getLabel() }}</td>
                            <td>{{ $expense->paidByOwner?->name }}</td>
                            <td class="num">{{ $this->money($expense->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>

                <tfoot>
                    <tr>
                        <td colspan="5">{{ __('report.grand_total') }}</td>
                        <td class="num">{{ $this->money($settlement['total']) }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>
</x-filament-panels::page>

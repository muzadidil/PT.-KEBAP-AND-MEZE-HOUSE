@php
    $rows = $this->rows;
@endphp

<x-filament-panels::page>
    <x-report-period :from="$this->from" :to="$this->to" />

    <p class="report__note">{{ __('report.source.salary') }}</p>
    <p class="report__note">{{ __('payroll_paid.report.note') }}</p>

    <div class="report__wrap">
        @if ($rows->isEmpty())
            <p class="report__empty">{{ __('report.no_data') }}</p>
        @else
            <table class="report">
                <thead>
                    <tr>
                        <th>{{ __('report.period') }}</th>
                        <th class="num">{{ __('report.people') }}</th>
                        <th class="num">{{ __('report.total') }}</th>
                        <th>{{ __('payroll_paid.report.col_paid') }}</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['month']->translatedFormat('F Y') }}</td>
                            <td class="num">{{ $row['people'] }}</td>
                            <td class="num">{{ $this->money($row['total']) }}</td>
                            <td>
                                @if ($row['paid_count'] === 0)
                                    <span style="opacity:.6">{{ __('payroll_paid.unpaid') }}</span>
                                @else
                                    <strong>{{ $row['paid_on']->translatedFormat('j M Y') }}</strong>
                                    · {{ __('payroll_paid.via.'.$row['paid_via']) }}
                                    @if ($row['paid_count'] < $row['people'])
                                        <br><small>{{ __('payroll_paid.report.partial', ['paid' => $row['paid_count'], 'people' => $row['people']]) }}</small>
                                    @endif
                                @endif
                            </td>
                            <td>
                                <x-filament::button size="xs" color="{{ $row['paid_count'] === 0 ? 'success' : 'gray' }}"
                                    wire:click="mountAction('pay', { month: '{{ $row['month']->toDateString() }}' })">
                                    {{ $row['paid_count'] === 0 ? __('payroll_paid.report.btn_record') : __('payroll_paid.report.btn_change') }}
                                </x-filament::button>
                                @if ($row['paid_count'] > 0)
                                    <x-filament::button size="xs" color="gray" outlined
                                        wire:click="mountAction('unpay', { month: '{{ $row['month']->toDateString() }}' })">
                                        {{ __('payroll_paid.report.btn_undo') }}
                                    </x-filament::button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>

                <tfoot>
                    <tr>
                        <td>{{ __('report.grand_total') }}</td>
                        <td class="num"></td>
                        <td class="num">{{ $this->money($rows->sum('total')) }}</td>
                        <td></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>
</x-filament-panels::page>

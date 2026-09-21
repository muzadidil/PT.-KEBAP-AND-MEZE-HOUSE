@php
    $rows = $this->rows;
@endphp

<x-filament-panels::page>
    <x-report-period :from="$this->from" :to="$this->to" />

    <p class="report__note">{{ __('report.source.salary') }}</p>

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
                    </tr>
                </thead>

                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['month']->translatedFormat('F Y') }}</td>
                            <td class="num">{{ $row['people'] }}</td>
                            <td class="num">{{ $this->money($row['total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>

                <tfoot>
                    <tr>
                        <td>{{ __('report.grand_total') }}</td>
                        <td class="num"></td>
                        <td class="num">{{ $this->money($rows->sum('total')) }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>
</x-filament-panels::page>

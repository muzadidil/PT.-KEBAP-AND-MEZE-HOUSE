@php
    $report = $this->report;
    $rows = $report['rows'];
    $totals = $report['totals'];
    $columns = \App\Support\Tax\TaxFilingExport::columns();
@endphp

<x-filament-panels::page>
    <div class="filters">
        <label class="filters__field">
            <span class="pos__label">{{ __('report.year') }}</span>
            <select wire:model.live="year">
                @foreach ($this->years() as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
        </label>

        <div class="filters__presets">
            <button type="button" class="pos__chip" wire:click="exportExcel">{{ __('report.export_excel') }}</button>
            <a class="pos__chip" href="{{ $this->pdfUrl() }}" target="_blank" rel="noopener">{{ __('report.pdf') }}</a>
            <button type="button" class="pos__chip" onclick="window.print()">{{ __('report.print') }}</button>
        </div>
    </div>

    {{-- Asal angkanya ditulis di layar: koreksi di sini tidak mengubah data asli. --}}
    <p class="report__note">{{ __('tax_filing.source') }}</p>

    <div class="stats">
        <div class="stat">
            <div class="stat__label">{{ __('tax_filing.col.revenue') }}</div>
            <div class="stat__value">{{ $this->money($totals['revenue']) }}</div>
            <div class="stat__hint">{{ __('tax_filing.card.system', ['amount' => $this->money($totals['system_revenue'])]) }}</div>
        </div>

        <div class="stat">
            <div class="stat__label">{{ __('tax_filing.card.final') }}</div>
            <div class="stat__value">{{ $this->money($totals['final_due']) }}</div>
            <div class="stat__hint">{{ __('tax_filing.card.paid', ['amount' => $this->money($totals['paid_final'])]) }}</div>
        </div>

        <div class="stat">
            <div class="stat__label">{{ __('tax_filing.card.ppn') }}</div>
            <div class="stat__value">{{ $this->money($totals['ppn_due']) }}</div>
            <div class="stat__hint">{{ __('tax_filing.card.paid', ['amount' => $this->money($totals['paid_ppn'])]) }}</div>
        </div>

        <div class="stat">
            <div class="stat__label">{{ __('tax_filing.col.outstanding') }}</div>
            <div class="stat__value {{ $totals['outstanding'] > 0 ? 'report__negative' : '' }}">{{ $this->money($totals['outstanding']) }}</div>
        </div>
    </div>

    <div class="report__wrap">
        <table class="report">
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th class="{{ $column['money'] ? 'num' : '' }}">{{ $column['label'] }}</th>
                    @endforeach
                    <th class="print-hide"></th>
                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($columns as $column)
                            <td class="{{ $column['money'] ? 'num' : '' }}">
                                {{ $column['money']
                                    ? $this->money((int) \App\Support\Tax\TaxFilingExport::cell($row, $column['key']))
                                    : \App\Support\Tax\TaxFilingExport::cell($row, $column['key']) }}

                                @if ($column['key'] === 'revenue' && $row['adjusted'])
                                    <span class="report__badge" title="{{ __('tax_filing.adjusted_hint') }}">{{ __('tax_filing.adjusted') }}</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="print-hide">
                            <button type="button" class="pos__chip" wire:click="mountAction('editMonth', { month: {{ $row['month'] }} })">
                                {{ __('tax_filing.edit.button') }}
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>

            <tfoot>
                <tr>
                    @foreach ($columns as $column)
                        <td class="{{ $column['money'] ? 'num' : '' }}">
                            @if ($loop->first)
                                {{ __('report.grand_total') }}
                            @elseif ($column['money'])
                                {{ $this->money($totals[$column['key']] ?? 0) }}
                            @endif
                        </td>
                    @endforeach
                    <td class="print-hide"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <p class="report__note">{{ __('tax_filing.adjusted_hint') }}</p>

    {{-- Riwayat perubahan --}}
    <div class="report__wrap print-hide">
        <h3 class="pos__label">{{ __('tax_filing.history.title') }}</h3>

        @if ($this->history->isEmpty())
            <p class="report__empty">{{ __('tax_filing.history.empty') }}</p>
        @else
            <table class="report">
                <thead>
                    <tr>
                        <th>{{ __('tax_filing.history.when') }}</th>
                        <th>{{ __('tax_filing.history.who') }}</th>
                        <th>{{ __('report.month') }}</th>
                        <th>{{ __('tax_filing.history.field') }}</th>
                        <th>{{ __('tax_filing.history.from') }}</th>
                        <th>{{ __('tax_filing.history.to') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->history as $log)
                        <tr>
                            <td>{{ $log->created_at->translatedFormat('j M Y H:i') }}</td>
                            <td>{{ $log->user?->name ?? '–' }}</td>
                            <td>{{ \Illuminate\Support\Carbon::create($log->year, $log->month, 1)->translatedFormat('F Y') }}</td>
                            <td>{{ __('tax_filing.field.'.$log->field) }}</td>
                            <td>{{ $log->old_value ?? '–' }}</td>
                            <td>{{ $log->new_value ?? '–' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-filament-panels::page>

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
            {{-- Formulir biasa, bukan tautan Livewire: angka yang dikirim selalu
                 angka yang sedang terketik di kotaknya, tanpa menunggu layar
                 diperbarui. --}}
            <form method="get" action="{{ route('filament.admin.pdf.tax-filing') }}" target="_blank" style="display: inline-flex; gap: .5rem; align-items: end;">
                <input type="hidden" name="year" value="{{ $this->year }}">
                @if ($this->month > 0)
                    <input type="hidden" name="month" value="{{ $this->month }}">
                @endif
                <label class="filters__field">
                    <span class="pos__label">{{ __('tax_filing.pdf_share') }}</span>
                    <input type="number" name="share" min="0" max="100" step="0.01" wire:model="pdfShare" style="width: 6rem;">
                </label>
                <button type="submit" class="pos__chip">{{ __('report.pdf') }}</button>
            </form>
            <button type="button" class="pos__chip" onclick="window.print()">{{ __('report.print') }}</button>
        </div>
    </div>

    {{-- Pilih tampilan: setahun, atau satu bulan --}}
    <div class="filters__presets">
        <button type="button" class="pos__chip" wire:click="openMonth(0)" @if ($this->month === 0) aria-current="true" @endif>
            {{ __('tax_filing.month.year_view') }}
        </button>

        @foreach (range(1, 12) as $m)
            <button type="button" class="pos__chip" wire:click="openMonth({{ $m }})" @if ($this->month === $m) aria-current="true" @endif>
                {{ \Illuminate\Support\Carbon::create($this->year, $m, 1)->translatedFormat('M') }}
            </button>
        @endforeach
    </div>

    {{-- Asal angkanya ditulis di layar: koreksi di sini tidak mengubah data asli. --}}
    <p class="report__note">{{ __('tax_filing.source') }}</p>

    @if ($this->month > 0)
        @php
            $mr = $this->monthReport;
            $sum = $mr['summary'];
            $dayColumns = \App\Support\Tax\TaxMonthExport::dayColumns();
        @endphp

        <div class="stats">
            <div class="stat">
                <div class="stat__label">{{ __('tax_filing.col.revenue') }} — {{ $mr['label'] }}</div>
                <div class="stat__value">{{ $this->money($sum['revenue']) }}</div>
                <div class="stat__hint">
                    {{ __('tax_filing.card.system', ['amount' => $this->money($sum['system_revenue'])]) }}<br>
                    {{ __('tax_filing.col.investor_share') }} ({{ $sum['investor_share_pct'] }}%) {{ $this->money($sum['investor_share']) }}<br>
                    {{ __('tax_filing.col.tax_base') }} {{ $this->money($sum['tax_base']) }}
                </div>
            </div>

            <div class="stat">
                <div class="stat__label">{{ __('tax_filing.card.final') }} ({{ $sum['final_rate'] }}%)</div>
                <div class="stat__value">{{ $this->money($sum['final_due']) }}</div>
                <div class="stat__hint">{{ __('tax_filing.card.paid', ['amount' => $this->money($sum['paid_final'])]) }}</div>
            </div>

            <div class="stat">
                <div class="stat__label">{{ __('tax_filing.card.ppn') }} ({{ $sum['ppn_rate'] }}%)</div>
                <div class="stat__value">{{ $this->money($sum['ppn_due']) }}</div>
                <div class="stat__hint">
                    {{ __('tax_filing.col.ppn_output') }} {{ $this->money($sum['ppn_output']) }}<br>
                    {{ __('tax_filing.col.ppn_input') }} {{ $this->money($sum['ppn_input']) }}<br>
                    {{ __('tax_filing.card.paid', ['amount' => $this->money($sum['paid_ppn'])]) }}
                </div>
            </div>

            <div class="stat">
                <div class="stat__label">{{ __('tax_filing.col.outstanding') }}</div>
                <div class="stat__value {{ $sum['outstanding'] > 0 ? 'report__negative' : '' }}">{{ $this->money($sum['outstanding']) }}</div>
                <div class="stat__hint">{{ $sum['is_reported'] ? __('tax_filing.reported') : __('tax_filing.not_reported') }}</div>
            </div>
        </div>

        <div class="filters__presets print-hide">
            <button type="button" class="pos__chip" wire:click="mountAction('editMonth', { month: {{ $mr['month'] }} })">
                {{ __('tax_filing.edit.button') }} {{ $mr['label'] }}
            </button>
        </div>

        @if ($sum['note'])
            <p class="report__note">{{ __('field.note') }}: {{ $sum['note'] }}</p>
        @endif

        <div class="report__wrap">
            <h3 class="pos__label">{{ __('tax_filing.month.daily_title') }}</h3>

            <table class="report">
                <thead>
                    <tr>
                        @foreach ($dayColumns as $column)
                            <th class="{{ $loop->first ? '' : 'num' }}">{{ $column['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($mr['days'] as $day)
                        <tr>
                            @foreach ($dayColumns as $column)
                                <td class="{{ $loop->first ? '' : 'num' }}">
                                    {{ $column['key'] === 'date' ? $day['date']->translatedFormat('D, j M Y') : $this->money((int) $day[$column['key']]) }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        @foreach ($dayColumns as $column)
                            <td class="{{ $loop->first ? '' : 'num' }}">
                                {{ $loop->first ? __('report.grand_total') : $this->money((int) $mr['days']->sum($column['key'])) }}
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </div>

        <p class="report__note">{{ __('tax_filing.adjusted_hint') }}</p>
    @else
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
                                @if ($column['key'] === 'label')
                                    <button type="button" wire:click="openMonth({{ $row['month'] }})" style="text-decoration: underline;">{{ $row['label'] }}</button>
                                @else
                                    {{ $column['money']
                                        ? $this->money((int) \App\Support\Tax\TaxFilingExport::cell($row, $column['key']))
                                        : \App\Support\Tax\TaxFilingExport::cell($row, $column['key']) }}
                                @endif

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

    @endif

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

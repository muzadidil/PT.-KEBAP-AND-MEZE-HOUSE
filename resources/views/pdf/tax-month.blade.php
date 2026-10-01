{{-- Laporan Pajak; lihat App\Support\Tax\TaxFilingPdf. Untuk dompdf: tabel dan CSS 2.1 saja. --}}
@php
    use App\Support\Money;
    $day = fn (array $column, array $row) => $column['key'] === 'date'
        ? $row['date']->translatedFormat('j M Y')
        : Money::format((int) $row[$column['key']]);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('nav.tax_filing') }} {{ $report['label'] }} — {{ $letterhead['name'] }}</title>
    <style>
        @page { margin: 96pt 40pt 56pt 40pt; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 7.5pt; line-height: 1.35; color: #111827; }
        table { border-collapse: collapse; }
        td, th { padding: 0; vertical-align: top; }
        .head { position: fixed; top: -80pt; left: 0; right: 0; height: 56pt; border-bottom: 1.5pt solid #4d7c0f; }
        .head table { width: 100%; }
        .head__brand { font-size: 17pt; font-weight: bold; color: #4d7c0f; line-height: 1; }
        .head__legal { margin-top: 5pt; font-size: 7.5pt; font-weight: bold; }
        .head__address { margin-top: 1pt; font-size: 6.5pt; color: #6b7280; }
        .head__title { font-size: 12pt; font-weight: bold; text-align: right; line-height: 1; }
        .head__period { margin-top: 5pt; font-size: 8pt; text-align: right; }
        .head__currency { margin-top: 1pt; font-size: 6.5pt; color: #6b7280; text-align: right; }
        .foot { position: fixed; bottom: -30pt; left: 0; right: 0; height: 14pt; padding-top: 5pt; border-top: .5pt solid #d1d5db; font-size: 6.5pt; color: #6b7280; }
        .source { margin: 0 0 10pt; font-size: 7pt; color: #6b7280; }
        table.data { width: 100%; }
        table.data th { padding: 0 4pt 4pt; border-bottom: 1pt solid #4d7c0f; font-size: 5.5pt; font-weight: bold; text-transform: uppercase; color: #374151; text-align: left; vertical-align: bottom; }
        table.data td { padding: 3.5pt 4pt; border-bottom: .5pt solid #e5e7eb; }
        table.data th.num, table.data td.num { text-align: right; white-space: nowrap; }
        table.data th.first, table.data td.first { padding-left: 0; }
        table.data th.last, table.data td.last { padding-right: 0; }
        table.data tfoot td { padding-top: 4pt; border-top: .75pt solid #111827; border-bottom: 2pt double #111827; font-weight: bold; }
        .adj { color: #b45309; }
            table.sum { width: 60%; margin-bottom: 14pt; }
        table.sum td { padding: 3pt 4pt; border-bottom: .5pt solid #e5e7eb; }
        table.sum td.v { text-align: right; white-space: nowrap; font-weight: bold; }
        h3 { margin: 0 0 6pt; font-size: 9pt; }
    </style>
</head>
<body>
    <div class="head">
        <table>
            <tr>
                <td>
                    <div class="head__brand">{{ $letterhead['name'] }}</div>
                    <div class="head__legal">{{ $letterhead['legal_name'] }}</div>
                    <div class="head__address">{{ $letterhead['address'] }}</div>
                </td>
                <td>
                    <div class="head__title">{{ __('nav.tax_filing') }}</div>
                    <div class="head__period">{{ $report['label'] }}</div>
                    @if (($report['share_override'] ?? null) !== null)
                        <div class="head__currency">{{ __('tax_filing.col.investor_share') }}: {{ $report['share_override'] }}%</div>
                    @endif
                    <div class="head__currency">{{ __('zeytin.pdf.currency') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="foot">
        {{ __('zeytin.pdf.generated') }} {{ now($letterhead['timezone'])->translatedFormat('j M Y, H:i T') }}
        · {{ $letterhead['name'] }} — {{ __('nav.tax_filing') }} {{ $report['label'] }}
    </div>

    <p class="source">{{ __('tax_filing.source') }}</p>

    <table class="sum">
        @foreach ($lines as [$label, $value, $money])
            <tr>
                <td>{{ $label }}</td>
                <td class="v">{{ $money ? Money::format((int) $value) : $value }}</td>
            </tr>
        @endforeach
    </table>

    <h3>{{ __('tax_filing.month.daily_title') }}</h3>

    <table class="data">
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th class="{{ $loop->first ? 'first' : 'num' }} {{ $loop->last ? 'last' : '' }}">{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($report['days'] as $row)
                <tr>
                    @foreach ($columns as $column)
                        <td class="{{ $loop->first ? 'first' : 'num' }} {{ $loop->last ? 'last' : '' }}">{{ $day($column, $row) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                @foreach ($columns as $column)
                    <td class="{{ $loop->first ? 'first' : 'num' }} {{ $loop->last ? 'last' : '' }}">
                        {{ $loop->first ? __('report.grand_total') : Money::format((int) $report['days']->sum($column['key'])) }}
                    </td>
                @endforeach
            </tr>
        </tfoot>
    </table>

    <p class="source" style="margin-top: 8pt;">{{ __('tax_filing.adjusted_hint') }}</p>
</body>
</html>

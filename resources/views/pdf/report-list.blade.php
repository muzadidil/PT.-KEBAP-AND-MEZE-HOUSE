{{--
    Laporan berbentuk daftar; lihat App\Support\Reports\ListPdf.

    Ditulis untuk dompdf: tata letak tabel dan CSS 2.1 saja. Bentuknya
    mengikuti pdf/zeytin-period: kop berulang tiap halaman, tabel bergaris
    tipis, dan baris total bergaris ganda.
--}}
@php
    use App\Support\Money;

    $cell = function (array $column, $row) {
        $value = ($column['value'])($row);

        if ($column['money'] ?? false) {
            return Money::format((int) $value);
        }

        if (($column['date'] ?? false) && $value) {
            return \Illuminate\Support\Carbon::parse($value)->translatedFormat('j M Y');
        }

        return (string) ($value ?? '–');
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — {{ $letterhead['name'] }}</title>
    <style>
        @page { margin: 96pt 40pt 56pt 40pt; }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 7.5pt;
            line-height: 1.35;
            color: #111827;
        }

        table { border-collapse: collapse; }
        td, th { padding: 0; vertical-align: top; }

        .head {
            position: fixed;
            top: -80pt;
            left: 0;
            right: 0;
            height: 56pt;
            border-bottom: 1.5pt solid #4d7c0f;
        }
        .head table { width: 100%; }
        .head__brand { font-size: 17pt; font-weight: bold; color: #4d7c0f; line-height: 1; }
        .head__legal { margin-top: 5pt; font-size: 7.5pt; font-weight: bold; }
        .head__address { margin-top: 1pt; font-size: 6.5pt; color: #6b7280; }
        .head__title { font-size: 12pt; font-weight: bold; text-align: right; line-height: 1; }
        .head__period { margin-top: 5pt; font-size: 8pt; text-align: right; }
        .head__currency { margin-top: 1pt; font-size: 6.5pt; color: #6b7280; text-align: right; }

        .foot {
            position: fixed;
            bottom: -30pt;
            left: 0;
            right: 0;
            height: 14pt;
            padding-top: 5pt;
            border-top: .5pt solid #d1d5db;
            font-size: 6.5pt;
            color: #6b7280;
        }

        .source { margin: 0 0 10pt; font-size: 7pt; color: #6b7280; }

        table.data { width: 100%; }
        table.data th {
            padding: 0 4pt 4pt;
            border-bottom: 1pt solid #4d7c0f;
            font-size: 6pt;
            font-weight: bold;
            letter-spacing: .3pt;
            text-transform: uppercase;
            color: #374151;
            text-align: left;
            vertical-align: bottom;
        }
        table.data td {
            padding: 3.5pt 4pt;
            border-bottom: .5pt solid #e5e7eb;
        }
        table.data th.num,
        table.data td.num { text-align: right; white-space: nowrap; }
        table.data th.first,
        table.data td.first { padding-left: 0; }
        table.data th.last,
        table.data td.last { padding-right: 0; }
        table.data tfoot td {
            padding-top: 4pt;
            border-top: .75pt solid #111827;
            border-bottom: 2pt double #111827;
            font-weight: bold;
        }

        .empty {
            padding: 22pt 0;
            border: .5pt dashed #d1d5db;
            text-align: center;
            color: #6b7280;
        }
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
                    <div class="head__title">{{ $title }}</div>
                    <div class="head__period">{{ $period }}</div>
                    <div class="head__currency">{{ __('zeytin.pdf.currency') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="foot">
        {{ __('zeytin.pdf.generated') }} {{ now($letterhead['timezone'])->translatedFormat('j M Y, H:i T') }}
        · {{ $letterhead['name'] }} — {{ $title }}, {{ $period }}
    </div>

    <p class="source">{{ $source }}</p>

    @if ($rows->isEmpty())
        <div class="empty">{{ __('report.no_data') }}</div>
    @else
        <table class="data">
            <thead>
                <tr>
                    @foreach ($columns as $i => $column)
                        <th class="{{ ($column['money'] ?? false) ? 'num' : '' }} {{ $loop->first ? 'first' : '' }} {{ $loop->last ? 'last' : '' }}">
                            {{ $column['label'] }}
                        </th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($columns as $column)
                            <td class="{{ ($column['money'] ?? false) ? 'num' : '' }} {{ $loop->first ? 'first' : '' }} {{ $loop->last ? 'last' : '' }}">
                                {{ $cell($column, $row) }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>

            <tfoot>
                <tr>
                    @foreach ($columns as $column)
                        <td class="{{ ($column['money'] ?? false) ? 'num' : '' }} {{ $loop->first ? 'first' : '' }} {{ $loop->last ? 'last' : '' }}">
                            @if ($loop->first)
                                {{ __('report.grand_total') }}
                            @elseif ($column['money'] ?? false)
                                {{ Money::format($total) }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            </tfoot>
        </table>
    @endif
</body>
</html>

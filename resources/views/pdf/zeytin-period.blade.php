{{--
    Laporan PDF satu periode; lihat App\Support\Zeytin\PeriodPdf.

    Ditulis untuk dompdf: tata letak tabel dan CSS 2.1 saja, tanpa flexbox
    atau grid. Hurufnya DejaVu Sans, yang ikut terpasang bersama dompdf,
    karena huruf bawaan PDF tidak punya tanda "—" dan "−".
--}}
@php
    use App\Support\Money;
    use App\Support\Zeytin\Channels;
    use App\Support\Zeytin\PeriodPdf;

    $num = fn (?int $amount) => ($amount < 0 ? '−' : '').number_format(abs((int) $amount), 0, ',', '.');

    $summary = [
        [__('zeytin.card.sales'), $report['total_sales'], false],
        [__('zeytin.card.cash_expense'), $report['cash_expense'], false],
        [__('zeytin.card.transfers'), $report['transfers'], false],
        [__('zeytin.card.payroll'), $report['payroll'], false],
        [__('zeytin.card.total_expenses'), $report['total_expenses'], false],
        [__('zeytin.card.profit'), $report['net_profit'], true],
        [__('zeytin.card.outstanding'), $report['outstanding'], false],
        [__('zeytin.card.global_balance'), $report['global_balance'], true],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('zeytin.pdf.title.'.$grouping) }} — {{ $letterhead['name'] }}</title>
    <style>
        @page { margin: 96px 40px 48px 40px; }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 8.5px;
            color: #0f172a;
        }

        /* Kop dan catatan kaki diulang di tiap halaman. */
        .head {
            position: fixed;
            top: -96px;
            left: -40px;
            right: -40px;
            height: 64px;
            padding: 0 40px;
            background: #4d7c0f;
            color: #fff;
        }
        .head table { width: 100%; height: 64px; border-collapse: collapse; }
        .head td { vertical-align: middle; padding: 0; }
        .head__name { font-size: 18px; font-weight: bold; }
        .head__meta { font-size: 8px; margin-top: 3px; }
        .head__title { font-size: 11px; font-weight: bold; text-align: right; }
        .head__period { font-size: 8px; text-align: right; margin-top: 3px; }

        .foot {
            position: fixed;
            bottom: -30px;
            left: 0;
            right: 0;
            font-size: 7px;
            color: #64748b;
        }
        .foot__page { float: right; }
        .foot__page:after { content: counter(page); }

        h2 {
            margin: 0 0 6px;
            font-size: 11px;
            color: #4d7c0f;
        }

        table.data { width: 100%; border-collapse: collapse; }
        table.data th,
        table.data td { padding: 3px 4px; text-align: right; white-space: nowrap; }
        table.data th:first-child,
        table.data td:first-child { text-align: left; }
        table.data thead th { background: #4d7c0f; color: #fff; font-weight: bold; }
        table.data tbody tr:nth-child(even) td { background: #f1f5f0; }
        table.data tfoot td { background: #f1f5f9; font-weight: bold; border-top: 1px solid #94a3b8; }

        table.summary { width: 320px; border-collapse: collapse; margin-bottom: 18px; }
        table.summary td { padding: 3px 0; font-size: 9.5px; }
        table.summary td.amount { text-align: right; font-weight: bold; }
        table.summary tr.hero td { border-top: 1px solid #94a3b8; padding-top: 5px; font-size: 11px; }

        .negative { color: #b91c1c; }
        .hint { font-size: 7.5px; color: #64748b; margin: -12px 0 18px; }
        .empty { padding: 16px 0; color: #64748b; }
    </style>
</head>
<body>
    <div class="head">
        <table>
            <tr>
                <td>
                    <div class="head__name">{{ $letterhead['name'] }}</div>
                    <div class="head__meta">{{ $letterhead['legal_name'] }} — {{ $letterhead['address'] }}</div>
                </td>
                <td>
                    <div class="head__title">{{ __('zeytin.pdf.title.'.$grouping) }}</div>
                    <div class="head__period">
                        {{ $report['from']->translatedFormat('d M Y') }} — {{ $report['to']->translatedFormat('d M Y') }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="foot">
        {{ __('zeytin.pdf.generated') }}: {{ now($letterhead['timezone'])->translatedFormat('d M Y H:i T') }} · {{ $letterhead['name'] }}
        <span class="foot__page">{{ __('zeytin.pdf.page') }} </span>
    </div>

    <h2>{{ __('zeytin.pdf.summary') }}</h2>

    <table class="summary">
        @foreach ($summary as [$label, $amount, $signed])
            <tr class="{{ $loop->last ? 'hero' : '' }}">
                <td>{{ $label }}</td>
                <td class="amount {{ $signed && $amount < 0 ? 'negative' : '' }}">{{ Money::format($amount) }}</td>
            </tr>
        @endforeach
    </table>

    <p class="hint">
        {{ __('zeytin.card.global_balance') }}: {{ __('zeytin.hint.global_balance') }}
        @foreach (Channels::all() as $channel)
            @unless ($channel['in_sales'])
                · {{ $channel['label'] }}: {{ __('zeytin.hint.petty_cash') }}
            @endunless
        @endforeach
    </p>

    <h2>{{ __('zeytin.pdf.breakdown') }}</h2>

    @if ($rows->isEmpty())
        <p class="empty">{{ __('report.no_data') }}</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('report.period') }}</th>
                    @foreach (Channels::all() as $channel)
                        <th>{{ $channel['label'] }}</th>
                    @endforeach
                    <th>{{ __('zeytin.col.total_sales') }}</th>
                    <th>{{ __('zeytin.col.cashless') }}</th>
                    <th>{{ __('zeytin.col.expense') }}</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ PeriodPdf::label($row, $grouping) }}</td>
                        @foreach (Channels::keys() as $key)
                            <td>{{ $num($row[$key]) }}</td>
                        @endforeach
                        <td>{{ $num($row['total_sales']) }}</td>
                        <td>{{ $num($row['cashless']) }}</td>
                        <td>{{ $num($row['expense']) }}</td>
                    </tr>
                @endforeach
            </tbody>

            <tfoot>
                <tr>
                    <td>{{ __('report.grand_total') }}</td>
                    @foreach (Channels::keys() as $key)
                        <td>{{ $num($report['by_channel'][$key]) }}</td>
                    @endforeach
                    <td>{{ $num($report['total_sales']) }}</td>
                    <td>{{ $num($report['cashless']) }}</td>
                    <td>{{ $num($report['cash_expense']) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif
</body>
</html>

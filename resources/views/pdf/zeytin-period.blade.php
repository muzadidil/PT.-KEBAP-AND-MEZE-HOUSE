{{--
    Laporan PDF satu periode; lihat App\Support\Zeytin\PeriodPdf.

    Ditulis untuk dompdf: tata letak tabel dan CSS 2.1 saja, tanpa flexbox
    atau grid. Hurufnya DejaVu Sans, yang ikut terpasang bersama dompdf,
    karena huruf bawaan PDF tidak punya tanda "—" dan "−".

    Kertasnya A4 tegak. Nomor halaman "x dari y" tidak bisa ditulis dari
    HTML, jadi digambar PeriodPdf setelah halamannya selesai disusun; ruang
    kosong di kanan kaki halaman memang disisakan untuknya.
--}}
@php
    use App\Support\Money;
    use App\Support\Zeytin\Channels;
    use App\Support\Zeytin\PeriodPdf;

    $num = fn (?int $amount) => ($amount < 0 ? '−' : '').number_format(abs((int) $amount), 0, ',', '.');

    // Kolom channel yang sepanjang periode nol semua tidak ikut tabel: di
    // kertas tegak tempatnya terbatas, dan kolom berisi strip saja hanya
    // membuat angka yang penting sulit dicari. Nilainya tetap tampil di
    // ringkasan per channel, dan namanya disebut di catatan.
    $columns = array_values(array_filter(Channels::all(), fn (array $c) => $report['by_channel'][$c['key']] !== 0));
    $hidden = array_values(array_filter(Channels::all(), fn (array $c) => $report['by_channel'][$c['key']] === 0));

    $share = fn (int $amount) => $report['total_sales'] > 0 && $amount !== 0
        ? number_format($amount / $report['total_sales'] * 100, 1, ',', '.').'%'
        : '–';

    $period = $report['from']->translatedFormat('j M Y').' – '.$report['to']->translatedFormat('j M Y');

    $kpis = [
        [__('zeytin.card.sales'), $report['total_sales'], __('zeytin.hint.recorded_days', [
            'recorded' => $report['recorded_days'],
            'days' => $report['days'],
        ])],
        [__('zeytin.card.total_expenses'), $report['total_expenses'], __('zeytin.pdf.expenses_hint')],
        [__('zeytin.card.profit'), $report['net_profit'], __('zeytin.pdf.profit_hint')],
        [__('zeytin.card.global_balance'), $report['global_balance'], __('zeytin.pdf.balance_hint')],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('zeytin.pdf.title.'.$grouping) }} — {{ $letterhead['name'] }}</title>
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

        .muted { color: #6b7280; }
        .negative { color: #b91c1c; }
        .num { text-align: right; white-space: nowrap; }
        .nil { color: #9ca3af; }

        /* ---------- kop dan kaki, diulang di tiap halaman ---------- */

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

        /* ---------- judul bagian ---------- */

        h2 {
            margin: 18pt 0 6pt;
            padding-bottom: 3pt;
            border-bottom: .5pt solid #d1d5db;
            font-size: 7pt;
            font-weight: bold;
            letter-spacing: .6pt;
            text-transform: uppercase;
            color: #4d7c0f;
        }
        h2.first { margin-top: 0; }

        /* ---------- angka utama ---------- */

        table.kpis { width: 100%; }
        .kpi {
            width: 23.5%;
            padding: 7pt 8pt 8pt;
            border: .5pt solid #d1d5db;
            border-top: 2.5pt solid #4d7c0f;
            background: #f8faf5;
        }
        .kpi__gap { width: 2%; }
        .kpi__label {
            font-size: 6pt;
            font-weight: bold;
            letter-spacing: .4pt;
            text-transform: uppercase;
            color: #6b7280;
        }
        .kpi__value { margin-top: 4pt; font-size: 11pt; font-weight: bold; white-space: nowrap; }
        .kpi__currency { font-size: 7pt; font-weight: normal; color: #6b7280; }
        .kpi__hint { margin-top: 3pt; font-size: 6pt; color: #6b7280; }

        /* ---------- ringkasan dua kolom ---------- */

        table.split { width: 100%; }
        .split__col { width: 48.5%; }
        .split__gap { width: 3%; }

        table.list { width: 100%; }
        table.list th {
            padding: 0 0 3pt;
            border-bottom: .75pt solid #111827;
            font-size: 6pt;
            font-weight: bold;
            letter-spacing: .4pt;
            text-transform: uppercase;
            color: #6b7280;
            text-align: left;
        }
        table.list th.num { text-align: right; }
        table.list td { padding: 3.5pt 0; border-bottom: .5pt solid #e5e7eb; }
        table.list td.share { width: 42pt; }
        table.list tr.subtotal td { border-top: .75pt solid #111827; border-bottom: none; font-weight: bold; }
        table.list tr.spacer td { padding: 4pt 0; border-bottom: none; }
        table.list tr.grand td {
            padding-top: 4pt;
            border-top: .75pt solid #111827;
            border-bottom: 2pt double #111827;
            font-weight: bold;
            font-size: 8.5pt;
        }
        .tag {
            margin-left: 3pt;
            padding: 0 2pt;
            border: .5pt solid #d1d5db;
            font-size: 5.5pt;
            color: #6b7280;
            text-transform: uppercase;
        }

        /* ---------- rincian ---------- */

        table.data { width: 100%; }
        table.data th {
            padding: 0 4pt 4pt;
            border-bottom: 1pt solid #4d7c0f;
            font-size: 6pt;
            font-weight: bold;
            letter-spacing: .3pt;
            text-transform: uppercase;
            color: #374151;
            text-align: right;
            vertical-align: bottom;
        }
        table.data td {
            padding: 3.5pt 4pt;
            border-bottom: .5pt solid #e5e7eb;
            text-align: right;
            white-space: nowrap;
        }
        table.data th.first,
        table.data td.first { padding-left: 0; text-align: left; }
        table.data th.last,
        table.data td.last { padding-right: 0; }
        table.data td.key { font-weight: bold; }
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

        /* ---------- catatan ---------- */

        .notes { margin-top: 16pt; font-size: 6.5pt; color: #6b7280; }
        .notes__title { font-weight: bold; color: #374151; margin-bottom: 2pt; }
        .notes ol { margin: 0; padding-left: 10pt; }
        .notes li { margin-bottom: 1.5pt; }
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
                    <div class="head__title">{{ __('zeytin.pdf.title.'.$grouping) }}</div>
                    <div class="head__period">{{ $period }}</div>
                    <div class="head__currency">{{ __('zeytin.pdf.currency') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="foot">
        {{ __('zeytin.pdf.generated') }} {{ now($letterhead['timezone'])->translatedFormat('j M Y, H:i T') }}
        · {{ $letterhead['name'] }} — {{ __('zeytin.pdf.title.'.$grouping) }}, {{ $period }}
    </div>

    <h2 class="first">{{ __('zeytin.pdf.summary') }}</h2>

    <table class="kpis">
        <tr>
            @foreach ($kpis as [$label, $amount, $hint])
                @unless ($loop->first)
                    <td class="kpi__gap"></td>
                @endunless
                <td class="kpi">
                    <div class="kpi__label">{{ $label }}</div>
                    <div class="kpi__value {{ $amount < 0 ? 'negative' : '' }}">
                        <span class="kpi__currency">Rp</span> {{ $num($amount) }}
                    </div>
                    <div class="kpi__hint">{{ $hint }}</div>
                </td>
            @endforeach
        </tr>
    </table>

    <table class="split" style="margin-top: 16pt;">
        <tr>
            <td class="split__col">
                <table class="list">
                    <thead>
                        <tr>
                            <th>{{ __('zeytin.pdf.sales_by_channel') }}</th>
                            <th class="num">{{ __('zeytin.pdf.share') }}</th>
                            <th class="num">{{ __('report.total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (Channels::all() as $channel)
                            @php($amount = $report['by_channel'][$channel['key']])
                            <tr>
                                <td>
                                    {{ $channel['label'] }}
                                    @unless ($channel['in_sales'])
                                        <span class="tag">{{ __('zeytin.ledger.excluded') }}</span>
                                    @endunless
                                </td>
                                <td class="num share muted">{{ $channel['in_sales'] ? $share($amount) : '–' }}</td>
                                <td class="num {{ $amount === 0 ? 'nil' : '' }}">{{ $amount === 0 ? '–' : $num($amount) }}</td>
                            </tr>
                        @endforeach
                        <tr class="grand">
                            <td>{{ __('zeytin.card.sales') }}</td>
                            <td class="num share">{{ $report['total_sales'] > 0 ? '100%' : '–' }}</td>
                            <td class="num">{{ Money::format($report['total_sales']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
            <td class="split__gap"></td>
            <td class="split__col">
                <table class="list">
                    <thead>
                        <tr>
                            <th>{{ __('zeytin.pdf.expenses_and_result') }}</th>
                            <th class="num">{{ __('report.total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ __('zeytin.card.cash_expense') }}</td>
                            <td class="num">{{ $num($report['cash_expense']) }}</td>
                        </tr>
                        <tr>
                            <td>{{ __('zeytin.card.transfers') }}</td>
                            <td class="num">{{ $num($report['transfers']) }}</td>
                        </tr>
                        <tr>
                            <td>{{ __('zeytin.card.payroll') }}</td>
                            <td class="num">{{ $num($report['payroll']) }}</td>
                        </tr>
                        <tr class="subtotal">
                            <td>{{ __('zeytin.card.total_expenses') }}</td>
                            <td class="num">{{ $num($report['total_expenses']) }}</td>
                        </tr>
                        <tr class="spacer"><td colspan="2"></td></tr>
                        <tr>
                            <td>{{ __('zeytin.card.sales') }}</td>
                            <td class="num">{{ $num($report['total_sales']) }}</td>
                        </tr>
                        <tr>
                            <td>{{ __('zeytin.card.total_expenses') }}</td>
                            <td class="num">{{ $num(-$report['total_expenses']) }}</td>
                        </tr>
                        <tr class="subtotal">
                            <td>{{ __('zeytin.card.profit') }}</td>
                            <td class="num {{ $report['net_profit'] < 0 ? 'negative' : '' }}">{{ $num($report['net_profit']) }}</td>
                        </tr>
                        <tr>
                            <td>{{ __('zeytin.card.outstanding') }}</td>
                            <td class="num">{{ $num(-$report['outstanding']) }}</td>
                        </tr>
                        <tr class="grand">
                            <td>{{ __('zeytin.card.global_balance') }}</td>
                            <td class="num {{ $report['global_balance'] < 0 ? 'negative' : '' }}">{{ Money::format($report['global_balance']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <h2>{{ __('zeytin.pdf.breakdown') }}</h2>

    @if ($rows->isEmpty())
        <div class="empty">{{ __('report.no_data') }}</div>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th class="first">{{ __('report.period') }}</th>
                    @foreach ($columns as $channel)
                        <th>{{ $channel['label'] }}</th>
                    @endforeach
                    <th>{{ __('zeytin.col.total_sales') }}</th>
                    <th>{{ __('zeytin.col.cashless') }}</th>
                    <th class="last">{{ __('zeytin.col.expense') }}</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="first">{{ PeriodPdf::label($row, $grouping) }}</td>
                        @foreach ($columns as $channel)
                            <td class="{{ $row[$channel['key']] === 0 ? 'nil' : '' }}">
                                {{ $row[$channel['key']] === 0 ? '–' : $num($row[$channel['key']]) }}
                            </td>
                        @endforeach
                        <td class="key">{{ $num($row['total_sales']) }}</td>
                        <td class="{{ $row['cashless'] === 0 ? 'nil' : '' }}">{{ $row['cashless'] === 0 ? '–' : $num($row['cashless']) }}</td>
                        <td class="last {{ $row['expense'] === 0 ? 'nil' : '' }}">{{ $row['expense'] === 0 ? '–' : $num($row['expense']) }}</td>
                    </tr>
                @endforeach
            </tbody>

            <tfoot>
                <tr>
                    <td class="first">{{ __('report.grand_total') }}</td>
                    @foreach ($columns as $channel)
                        <td>{{ $num($report['by_channel'][$channel['key']]) }}</td>
                    @endforeach
                    <td>{{ $num($report['total_sales']) }}</td>
                    <td>{{ $num($report['cashless']) }}</td>
                    <td class="last">{{ $num($report['cash_expense']) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <div class="notes">
        <div class="notes__title">{{ __('zeytin.pdf.notes') }}</div>
        <ol>
            <li>{{ __('zeytin.card.global_balance') }}: {{ __('zeytin.hint.global_balance') }} {{ __('zeytin.hint.outstanding') }}</li>
            @foreach (Channels::all() as $channel)
                @unless ($channel['in_sales'])
                    <li>{{ $channel['label'] }}: {{ __('zeytin.hint.petty_cash') }}</li>
                @endunless
            @endforeach
            <li>{{ __('zeytin.pdf.note_purchases') }}</li>
            @if ($grouping === 'daily' && $rows->isNotEmpty())
                <li>{{ __('zeytin.pdf.note_skipped_days') }}</li>
            @endif
            @if ($hidden !== [] && $rows->isNotEmpty())
                <li>{{ __('zeytin.pdf.note_hidden_channels', ['channels' => implode(', ', array_column($hidden, 'label'))]) }}</li>
            @endif
        </ol>
    </div>
</body>
</html>

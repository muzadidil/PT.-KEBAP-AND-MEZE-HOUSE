{{--
    Kertas slip gaji, dipakai pratinjau di formulir dan PDF sekaligus;
    lihat App\Support\Payroll\PayslipPdf::paper().

    Ditulis dengan tabel dan CSS 2.1 supaya dompdf merendernya sama dengan
    peramban. Latarnya selalu putih, juga di tema gelap: ini gambar kertas.
    Teksnya selalu bahasa Indonesia — slip diserahkan ke karyawan.
--}}
@php
    $t = fn (string $key, array $replace = []) => __('payroll.slip.'.$key, $replace, 'id');
    $num = fn (?int $amount) => number_format((int) $amount, 0, ',', '.');
    $rows = max(count($paper['earnings']), count($paper['deductions']), 1);
    $lh = $paper['letterhead'];
@endphp

<style>
    .slip { background: #fff; color: #111; font-family: Times, "Times New Roman", serif; font-size: 12px; line-height: 1.35; }
    .slip table { border-collapse: collapse; }
    .slip-kop { width: 100%; }
    .slip-kop td { vertical-align: middle; padding: 0; }
    .slip-kop__side { width: 90px; }
    .slip-kop__side img { max-width: 80px; max-height: 56px; }
    .slip-kop__text { text-align: center; }
    .slip-kop__name { font-size: 18px; font-weight: bold; letter-spacing: .5px; text-transform: uppercase; }
    .slip-kop__address { font-size: 11px; color: #333; margin-top: 2px; }
    .slip-rule { border-top: 2.5px solid #111; margin-top: 8px; }
    .slip-rule2 { border-top: 1px solid #111; margin-top: 2px; margin-bottom: 14px; }
    .slip-title { text-align: center; font-weight: bold; font-size: 14px; letter-spacing: 1px; text-decoration: underline; }
    .slip-period { text-align: center; font-size: 12px; margin-top: 3px; }
    .slip-number { text-align: center; font-size: 11px; color: #444; margin-bottom: 14px; }
    .slip-info { font-size: 12.5px; margin-bottom: 12px; }
    .slip-info td { padding: 1.5px 0; vertical-align: top; }
    .slip-rinci { width: 100%; font-size: 12px; }
    .slip-rinci th { border: 1px solid #111; padding: 5px 8px; font-size: 11.5px; letter-spacing: .4px; background: #ebebe6; }
    .slip-rinci td { border: 1px solid #111; padding: 4.5px 8px; vertical-align: top; }
    .slip-rinci .num { text-align: right; white-space: nowrap; }
    .slip-rinci .subtotal td { font-weight: bold; background: #f2f2ee; }
    .slip-thp { width: 100%; margin-top: 12px; border: 1.5px solid #111; background: #f2f2ee; font-weight: bold; font-size: 13.5px; }
    .slip-thp td { padding: 8px 12px; }
    .slip-spelled { font-style: italic; font-size: 11.5px; margin-top: 6px; color: #222; }
    .slip-sign { width: 100%; margin-top: 26px; font-size: 12px; }
    .slip-sign td { width: 50%; text-align: center; vertical-align: top; padding: 0; }
    .slip-sign__name { display: inline-block; min-width: 55%; margin-top: 56px; border-top: 1px solid #111; padding-top: 3px; font-weight: bold; }
</style>

<div class="slip">
    <table class="slip-kop">
        <tr>
            <td class="slip-kop__side">
                @if ($paper['logo'])
                    <img src="{{ $paper['logo'] }}" alt="">
                @endif
            </td>
            <td class="slip-kop__text">
                <div class="slip-kop__name">{{ $lh['company'] ?: $t('company') }}</div>
                <div class="slip-kop__address">{{ $lh['address'] }}</div>
            </td>
            <td class="slip-kop__side"></td>
        </tr>
    </table>
    <div class="slip-rule"></div>
    <div class="slip-rule2"></div>

    <div class="slip-title">{{ $t('title') }}</div>
    <div class="slip-period">{{ $t('period', ['period' => $paper['period']]) }}</div>
    <div class="slip-number">{{ $t('number', ['number' => $paper['number']]) }}</div>

    <table class="slip-info">
        <tr><td style="width: 90px">{{ $t('name') }}</td><td style="width: 12px">:</td><td><b>{{ $paper['name'] ?: '—' }}</b></td></tr>
        <tr><td>{{ $t('nik') }}</td><td>:</td><td>{{ $paper['nik'] ?: '—' }}</td></tr>
        <tr><td>{{ $t('position') }}</td><td>:</td><td>{{ $paper['position'] ?: '—' }}</td></tr>
    </table>

    <table class="slip-rinci">
        <tr>
            <th colspan="2" style="width: 50%">{{ $t('earnings') }}</th>
            <th colspan="2" style="width: 50%">{{ $t('deductions') }}</th>
        </tr>

        @for ($i = 0; $i < $rows; $i++)
            @php
                $earning = $paper['earnings'][$i] ?? null;
                $deduction = $paper['deductions'][$i] ?? null;
            @endphp
            <tr>
                <td>{{ $earning['label'] ?? '' }}</td>
                <td class="num">{{ $earning ? $num($earning['amount']) : '' }}</td>
                <td>{{ $deduction['label'] ?? '' }}</td>
                <td class="num">{{ $deduction ? $num($deduction['amount']) : '' }}</td>
            </tr>
        @endfor

        <tr class="subtotal">
            <td>{{ $t('total_earnings') }}</td>
            <td class="num">{{ $num($paper['total_earnings']) }}</td>
            <td>{{ $t('total_deductions') }}</td>
            <td class="num">{{ $num($paper['total_deductions']) }}</td>
        </tr>
    </table>

    <table class="slip-thp">
        <tr>
            <td>{{ $t('net_pay') }}</td>
            <td style="text-align: right; white-space: nowrap">{{ \App\Support\Money::format($paper['net_pay']) }}</td>
        </tr>
    </table>

    <div class="slip-spelled">{{ $t('spelled', ['words' => $paper['spelled']]) }}</div>

    <table class="slip-sign">
        <tr>
            <td>&nbsp;</td>
            <td>{{ $lh['city'] ? $lh['city'].', ' : '' }}{{ $paper['issued'] }}</td>
        </tr>
        <tr>
            <td>{{ $t('recipient') }}</td>
            <td>{{ $lh['signer_title'] ?: $t('signer') }},</td>
        </tr>
        <tr>
            <td><span class="slip-sign__name">{{ $paper['name'] ?: $t('blank') }}</span></td>
            <td><span class="slip-sign__name">{{ $lh['signer_name'] ?: $t('blank') }}</span></td>
        </tr>
    </table>
</div>

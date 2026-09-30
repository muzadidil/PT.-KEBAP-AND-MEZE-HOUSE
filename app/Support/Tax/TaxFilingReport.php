<?php

namespace App\Support\Tax;

use App\Models\Setting;
use App\Models\TaxFiling;
use App\Support\Zeytin\DailyLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Angka Laporan Pajak: PPh Final UMKM dan PPN/PBJT, per bulan dan per tahun.
 *
 * Omzet ditarik dari DailyLedger (sumber yang sama dengan Buku Besar).
 * Kalau untuk bulan itu ada koreksi di tax_filings, koreksi yang dipakai.
 * Data asli tidak pernah diubah dari sini.
 */
class TaxFilingReport
{
    public const DEFAULT_FINAL_RATE = 0.5;

    public const DEFAULT_PPN_RATE = 11.0;

    /** @return array{final_rate: float, ppn_rate: float, ppn_inclusive: bool} */
    public static function defaults(): array
    {
        return [
            'final_rate' => (float) Setting::get('tax.final_rate', static::DEFAULT_FINAL_RATE),
            'ppn_rate' => (float) Setting::get('tax.ppn_rate', static::DEFAULT_PPN_RATE),
            'ppn_inclusive' => (bool) Setting::get('tax.ppn_inclusive', false),
        ];
    }

    /** PPN keluaran dari dasar penjualan; harga sudah termasuk pajak → dipisah dulu. */
    public static function outputTax(int $revenue, float $rate, bool $inclusive): int
    {
        if ($revenue <= 0 || $rate <= 0) {
            return 0;
        }

        return (int) round($inclusive ? $revenue * $rate / (100 + $rate) : $revenue * $rate / 100);
    }

    /**
     * Dua belas baris bulan untuk satu tahun, plus total tahunan.
     *
     * @return array{year: int, rows: Collection<int, array<string, mixed>>, totals: array<string, int>}
     */
    public static function year(int $year): array
    {
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $sales = DailyLedger::groupByMonth(
            DailyLedger::periodReport($start, $start->copy()->endOfYear()->startOfDay())['rows']
        )->keyBy(fn (array $row) => (int) $row['start']->format('n'));

        $filings = TaxFiling::query()->where('year', $year)->get()->keyBy('month');
        $defaults = static::defaults();

        $rows = collect(range(1, 12))->map(function (int $month) use ($year, $sales, $filings, $defaults) {
            $filing = $filings->get($month);
            $system = (int) ($sales->get($month)['total_sales'] ?? 0);
            $revenue = $filing?->revenue_override ?? $system;

            $finalRate = $filing?->final_rate ?? $defaults['final_rate'];
            $ppnRate = $filing?->ppn_rate ?? $defaults['ppn_rate'];
            $inclusive = $filing?->ppn_inclusive ?? $defaults['ppn_inclusive'];

            $finalDue = (int) round($revenue * $finalRate / 100);
            $ppnOut = static::outputTax($revenue, $ppnRate, $inclusive);
            $ppnInput = (int) ($filing?->ppn_input ?? 0);
            $ppnDue = $ppnOut - $ppnInput;

            $paidFinal = (int) ($filing?->paid_final ?? 0);
            $paidPpn = (int) ($filing?->paid_ppn ?? 0);

            return [
                'month' => $month,
                'label' => Carbon::create($year, $month, 1)->translatedFormat('F Y'),
                'system_revenue' => $system,
                'revenue' => $revenue,
                'adjusted' => $filing?->revenue_override !== null,
                'final_rate' => $finalRate,
                'final_due' => $finalDue,
                'paid_final' => $paidFinal,
                'ppn_rate' => $ppnRate,
                'ppn_inclusive' => $inclusive,
                'ppn_output' => $ppnOut,
                'ppn_input' => $ppnInput,
                'ppn_due' => $ppnDue,
                'paid_ppn' => $paidPpn,
                'outstanding' => ($finalDue - $paidFinal) + ($ppnDue - $paidPpn),
                'is_reported' => (bool) ($filing?->is_reported ?? false),
                'note' => $filing?->note,
            ];
        });

        $totals = [];

        foreach (['system_revenue', 'revenue', 'final_due', 'paid_final', 'ppn_output', 'ppn_input', 'ppn_due', 'paid_ppn', 'outstanding'] as $field) {
            $totals[$field] = (int) $rows->sum($field);
        }

        return ['year' => $year, 'rows' => $rows, 'totals' => $totals];
    }

    /**
     * Rincian satu bulan: ringkasan pajaknya plus penjualan per hari.
     *
     * Ringkasannya baris bulan yang sama dengan tabel tahunan, jadi angka di
     * layar bulanan tidak mungkin berbeda dari tabel tahunan.
     *
     * @return array{year: int, month: int, label: string, summary: array<string, mixed>, days: Collection<int, array<string, mixed>>, sales: int}
     */
    public static function month(int $year, int $month): array
    {
        $month = max(1, min(12, $month));
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $days = DailyLedger::daily($start, $start->copy()->endOfMonth()->startOfDay());

        return [
            'year' => $year,
            'month' => $month,
            'label' => $start->translatedFormat('F Y'),
            'summary' => static::year($year)['rows']->firstWhere('month', $month),
            'days' => $days,
            'sales' => (int) $days->sum('total_sales'),
        ];
    }
}

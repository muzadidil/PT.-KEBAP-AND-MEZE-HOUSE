<?php

namespace App\Filament\Admin\Widgets;

use App\Support\Ledger;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Empat angka yang paling sering ditanyakan pemilik: pemasukan hari ini,
 * pemasukan bulan berjalan, pengeluaran bulan berjalan, dan selisihnya.
 *
 * Semuanya dari App\Support\Ledger, sumber yang sama dengan seluruh laporan,
 * jadi angka di dasbor tidak mungkin berbeda dari angka di halaman laporan.
 */
class BusinessOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -10;

    protected function getStats(): array
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();

        $todaySales = Ledger::salesSummary($today, $today);
        $month = Ledger::profit($monthStart, $today);

        return [
            Stat::make(__('report.preset.last_7'), Money::format(
                Ledger::salesSummary($today->copy()->subDays(6), $today)['total']
            ))->description(__('report.summary.sales'))->color('gray'),

            Stat::make($today->translatedFormat('d M Y'), Money::format($todaySales['total']))
                ->description(sprintf(
                    '%s · %s · %s',
                    Money::format($todaySales['cash']),
                    Money::format($todaySales['cashless']),
                    Money::format($todaySales['grab']),
                ))
                ->color('success'),

            Stat::make(__('report.summary.expenses'), Money::format($month['expenses']))
                ->description($monthStart->translatedFormat('F Y'))
                ->color('warning'),

            Stat::make(__('report.summary.profit'), Money::format($month['profit']))
                ->description($monthStart->translatedFormat('F Y'))
                ->color($month['profit'] < 0 ? 'danger' : 'success'),
        ];
    }
}

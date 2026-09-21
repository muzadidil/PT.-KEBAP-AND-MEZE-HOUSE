<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Pages\Zeytin\MonthlyLedger;
use App\Support\Money;
use App\Support\Zeytin\DailyLedger;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Dasbor aplikasi Zeytin, dipindahkan ke sini: satu angka yang ditonjolkan,
 * yaitu saldo global, dan sisanya pendukung. Semuanya untuk bulan berjalan.
 *
 * Angkanya dari DailyLedger::periodReport(), sumber yang sama dengan Buku
 * Besar Bulanan beserta unduhan Excel dan PDF-nya. Mengeklik salah satu
 * kartu membuka buku besar bulan itu, tempat tiap angka bisa ditelusuri.
 */
class ZeytinOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -20;

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    protected function getHeading(): ?string
    {
        return __('zeytin.nav.group').' · '.Carbon::today()->translatedFormat('F Y');
    }

    protected function getStats(): array
    {
        $today = Carbon::today();
        $report = DailyLedger::periodReport($today->copy()->startOfMonth(), $today);
        $ledger = MonthlyLedger::getUrl();

        $stat = fn (string $label, int $value) => Stat::make(__('zeytin.card.'.$label), Money::format($value))
            ->url($ledger);

        return [
            $stat('global_balance', $report['global_balance'])
                ->description(__('zeytin.hint.global_balance'))
                ->color($report['global_balance'] < 0 ? 'danger' : 'success'),

            $stat('sales', $report['total_sales'])
                ->description(__('zeytin.hint.recorded_days', [
                    'recorded' => $report['recorded_days'],
                    'days' => $report['days'],
                ])),

            $stat('total_expenses', $report['total_expenses'])
                ->description(Money::format($report['cash_expense']).' · '
                    .Money::format($report['transfers']).' · '
                    .Money::format($report['payroll']))
                ->color('warning'),

            $stat('profit', $report['net_profit'])
                ->color($report['net_profit'] < 0 ? 'danger' : 'success'),

            $stat('remaining_supplier_cash', $report['remaining_supplier_cash'])
                ->description(__('zeytin.hint.remaining_supplier_cash'))
                ->color($report['remaining_supplier_cash'] < 0 ? 'danger' : 'gray'),

            $stat('outstanding', $report['outstanding'])
                ->description(__('zeytin.hint.outstanding'))
                ->color($report['outstanding'] > 0 ? 'warning' : 'gray'),
        ];
    }
}

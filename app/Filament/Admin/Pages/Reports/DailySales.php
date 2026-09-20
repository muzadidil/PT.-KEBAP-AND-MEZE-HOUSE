<?php

namespace App\Filament\Admin\Pages\Reports;

use App\Support\Ledger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Penjualan per hari, dipecah Cash / Cashless / Grab.
 *
 * Hari tanpa penjualan tetap muncul sebagai nol, bukan hilang dari daftar:
 * hari yang lupa dicatat dan hari yang memang tutup harus sama-sama terlihat.
 */
class DailySales extends SalesReport
{
    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('nav.daily_sales');
    }

    public function getTitle(): string
    {
        return __('nav.daily_sales');
    }

    protected static function defaultFrom(): Carbon
    {
        return Carbon::today()->subDays(13);
    }

    protected function buildRows(Carbon $from, Carbon $to): Collection
    {
        return Ledger::daily($from, $to);
    }

    public function periodLabel(array $row): string
    {
        return $row['date']->translatedFormat('D, d M Y');
    }
}

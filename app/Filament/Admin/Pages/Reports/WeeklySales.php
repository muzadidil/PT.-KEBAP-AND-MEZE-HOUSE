<?php

namespace App\Filament\Admin\Pages\Reports;

use App\Support\Zeytin\DailyLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class WeeklySales extends SalesReport
{
    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('nav.weekly_sales');
    }

    public function getTitle(): string
    {
        return __('nav.weekly_sales');
    }

    protected static function defaultFrom(): Carbon
    {
        return Carbon::today()->subWeeks(11)->startOfWeek();
    }

    protected function group(Collection $days): Collection
    {
        return DailyLedger::groupByWeek($days);
    }

    public function periodLabel(array $row): string
    {
        // Nomor minggu saja sulit dibayangkan; tanggal awal dan akhirnya
        // yang membuat barisnya bisa dicocokkan dengan ingatan.
        return $row['key'].' · '
            .$row['start']->translatedFormat('d M').' – '
            .$row['end']->translatedFormat('d M Y');
    }
}

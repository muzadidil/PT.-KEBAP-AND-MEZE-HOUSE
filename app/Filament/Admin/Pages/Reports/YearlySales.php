<?php

namespace App\Filament\Admin\Pages\Reports;

use App\Support\Zeytin\DailyLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class YearlySales extends SalesReport
{
    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return __('nav.yearly_sales');
    }

    public function getTitle(): string
    {
        return __('nav.yearly_sales');
    }

    protected static function defaultFrom(): Carbon
    {
        return Carbon::today()->subYears(4)->startOfYear();
    }

    protected function group(Collection $days): Collection
    {
        return DailyLedger::groupByYear($days);
    }

    public function periodLabel(array $row): string
    {
        return $row['key'];
    }
}

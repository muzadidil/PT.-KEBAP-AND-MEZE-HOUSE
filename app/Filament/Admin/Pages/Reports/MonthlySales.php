<?php

namespace App\Filament\Admin\Pages\Reports;

use App\Support\Ledger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MonthlySales extends SalesReport
{
    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('nav.monthly_sales');
    }

    public function getTitle(): string
    {
        return __('nav.monthly_sales');
    }

    protected static function defaultFrom(): Carbon
    {
        return Carbon::today()->startOfYear();
    }

    protected function buildRows(Carbon $from, Carbon $to): Collection
    {
        return Ledger::monthly($from, $to);
    }

    public function periodLabel(array $row): string
    {
        return $row['start']->translatedFormat('F Y');
    }
}

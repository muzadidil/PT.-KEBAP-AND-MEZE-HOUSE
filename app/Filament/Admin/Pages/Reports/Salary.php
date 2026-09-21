<?php

namespace App\Filament\Admin\Pages\Reports;

use App\Filament\Admin\Concerns\ForAdmin;
use App\Filament\Admin\Pages\Reports\Concerns\HasPeriod;
use App\Support\Money;
use App\Support\Zeytin\DailyLedger;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use UnitEnum;

/**
 * Gaji per bulan — hanya totalnya.
 *
 * Membaca Gaji di Penggajian, baris yang sama yang dijumlahkan Buku Besar.
 * Gaji per orang tidak ditampilkan: itu hanya untuk Super Admin, sedangkan
 * halaman ini milik Admin. Yang terlihat di sini jumlah orang dan total gaji
 * tiap bulan, cukup untuk mencocokkan kartu Gaji di Buku Besar.
 */
class Salary extends Page
{
    use ForAdmin;
    use HasPeriod;

    protected static ?int $navigationSort = 70;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected string $view = 'filament.admin.pages.reports.salary';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.group.reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.salary');
    }

    public function getTitle(): string
    {
        return __('nav.salary');
    }

    protected static function defaultFrom(): Carbon
    {
        return Carbon::today()->startOfYear();
    }

    /** @return Collection<int, array{month: Carbon, people: int, total: int}> */
    #[Computed]
    public function rows(): Collection
    {
        return DailyLedger::payroll($this->fromDate(), $this->toDate())
            ->selectRaw('month, COUNT(*) as people, SUM(grand_total) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => Carbon::parse($row->month),
                'people' => (int) $row->people,
                'total' => (int) $row->total,
            ]);
    }

    protected function forget(): void
    {
        unset($this->rows);
    }

    public function money(?int $amount): string
    {
        return Money::format($amount);
    }
}

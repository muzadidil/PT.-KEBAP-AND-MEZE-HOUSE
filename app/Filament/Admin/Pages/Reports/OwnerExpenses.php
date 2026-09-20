<?php

namespace App\Filament\Admin\Pages\Reports;

use App\Models\Expense;
use App\Models\Owner;
use App\Support\Ledger;
use App\Support\Money;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Pengeluaran bulanan yang ditalangi pemilik, beserta pembagiannya.
 *
 * Tiap pemilik menanggung porsi yang disepakati (Aslan 60%, Leo 40%), tapi
 * yang benar-benar merogoh kantong bisa siapa saja. Halaman ini menghitung
 * selisih antara yang dibayar dan yang seharusnya ditanggung, sehingga
 * terbaca siapa yang berhak menerima dan siapa yang masih harus menyetor.
 *
 * Porsinya dibaca dari data Owners, bukan ditulis mati di kode, dan sisa
 * pembagian rupiah dibagikan lewat Money::split supaya jumlah tanggungan
 * selalu persis sama dengan total yang ditalangi.
 */
class OwnerExpenses extends Page
{
    protected static ?int $navigationSort = 90;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected string $view = 'filament.admin.pages.reports.owner-expenses';

    #[Url]
    public string $month = '';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.group.reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.owner_expenses');
    }

    public function getTitle(): string
    {
        return __('report.owner_split.title');
    }

    public function mount(): void
    {
        $this->month = $this->month ?: Carbon::today()->format('Y-m');
    }

    public function fromDate(): Carbon
    {
        return Carbon::createFromFormat('Y-m', $this->month ?: Carbon::today()->format('Y-m'))
            ->startOfMonth();
    }

    public function toDate(): Carbon
    {
        return $this->fromDate()->copy()->endOfMonth();
    }

    public function updatedMonth(): void
    {
        unset($this->settlement, $this->expenses);
    }

    /** @return array{total: int, rows: array<int, array<string, mixed>>} */
    #[Computed]
    public function settlement(): array
    {
        return Ledger::ownerSettlement($this->fromDate(), $this->toDate());
    }

    /** @return Collection<int, Expense> */
    #[Computed]
    public function expenses(): Collection
    {
        return Expense::query()
            ->with('paidByOwner', 'supplier')
            ->ownerPaid()
            ->between($this->fromDate(), $this->toDate())
            ->orderBy('spent_on')
            ->get();
    }

    /**
     * Jumlah seluruh porsi. Kalau bukan 100%, hasil pembagiannya tidak bisa
     * dipercaya, dan halaman memperingatkan alih-alih diam.
     */
    #[Computed]
    public function sharePercentTotal(): int
    {
        return (int) Owner::where('active', true)->sum('share_percent');
    }

    public function money(?int $amount): string
    {
        return Money::format($amount);
    }
}

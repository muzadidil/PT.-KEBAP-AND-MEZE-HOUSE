<?php

namespace App\Filament\Admin\Pages\Reports\Concerns;

use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

/**
 * Rentang tanggal laporan: isian Dari–Sampai beserta tombol presetnya.
 *
 * Satu tempat untuk seluruh laporan yang berbentuk rentang, supaya "Tahun
 * lalu" atau rentang terbalik tidak bisa berarti hal berbeda di dua halaman.
 * Tampilannya komponen <x-report-period>.
 */
trait HasPeriod
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    /** Hasil hitungan yang harus dibuang begitu rentangnya berubah. */
    abstract protected function forget(): void;

    public function mount(): void
    {
        $this->from = $this->from ?: static::defaultFrom()->toDateString();
        $this->to = $this->to ?: static::defaultTo()->toDateString();
    }

    protected static function defaultFrom(): Carbon
    {
        return Carbon::today()->startOfMonth();
    }

    protected static function defaultTo(): Carbon
    {
        return Carbon::today();
    }

    public function fromDate(): Carbon
    {
        return Carbon::parse($this->from ?: static::defaultFrom())->startOfDay();
    }

    public function toDate(): Carbon
    {
        $to = Carbon::parse($this->to ?: static::defaultTo())->startOfDay();

        // Rentang terbalik tidak pernah berguna, dan kalau dibiarkan akan
        // memberi tabel kosong tanpa penjelasan. Diperlakukan sebagai satu
        // hari saja.
        return $to->lt($this->fromDate()) ? $this->fromDate() : $to;
    }

    public function applyPreset(string $preset): void
    {
        $today = Carbon::today();

        [$from, $to] = match ($preset) {
            'today' => [$today->copy(), $today->copy()],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()],
            'last_month' => [
                $today->copy()->subMonthNoOverflow()->startOfMonth(),
                $today->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'this_year' => [$today->copy()->startOfYear(), $today->copy()],
            'last_year' => [
                $today->copy()->subYearNoOverflow()->startOfYear(),
                $today->copy()->subYearNoOverflow()->endOfYear(),
            ],
            'last_7' => [$today->copy()->subDays(6), $today->copy()],
            'last_30' => [$today->copy()->subDays(29), $today->copy()],
            default => [$this->fromDate(), $this->toDate()],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();

        $this->forget();
    }

    public function updated(): void
    {
        $this->forget();
    }
}

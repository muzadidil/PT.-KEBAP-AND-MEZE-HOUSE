<?php

namespace App\Support\Zeytin;

use App\Models\DailyIncome;
use App\Models\Purchase;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sisa uang cash di kasir.
 *
 *   saldo = saldo awal + penjualan cash − belanja tunai
 *
 * Penjualan cash dan belanja tunai dibaca dari sumber yang sama dengan Buku
 * Besar Bulanan (DailyLedger). Petty cash tidak ikut, sama seperti di
 * Total Sales: rumus aslinya memang melewati kolom itu. Transfer pemasok
 * lewat bank dan gaji tidak diasumsikan tunai, jadi keduanya tidak ikut.
 *
 * Saldo awal dan tanggalnya diatur di halaman; hitungan dimulai pada
 * tanggal itu. Tanpa tanggal, semua catatan dihitung dengan saldo awal 0.
 */
class CashBalance
{
    /** @return array{amount: int, date: ?string} */
    public static function opening(): array
    {
        return [
            'amount' => (int) Setting::get('cash.opening_amount', 0),
            'date' => Setting::get('cash.opening_date') ?: null,
        ];
    }

    public static function setOpening(int $amount, ?string $date): void
    {
        Setting::put('cash.opening_amount', max(0, $amount));
        Setting::put('cash.opening_date', $date ?: null);
    }

    /** Penjualan cash satu baris pemasukan harian: jumlah seluruh channel tunai. */
    protected static function cashIn(array $row): int
    {
        return array_sum(array_map(fn (string $key) => (int) ($row[$key] ?? 0), Channels::cash()));
    }

    /**
     * Saldo harian sepanjang rentang.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, start_balance: int, end_balance: int, cash_in: int, cash_out: int, opening: array{amount: int, date: ?string}}
     */
    public static function report(Carbon $from, Carbon $to): array
    {
        $opening = static::opening();
        $start = $opening['date'] ? Carbon::parse($opening['date'])->startOfDay() : Carbon::create(2000, 1, 1)->startOfDay();
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        // Saldo di awal rentang: saldo awal ditambah semua yang terjadi sejak
        // tanggal mulai sampai sehari sebelum rentang.
        $running = 0;

        if ($start->lt($from)) {
            $incomes = DailyIncome::query()
                ->whereDate('date', '>=', $start->toDateString())
                ->whereDate('date', '<', $from->toDateString())
                ->get();
            $in = $incomes->sum(fn (DailyIncome $row) => static::cashIn($row->channelAmounts()));
            $out = (int) Purchase::query()
                ->whereDate('date', '>=', $start->toDateString())
                ->whereDate('date', '<', $from->toDateString())
                ->sum('total');

            $running = $opening['amount'] + $in - $out;
        }

        $startBalance = $running;
        $totalIn = 0;
        $totalOut = 0;

        $rows = DailyLedger::daily($from, $to)->map(function (array $day) use (&$running, &$totalIn, &$totalOut, $start, $opening) {
            $counted = ! $day['date']->lt($start);
            $in = $counted ? static::cashIn($day) : 0;
            $out = $counted ? (int) $day['expense'] : 0;
            $before = $running;

            if ($counted && $day['date']->isSameDay($start)) {
                $running += $opening['amount'];
                $before = $running;
            }

            $running += $in - $out;
            $totalIn += $in;
            $totalOut += $out;

            return [
                'date' => $day['date'],
                'counted' => $counted,
                'opening' => $before,
                'cash_in' => $in,
                'cash_out' => $out,
                'balance' => $running,
            ];
        })->values();

        return [
            'rows' => $rows,
            'start_balance' => $startBalance,
            'end_balance' => $running,
            'cash_in' => $totalIn,
            'cash_out' => $totalOut,
            'opening' => $opening,
        ];
    }
}

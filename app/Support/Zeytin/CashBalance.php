<?php

namespace App\Support\Zeytin;

use App\Enums\PaymentMethod;
use App\Models\BalanceAdjustment;
use App\Models\DailyIncome;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sisa uang cash di kasir.
 *
 *   saldo = saldo awal + penjualan cash − pengeluaran tunai
 *
 * Penjualan cash dibaca dari Pemasukan Harian, sama dengan Buku Besar
 * Bulanan. Pengeluaran tunai ada dua sumber: Belanja Tunai, dan menu
 * Pengeluaran yang cara bayarnya Cash (termasuk gaji tunai). Pengeluaran
 * yang ditalangi pemilik atau belum dibayar tidak keluar dari laci, jadi
 * tidak ikut. Petty cash tidak ikut, sama seperti di Total Sales. Transfer
 * pemasok lewat bank.
 *
 * Awas dobel: kalau belanja yang sama dicatat di Belanja Tunai DAN di
 * Pengeluaran, ia terkurang dua kali.
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
     * Pengeluaran tunai dari menu Pengeluaran per tanggal (Y-m-d => jumlah).
     * Hanya yang keluar dari laci: cara bayar Cash, sudah dibayar, dan
     * bukan talangan pemilik.
     *
     * @return array<string, int>
     */
    protected static function expensesByDate(Carbon $from, Carbon $to): array
    {
        $methods = array_map(
            fn (PaymentMethod $m) => $m->value,
            array_filter(PaymentMethod::cases(), fn (PaymentMethod $m) => $m->isCash()),
        );

        return Expense::query()
            ->whereIn('method', $methods)
            ->where('is_paid', true)
            ->whereNull('paid_by_owner_id')
            ->whereDate('spent_on', '>=', $from->toDateString())
            ->whereDate('spent_on', '<=', $to->toDateString())
            ->get()
            ->groupBy(fn (Expense $e) => $e->spent_on->toDateString())
            ->map(fn (Collection $rows) => (int) $rows->sum('amount'))
            ->all();
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
                ->sum('total')
                + array_sum(static::expensesByDate($start, $from->copy()->subDay()));

            $adjusted = array_sum(BalanceAdjustment::byDate(BalanceAdjustment::CASH, $start, $from->copy()->subDay()));

            $running = $opening['amount'] + $in - $out + $adjusted;
        }

        $startBalance = $running;
        $totalIn = 0;
        $totalOut = 0;
        $totalAdjustment = 0;

        $expenses = static::expensesByDate($from, $to);
        $adjustments = BalanceAdjustment::byDate(BalanceAdjustment::CASH, $from, $to);

        $rows = DailyLedger::daily($from, $to)->map(function (array $day) use (&$running, &$totalIn, &$totalOut, &$totalAdjustment, $start, $opening, $expenses, $adjustments) {
            $counted = ! $day['date']->lt($start);
            $in = $counted ? static::cashIn($day) : 0;
            $out = $counted ? (int) $day['expense'] + ($expenses[$day['date']->toDateString()] ?? 0) : 0;
            $before = $running;

            if ($counted && $day['date']->isSameDay($start)) {
                $running += $opening['amount'];
                $before = $running;
            }

            $adjustment = $counted ? ($adjustments[$day['date']->toDateString()] ?? 0) : 0;

            $running += $in - $out + $adjustment;
            $totalIn += $in;
            $totalOut += $out;
            $totalAdjustment += $adjustment;

            return [
                'date' => $day['date'],
                'counted' => $counted,
                'opening' => $before,
                'cash_in' => $in,
                'cash_out' => $out,
                'adjustment' => $adjustment,
                'balance' => $running,
            ];
        })->values();

        return [
            'rows' => $rows,
            'start_balance' => $startBalance,
            'end_balance' => $running,
            'cash_in' => $totalIn,
            'cash_out' => $totalOut,
            'adjustment' => $totalAdjustment,
            'opening' => $opening,
        ];
    }
}

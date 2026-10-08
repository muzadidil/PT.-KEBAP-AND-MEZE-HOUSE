<?php

namespace App\Support\Zeytin;

use App\Enums\PaymentMethod;
use App\Models\BalanceAdjustment;
use App\Models\DailyIncome;
use App\Models\Expense;
use App\Models\Setting;
use App\Models\SupplierTransfer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Perkiraan sisa uang di rekening perusahaan.
 *
 *   saldo = saldo awal + penjualan non-tunai − pembayaran lewat rekening
 *
 * Masuk: BNI, Grab Food, Go Food, Go Pay (semua channel penjualan yang bukan
 * tunai). Keluar: Transfer Pemasok (termasuk yang dicatat dari notifikasi
 * bank) dan menu Pengeluaran yang cara bayarnya bukan Cash, sudah dibayar,
 * dan bukan talangan pemilik. Petty cash, gaji per orang (Gaji), dan Gaji
 * Pemilik tidak ikut: gaji lewat bank dicatat di Pengeluaran, supaya tidak
 * terhitung dua kali.
 *
 * Ini PERKIRAAN, bukan mutasi bank. Biaya bank, komisi Grab/Go, dan jeda
 * pencairan dari platform belum tercatat, jadi angkanya bisa lebih besar
 * dari saldo sebenarnya. Cocokkan dengan mutasi, dan atur ulang saldo awal
 * bila perlu.
 */
class BankBalance
{
    /** @return array{amount: int, date: ?string} */
    public static function opening(): array
    {
        return [
            'amount' => (int) Setting::get('bank.opening_amount', 0),
            'date' => Setting::get('bank.opening_date') ?: null,
        ];
    }

    public static function setOpening(int $amount, ?string $date): void
    {
        Setting::put('bank.opening_amount', max(0, $amount));
        Setting::put('bank.opening_date', $date ?: null);
    }

    /** Channel penjualan yang masuk ke rekening: ikut penjualan, bukan tunai. */
    protected static function accountChannels(): array
    {
        return array_values(array_diff(Channels::inSales(), Channels::cash()));
    }

    protected static function moneyIn(array $row): int
    {
        return array_sum(array_map(fn (string $key) => (int) ($row[$key] ?? 0), static::accountChannels()));
    }

    /**
     * Pembayaran keluar per tanggal (Y-m-d => jumlah).
     *
     * @return array<string, int>
     */
    protected static function outByDate(Carbon $from, Carbon $to): array
    {
        $out = [];

        SupplierTransfer::query()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->get()
            ->each(function (SupplierTransfer $row) use (&$out) {
                $key = $row->date->toDateString();
                $out[$key] = ($out[$key] ?? 0) + (int) $row->total;
            });

        $cash = array_map(fn (PaymentMethod $m) => $m->value, array_filter(PaymentMethod::cases(), fn (PaymentMethod $m) => $m->isCash()));

        Expense::query()
            ->whereNotIn('method', $cash)
            ->where('is_paid', true)
            ->whereNull('paid_by_owner_id')
            ->whereDate('spent_on', '>=', $from->toDateString())
            ->whereDate('spent_on', '<=', $to->toDateString())
            ->get()
            ->each(function (Expense $row) use (&$out) {
                $key = $row->spent_on->toDateString();
                $out[$key] = ($out[$key] ?? 0) + (int) $row->amount;
            });

        return $out;
    }

    /**
     * @return array{rows: Collection<int, array<string, mixed>>, start_balance: int, end_balance: int, money_in: int, money_out: int, opening: array{amount: int, date: ?string}}
     */
    public static function report(Carbon $from, Carbon $to): array
    {
        $opening = static::opening();
        $start = $opening['date'] ? Carbon::parse($opening['date'])->startOfDay() : Carbon::create(2000, 1, 1)->startOfDay();
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $running = 0;

        if ($start->lt($from)) {
            $before = $from->copy()->subDay();

            $in = DailyIncome::query()
                ->whereDate('date', '>=', $start->toDateString())
                ->whereDate('date', '<', $from->toDateString())
                ->get()
                ->sum(fn (DailyIncome $row) => static::moneyIn($row->channelAmounts()));

            $adjusted = array_sum(BalanceAdjustment::byDate(BalanceAdjustment::BANK, $start, $before));

            $running = $opening['amount'] + $in - array_sum(static::outByDate($start, $before)) + $adjusted;
        }

        $startBalance = $running;
        $totalIn = 0;
        $totalOut = 0;
        $totalAdjustment = 0;
        $out = static::outByDate($from, $to);
        $adjustments = BalanceAdjustment::byDate(BalanceAdjustment::BANK, $from, $to);

        $rows = DailyLedger::daily($from, $to)->map(function (array $day) use (&$running, &$totalIn, &$totalOut, &$totalAdjustment, $start, $opening, $out, $adjustments) {
            $counted = ! $day['date']->lt($start);
            $in = $counted ? static::moneyIn($day) : 0;
            $spent = $counted ? ($out[$day['date']->toDateString()] ?? 0) : 0;
            $before = $running;

            if ($counted && $day['date']->isSameDay($start)) {
                $running += $opening['amount'];
                $before = $running;
            }

            $adjustment = $counted ? ($adjustments[$day['date']->toDateString()] ?? 0) : 0;

            $running += $in - $spent + $adjustment;
            $totalIn += $in;
            $totalOut += $spent;
            $totalAdjustment += $adjustment;

            return [
                'date' => $day['date'],
                'counted' => $counted,
                'opening' => $before,
                'money_in' => $in,
                'money_out' => $spent,
                'adjustment' => $adjustment,
                'balance' => $running,
            ];
        })->values();

        return [
            'rows' => $rows,
            'start_balance' => $startBalance,
            'end_balance' => $running,
            'money_in' => $totalIn,
            'money_out' => $totalOut,
            'adjustment' => $totalAdjustment,
            'opening' => $opening,
        ];
    }
}

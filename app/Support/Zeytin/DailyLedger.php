<?php

namespace App\Support\Zeytin;

use App\Models\DailyIncome;
use App\Models\OutstandingBill;
use App\Models\Payroll;
use App\Models\Purchase;
use App\Models\SupplierTransfer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sumber tunggal seluruh angka pembukuan bulanan.
 *
 * Tidak ada saldo yang disimpan. Semua dihitung ulang dari pemasukan harian,
 * belanja, transfer, gaji, dan tagihan setiap kali diminta — alasan yang sama
 * dengan App\Support\Ledger untuk sisi kasir.
 *
 * ── Konsep yang dipertahankan dari berkas Excel klien ──
 *
 *   Total Sales             = SUM(D:H)  → cash + bni + grabFood + goFood + goPay
 *                             (petty cash TIDAK ikut; rumus aslinya memang
 *                             mulai dari kolom D dan melewati C)
 *   Supplier Cash           = 456560 + cash
 *   Remaining Supplier Cash = Supplier Cash − belanja tunai hari itu
 *   Grand Income (cashless) = Total Sales − cash
 *   Total baris belanja     = ((qty × price) + tax − disc)
 *
 * ── Tiga hal yang sengaja DIPERBAIKI, atas persetujuan klien ──
 *
 *   1. Grand Expense di berkas aslinya menunjuk satu baris belanja
 *      (Expense!J80 = 500.000), bukan totalnya. Di sini dijumlahkan penuh.
 *   2. Transfer ke pemasok tidak pernah ikut hitungan laba di berkas
 *      aslinya. Di sini ikut sebagai pengeluaran.
 *   3. Belanja harian di berkas aslinya menunjuk rentang baris yang diketik
 *      tangan, sehingga ada baris yang terlewat. Di sini pengelompokannya
 *      berdasarkan tanggal, jadi tidak ada yang bisa luput.
 */
class DailyLedger
{
    /**
     * Total satu baris belanja, mengikuti rumus asli di berkas Excel:
     * `=((qty * price) + tax - disc)`.
     */
    public static function lineTotal(mixed $qty, mixed $price, mixed $tax = 0, mixed $disc = 0): int
    {
        return static::int($qty) * static::int($price) + static::int($tax) - static::int($disc);
    }

    /**
     * Status tagihan yang dianggap sudah beres, apa pun ejaannya.
     *
     * Dicocokkan longgar karena statusnya diketik tangan di berkas Excel dan
     * ejaannya tidak pernah seragam. Yang salah kenal di sini bukan cuma
     * angka di satu kolom: tagihan yang sudah lunas tapi tidak dikenali akan
     * terus mengurangi saldo global sampai ada yang menyadarinya.
     */
    public static function isSettled(?string $status): bool
    {
        $value = mb_strtolower(trim((string) $status));

        return str_contains($value, 'paid')
            || str_contains($value, 'lunas')
            || str_contains($value, 'settled');
    }

    /**
     * Angka satu hari, mengikuti definisi kolom di berkas Excel.
     *
     * @param  array<string, mixed>  $day  baris pemasukan harian (boleh kosong)
     * @param  int  $spent  belanja tunai hari itu
     * @return array<string, mixed>
     */
    public static function dayFigures(array $day = [], int $spent = 0): array
    {
        $channels = [];

        foreach (Channels::keys() as $key) {
            $channels[$key] = static::int($day[$key] ?? 0);
        }

        $totalSales = 0;

        foreach (Channels::inSales() as $key) {
            $totalSales += $channels[$key];
        }

        $cash = 0;

        foreach (Channels::cash() as $key) {
            $cash += $channels[$key];
        }

        $supplierCash = (int) config('zeytin.supplier_cash_opening') + $cash;

        return [
            'date' => $day['date'] ?? null,
            ...$channels,
            'total_sales' => $totalSales,
            'cashless' => $totalSales - $cash,
            'supplier_cash' => $supplierCash,
            'expense' => $spent,
            'remaining_supplier_cash' => $supplierCash - $spent,
        ];
    }

    /**
     * Baris harian sepanjang rentang, termasuk hari tanpa catatan.
     *
     * Hari yang kosong tetap muncul sebagai nol, bukan hilang dari daftar:
     * hari libur dan hari yang lupa dicatat harus sama-sama terlihat.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function daily(Carbon $from, Carbon $to): Collection
    {
        $days = DailyIncome::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn (DailyIncome $row) => $row->date->toDateString());

        $spent = static::purchases($from, $to)
            ->selectRaw('date, SUM(total) as amount')
            ->groupBy('date')
            ->pluck('amount', 'date')
            ->mapWithKeys(fn ($amount, $date) => [Carbon::parse($date)->toDateString() => (int) $amount]);

        $rows = collect();

        for ($date = $from->copy()->startOfDay(); $date->lte($to); $date->addDay()) {
            $key = $date->toDateString();
            $income = $days->get($key);

            $rows->push(static::dayFigures(
                ['date' => $date->copy()] + ($income ? $income->channelAmounts() : []),
                $spent[$key] ?? 0,
            ));
        }

        return $rows;
    }

    /**
     * Seluruh angka untuk satu rentang tanggal.
     *
     * @return array<string, mixed>
     */
    public static function periodReport(Carbon $from, Carbon $to): array
    {
        $rows = static::daily($from, $to);
        $totals = static::blankTotals();

        foreach ($rows as $row) {
            foreach (array_keys($totals) as $field) {
                $totals[$field] += $row[$field] ?? 0;
            }
        }

        // Sisa titipan pemasok bukan angka yang boleh dijumlahkan antar hari —
        // yang bermakna adalah keadaan hari terakhir yang benar-benar tercatat.
        $lastRecorded = $rows->last(fn (array $row) => $row['total_sales'] > 0 || $row['expense'] > 0);
        $totals['remaining_supplier_cash'] = $lastRecorded['remaining_supplier_cash'] ?? 0;

        $transfers = (int) static::transfers($from, $to)->sum('total');
        $payroll = (int) static::payroll($from, $to)->sum('grand_total');
        $outstanding = static::unsettledBills()->sum('total');

        $totalExpenses = $totals['expense'] + $transfers + $payroll;

        $byChannel = [];

        foreach (Channels::keys() as $key) {
            $byChannel[$key] = $totals[$key];
        }

        return [
            'from' => $from->copy(),
            'to' => $to->copy(),
            'rows' => $rows,
            'days' => $rows->count(),
            'recorded_days' => $rows->filter(fn (array $row) => $row['total_sales'] > 0)->count(),

            'total_sales' => $totals['total_sales'],
            'cashless' => $totals['cashless'],
            'by_channel' => $byChannel,

            'cash_expense' => $totals['expense'],
            'transfers' => $transfers,
            'payroll' => $payroll,
            'total_expenses' => $totalExpenses,

            'remaining_supplier_cash' => $totals['remaining_supplier_cash'],
            'outstanding' => (int) $outstanding,

            'net_profit' => $totals['total_sales'] - $totalExpenses,
            'average_per_day' => $rows->count() ? intdiv($totals['total_sales'], $rows->count()) : 0,

            /*
             * Saldo global.
             *
             * Uang yang benar-benar dipegang usaha: seluruh pemasukan
             * dikurangi yang sudah dibayarkan, dikurangi tagihan yang sudah
             * jatuh tapi belum dibayar.
             *
             * Sisa titipan belanja pemasok SENGAJA tidak ikut ditambahkan.
             * Rumusnya `456.560 + tunai − belanja`, jadi uang tunai di
             * dalamnya sudah terhitung di Total Sales; menambahkannya membuat
             * tunai dihitung dua kali — pemasukan tunai 5 juta muncul sebagai
             * saldo 10,4 juta. Angkanya tetap tampil sebagai kartu tersendiri,
             * jadi tidak ada yang hilang dari layar.
             */
            'global_balance' => $totals['total_sales'] - $totalExpenses - (int) $outstanding,
        ];
    }

    /* ------------------------------------------------ baris yang dihitung */

    /*
     * Baris mana yang masuk hitungan satu rentang ditentukan di sini saja.
     * periodReport() menjumlahkannya, unduhan Excel mencantumkannya satu per
     * satu; karena keduanya memanggil yang sama, tiap angka di ringkasan
     * selalu bisa ditelusuri ke baris-baris yang membentuknya.
     */

    /** @return Builder<Purchase> */
    public static function purchases(Carbon $from, Carbon $to): Builder
    {
        return Purchase::query()->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
    }

    /** @return Builder<SupplierTransfer> */
    public static function transfers(Carbon $from, Carbon $to): Builder
    {
        return SupplierTransfer::query()->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * Gaji disimpan per bulan, jadi batas rentangnya dipotong ke awal bulan:
     * rentang 5–20 September tetap memuat payroll September, yang memang
     * dibayarkan sekali untuk bulan itu, bukan per tanggal.
     *
     * @return Builder<Payroll>
     */
    public static function payroll(Carbon $from, Carbon $to): Builder
    {
        return Payroll::query()->whereBetween('month', [
            $from->copy()->startOfMonth()->toDateString(),
            $to->copy()->startOfMonth()->toDateString(),
        ]);
    }

    /**
     * Tagihan yang belum beres TIDAK dibatasi rentang: yang belum lunas dari
     * bulan lalu tetap kewajiban hari ini. Membatasinya ke rentang yang
     * sedang dilihat akan membuat utang lama menghilang dari layar hanya
     * karena orangnya membuka bulan yang lain.
     *
     * @return Collection<int, OutstandingBill>
     */
    public static function unsettledBills(): Collection
    {
        return OutstandingBill::query()
            ->orderBy('date')
            ->get()
            ->reject(fn (OutstandingBill $bill) => static::isSettled($bill->status))
            ->values();
    }

    /**
     * Baris harian digulung ke bulan. Bulan tanpa catatan tidak ditampilkan.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public static function groupByMonth(Collection $rows): Collection
    {
        return static::rollUp($rows, fn (Carbon $date) => $date->format('Y-m'));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public static function groupByYear(Collection $rows): Collection
    {
        return static::rollUp($rows, fn (Carbon $date) => $date->format('Y'));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  callable(Carbon): string  $keyOf
     * @return Collection<int, array<string, mixed>>
     */
    protected static function rollUp(Collection $rows, callable $keyOf): Collection
    {
        $buckets = [];

        foreach ($rows as $row) {
            if (! $row['date'] instanceof Carbon) {
                continue;
            }

            $key = $keyOf($row['date']);

            $buckets[$key] ??= ['key' => $key, 'days' => 0, 'recorded_days' => 0] + static::blankTotals();

            $buckets[$key]['days']++;

            if ($row['total_sales'] > 0) {
                $buckets[$key]['recorded_days']++;
            }

            foreach (array_keys(static::blankTotals()) as $field) {
                $buckets[$key][$field] += $row[$field] ?? 0;
            }

            // Sama seperti di periodReport: sisa titipan adalah keadaan
            // terakhir, bukan jumlah antar hari.
            if ($row['total_sales'] > 0 || $row['expense'] > 0) {
                $buckets[$key]['remaining_supplier_cash'] = $row['remaining_supplier_cash'];
            }
        }

        ksort($buckets);

        return collect(array_values($buckets));
    }

    /** @return array<string, int> */
    protected static function blankTotals(): array
    {
        $totals = [
            'total_sales' => 0,
            'cashless' => 0,
            'expense' => 0,
            'supplier_cash' => 0,
            'remaining_supplier_cash' => 0,
        ];

        foreach (Channels::keys() as $key) {
            $totals[$key] = 0;
        }

        return $totals;
    }

    protected static function int(mixed $value): int
    {
        return (int) round((float) ($value ?: 0));
    }
}

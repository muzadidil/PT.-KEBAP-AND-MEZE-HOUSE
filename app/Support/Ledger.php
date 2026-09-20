<?php

namespace App\Support;

use App\Enums\CapitalDirection;
use App\Enums\ExpenseCategory;
use App\Enums\SalesChannel;
use App\Models\CapitalEntry;
use App\Models\Expense;
use App\Models\Owner;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sumber tunggal seluruh angka laporan.
 *
 * Tidak ada tabel saldo. Setiap angka dihitung ulang dari `sales`,
 * `expenses`, dan `capital_entries` setiap kali diminta. Saldo yang
 * disimpan terpisah bisa menyimpang dari transaksinya, dan itu jenis galat
 * yang paling sulit dilacak di pembukuan.
 *
 * Semua laporan berkala (harian, mingguan, bulanan, tahunan) dibangun dari
 * satu primitif yang sama, `dailyTotals()`, lalu dijumlahkan ulang di PHP.
 * Agregat harian paling banyak 366 baris per tahun, jadi murah, dan
 * caranya tidak bergantung pada fungsi tanggal khas satu mesin basis data.
 */
class Ledger
{
    /**
     * Penjualan per tanggal per channel.
     *
     * @return Collection<string, array{date: Carbon, cash: int, cashless: int, grab: int, total: int, transactions: int}>
     */
    public static function dailyTotals(Carbon $from, Carbon $to): Collection
    {
        $rows = Sale::query()
            ->selectRaw('sold_on, channel, SUM(total) as amount, COUNT(*) as transactions')
            ->whereBetween('sold_on', [$from->toDateString(), $to->toDateString()])
            ->groupBy('sold_on', 'channel')
            ->get();

        $days = collect();

        foreach ($rows as $row) {
            $date = Carbon::parse($row->sold_on);
            $key = $date->toDateString();
            // `channel` sudah di-cast jadi enum oleh model, sedangkan yang
            // dipakai di sini adalah nama kolom penampung per channel.
            $channel = $row->channel->value;

            $days[$key] ??= [
                'date' => $date,
                'cash' => 0,
                'cashless' => 0,
                'grab' => 0,
                'total' => 0,
                'transactions' => 0,
            ];

            $bucket = $days[$key];
            $bucket[$channel] += (int) $row->amount;
            $bucket['total'] += (int) $row->amount;
            $bucket['transactions'] += (int) $row->transactions;
            $days[$key] = $bucket;
        }

        return $days->sortKeys();
    }

    /**
     * Baris harian lengkap termasuk hari tanpa penjualan, supaya hari libur
     * atau hari yang terlupa dicatat terlihat sebagai nol, bukan hilang.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function daily(Carbon $from, Carbon $to): Collection
    {
        $totals = static::dailyTotals($from, $to);
        $rows = collect();

        for ($date = $from->copy()->startOfDay(); $date->lte($to); $date->addDay()) {
            $rows->push($totals[$date->toDateString()] ?? [
                'date' => $date->copy(),
                'cash' => 0,
                'cashless' => 0,
                'grab' => 0,
                'total' => 0,
                'transactions' => 0,
            ]);
        }

        return $rows;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function weekly(Carbon $from, Carbon $to): Collection
    {
        return static::rollUp($from, $to, fn (Carbon $date) => [
            'key' => $date->isoFormat('GGGG-[W]WW'),
            'start' => $date->copy()->startOfWeek(),
            'end' => $date->copy()->endOfWeek(),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function monthly(Carbon $from, Carbon $to): Collection
    {
        return static::rollUp($from, $to, fn (Carbon $date) => [
            'key' => $date->format('Y-m'),
            'start' => $date->copy()->startOfMonth(),
            'end' => $date->copy()->endOfMonth(),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function yearly(Carbon $from, Carbon $to): Collection
    {
        return static::rollUp($from, $to, fn (Carbon $date) => [
            'key' => $date->format('Y'),
            'start' => $date->copy()->startOfYear(),
            'end' => $date->copy()->endOfYear(),
        ]);
    }

    /**
     * Menjumlahkan agregat harian ke periode yang lebih besar. Hanya periode
     * yang benar-benar punya penjualan yang muncul, karena daftar minggu atau
     * tahun kosong tidak menjelaskan apa pun.
     *
     * @param  callable(Carbon): array{key: string, start: Carbon, end: Carbon}  $bucketOf
     * @return Collection<int, array<string, mixed>>
     */
    protected static function rollUp(Carbon $from, Carbon $to, callable $bucketOf): Collection
    {
        $buckets = [];

        foreach (static::dailyTotals($from, $to) as $day) {
            $bucket = $bucketOf($day['date']);
            $key = $bucket['key'];

            $buckets[$key] ??= [
                'key' => $key,
                'start' => $bucket['start'],
                'end' => $bucket['end'],
                'cash' => 0,
                'cashless' => 0,
                'grab' => 0,
                'total' => 0,
                'transactions' => 0,
                'days' => 0,
            ];

            foreach (['cash', 'cashless', 'grab', 'total', 'transactions'] as $field) {
                $buckets[$key][$field] += $day[$field];
            }

            $buckets[$key]['days']++;
        }

        ksort($buckets);

        return collect(array_values($buckets));
    }

    /**
     * @return array{cash: int, cashless: int, grab: int, total: int, transactions: int}
     */
    public static function salesSummary(Carbon $from, Carbon $to): array
    {
        $summary = ['cash' => 0, 'cashless' => 0, 'grab' => 0, 'total' => 0, 'transactions' => 0];

        foreach (static::dailyTotals($from, $to) as $day) {
            foreach (array_keys($summary) as $field) {
                $summary[$field] += $day[$field];
            }
        }

        return $summary;
    }

    /**
     * Pengeluaran per kategori dalam satu rentang.
     *
     * @return array<string, int>
     */
    public static function expensesByCategory(Carbon $from, Carbon $to): array
    {
        $rows = Expense::query()
            ->selectRaw('category, SUM(amount) as amount')
            ->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])
            ->groupBy('category')
            ->pluck('amount', 'category');

        $totals = [];

        foreach (ExpenseCategory::cases() as $category) {
            $totals[$category->value] = (int) ($rows[$category->value] ?? 0);
        }

        return $totals;
    }

    public static function expenseTotal(Carbon $from, Carbon $to): int
    {
        return (int) Expense::query()
            ->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');
    }

    /**
     * Laba periode: seluruh penjualan dikurangi seluruh pengeluaran yang
     * dicatat di periode itu, dibayar maupun belum. Pengeluaran yang belum
     * dibayar tetap mengurangi laba karena kewajibannya sudah timbul.
     *
     * @return array{sales: int, expenses: int, profit: int}
     */
    public static function profit(Carbon $from, Carbon $to): array
    {
        $sales = static::salesSummary($from, $to)['total'];
        $expenses = static::expenseTotal($from, $to);

        return [
            'sales' => $sales,
            'expenses' => $expenses,
            'profit' => $sales - $expenses,
        ];
    }

    /**
     * Pembagian pengeluaran yang ditalangi pemilik.
     *
     * Tiap pemilik menanggung porsinya (Aslan 60%, Leo 40%), tapi yang
     * benar-benar membayar bisa siapa saja. Selisih antara yang dibayar dan
     * yang seharusnya ditanggung itulah yang perlu dibereskan antar pemilik.
     * Sisa pembagian rupiah diberikan ke porsi terbesar lewat Money::split,
     * jadi jumlah tanggungan selalu persis sama dengan totalnya.
     *
     * @return array{total: int, rows: array<int, array<string, mixed>>}
     */
    public static function ownerSettlement(Carbon $from, Carbon $to): array
    {
        $owners = Owner::query()->where('active', true)->orderByDesc('share_percent')->get();

        $paid = Expense::query()
            ->selectRaw('paid_by_owner_id, SUM(amount) as amount')
            ->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('paid_by_owner_id')
            ->groupBy('paid_by_owner_id')
            ->pluck('amount', 'paid_by_owner_id');

        $total = (int) $paid->sum();

        $shares = Money::split($total, $owners->pluck('share_percent', 'id')->all());

        $rows = $owners->map(function (Owner $owner) use ($paid, $shares) {
            $actual = (int) ($paid[$owner->id] ?? 0);
            $share = $shares[$owner->id] ?? 0;

            return [
                'owner' => $owner,
                'name' => $owner->name,
                'percent' => $owner->share_percent,
                'paid' => $actual,
                'share' => $share,
                // Positif: menalangi lebih dari porsinya, jadi berhak
                // menerima. Negatif: masih harus menyetor.
                'balance' => $actual - $share,
            ];
        })->all();

        return ['total' => $total, 'rows' => $rows];
    }

    /**
     * Neraca per satu tanggal.
     *
     * Aturan yang membuatnya selalu seimbang:
     *
     *  - Kas dan bank hanya bergerak untuk pengeluaran yang sudah dibayar
     *    usaha sendiri. Yang belum dibayar adalah kewajiban.
     *  - Pengeluaran yang ditalangi pemilik tidak mengurangi kas usaha sama
     *    sekali; uangnya dari kantong pemilik, jadi dicatat menambah modal
     *    pemilik itu.
     *
     * Dengan dua aturan itu, Aset = Kewajiban + Ekuitas berlaku secara
     * aljabar untuk kombinasi transaksi apa pun, bukan kebetulan cocok di
     * skenario tertentu. Pembuktiannya ada di tests/Feature/BalanceSheetTest.
     *
     * @return array<string, mixed>
     */
    public static function balanceSheet(Carbon $asOf): array
    {
        $start = Carbon::create(2000, 1, 1)->startOfDay();

        $sales = static::salesSummary($start, $asOf);

        // Pengeluaran yang benar-benar mengurangi uang usaha.
        $businessPaid = Expense::query()
            ->selectRaw('method, SUM(amount) as amount')
            ->where('spent_on', '<=', $asOf->toDateString())
            ->where('is_paid', true)
            ->whereNull('paid_by_owner_id')
            ->groupBy('method')
            ->pluck('amount', 'method');

        $capital = CapitalEntry::query()
            ->selectRaw('direction, method, SUM(amount) as amount')
            ->where('entry_on', '<=', $asOf->toDateString())
            ->groupBy('direction', 'method')
            ->get();

        $capitalCash = fn (CapitalDirection $direction) => (int) $capital
            ->where('direction', $direction->value)
            ->where('method', 'cash')
            ->sum('amount');

        $capitalBank = fn (CapitalDirection $direction) => (int) $capital
            ->where('direction', $direction->value)
            ->where('method', '!=', 'cash')
            ->sum('amount');

        $cashOut = (int) ($businessPaid['cash'] ?? 0);
        $bankOut = (int) $businessPaid->reject(fn ($amount, $method) => $method === 'cash')->sum();

        $cashOnHand = $sales['cash']
            + $capitalCash(CapitalDirection::In)
            - $capitalCash(CapitalDirection::Out)
            - $cashOut;

        $bank = $sales['cashless'] + $sales['grab']
            + $capitalBank(CapitalDirection::In)
            - $capitalBank(CapitalDirection::Out)
            - $bankOut;

        // Kewajiban: pengeluaran yang sudah dicatat tapi belum dibayar.
        $unpaid = Expense::query()
            ->selectRaw('category, SUM(amount) as amount')
            ->where('spent_on', '<=', $asOf->toDateString())
            ->where('is_paid', false)
            ->groupBy('category')
            ->pluck('amount', 'category');

        $taxPayable = (int) ($unpaid[ExpenseCategory::Tax->value] ?? 0);
        $supplierPayable = (int) ($unpaid[ExpenseCategory::Supplier->value] ?? 0);
        $otherPayable = (int) $unpaid->sum() - $taxPayable - $supplierPayable;

        // Ekuitas per pemilik: setoran, dikurangi penarikan, ditambah yang
        // ditalanginya dari kantong sendiri.
        $ownerPaid = Expense::query()
            ->selectRaw('paid_by_owner_id, SUM(amount) as amount')
            ->where('spent_on', '<=', $asOf->toDateString())
            ->whereNotNull('paid_by_owner_id')
            ->groupBy('paid_by_owner_id')
            ->pluck('amount', 'paid_by_owner_id');

        $perOwnerCapital = CapitalEntry::query()
            ->selectRaw('owner_id, direction, SUM(amount) as amount')
            ->where('entry_on', '<=', $asOf->toDateString())
            ->groupBy('owner_id', 'direction')
            ->get();

        $owners = Owner::query()->orderByDesc('share_percent')->get()->map(function (Owner $owner) use ($perOwnerCapital, $ownerPaid) {
            $in = (int) $perOwnerCapital->where('owner_id', $owner->id)->where('direction', 'in')->sum('amount');
            $out = (int) $perOwnerCapital->where('owner_id', $owner->id)->where('direction', 'out')->sum('amount');
            $advanced = (int) ($ownerPaid[$owner->id] ?? 0);

            return [
                'name' => $owner->name,
                'percent' => $owner->share_percent,
                'invested' => $in,
                'withdrawn' => $out,
                'advanced' => $advanced,
                'capital' => $in - $out + $advanced,
            ];
        })->all();

        $expensesToDate = (int) Expense::query()->where('spent_on', '<=', $asOf->toDateString())->sum('amount');
        $retained = $sales['total'] - $expensesToDate;

        $assets = $cashOnHand + $bank;
        $liabilities = $taxPayable + $supplierPayable + $otherPayable;
        $capitalTotal = array_sum(array_column($owners, 'capital'));
        $equity = $capitalTotal + $retained;

        return [
            'as_of' => $asOf->copy(),
            'cash_on_hand' => $cashOnHand,
            'bank' => $bank,
            'assets' => $assets,
            'tax_payable' => $taxPayable,
            'supplier_payable' => $supplierPayable,
            'other_payable' => $otherPayable,
            'liabilities' => $liabilities,
            'owners' => $owners,
            'owner_capital' => $capitalTotal,
            'retained_earnings' => $retained,
            'equity' => $equity,
            'total_sales' => $sales['total'],
            'total_expenses' => $expensesToDate,
            // Selalu 0 di buku yang tertib. Ditampilkan apa adanya supaya
            // kalau suatu saat tidak nol, ketahuan dari halamannya sendiri.
            'difference' => $assets - ($liabilities + $equity),
        ];
    }

    /** Channel yang dipakai di laporan, berurutan seperti di kolom tabel. */
    public static function channels(): array
    {
        return [SalesChannel::Cash, SalesChannel::Cashless, SalesChannel::Grab];
    }
}

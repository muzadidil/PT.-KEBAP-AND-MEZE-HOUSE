<?php

namespace App\Support;

/**
 * Uang disimpan sebagai bilangan bulat rupiah, tanpa sen. Restoran ini tidak
 * pernah menagih pecahan rupiah, dan bilangan bulat menutup seluruh
 * kemungkinan galat pembulatan di laporan.
 */
class Money
{
    public static function format(?int $amount): string
    {
        $amount ??= 0;
        $sign = $amount < 0 ? '-' : '';

        return $sign.'Rp '.number_format(abs($amount), 0, ',', '.');
    }

    /** Menerima "45.000", "45000", "Rp 45.000" — semuanya jadi 45000. */
    public static function parse(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        $digits = preg_replace('/[^0-9-]/', '', (string) $value);

        return $digits === '' || $digits === '-' ? 0 : (int) $digits;
    }

    /**
     * Membagi satu jumlah menurut persentase tanpa kehilangan atau
     * menciptakan rupiah: sisa pembagian diberikan ke porsi dengan pecahan
     * terbesar, sehingga jumlah hasilnya selalu persis sama dengan $amount.
     *
     * @param  array<int|string, int>  $percents  kunci apa pun => persen
     * @return array<int|string, int>
     */
    public static function split(int $amount, array $percents): array
    {
        $totalPercent = array_sum($percents);

        if ($totalPercent <= 0 || $amount === 0) {
            return array_map(fn () => 0, $percents);
        }

        $shares = [];
        $remainders = [];

        foreach ($percents as $key => $percent) {
            $exact = $amount * $percent;
            $shares[$key] = intdiv($exact, $totalPercent);
            $remainders[$key] = $exact % $totalPercent;
        }

        $leftover = $amount - array_sum($shares);

        arsort($remainders);

        foreach (array_keys($remainders) as $key) {
            if ($leftover <= 0) {
                break;
            }

            $shares[$key]++;
            $leftover--;
        }

        return $shares;
    }
}

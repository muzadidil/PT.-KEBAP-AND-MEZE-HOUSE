<?php

namespace App\Support;

/**
 * Angka rupiah dalam kata-kata bahasa Indonesia, untuk baris "Terbilang" di
 * slip gaji: 1.250.000 → "Satu juta dua ratus lima puluh ribu rupiah".
 *
 * Aturannya sama dengan aplikasi Slip Gaji aslinya, termasuk bentuk khusus
 * "sebelas", "seratus", dan "seribu".
 */
class Terbilang
{
    protected const UNITS = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    public static function rupiah(int $amount): string
    {
        if ($amount === 0) {
            return 'Nol rupiah';
        }

        $words = ($amount < 0 ? 'minus ' : '').static::words(abs($amount)).' rupiah';

        return mb_strtoupper(mb_substr($words, 0, 1)).mb_substr($words, 1);
    }

    public static function words(int $n): string
    {
        return match (true) {
            $n < 12 => static::UNITS[$n],
            $n < 20 => static::words($n - 10).' belas',
            $n < 100 => static::words(intdiv($n, 10)).' puluh'.static::rest($n % 10),
            $n < 200 => 'seratus'.static::rest($n - 100),
            $n < 1_000 => static::words(intdiv($n, 100)).' ratus'.static::rest($n % 100),
            $n < 2_000 => 'seribu'.static::rest($n - 1_000),
            $n < 1_000_000 => static::words(intdiv($n, 1_000)).' ribu'.static::rest($n % 1_000),
            $n < 1_000_000_000 => static::words(intdiv($n, 1_000_000)).' juta'.static::rest($n % 1_000_000),
            $n < 1_000_000_000_000 => static::words(intdiv($n, 1_000_000_000)).' miliar'.static::rest($n % 1_000_000_000),
            default => static::words(intdiv($n, 1_000_000_000_000)).' triliun'.static::rest($n % 1_000_000_000_000),
        };
    }

    protected static function rest(int $n): string
    {
        return $n ? ' '.static::words($n) : '';
    }
}

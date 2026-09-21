<?php

namespace App\Support\Zeytin;

/**
 * Channel pemasukan harian, dibaca dari config/zeytin.php.
 *
 * Dipusatkan di sini supaya menambah satu channel cukup diubah di berkas
 * konfigurasi: migrasi, formulir, tabel, laporan, dan pengimpor semuanya
 * membaca dari sini, jadi keenamnya tidak mungkin berbeda pendapat soal
 * channel mana yang ada dan mana yang tunai.
 */
class Channels
{
    /** @return array<int, array{key: string, label: string, in_sales: bool, is_cash: bool}> */
    public static function all(): array
    {
        return config('zeytin.channels', []);
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_column(static::all(), 'key');
    }

    /**
     * Channel yang ikut Total Sales. Petty cash tidak termasuk — rumus
     * aslinya `=SUM(D8:H8)` memang melewati kolomnya.
     *
     * @return array<int, string>
     */
    public static function inSales(): array
    {
        return array_column(array_filter(static::all(), fn ($c) => $c['in_sales']), 'key');
    }

    /**
     * Channel yang berupa uang tunai.
     *
     * Ditandai di konfigurasi, bukan dikenali dari namanya: kalau suatu saat
     * ada channel tunai kedua (mis. QRIS yang disetor tunai), menandainya di
     * config sudah cukup, dan tidak ada rumus yang perlu diingat untuk ikut
     * diubah.
     *
     * @return array<int, string>
     */
    public static function cash(): array
    {
        return array_column(array_filter(static::all(), fn ($c) => $c['is_cash']), 'key');
    }

    public static function label(string $key): string
    {
        foreach (static::all() as $channel) {
            if ($channel['key'] === $key) {
                return $channel['label'];
            }
        }

        return $key;
    }
}

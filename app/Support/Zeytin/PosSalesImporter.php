<?php

namespace App\Support\Zeytin;

use App\Support\Excel\ImportReport;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Mengimpor laporan "Report Item Details" dari aplikasi kasir (CSV atau
 * Excel) ke Pemasukan Harian.
 *
 * Satu baris berkas = satu item di satu struk. Yang diambil hanya tiga
 * kolom: Date, Payment Method, dan Net Sales. Net Sales dijumlahkan per
 * tanggal dan per cara bayar, lalu menjadi satu baris Pemasukan Harian —
 * tanpa Gratuity dan Tax, karena itu bukan penjualan.
 *
 * Aturan yang melindungi pembukuan:
 *   - Tanggal yang SUDAH punya baris Pemasukan Harian dilewati dan
 *     dilaporkan; tidak pernah ditimpa, juga bukan yang diketik tangan.
 *     Mengimpor berkas yang sama dua kali karenanya tidak menggandakan apa pun.
 *   - Cara bayar yang tidak dikenal menghentikan seluruh impor dan
 *     disebutkan namanya. Menebak (mis. memasukkannya ke Cash) akan
 *     diam-diam mengubah angka tunai.
 *   - Satu kesalahan apa pun berarti tidak ada yang disimpan.
 */
class PosSalesImporter
{
    /** Nama cara bayar di aplikasi kasir (huruf kecil, tanpa spasi) → kunci channel. */
    protected const METHODS = [
        'cash' => 'cash',
        'tunai' => 'cash',
        'bni' => 'bni',
        'grabfood' => 'grab_food',
        'gofood' => 'go_food',
        'gopay' => 'go_pay',
    ];

    public function import(string $path): ImportReport
    {
        try {
            $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);
        } catch (\Throwable) {
            return ImportReport::failed([__('excel.pos.unreadable')]);
        }

        $header = array_map(fn ($cell) => static::key((string) $cell), array_shift($rows) ?? []);
        $columns = [];

        foreach (['date', 'paymentmethod', 'netsales'] as $needed) {
            $index = array_search($needed, $header, true);

            if ($index === false) {
                return ImportReport::failed([__('excel.pos.missing_column', ['column' => match ($needed) {
                    'date' => 'Date',
                    'paymentmethod' => 'Payment Method',
                    'netsales' => 'Net Sales',
                }])]);
            }

            $columns[$needed] = $index;
        }

        $errors = [];
        $unknown = [];
        $days = [];

        foreach ($rows as $offset => $row) {
            $line = $offset + 2;
            $rawDate = $row[$columns['date']] ?? null;
            $rawMethod = trim((string) ($row[$columns['paymentmethod']] ?? ''));
            $rawAmount = $row[$columns['netsales']] ?? null;

            if (blank($rawDate) && $rawMethod === '' && blank($rawAmount)) {
                continue;
            }

            $date = static::date($rawDate);

            if (! $date) {
                $errors[] = __('excel.pos.bad_date', ['row' => $line, 'value' => (string) $rawDate]);

                continue;
            }

            if (! is_numeric($rawAmount)) {
                $errors[] = __('excel.pos.bad_amount', ['row' => $line, 'value' => (string) $rawAmount]);

                continue;
            }

            $channel = self::METHODS[static::key($rawMethod)] ?? null;

            if (! $channel || ! in_array($channel, Channels::keys(), true)) {
                $unknown[$rawMethod === '' ? '(kosong)' : $rawMethod][] = $line;

                continue;
            }

            $days[$date->toDateString()][$channel] = ($days[$date->toDateString()][$channel] ?? 0) + (int) round((float) $rawAmount);
        }

        foreach ($unknown as $method => $lines) {
            $errors[] = __('excel.pos.unknown_method', [
                'method' => $method,
                'rows' => implode(', ', array_slice($lines, 0, 5)).(count($lines) > 5 ? ', …' : ''),
                'valid' => 'Cash, BNI, GrabFood, GoFood, GoPay',
            ]);
        }

        if ($errors) {
            return ImportReport::failed($errors);
        }

        return DailyIncomeWriter::save($days);
    }

    /** "Payment Method" → "paymentmethod": dicocokkan tanpa memedulikan spasi dan huruf. */
    protected static function key(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($value)) ?? '';
    }

    /** Tanggal hari-dulu (30-09-2026, 30/09/2026), ISO, atau tanggal asli Excel. */
    protected static function date(mixed $value): ?Carbon
    {
        if (is_numeric($value) && (float) $value > 20000) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
        }

        $text = trim((string) $value);

        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $text);
            } catch (\Throwable) {
                continue;
            }

            // createFromFormat menggulung 31-02 menjadi Maret; yang dicari
            // hanya tanggal yang ditulis persis seperti yang dibaca.
            if ($date->format($format) === $text) {
                return $date;
            }
        }

        return null;
    }
}

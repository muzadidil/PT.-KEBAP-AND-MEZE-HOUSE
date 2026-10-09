<?php

namespace App\Support\Zeytin;

use App\Support\Excel\ImportReport;
use Illuminate\Support\Carbon;

/**
 * Membaca laporan penjualan harian yang dikirim lewat Telegram dan
 * memasukkannya ke Pemasukan Harian.
 *
 * Format pesan (satu pesan = satu hari):
 *
 *     Penjualan 26 Sep 2026
 *     Petty Cash: 0
 *     Cash: 1.545.390
 *     BNI: 9.320.850
 *     Grab Food: 207.900
 *     Go Food: 0
 *     Go Pay: 0
 *     Total: 11.074.140        (boleh tidak ada; kalau ada, harus cocok)
 *
 * Sumber bisa teks yang ditempel, atau berkas hasil "Export chat history"
 * Telegram Desktop (JSON). Pesan tanpa judul "Penjualan <tanggal>" dianggap
 * obrolan biasa dan diabaikan.
 *
 * Aturan yang melindungi pembukuan:
 *   - Nama channel yang tidak dikenal, angka yang tidak wajar, atau Total
 *     yang tidak cocok menghentikan seluruh impor — tidak ada yang disimpan.
 *   - Tanggal yang sudah punya baris dilewati (lihat DailyIncomeWriter).
 *   - Kalau satu tanggal dikirim dua kali, pesan yang terakhir dipakai.
 */
class TelegramSalesImporter
{
    /** Nama di pesan (huruf kecil, tanpa spasi) → kunci channel. */
    protected const LABELS = [
        'pettycash' => 'petty_cash',
        'cash' => 'cash',
        'tunai' => 'cash',
        'bni' => 'bni',
        'grabfood' => 'grab_food',
        'gofood' => 'go_food',
        'gopay' => 'go_pay',
    ];

    protected const TOTAL_LABELS = ['total', 'totalpenjualan', 'totalsales', 'jumlah'];

    protected const MONTHS = [
        'jan' => 1, 'januari' => 1, 'january' => 1, 'feb' => 2, 'februari' => 2, 'february' => 2,
        'mar' => 3, 'maret' => 3, 'march' => 3, 'apr' => 4, 'april' => 4,
        'mei' => 5, 'may' => 5, 'jun' => 6, 'juni' => 6, 'june' => 6,
        'jul' => 7, 'juli' => 7, 'july' => 7, 'agu' => 8, 'agt' => 8, 'agustus' => 8, 'aug' => 8, 'august' => 8,
        'sep' => 9, 'sept' => 9, 'september' => 9, 'okt' => 10, 'oktober' => 10, 'oct' => 10, 'october' => 10,
        'nov' => 11, 'november' => 11, 'des' => 12, 'desember' => 12, 'dec' => 12, 'december' => 12,
    ];

    public function import(string $input): ImportReport
    {
        $messages = static::messages($input);

        if ($messages === null) {
            return ImportReport::failed([__('excel.telegram.unreadable')]);
        }

        $errors = [];
        $days = [];
        $repeated = [];

        foreach (static::blocks($messages) as $block) {
            $date = $block['date'];
            $channels = array_fill_keys(Channels::keys(), 0);
            $seen = 0;
            $total = null;
            $label = $date->translatedFormat('j M Y');

            foreach ($block['lines'] as $line) {
                if (! preg_match('/^\s*([\p{L}][\p{L} ]*?)\s*[:=]\s*(?:rp\.?\s*)?([\d.,]+)\s*$/iu', $line, $m)) {
                    continue;
                }

                $key = static::key($m[1]);
                $amount = static::amount($m[2]);

                if ($amount === null) {
                    $errors[] = __('excel.telegram.bad_amount', ['date' => $label, 'label' => trim($m[1]), 'value' => $m[2]]);

                    continue;
                }

                if (in_array($key, self::TOTAL_LABELS, true)) {
                    $total = $amount;

                    continue;
                }

                $channel = self::LABELS[$key] ?? null;

                if (! $channel || ! in_array($channel, Channels::keys(), true)) {
                    $errors[] = __('excel.telegram.unknown_label', ['date' => $label, 'label' => trim($m[1]), 'valid' => 'Petty Cash, Cash, BNI, Grab Food, Go Food, Go Pay']);

                    continue;
                }

                $channels[$channel] = $amount;
                $seen++;
            }

            if ($seen === 0) {
                $errors[] = __('excel.telegram.no_amounts', ['date' => $label]);

                continue;
            }

            if ($total !== null) {
                $sum = array_sum(array_map(fn ($k) => $channels[$k], Channels::inSales()));

                if ($sum !== $total) {
                    $errors[] = __('excel.telegram.total_mismatch', [
                        'date' => $label,
                        'total' => number_format($total, 0, ',', '.'),
                        'sum' => number_format($sum, 0, ',', '.'),
                    ]);

                    continue;
                }
            }

            $iso = $date->toDateString();

            if (isset($days[$iso])) {
                $repeated[$iso] = $label;
            }

            $days[$iso] = $channels;
        }

        if ($errors) {
            return ImportReport::failed($errors);
        }

        if (! $days) {
            return ImportReport::failed([__('excel.telegram.nothing_found')]);
        }

        return DailyIncomeWriter::save($days, $repeated
            ? __('excel.telegram.repeated_note', ['dates' => implode(', ', $repeated)])
            : null);
    }

    /**
     * Teks pesan satu per satu. JSON ekspor Telegram diuraikan per pesan;
     * selain itu seluruh teks diperlakukan sebagai satu aliran.
     *
     * @return array<int, string>|null null kalau JSON-nya rusak
     */
    protected static function messages(string $input): ?array
    {
        $input = ltrim($input, "\xEF\xBB\xBF \t\r\n");

        if (! str_starts_with($input, '{') && ! str_starts_with($input, '[')) {
            return [$input];
        }

        $data = json_decode($input, true);

        if (! is_array($data)) {
            return null;
        }

        $list = $data['messages'] ?? $data;

        if (! is_array($list)) {
            return null;
        }

        $texts = [];

        foreach ($list as $message) {
            $text = is_array($message) ? ($message['text'] ?? '') : $message;

            if (is_array($text)) {
                // Telegram memecah pesan berformat jadi potongan string/objek.
                $text = implode('', array_map(fn ($part) => is_array($part) ? ($part['text'] ?? '') : $part, $text));
            }

            if (is_string($text) && $text !== '') {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    /**
     * Memecah teks menjadi blok per hari: dimulai dari baris "Penjualan
     * <tanggal>", berakhir di judul berikutnya. Baris sebelum judul pertama
     * diabaikan.
     *
     * @param  array<int, string>  $messages
     * @return array<int, array{date: Carbon, lines: array<int, string>}>
     */
    protected static function blocks(array $messages): array
    {
        $blocks = [];

        foreach ($messages as $message) {
            $current = null;

            foreach (preg_split('/\R/u', $message) ?: [] as $line) {
                if (preg_match('/penjualan/iu', $line) && ! preg_match('/^\s*total/iu', $line)) {
                    $date = static::date($line);

                    if ($date) {
                        $current = count($blocks);
                        $blocks[] = ['date' => $date, 'lines' => []];

                        continue;
                    }
                }

                if ($current !== null) {
                    $blocks[$current]['lines'][] = $line;
                }
            }
        }

        return $blocks;
    }

    /** "Penjualan 26 Sep 2026", "26-09-2026", "26/9/2026", "26 September 2026". */
    protected static function date(string $line): ?Carbon
    {
        if (! preg_match('/(\d{1,2})[\s\-\/.]+(\d{1,2}|[\p{L}]+)[\s\-\/.,]+(\d{4})/u', $line, $m)) {
            return null;
        }

        $month = ctype_digit($m[2]) ? (int) $m[2] : (self::MONTHS[mb_strtolower($m[2])] ?? null);

        if (! $month || ! checkdate($month, (int) $m[1], (int) $m[3])) {
            return null;
        }

        return Carbon::create((int) $m[3], $month, (int) $m[1])->startOfDay();
    }

    /**
     * "1.545.390", "1,545,390", "1545390", "98.175,50" → rupiah bulat.
     * Bentuk yang ambigu (mis. "1.5") ditolak, bukan ditebak.
     */
    protected static function amount(string $value): ?int
    {
        $value = trim($value);

        if (preg_match('/^\d{1,3}(?:[.,]\d{3})+(?:,\d{1,2})?$/', $value) || preg_match('/^\d+(?:,\d{1,2})?$/', $value)) {
            $decimals = 0.0;

            if (preg_match('/,(\d{1,2})$/', $value, $d) && ! preg_match('/^\d{1,3}(?:,\d{3})+$/', $value)) {
                $decimals = (float) ('0.'.$d[1]);
                $value = substr($value, 0, -strlen($d[0]));
            }

            return (int) round((int) preg_replace('/\D/', '', $value) + $decimals);
        }

        return null;
    }

    protected static function key(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($value)) ?? '';
    }
}

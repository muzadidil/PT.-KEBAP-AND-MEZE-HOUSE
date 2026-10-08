<?php

namespace App\Support\Bank;

use Illuminate\Support\Carbon;

/**
 * Membaca email notifikasi transaksi BNI (bahasa Indonesia atau Inggris).
 *
 * Satu email memuat dua blok, Indonesia lalu Inggris, dengan isi yang
 * sama; blok dengan nomor referensi yang sama hanya dihitung sekali. Teks
 * boleh memuat beberapa email sekaligus.
 *
 * Yang dibaca hanya transaksi BERHASIL. Arah ditentukan dari pengirim: kalau
 * pengirimnya perusahaan sendiri, ini pembayaran keluar.
 *
 * Pembacaan mengikuti bentuk email BNI saat ini. Kalau BNI mengubah susunan
 * kata, bagian yang gagal dibaca dilaporkan apa adanya; tidak ada angka
 * yang ditebak.
 */
class BniNotification
{
    protected const LABELS = [
        'reference' => ['No\. Referensi BNI', 'BNI Reference Number'],
        'datetime' => ['Tanggal\/Jam', 'Date\/Time'],
        'type' => ['Jenis Transaksi', 'Transaction Type'],
        'amount' => ['Nominal', 'Amount'],
        'remitter' => ['Pengirim', 'Remitter'],
        'beneficiary' => ['Penerima', 'Beneficiary'],
        'bank' => ['Bank Penerima', 'Beneficiary Bank'],
        'remark' => ['Keterangan Pembayaran', 'Transaction Remark'],
        'status' => ['Status'],
    ];

    /** Apakah teks ini memuat nomor referensi BNI, yaitu tanda notifikasi transaksi. */
    public static function looksLikeTransaction(string $text): bool
    {
        return (bool) preg_match('/(No\. Referensi BNI|BNI Reference Number)\s*:/i', static::flat($text));
    }

    /** Email transaksi dengan isian lain (tanpa nomor referensi BNI): belum dibaca. */
    public static function looksLikeOtherFormat(string $text): bool
    {
        $flat = static::flat($text);

        return ! static::looksLikeTransaction($text)
            && preg_match('/(Tanggal\/Jam|Date\/Time)\s*:/i', $flat)
            && preg_match('/(Jenis Transaksi|Transaction Type)\s*:/i', $flat);
    }

    /**
     * Judul isian yang ada di teks, berurutan, untuk diagnosa: bentuk email
     * terlihat tanpa membuka isiannya (nama dan angka).
     *
     * @return array<int, string>
     */
    public static function labels(string $text): array
    {
        preg_match_all('/(?<![\p{L}])([\p{Lu}][\p{L}\.\/ ]{2,40}?)\s*:(?=\s)/u', static::flat($text), $m);

        return array_values(array_unique(array_map('trim', $m[1])));
    }

    /**
     * @param  array<int, string>  $companyNames  nama perusahaan (huruf besar), untuk menentukan arah
     * @return array{parsed: array<int, array<string, mixed>>, problems: array<int, string>}
     */
    public static function parse(string $text, array $companyNames = ['KEBAP AND MEZE HOUSE', 'KEBAP & MEZE HOUSE']): array
    {
        $flat = static::flat($text);
        $parsed = [];
        $problems = [];

        preg_match_all('/(?<![\p{L}])(?:No\. Referensi BNI|BNI Reference Number)\s*:/iu', $flat, $matches, PREG_OFFSET_CAPTURE);
        $starts = array_map(fn ($match) => $match[1], $matches[0]);

        foreach ($starts as $i => $start) {
            $block = substr($flat, $start, ($starts[$i + 1] ?? strlen($flat)) - $start);
            $row = static::block($block, $companyNames);

            if (is_string($row)) {
                $problems[] = $row;

                continue;
            }

            // Blok Inggris dari email yang sama: nomor referensi yang sama.
            $parsed[$row['reference']] ??= $row;
        }

        if ($starts === []) {
            $problems[] = 'Tidak ada nomor referensi BNI di teks ini.';
        }

        return ['parsed' => array_values($parsed), 'problems' => $problems];
    }

    /**
     * Teks jadi satu baris: HTML dibuang, spasi dirapikan. Pembacaan memakai
     * judul isian, bukan baris, karena email HTML sering tanpa pemisah baris.
     */
    protected static function flat(string $text): string
    {
        $text = html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>|<\/(p|div|tr|td|li)>/i', ' ', $text)));

        return trim(preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', $text)));
    }

    /**
     * Isian satu blok: tiap judul dicari di mana pun ia berada, dan nilainya
     * adalah teks sampai judul berikutnya.
     *
     * @return array<string, string>
     */
    protected static function fields(string $block): array
    {
        $found = [];

        foreach (static::LABELS as $field => $names) {
            preg_match_all('/(?<![\p{L}])(?:'.implode('|', $names).')\s*:/iu', $block, $m, PREG_OFFSET_CAPTURE);

            foreach ($m[0] as [$label, $offset]) {
                $found[] = ['field' => $field, 'start' => $offset, 'end' => $offset + strlen($label)];
            }
        }

        // Yang mulai lebih awal menang, supaya "Penerima" di dalam "Bank Penerima" tidak dihitung.
        usort($found, fn ($a, $b) => [$a['start'], $b['end']] <=> [$b['start'], $a['end']]);

        $kept = [];

        foreach ($found as $item) {
            $last = end($kept);

            if ($last && $item['start'] < $last['end']) {
                continue;
            }

            $kept[] = $item;
        }

        $values = [];

        foreach ($kept as $i => $item) {
            $until = $kept[$i + 1]['start'] ?? strlen($block);
            $values[$item['field']] ??= trim(substr($block, $item['end'], $until - $item['end']));
        }

        // Isian terakhir biasanya disusul kalimat penutup; statusnya satu kata.
        if (isset($values['status'])) {
            $values['status'] = preg_split('/\s+/', $values['status'])[0];
        }

        return $values;
    }

    /** @return array<string, mixed>|string baris terbaca, atau alasan gagal */
    protected static function block(string $block, array $companyNames): array|string
    {
        $fields = static::fields($block);
        $value = fn (string $field): ?string => ($fields[$field] ?? '') !== '' ? $fields[$field] : null;

        $reference = $value('reference');

        if (! $reference) {
            return 'Nomor referensi tidak terbaca.';
        }

        $status = mb_strtolower((string) $value('status'));

        if (! in_array($status, ['berhasil', 'success'], true)) {
            return "$reference: status bukan Berhasil, dilewati.";
        }

        $when = null;

        try {
            $when = Carbon::createFromFormat('d-m-Y H:i:s', (string) $value('datetime'));
        } catch (\Throwable) {
        }

        if (! $when) {
            return "$reference: tanggal dan jam tidak terbaca.";
        }

        $rawAmount = (string) $value('amount');
        $number = preg_replace('/[^0-9.,]/', '', $rawAmount);

        // Format BNI: "IDR 1,010,000.00" (koma ribuan, titik desimal).
        if ($number === '' || ! preg_match('/^\d{1,3}(,\d{3})*(\.\d+)?$|^\d+(\.\d+)?$/', $number)) {
            return "$reference: nominal tidak terbaca (\"$rawAmount\").";
        }

        $amount = (int) round((float) str_replace(',', '', $number));

        if ($amount <= 0) {
            return "$reference: nominal nol atau tidak valid.";
        }

        $remitter = static::name((string) $value('remitter'));
        $direction = 'in';

        foreach ($companyNames as $company) {
            if ($remitter !== '' && str_contains(mb_strtoupper($remitter), mb_strtoupper($company))) {
                $direction = 'out';
            }
        }

        return [
            'reference' => $reference,
            'occurred_at' => $when,
            'type' => $value('type'),
            'direction' => $direction,
            'amount' => $amount,
            'remitter' => $remitter ?: null,
            'beneficiary' => static::name((string) $value('beneficiary')) ?: null,
            'beneficiary_bank' => $value('bank'),
            'remark' => $value('remark'),
        ];
    }

    /** "*******788 - SERDAR BAGLAYAN" dan "*******882 PT KEBAP ..." -> nama saja. */
    protected static function name(string $raw): string
    {
        return trim(preg_replace('/^[\*xX•\d]+\s*-?\s*/u', '', $raw));
    }
}

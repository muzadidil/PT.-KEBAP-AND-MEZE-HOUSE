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
        $text = html_entity_decode(strip_tags($text));

        return (bool) preg_match('/(No\. Referensi BNI|BNI Reference Number)\s*:/i', $text);
    }

    /**
     * @param  array<int, string>  $companyNames  nama perusahaan (huruf besar), untuk menentukan arah
     * @return array{parsed: array<int, array<string, mixed>>, problems: array<int, string>}
     */
    public static function parse(string $text, array $companyNames = ['KEBAP AND MEZE HOUSE', 'KEBAP & MEZE HOUSE']): array
    {
        $text = html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>|<\/(p|div|tr)>/i', "\n", $text)));
        $text = str_replace(["\r\n", "\r", "\xC2\xA0"], ["\n", "\n", ' '], $text);

        $starts = [];
        preg_match_all('/^[ \t]*(?:No\. Referensi BNI|BNI Reference Number)[ \t]*:/mi', $text, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as [, $offset]) {
            $starts[] = $offset;
        }

        $parsed = [];
        $problems = [];

        foreach ($starts as $i => $start) {
            $block = substr($text, $start, ($starts[$i + 1] ?? strlen($text)) - $start);
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

    /** @return array<string, mixed>|string baris terbaca, atau alasan gagal */
    protected static function block(string $block, array $companyNames): array|string
    {
        $value = function (string $field) use ($block): ?string {
            $labels = implode('|', static::LABELS[$field]);

            return preg_match('/^[ \t]*(?:'.$labels.')[ \t]*:[ \t]*(.+?)[ \t]*$/mi', $block, $m) ? trim($m[1]) : null;
        };

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

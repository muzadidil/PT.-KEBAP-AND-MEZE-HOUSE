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
        'reference' => ['No\. Referensi BNI', 'BNI Reference Number', 'No\. Referensi', 'Reference No\.'],
        'datetime' => ['Tanggal\/Jam', 'Date\/Time'],
        'type' => ['Jenis Transaksi', 'Transaction Type'],
        'amount' => ['Nominal', 'Amount'],
        'remitter' => ['Pengirim', 'Dari Rekening', 'Remitter', 'From Account'],
        'beneficiary' => ['Penerima', 'Ke Rekening', 'Beneficiary', 'To Account'],
        'bank' => ['Bank Penerima', 'Beneficiary Bank'],
        'remark' => ['Keterangan Pembayaran', 'Keterangan', 'Transaction Remark', 'Remark'],
        'status' => ['Status'],
        // Hanya sebagai batas: tanpa ini nilai isian di sebelahnya ikut terbawa.
        'mode' => ['Jenis Transfer', 'Instruction Mode'],
        'npwp' => ['NPWP'],
    ];

    /** Notifikasi transaksi: ada tanggal/jam, jenis transaksi, dan nominal. */
    public static function looksLikeTransaction(string $text): bool
    {
        $flat = static::flat($text);

        return (bool) preg_match('/(Tanggal\/Jam|Date\/Time)\s*:/iu', $flat)
            && preg_match('/(Jenis Transaksi|Transaction Type)\s*:/iu', $flat)
            && preg_match('/(Nominal|Amount)\s*:/iu', $flat);
    }

    /** Email transaksi yang tidak memuat nominal: bentuk lain, belum dibaca. */
    public static function looksLikeOtherFormat(string $text): bool
    {
        $flat = static::flat($text);

        return ! static::looksLikeTransaction($text)
            && preg_match('/(Tanggal\/Jam|Date\/Time)\s*:/iu', $flat)
            && preg_match('/(Jenis Transaksi|Transaction Type)\s*:/iu', $flat);
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
        $withoutStatus = 0;

        // Satu transaksi dimulai di judul "Tanggal/Jam" (atau "Date/Time"). Letak
        // nomor referensi berbeda antar bentuk email: di depan tanggal, atau di
        // tengah isian. Karena itu segmennya dipotong di tanggal, dan nomor
        // referensi yang menempel tepat di depannya ikut dibawa.
        preg_match_all('/(?<![\p{L}])(?:Tanggal\/Jam|Date\/Time)\s*:/iu', $flat, $matches, PREG_OFFSET_CAPTURE);
        $starts = array_map(fn ($match) => $match[1], $matches[0]);

        foreach ($starts as $i => $start) {
            $segment = substr($flat, $start, ($starts[$i + 1] ?? strlen($flat)) - $start);
            $before = substr($flat, max(0, $start - 160), min(160, $start));
            $lead = preg_match('/(?:No\. Referensi BNI|BNI Reference Number)\s*:\s*(\S+)\s*$/iu', $before, $m) ? $m[1] : null;

            $row = static::block($segment, $companyNames, $lead);

            if ($row === null) {
                $withoutStatus++;

                continue;
            }

            if (is_string($row)) {
                $problems[] = $row;

                continue;
            }

            // Blok Inggris dari email yang sama: nomor referensi yang sama.
            $parsed[$row['reference']] ??= $row;
        }

        // Blok Inggris bentuk BI-FAST tidak memuat status; yang Indonesia sudah.
        // Hanya bila tak ada satu pun yang terbaca, itu dilaporkan.
        if ($withoutStatus > 0 && $parsed === [] && $problems === []) {
            $problems[] = 'Status transaksi tidak terbaca.';
        }

        if ($starts === []) {
            $problems[] = 'Tidak ada transaksi BNI di teks ini.';
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

    /** @return array<string, mixed>|string|null baris terbaca, alasan gagal, atau null bila segmen tanpa status */
    protected static function block(string $block, array $companyNames, ?string $lead = null): array|string|null
    {
        $fields = static::fields($block);
        $value = fn (string $field): ?string => ($fields[$field] ?? '') !== '' ? $fields[$field] : null;

        if ($value('status') === null) {
            return null;
        }

        $reference = $lead ?: $value('reference');

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
        return trim(preg_replace('/^[\*•]+\d*\s*-?\s*|^\d+\s*-\s*|^\d{3,}\s+/u', '', $raw));
    }
}

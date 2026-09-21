<?php

namespace App\Support\Zeytin\Workbook;

use Illuminate\Support\Carbon;

/**
 * Membaca isi sel berkas Excel yang ditulis manusia untuk dibaca manusia.
 *
 * Tiap fungsi di sini punya satu lawan yang sama: sel yang bentuknya tidak
 * seperti yang diharapkan tidak boleh diam-diam berubah jadi angka atau
 * tanggal yang keliru. Lebih baik kosong dan kelihatan daripada salah dan
 * tersembunyi.
 */
class Cells
{
    /** Judul kolom disamakan dulu: huruf kecil, spasi rangkap dirapatkan. */
    public static function norm(mixed $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) ($value ?? ''))));
    }

    /** "Rp 45.000", "45000", 45000.4 — semuanya jadi 45000. */
    public static function toNumber(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        $digits = preg_replace('/[^0-9.-]/', '', (string) $value);

        return is_numeric($digits) ? (int) round((float) $digits) : 0;
    }

    public static function toText(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    /**
     * Tanggal Excel bisa berupa serial angka atau teks.
     *
     * Teksnya ditulis tangan oleh staf dengan urutan hari dulu —
     * "31/08/2026". Pengurai bawaan menolak bentuk itu; ia mengharap bulan
     * dulu. Kalau ditolak, baris itu dianggap tak bertanggal lalu mewarisi
     * tanggal baris sebelumnya, sehingga belanja dua minggu terakhir
     * menumpuk di satu hari. Totalnya tetap benar, yang rusak rinciannya —
     * galat semacam itu yang paling lama tidak ketahuan.
     *
     * Urutan hari-dulu dipakai tanpa menebak: itu yang ada di berkas klien
     * dan lazim di Indonesia. "05/08/2026" berarti 5 Agustus, bukan 8 Mei.
     */
    public static function toDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        if (is_numeric($value)) {
            return static::fromExcelSerial((float) $value);
        }

        $text = trim((string) $value);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $iso)) {
            return static::make((int) $iso[1], (int) $iso[2], (int) $iso[3]);
        }

        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $text, $dmy)) {
            return static::make((int) $dmy[3], (int) $dmy[2], (int) $dmy[1]);
        }

        try {
            return Carbon::parse($text)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Serial Excel dihitung dari 30 Desember 1899 — pergeseran satu hari yang
     * terkenal itu memang bagian dari formatnya, bukan salah hitung di sini.
     */
    public static function fromExcelSerial(float $serial): ?Carbon
    {
        if ($serial <= 0) {
            return null;
        }

        return Carbon::create(1899, 12, 30)->startOfDay()->addDays((int) round($serial));
    }

    /**
     * Tanggal mustahil ditolak, bukan digeser ke bulan berikutnya seperti
     * yang dilakukan pengurai tanggal pada umumnya. "31/13/2026" adalah salah
     * ketik, dan menerjemahkannya jadi 31 Januari 2027 berarti memindahkan
     * satu baris belanja ke tahun yang salah tanpa memberi tanda apa pun.
     */
    protected static function make(int $year, int $month, int $day): ?Carbon
    {
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return Carbon::create($year, $month, $day)->startOfDay();
    }

    /**
     * Nomor identitas yang selalu sama untuk baris yang isinya sama.
     *
     * Tanpa ini tiap baris impor akan dapat nomor baru, jadi mengimpor berkas
     * yang sama dua kali menggandakan seluruh isinya — belanja sebulan
     * terhitung dua kali, dan tidak ada tanda apa pun bahwa itu terjadi.
     * Dengan nomor yang diturunkan dari isinya, impor kedua menimpa baris
     * yang sama, bukan menambah baris baru.
     *
     * Nomor urut di belakang menjaga baris kembar yang memang sah: dua kali
     * beli Aqua Galon di hari dan harga yang sama tetap dua baris, karena
     * urutannya dihitung, bukan ditebak dari isinya.
     *
     * @param  array<int, string>  $fields
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $seen  penghitung kemunculan, dibawa antar baris
     */
    public static function stableKey(array $fields, array $row, array &$seen): string
    {
        $parts = [];

        foreach ($fields as $field) {
            $value = $row[$field] ?? '';

            if ($value instanceof Carbon) {
                $value = $value->toDateString();
            }

            $parts[] = mb_strtolower(trim((string) $value));
        }

        $key = implode('|', $parts);

        if (str_replace('|', '', $key) === '') {
            return '';
        }

        $occurrence = $seen[$key] = ($seen[$key] ?? 0) + 1;

        // Bagian yang terbaca manusia memudahkan menelusuri satu baris lewat
        // basis data; ekor hash-nya yang menjamin tidak ada dua kunci berbeda
        // jatuh ke nomor yang sama setelah dipangkas.
        $readable = trim(preg_replace('/[^a-z0-9]+/', '-', $key), '-');
        $readable = mb_substr($readable, 0, 80);
        $tail = substr(md5($key), 0, 8);

        return $readable.'-'.$tail.($occurrence > 1 ? '-'.$occurrence : '');
    }
}

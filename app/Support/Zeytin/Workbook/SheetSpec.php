<?php

namespace App\Support\Zeytin\Workbook;

use App\Models\DailyIncome;
use App\Models\OutstandingBill;
use App\Models\Payroll;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierTransfer;

/**
 * Bentuk tiap sheet yang dikenali di berkas bulanan klien.
 *
 * `required` adalah judul kolom yang harus ada supaya sebuah baris dianggap
 * baris header. Sengaja dipilih yang paling khas, bukan semuanya, supaya
 * penambahan kolom oleh klien tidak langsung membuat impor gagal.
 *
 * `map` memasangkan kolom basis data dengan judul kolom di Excel. Judul
 * pertama tiap daftar adalah ejaan yang benar-benar ada di berkas klien —
 * termasuk salah ejanya, seperti "Basic Sallary" dan "Payment Methode".
 * Ejaan yang sudah dibetulkan ikut didaftar sebagai alias, jadi berkas lama
 * dan berkas baru sama-sama terbaca.
 */
class SheetSpec
{
    /**
     * @param  string  $sheet  nama sheet, harus sama persis
     * @param  class-string  $model
     * @param  array<int, string>  $required  judul kolom penanda baris header
     * @param  array<string, array<int, string>>  $map  kolom DB => judul di Excel
     * @param  string  $gate  kolom yang harus terisi supaya barisnya dipakai
     * @param  array<int, string>  $keyFrom  kolom pembentuk nomor baris
     * @param  string|null  $uniqueBy  kolom yang sudah unik sendiri, kalau ada
     * @param  bool  $inheritDate  tanggal kosong mewarisi baris di atasnya
     * @param  bool  $needsMonth  bulannya ditanyakan, tidak ada di sheet
     * @param  bool  $hasSections  ada baris penanda bagian di tengah data
     */
    public function __construct(
        public string $sheet,
        public string $model,
        public array $required,
        public array $map,
        public string $gate,
        public array $keyFrom = [],
        public ?string $uniqueBy = null,
        public bool $inheritDate = false,
        public bool $needsMonth = false,
        public bool $hasSections = false,
    ) {}

    /** @return array<int, self> */
    public static function all(): array
    {
        return [
            new self(
                sheet: 'Income',
                model: DailyIncome::class,
                required: ['date', 'total sales'],
                map: [
                    'date' => ['date'],
                    'petty_cash' => ['petty cash'],
                    'cash' => ['cash'],
                    'bni' => ['bni'],
                    'grab_food' => ['grab food'],
                    'go_food' => ['go food'],
                    'go_pay' => ['go pay'],
                ],
                gate: 'date',
                // Satu baris per tanggal, jadi tanggalnya sendiri yang jadi
                // nomor barisnya — tidak perlu nomor turunan.
                uniqueBy: 'date',
            ),

            new self(
                sheet: 'Expense',
                model: Purchase::class,
                required: ['date', 'items', 'total'],
                map: [
                    'date' => ['date'],
                    'vendor' => ['vendor'],
                    'item' => ['items', 'item'],
                    'qty' => ['qty'],
                    'unit' => ['unit'],
                    'price' => ['price'],
                    'disc' => ['disc'],
                    'tax' => ['tax'],
                ],
                gate: 'item',
                keyFrom: ['date', 'vendor', 'item', 'qty', 'price'],
                inheritDate: true,
            ),

            new self(
                sheet: 'Supplier Transfer Payment',
                model: SupplierTransfer::class,
                required: ['date', 'items', 'total expense'],
                map: [
                    'date' => ['date'],
                    'vendor' => ['vendor'],
                    'item' => ['items', 'item'],
                    'unit' => ['unit'],
                    'qty' => ['qty'],
                    'price' => ['price'],
                    'total' => ['total expense'],
                    'method' => ['payment methode', 'payment method'],
                    'status' => ['payment status'],
                ],
                gate: 'item',
                keyFrom: ['date', 'vendor', 'item', 'qty', 'price', 'total'],
                inheritDate: true,
            ),

            new self(
                sheet: 'Outstanding INV',
                model: OutstandingBill::class,
                required: ['vendor', 'items', 'total'],
                map: [
                    'date' => ['date order'],
                    'due_date' => ['due time', 'deliv date'],
                    'vendor' => ['vendor'],
                    'item' => ['items', 'item'],
                    'unit' => ['unit'],
                    'qty' => ['qty'],
                    'price' => ['price'],
                    'disc' => ['disc'],
                    'tax' => ['tax'],
                    'status' => ['status'],
                ],
                gate: 'item',
                keyFrom: ['date', 'vendor', 'item', 'qty', 'price'],
                inheritDate: true,
            ),

            new self(
                sheet: 'Supplier Database',
                model: Supplier::class,
                required: ["supplier's name", 'contact person'],
                map: [
                    'name' => ["supplier's name", 'supplier name'],
                    'contact_person' => ['contact person'],
                    'supplies' => ['items', 'item'],
                    'last_price' => ['price'],
                    'bank' => ['bank'],
                    'bank_account' => ['bank account'],
                    'account_name' => ['a/n'],
                    'payment_method' => ['payment methode', 'payment method'],
                ],
                gate: 'name',
                keyFrom: ['name', 'bank_account'],
            ),

            new self(
                sheet: 'Payroll',
                model: Payroll::class,
                required: ['name', 'basic sallary'],
                map: [
                    'name' => ['name'],
                    'basic' => ['basic sallary', 'basic salary'],
                    'bpjs' => ['deduction bpjs'],
                    'grand_total' => ['grand total sallary', 'grand total salary'],
                ],
                gate: 'name',
                keyFrom: ['month', 'name'],
                // Bulannya tidak bisa dibaca dari sheet: judulnya berupa
                // kalimat bebas ("Work Sheet Summary 1st - 31th September
                // 2025") yang tidak dijamin bentuknya. Ditanyakan ke
                // pengguna, bukan ditebak.
                needsMonth: true,
                hasSections: true,
            ),
        ];
    }

    /** Kolom tanggal yang dipakai menyaring rentang, kalau sheet-nya bertanggal. */
    public function dateColumn(): ?string
    {
        if ($this->needsMonth) {
            return 'month';
        }

        return isset($this->map['date']) ? 'date' : null;
    }

    /**
     * Judul kolom satu sheet, diturunkan dari map yang sama dipakai pembacanya.
     *
     * Diturunkan, bukan diketik ulang: template yang judulnya beda sedikit
     * saja dari yang dicari pembaca akan ditolak saat diunggah — persis
     * masalah yang hendak dihilangkan template itu.
     *
     * Kolom di `required` ikut ditulis walau tidak dipetakan: Income butuh
     * "Total Sales" dan Expense butuh "Total" untuk mengenali baris
     * headernya, padahal angkanya dihitung ulang oleh aplikasi, bukan dibaca.
     *
     * @return array<int, string>
     */
    public function templateHeader(): array
    {
        $titles = array_map(fn (array $aliases) => $aliases[0], array_values($this->map));
        $missing = array_values(array_diff($this->required, $titles));

        return array_map(
            fn (string $label) => preg_replace_callback('/\b[a-z]/', fn ($m) => strtoupper($m[0]), $label),
            [...$titles, ...$missing],
        );
    }
}

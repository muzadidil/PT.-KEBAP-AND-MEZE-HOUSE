<?php

namespace App\Models;

use App\Models\Concerns\BookkeepingRecord;
use App\Support\Zeytin\RecordSource;
use Illuminate\Database\Eloquent\Model;

/**
 * Gaji per orang per bulan — sheet Payroll.
 *
 * Bulannya disimpan sebagai tanggal 1, bukan teks "September 2026", supaya
 * bisa diurutkan dan disaring seperti tanggal biasa.
 */
class Payroll extends Model
{
    use BookkeepingRecord;

    protected $guarded = ['id'];

    public static function dateColumn(): string
    {
        return 'month';
    }

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'basic' => 'integer',
            'bpjs' => 'integer',
            'grand_total' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        /*
         * Sama seperti SupplierTransfer: yang diketik orang dihitung
         * (pokok − BPJS), yang dibawa berkas Excel dipakai apa adanya. Sheet
         * Payroll klien punya kolom "Grand Total Sallary" yang sudah memuat
         * potongan dan tambahan lain yang tidak semuanya berkolom sendiri.
         */
        static::saving(function (self $payroll) {
            if ($payroll->source !== RecordSource::IMPORT) {
                $payroll->grand_total = (int) $payroll->basic - (int) $payroll->bpjs;
            }
        });
    }
}

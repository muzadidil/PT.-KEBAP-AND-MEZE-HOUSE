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
            'paid_on' => 'date:Y-m-d',
        ];
    }

    public const VIA_BANK = 'bank';

    public const VIA_CASH = 'cash';

    /**
     * Gaji yang sudah dibayar lewat $via, dijumlah per tanggal bayar
     * (Y-m-d => rupiah). Yang belum diberi tanggal bayar tidak ikut.
     *
     * @return array<string, int>
     */
    public static function paidByDate(string $via, \Illuminate\Support\Carbon $from, \Illuminate\Support\Carbon $to): array
    {
        $out = [];

        static::query()
            ->where('paid_via', $via)
            ->whereNotNull('paid_on')
            ->whereDate('paid_on', '>=', $from->toDateString())
            ->whereDate('paid_on', '<=', $to->toDateString())
            ->get(['paid_on', 'grand_total'])
            ->each(function (self $row) use (&$out) {
                $key = $row->paid_on->toDateString();
                $out[$key] = ($out[$key] ?? 0) + (int) $row->grand_total;
            });

        return $out;
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

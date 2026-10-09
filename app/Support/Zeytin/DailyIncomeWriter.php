<?php

namespace App\Support\Zeytin;

use App\Models\DailyIncome;
use App\Support\Excel\ImportReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan hasil impor penjualan harian ke Pemasukan Harian.
 *
 * Dipakai bersama oleh impor kasir dan impor Telegram supaya aturannya sama:
 * tanggal yang SUDAH punya baris dilewati dan dilaporkan, tidak pernah ditimpa
 * (juga bukan yang diketik tangan), jadi mengimpor yang sama dua kali tidak
 * menggandakan apa pun.
 */
class DailyIncomeWriter
{
    /**
     * @param  array<string, array<string, int>>  $days  tanggal (Y-m-d) => [kunci channel => rupiah]
     */
    public static function save(array $days, ?string $extraNote = null): ImportReport
    {
        if (! $days) {
            return new ImportReport;
        }

        ksort($days);

        $created = 0;
        $skipped = [];

        DB::transaction(function () use ($days, &$created, &$skipped) {
            // whereDate, bukan whereIn: tidak bergantung pada apakah kolom
            // menyimpan "2026-09-30" atau "2026-09-30 00:00:00".
            $existing = DailyIncome::query()
                ->whereDate('date', '>=', array_key_first($days))
                ->whereDate('date', '<=', array_key_last($days))
                ->pluck('date')
                ->map(fn ($date) => Carbon::parse($date)->toDateString())
                ->all();

            foreach ($days as $date => $channels) {
                if (in_array($date, $existing, true)) {
                    $skipped[] = Carbon::parse($date)->translatedFormat('j M Y');

                    continue;
                }

                DailyIncome::create([
                    'date' => $date,
                    ...$channels,
                    'source' => RecordSource::MANUAL,
                ]);

                $created++;
            }
        });

        $first = Carbon::parse(array_key_first($days))->translatedFormat('j M Y');
        $last = Carbon::parse(array_key_last($days))->translatedFormat('j M Y');

        $note = __('excel.result.range', ['range' => $first === $last ? $first : $first.' – '.$last]);

        if ($skipped) {
            $note .= ' · '.__('excel.pos.skipped_note', ['dates' => implode(', ', $skipped)]);
        }

        if ($extraNote) {
            $note .= ' · '.$extraNote;
        }

        return new ImportReport(['created' => $created, 'skipped' => count($skipped)], [], $note);
    }
}

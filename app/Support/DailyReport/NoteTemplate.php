<?php

namespace App\Support\DailyReport;

use App\Models\DailyNote;
use Illuminate\Support\Carbon;

/**
 * Bagian standar Daily Report — Sales, Operation, Staff, Google Reviews,
 * Task/Work Update, Important Notes, Plan/Follow-up — meniru contoh laporan
 * tim persis. Sekali klik untuk mengisi tanggal yang masih kosong; sesudah
 * itu bagiannya bebas diubah, dihapus, atau ditambah sendiri — bukan bentuk
 * yang dipaksakan tiap hari.
 */
class NoteTemplate
{
    /** @var array<int, array{key: string, children: array<int, string>}> */
    public const SECTIONS = [
        ['key' => 'sales', 'children' => ['sales.total_sales', 'sales.total_guest']],
        ['key' => 'operation', 'children' => ['operation.overall', 'operation.main_issue']],
        ['key' => 'staff', 'children' => ['staff.attendance', 'staff.staff_issue']],
        ['key' => 'reviews', 'children' => [
            'reviews.rating', 'reviews.total_reviews', 'reviews.new_reviews',
            'reviews.replied', 'reviews.negative_reviews', 'reviews.follow_up',
        ]],
        ['key' => 'task', 'children' => []],
        ['key' => 'notes', 'children' => []],
        ['key' => 'plan', 'children' => []],
    ];

    /** Tidak melakukan apa-apa kalau tanggal ini sudah punya bagian utama. */
    public static function apply(Carbon $date): void
    {
        if (DailyNote::whereDate('date', $date)->whereNull('parent_id')->exists()) {
            return;
        }

        foreach (static::SECTIONS as $section) {
            $master = DailyNote::create([
                'date' => $date->toDateString(),
                'text' => __('daily_report.template.'.$section['key']),
            ]);

            foreach ($section['children'] as $childKey) {
                DailyNote::create([
                    'date' => $date->toDateString(),
                    'parent_id' => $master->id,
                    'text' => __('daily_report.template.'.$childKey),
                ]);
            }
        }
    }
}

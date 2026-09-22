<?php

namespace App\Support\DailyReport;

use App\Models\DailyNote;
use Illuminate\Support\Carbon;

/**
 * Bagian standar Daily Report, meniru contoh laporan tim persis:
 *
 *   1. Operation           satu pilihan kondisi (Good / Need Attention / Problem)
 *   2. Staff Issue         catatan bebas
 *   3. Google Reviews      angka saja; Rating ditulis dengan bintang
 *   4. Task/Work Update    poin dengan status (Pending / Process / Finish)
 *   5. Important Notes     catatan bebas
 *   6. Plan/Follow Up      catatan bebas
 *
 * Sekali klik untuk mengisi tanggal yang masih kosong; sesudah itu bagiannya
 * bebas diubah, dihapus, atau ditambah sendiri — bukan bentuk yang
 * dipaksakan tiap hari.
 */
class NoteTemplate
{
    /**
     * Anak berupa [kunci => jenis].
     *
     * @var array<int, array{key: string, kind: string, icon: string, children: array<string, string>}>
     */
    public const SECTIONS = [
        ['key' => 'operation', 'kind' => 'choice', 'icon' => '⚙️', 'children' => []],
        ['key' => 'staff_issue', 'kind' => 'text', 'icon' => '👥', 'children' => []],
        ['key' => 'reviews', 'kind' => 'number', 'icon' => '⭐', 'children' => [
            'reviews_rating' => 'rating',
            'reviews_total_reviews' => 'number',
            'reviews_new_reviews' => 'number',
            'reviews_replied' => 'number',
            'reviews_negative_reviews' => 'number',
            'reviews_follow_up' => 'number',
        ]],
        ['key' => 'task', 'kind' => 'status', 'icon' => '📋', 'children' => []],
        ['key' => 'notes', 'kind' => 'text', 'icon' => '📌', 'children' => []],
        ['key' => 'plan', 'kind' => 'text', 'icon' => '🗓️', 'children' => []],
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
                'kind' => $section['kind'],
                'icon' => $section['icon'],
            ]);

            foreach ($section['children'] as $childKey => $kind) {
                DailyNote::create([
                    'date' => $date->toDateString(),
                    'parent_id' => $master->id,
                    'text' => __('daily_report.template.'.$childKey),
                    'kind' => $kind,
                ]);
            }
        }
    }
}

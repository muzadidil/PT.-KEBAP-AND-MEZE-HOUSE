<?php

namespace App\Support\DailyReport;

use App\Models\DailyNote;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Satu Daily Report sebagai pesan WhatsApp, meniru contoh laporan tim
 * persis: judul dan tanggal tebal, bagian utama (Sales, Operation, …)
 * tebal dan bernomor, sub-catatannya poin biasa dengan nominal opsional
 * di ujungnya.
 *
 * Tulisan tebal/miring/coret yang diketik sendiri di dalam catatan (mis.
 * "~sudah batal~") tidak disentuh — teksnya dikirim apa adanya, jadi format
 * WhatsApp manual tetap jalan di atas yang otomatis.
 */
class DailyReportText
{
    public function __construct(protected NoteBoard $board, protected Carbon $date) {}

    public static function make(Carbon $date): static
    {
        return new static(NoteBoard::load($date), $date);
    }

    public function whatsapp(): string
    {
        $lines = [
            '*'.mb_strtoupper(__('daily_report.share.title', ['name' => config('zeytin.letterhead.name')])).'*',
            '📅 *'.__('daily_report.share.date_label').':* '.$this->date->copy()->locale(app()->getLocale())->translatedFormat('j M Y'),
        ];

        $top = $this->board->topLevel();

        if ($top->isEmpty()) {
            $lines[] = '';
            $lines[] = '_'.__('daily_report.empty_day').'_';

            return implode("\n", $lines);
        }

        foreach ($top as $i => $note) {
            $lines[] = '';
            $lines[] = '*'.($i + 1).'. '.mb_strtoupper($note->text).'*'.$this->suffix($note);
            $this->children($note, 0, $lines);
        }

        return implode("\n", $lines);
    }

    /** Tautan yang membuka WhatsApp dengan pesan ini sudah terisi. */
    public function whatsappUrl(): string
    {
        return 'https://wa.me/?text='.rawurlencode($this->whatsapp());
    }

    protected function children(DailyNote $note, int $depth, array &$lines): void
    {
        foreach ($this->board->children($note) as $child) {
            $bullet = $depth === 0 ? '•' : '◦';
            $lines[] = str_repeat('   ', $depth).$bullet.' '.$child->text.$this->suffix($child);
            $this->children($child, $depth + 1, $lines);
        }
    }

    protected function suffix(DailyNote $note): string
    {
        return $note->nominal !== null ? ' '.Money::format($note->nominal) : '';
    }
}

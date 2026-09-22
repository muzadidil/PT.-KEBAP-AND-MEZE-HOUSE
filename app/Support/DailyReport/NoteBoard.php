<?php

namespace App\Support\DailyReport;

use App\Models\DailyNote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Seluruh catatan satu tanggal, disusun jadi pohon dari satu kueri —
 * sama seperti TaskBoard di Progres Rapat, tapi tanpa hitungan progres:
 * catatan tidak dicentang selesai/belum.
 */
class NoteBoard
{
    /** @var Collection<int|string, Collection<int, DailyNote>> */
    protected Collection $byParent;

    /** @param  Collection<int, DailyNote>  $notes  seluruh catatan satu tanggal */
    public function __construct(protected Collection $notes)
    {
        $this->byParent = $notes->groupBy(fn (DailyNote $note) => $note->parent_id ?? 'root');
    }

    public static function load(Carbon $date): static
    {
        return new static(DailyNote::query()->whereDate('date', $date)->orderBy('id')->get());
    }

    /** @return Collection<int, DailyNote> */
    public function topLevel(): Collection
    {
        return $this->byParent->get('root', collect());
    }

    /** @return Collection<int, DailyNote> */
    public function children(DailyNote $note): Collection
    {
        return $this->byParent->get($note->id, collect());
    }
}

<?php

namespace App\Support\Meetings;

use App\Models\MeetingTask;
use Illuminate\Support\Collection;

/**
 * Seluruh tugas rapat dalam satu muatan, disusun jadi pohon.
 *
 * Sub-tugas bisa bertingkat tanpa batas, jadi progres dihitung dengan
 * menelusuri pohonnya — dari satu kueri, bukan satu kueri per tingkat.
 * Rumusnya sama dengan TaskFlow:
 *
 *   progres tugas   = selesai ? 100 : sub-tugas selesai / seluruh sub-tugas
 *   progres rata-rata = rata-rata progres tugas utama
 */
class TaskBoard
{
    /** @var Collection<int|string, Collection<int, MeetingTask>> */
    protected Collection $byParent;

    /** @param  Collection<int, MeetingTask>  $tasks  seluruh tugas dan sub-tugas */
    public function __construct(protected Collection $tasks)
    {
        $this->byParent = $tasks->groupBy(fn (MeetingTask $task) => $task->parent_id ?? 'root');
    }

    public static function load(): static
    {
        return new static(MeetingTask::query()->with('project')->orderBy('id')->get());
    }

    /** @return Collection<int, MeetingTask> */
    public function topLevel(): Collection
    {
        return $this->byParent->get('root', collect());
    }

    /** @return Collection<int, MeetingTask> */
    public function children(MeetingTask $task): Collection
    {
        return $this->byParent->get($task->id, collect());
    }

    /** @return array{total: int, done: int} seluruh sub-tugas di bawahnya, di level mana pun */
    public function count(MeetingTask $task): array
    {
        $total = 0;
        $done = 0;

        foreach ($this->children($task) as $child) {
            $total++;
            $done += $child->completed ? 1 : 0;

            $below = $this->count($child);
            $total += $below['total'];
            $done += $below['done'];
        }

        return ['total' => $total, 'done' => $done];
    }

    public function progress(MeetingTask $task): int
    {
        if ($task->completed) {
            return 100;
        }

        ['total' => $total, 'done' => $done] = $this->count($task);

        return $total === 0 ? 0 : (int) round($done / $total * 100);
    }

    /** @param  Collection<int, MeetingTask>  $tasks */
    public function mean(Collection $tasks): int
    {
        return $tasks->isEmpty()
            ? 0
            : (int) round($tasks->sum(fn (MeetingTask $task) => $this->progress($task)) / $tasks->count());
    }
}

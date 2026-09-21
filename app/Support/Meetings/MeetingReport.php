<?php

namespace App\Support\Meetings;

use App\Models\MeetingProject;
use App\Models\MeetingTask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Daftar tugas rapat sebagai teks biasa dan sebagai pesan WhatsApp, untuk
 * dibagikan ke grup setelah rapat.
 *
 * Bentuknya meniru TaskFlow persis: judul, tanggal, progres, lalu BELUM
 * SELESAI dan SELESAI per proyek, dengan sub-tugas menjorok. Format WhatsApp
 * memakai *tebal*, _miring_, dan ~coret~ — tugas yang selesai tercoret.
 * Tugas yang sudah diarsipkan tidak ikut.
 */
class MeetingReport
{
    /** @param  Collection<int, MeetingProject>  $projects */
    public function __construct(protected TaskBoard $board, protected Collection $projects) {}

    public static function make(): static
    {
        return new static(TaskBoard::load(), MeetingProject::query()->orderBy('id')->get());
    }

    /**
     * Tugas utama yang belum diarsipkan untuk satu cakupan.
     *
     * @param  string  $target  all | none | id proyek
     * @return Collection<int, MeetingTask>
     */
    public function tasks(string $target): Collection
    {
        $live = $this->board->topLevel()->reject(fn (MeetingTask $task) => $task->archived);

        $scoped = match ($target) {
            'all' => $live,
            'none' => $live->whereNull('project_id'),
            default => $live->where('project_id', (int) $target),
        };

        return $scoped->values();
    }

    public function title(string $target): string
    {
        return match ($target) {
            'all' => __('meeting.all_projects'),
            'none' => __('meeting.no_project'),
            default => $this->projects->firstWhere('id', (int) $target)?->name ?? __('meeting.no_project'),
        };
    }

    public function progress(string $target): int
    {
        return $this->board->mean($this->tasks($target));
    }

    /**
     * Kelompok per proyek untuk cakupan "semua": proyek yang punya tugas,
     * lalu tugas tanpa proyek di akhir.
     *
     * @return array<int, array{name: string, tasks: Collection<int, MeetingTask>}>
     */
    public function groups(string $target): array
    {
        $tasks = $this->tasks($target);

        if ($target !== 'all') {
            return [['name' => $this->title($target), 'tasks' => $tasks]];
        }

        $groups = $this->projects
            ->map(fn (MeetingProject $project) => ['name' => $project->name, 'tasks' => $tasks->where('project_id', $project->id)->values()])
            ->filter(fn (array $group) => $group['tasks']->isNotEmpty())
            ->values()
            ->all();

        $orphans = $tasks->whereNull('project_id')->values();

        if ($orphans->isNotEmpty()) {
            $groups[] = ['name' => __('meeting.no_project'), 'tasks' => $orphans];
        }

        return $groups;
    }

    /* ---------------------------------------------------------- teks biasa */

    public function text(string $target): string
    {
        $tasks = $this->tasks($target);

        $lines = [
            __('meeting.share.text_title', ['name' => $this->title($target)]),
            $this->today(),
            __('meeting.share.overall', ['percent' => $this->board->mean($tasks)]),
            '',
        ];

        if ($tasks->isEmpty()) {
            $lines[] = __('meeting.share.empty');

            return implode("\n", $lines);
        }

        foreach ($this->groups($target) as $i => $group) {
            if ($target === 'all') {
                if ($i > 0) {
                    $lines[] = '';
                }

                $lines[] = '===== '.$group['name'].' ('.$this->board->mean($group['tasks']).'%) =====';
            }

            $this->textGroup($group['tasks'], $lines);
        }

        return implode("\n", $lines);
    }

    /** @param  Collection<int, MeetingTask>  $tasks */
    protected function textGroup(Collection $tasks, array &$lines): void
    {
        foreach ([false => 'active', true => 'done'] as $completed => $heading) {
            $list = $tasks->where('completed', (bool) $completed)->values();

            $lines[] = __('meeting.share.'.$heading).' ('.$list->count().')';

            if ($list->isEmpty()) {
                $lines[] = '  '.__('meeting.share.none');
            }

            foreach ($list as $task) {
                $meta = implode(', ', array_filter([
                    __('meeting.category.'.$task->category),
                    __('meeting.share.priority', ['priority' => __('meeting.priority.'.$task->priority)]),
                    $task->deadline ? __('meeting.share.deadline', ['date' => $this->date($task->deadline)]) : null,
                ]));

                $lines[] = '['.($task->completed ? 'x' : ' ').'] '.$task->text.($meta ? ' ('.$meta.')' : '');
                $this->textChildren($task, 0, $lines);
            }

            if (! $completed) {
                $lines[] = '';
            }
        }
    }

    protected function textChildren(MeetingTask $task, int $depth, array &$lines): void
    {
        foreach ($this->board->children($task) as $child) {
            $lines[] = str_repeat('  ', $depth + 1).($child->completed ? '[x] ' : '[ ] ').$child->text;
            $this->textChildren($child, $depth + 1, $lines);
        }
    }

    /* ------------------------------------------------------------ WhatsApp */

    public function whatsapp(string $target): string
    {
        $tasks = $this->tasks($target);

        $lines = [
            '*'.mb_strtoupper(__('meeting.share.wa_title', ['name' => $this->title($target)])).'*',
            '_'.$this->today().' • '.__('meeting.share.progress', ['percent' => $this->board->mean($tasks)]).'_',
            '',
        ];

        if ($tasks->isEmpty()) {
            $lines[] = '_'.__('meeting.share.empty').'_';

            return implode("\n", $lines);
        }

        foreach ($this->groups($target) as $i => $group) {
            if ($target === 'all') {
                if ($i > 0) {
                    $lines[] = '';
                }

                $lines[] = '*'.mb_strtoupper($group['name']).' — '.$this->board->mean($group['tasks']).'%*';
            }

            $this->waGroup($group['tasks'], $lines);
        }

        return implode("\n", $lines);
    }

    /** @param  Collection<int, MeetingTask>  $tasks */
    protected function waGroup(Collection $tasks, array &$lines): void
    {
        foreach ([false => 'active', true => 'done'] as $completed => $heading) {
            $list = $tasks->where('completed', (bool) $completed)->values();

            $lines[] = '*'.__('meeting.share.'.$heading).' ('.$list->count().')*';

            if ($list->isEmpty()) {
                $lines[] = '_'.__('meeting.share.none').'_';
            }

            foreach ($list as $i => $task) {
                $meta = implode(' • ', array_filter([
                    __('meeting.category.'.$task->category),
                    __('meeting.priority.'.$task->priority),
                    $task->deadline ? __('meeting.share.due', ['date' => $this->date($task->deadline)]) : null,
                ]));

                $text = $task->completed ? '~'.$task->text.'~' : $task->text;

                $lines[] = ($i + 1).'. '.$text.($meta ? ' _('.$meta.')_' : '');
                $this->waChildren($task, 0, $lines);
            }

            if (! $completed) {
                $lines[] = '';
            }
        }
    }

    protected function waChildren(MeetingTask $task, int $depth, array &$lines): void
    {
        foreach ($this->board->children($task) as $child) {
            $bullet = $depth === 0 ? '◦' : '-';
            $text = $child->completed ? '~'.$child->text.'~' : $child->text;

            $lines[] = str_repeat('   ', $depth + 1).'_'.$bullet.' '.$text.'_';
            $this->waChildren($child, $depth + 1, $lines);
        }
    }

    /** Tautan yang membuka WhatsApp dengan pesan ini sudah terisi. */
    public function whatsappUrl(string $target): string
    {
        return 'https://wa.me/?text='.rawurlencode($this->whatsapp($target));
    }

    /* ------------------------------------------------------------ pembantu */

    public function board(): TaskBoard
    {
        return $this->board;
    }

    protected function today(): string
    {
        return Carbon::today()->locale(app()->getLocale())->translatedFormat('j F Y');
    }

    protected function date(Carbon $date): string
    {
        return $date->copy()->locale(app()->getLocale())->translatedFormat('d M Y');
    }
}

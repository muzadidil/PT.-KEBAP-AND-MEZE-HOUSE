<?php

namespace App\Support\Meetings;

use App\Models\MeetingTask;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Laporan PDF Progres Rapat — meniru export PDF TaskFlow: progres
 * keseluruhan, ringkasan Total/Selesai/Proses/Belum Mulai, lalu tabel tugas
 * per proyek beserta sub-tugasnya.
 */
class MeetingPdf
{
    public function __construct(protected MeetingReport $report, protected string $scope = 'all') {}

    /** @return array{total: int, done: int, in_progress: int, not_started: int} */
    public function summary(): array
    {
        $tasks = $this->report->tasks($this->scope);
        $board = $this->report->board();

        $done = $tasks->where('completed', true)->count();
        $inProgress = $tasks->where('completed', false)->filter(fn (MeetingTask $t) => $board->progress($t) > 0)->count();

        return [
            'total' => $tasks->count(),
            'done' => $done,
            'in_progress' => $inProgress,
            'not_started' => $tasks->count() - $done - $inProgress,
        ];
    }

    /**
     * Baris tabel satu kelompok: tugas utama, lalu sub-tugasnya menjorok.
     *
     * @param  Collection<int, MeetingTask>  $tasks
     * @return array<int, array<string, mixed>>
     */
    public function rows(Collection $tasks): array
    {
        $board = $this->report->board();
        $rows = [];

        foreach ($tasks as $task) {
            $progress = $board->progress($task);

            $rows[] = [
                'depth' => 0,
                'text' => $task->text,
                'category' => __('meeting.category.'.$task->category),
                'priority' => __('meeting.priority.'.$task->priority),
                'deadline' => $task->deadline?->translatedFormat('d M Y'),
                'status' => $task->completed ? 'done' : ($progress > 0 ? 'in_progress' : 'not_started'),
                'progress' => $progress,
            ];

            $this->childRows($task, 1, $rows);
        }

        return $rows;
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    protected function childRows(MeetingTask $task, int $depth, array &$rows): void
    {
        foreach ($this->report->board()->children($task) as $child) {
            $rows[] = [
                'depth' => $depth,
                'text' => $child->text,
                'category' => null,
                'priority' => null,
                'deadline' => $child->deadline?->translatedFormat('d M Y'),
                // Sub-tugas hanya selesai atau belum; tidak ada "proses".
                'status' => $child->completed ? 'done' : 'pending',
                'progress' => null,
            ];

            $this->childRows($child, $depth + 1, $rows);
        }
    }

    public function render(): string
    {
        return Pdf::loadView('pdf.meeting', [
            'pdf' => $this,
            'report' => $this->report,
            'scope' => $this->scope,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    public function filename(): string
    {
        return 'progres-rapat-'.Str::slug($this->report->title($this->scope)).'-'.now()->toDateString().'.pdf';
    }
}

<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Concerns\ForBackoffice;
use App\Models\MeetingProject;
use App\Models\MeetingTask;
use App\Support\Meetings\MeetingReport;
use App\Support\Meetings\TaskBoard;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * Progres Rapat — meniru aplikasi TaskFlow (repo TODO_muzadidil).
 *
 * Tugas hasil rapat per proyek, dengan sub-tugas bertingkat tanpa batas dan
 * progres yang dihitung dari sub-tugas yang selesai. Daftarnya bisa disalin
 * sebagai teks atau pesan WhatsApp, langsung dikirim ke WhatsApp, atau
 * dicetak sebagai PDF — untuk dibagikan ke grup setelah rapat.
 *
 * Dipakai Super Admin dan Admin. Hitungannya di App\Support\Meetings.
 */
class MeetingProgress extends Page
{
    use ForBackoffice;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = -1;

    protected string $view = 'filament.admin.pages.meeting-progress';

    /** all | none | id proyek */
    #[Url]
    public string $project = 'all';

    /** all | today | active | completed | overdue | archived */
    #[Url]
    public string $filter = 'all';

    public string $category = 'all';

    public string $priority = 'all';

    public string $search = '';

    public string $sort = 'newest';

    /** @var array<int, int> tugas yang panel sub-tugasnya terbuka */
    public array $expanded = [];

    /** @var array<string, mixed> isian tugas baru */
    public array $draft = [];

    /** @var array<int, string> isian sub-tugas baru, per induknya */
    public array $subDraft = [];

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $edit = [];

    public string $newProject = '';

    public ?int $renamingProject = null;

    public string $renameValue = '';

    public string $importText = '';

    public static function getNavigationLabel(): string
    {
        return __('meeting.nav');
    }

    public function getTitle(): string
    {
        return __('meeting.nav');
    }

    public function getSubheading(): ?string
    {
        return __('meeting.subtitle');
    }

    public function mount(): void
    {
        MeetingTask::archiveFinished();

        $this->resetDraft();
    }

    /* ------------------------------------------------------------- data */

    #[Computed]
    public function board(): TaskBoard
    {
        return TaskBoard::load();
    }

    /** @return Collection<int, MeetingProject> */
    #[Computed]
    public function projects(): Collection
    {
        return MeetingProject::query()->orderBy('id')->get();
    }

    #[Computed]
    public function report(): MeetingReport
    {
        return new MeetingReport($this->board, $this->projects);
    }

    /**
     * Tugas utama di proyek yang dipilih; arsip dipisah, seperti TaskFlow.
     *
     * @return Collection<int, MeetingTask>
     */
    protected function scoped(bool $archived = false): Collection
    {
        $tasks = $this->board->topLevel()->where('archived', $archived);

        return match ($this->project) {
            'all' => $tasks,
            'none' => $tasks->whereNull('project_id'),
            default => $tasks->where('project_id', (int) $this->project),
        };
    }

    /** @return Collection<int, MeetingTask> yang tampil setelah seluruh saringan */
    #[Computed]
    public function tasks(): Collection
    {
        $list = $this->scoped($this->filter === 'archived');
        $today = Carbon::today();

        $list = match ($this->filter) {
            'today' => $list->filter(fn (MeetingTask $t) => $t->created_at?->isSameDay($today)),
            'active' => $list->where('completed', false),
            'completed' => $list->where('completed', true),
            'overdue' => $list->filter(fn (MeetingTask $t) => $t->isOverdue()),
            default => $list,
        };

        if ($this->category !== 'all') {
            $list = $list->where('category', $this->category);
        }

        if ($this->priority !== 'all') {
            $list = $list->where('priority', $this->priority);
        }

        if (($needle = mb_strtolower(trim($this->search))) !== '') {
            $list = $list->filter(fn (MeetingTask $t) => str_contains(mb_strtolower($t->text), $needle));
        }

        $order = array_flip(MeetingTask::PRIORITIES);

        $sorted = match ($this->sort) {
            'oldest' => $list->sortBy('id'),
            'priority' => $list->sortBy(fn (MeetingTask $t) => $order[$t->priority] ?? 9),
            'due' => $list->sortBy(fn (MeetingTask $t) => $t->deadline?->toDateString() ?? '9999'),
            default => $list->sortByDesc('id'),
        };

        return $sorted->values();
    }

    /** @return array{total: int, active: int, done: int, progress: int} */
    #[Computed]
    public function stats(): array
    {
        $tasks = $this->scoped();

        return [
            'total' => $tasks->count(),
            'active' => $tasks->where('completed', false)->count(),
            'done' => $tasks->where('completed', true)->count(),
            'progress' => $this->board->mean($tasks),
        ];
    }

    public function projectProgress(?int $projectId): int
    {
        return $this->board->mean(
            $this->board->topLevel()->where('archived', false)->where('project_id', $projectId),
        );
    }

    /** Cakupan salin/kirim/PDF: proyek yang sedang dipilih. */
    public function shareScope(): string
    {
        return $this->project;
    }

    public function pdfUrl(): string
    {
        return route('filament.admin.pdf.meeting', ['scope' => $this->shareScope()]);
    }

    /* ---------------------------------------------------------- saringan */

    public function setProject(string $project): void
    {
        $this->project = $project;
        $this->draft['project_id'] = is_numeric($project) ? $project : '';
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'today', 'active', 'completed', 'overdue', 'archived'], true) ? $filter : 'all';
    }

    public function toggleExpand(int $id): void
    {
        $this->expanded = in_array($id, $this->expanded, true)
            ? array_values(array_diff($this->expanded, [$id]))
            : [...$this->expanded, $id];
    }

    /* ------------------------------------------------------------- tugas */

    public function addTask(): void
    {
        $data = $this->validate($this->taskRules('draft'))['draft'];

        MeetingTask::create([
            'text' => trim($data['text']),
            'project_id' => $data['project_id'] ?: null,
            'category' => $data['category'],
            'priority' => $data['priority'],
            'deadline' => $data['deadline'] ?: null,
            'link' => $data['link'] ?: null,
        ]);

        $this->resetDraft();
    }

    public function addSubtask(int $parentId): void
    {
        $text = trim((string) ($this->subDraft[$parentId] ?? ''));

        if ($text === '') {
            return;
        }

        $parent = MeetingTask::findOrFail($parentId);

        MeetingTask::create(['parent_id' => $parent->id, 'text' => mb_substr($text, 0, 500)]);

        unset($this->subDraft[$parentId]);

        // Menambah sub-tugas di tugas yang sudah selesai membukanya lagi:
        // ada pekerjaan baru yang belum dikerjakan.
        $this->reopenAncestors($parent);
    }

    public function toggle(int $id): void
    {
        $task = MeetingTask::findOrFail($id);
        $task->update(['completed' => ! $task->completed]);

        if (! $task->completed) {
            $this->reopenAncestors($task);
        }
    }

    public function archive(int $id): void
    {
        MeetingTask::whereKey($id)->topLevel()->update(['archived' => true]);
    }

    public function restore(int $id): void
    {
        // Dikembalikan dalam keadaan belum selesai supaya tidak langsung
        // diarsipkan lagi oleh arsip otomatis.
        MeetingTask::findOrFail($id)->update(['archived' => false, 'completed' => false]);
    }

    public function delete(int $id): void
    {
        MeetingTask::whereKey($id)->delete();

        $this->expanded = array_values(array_diff($this->expanded, [$id]));
    }

    public function startEdit(int $id): void
    {
        $task = MeetingTask::findOrFail($id);

        $this->editingId = $task->id;
        $this->edit = [
            'text' => $task->text,
            'project_id' => (string) ($task->project_id ?? ''),
            'category' => $task->category,
            'priority' => $task->priority,
            'deadline' => $task->deadline?->toDateString() ?? '',
            'link' => $task->link ?? '',
        ];
    }

    public function saveEdit(): void
    {
        $task = MeetingTask::findOrFail($this->editingId);
        $data = $this->validate($this->taskRules('edit'))['edit'];

        // Sub-tugas hanya punya teks, tenggat, dan link; proyek, kategori,
        // dan prioritasnya ikut tugas utamanya.
        $task->update([
            'text' => trim($data['text']),
            'deadline' => $data['deadline'] ?: null,
            'link' => $data['link'] ?: null,
            ...($task->parent_id ? [] : [
                'project_id' => $data['project_id'] ?: null,
                'category' => $data['category'],
                'priority' => $data['priority'],
            ]),
        ]);

        $this->cancelEdit();
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->edit = [];
    }

    public function importTasks(): void
    {
        $lines = collect(preg_split('/\R/', $this->importText))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values();

        if ($lines->isEmpty()) {
            Notification::make()->warning()->title(__('meeting.import.empty'))->send();

            return;
        }

        $data = $this->validate($this->taskRules('draft', withText: false))['draft'];

        foreach ($lines as $line) {
            MeetingTask::create([
                'text' => mb_substr($line, 0, 500),
                'project_id' => $data['project_id'] ?: null,
                'category' => $data['category'],
                'priority' => $data['priority'],
            ]);
        }

        $this->importText = '';
        $this->dispatch('meeting-imported');

        Notification::make()->success()->title(__('meeting.import.done', ['count' => $lines->count()]))->send();
    }

    /* ------------------------------------------------------------ proyek */

    public function addProject(): void
    {
        $name = trim($this->newProject);

        if ($name === '') {
            return;
        }

        $project = MeetingProject::create(['name' => mb_substr($name, 0, 120)]);
        $this->newProject = '';
        $this->setProject((string) $project->id);
    }

    public function startRename(int $id): void
    {
        $this->renamingProject = $id;
        $this->renameValue = (string) MeetingProject::findOrFail($id)->name;
    }

    public function saveRename(): void
    {
        $name = trim($this->renameValue);

        if ($this->renamingProject && $name !== '') {
            MeetingProject::whereKey($this->renamingProject)->update(['name' => mb_substr($name, 0, 120)]);
        }

        $this->renamingProject = null;
        $this->renameValue = '';
    }

    public function deleteProject(int $id): void
    {
        MeetingProject::whereKey($id)->delete();

        if ($this->project === (string) $id) {
            $this->setProject('all');
        }
    }

    /* ---------------------------------------------------------- pembantu */

    /** @return array<string, mixed> */
    protected function taskRules(string $key, bool $withText = true): array
    {
        return [
            ...($withText ? ["{$key}.text" => ['required', 'string', 'max:500']] : []),
            "{$key}.project_id" => ['nullable', Rule::exists('meeting_projects', 'id')],
            "{$key}.category" => ['required', Rule::in(MeetingTask::CATEGORIES)],
            "{$key}.priority" => ['required', Rule::in(MeetingTask::PRIORITIES)],
            "{$key}.deadline" => ['nullable', 'date'],
            "{$key}.link" => ['nullable', 'url', 'max:500'],
        ];
    }

    protected function resetDraft(): void
    {
        $this->draft = [
            'text' => '',
            'project_id' => is_numeric($this->project) ? $this->project : '',
            'category' => 'kerja',
            'priority' => 'medium',
            'deadline' => '',
            'link' => '',
        ];
    }

    /** Tugas yang selesai dibuka lagi kalau ada bagian di bawahnya yang belum. */
    protected function reopenAncestors(MeetingTask $task): void
    {
        for ($node = $task; $node; $node = $node->parent) {
            if ($node->completed) {
                $node->update(['completed' => false]);
            }
        }
    }
}

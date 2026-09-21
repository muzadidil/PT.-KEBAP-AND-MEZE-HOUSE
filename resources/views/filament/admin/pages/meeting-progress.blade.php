{{--
    Progres Rapat — lihat App\Filament\Admin\Pages\MeetingProgress.

    Teks untuk disalin dibaca dari atribut data tombolnya saat diklik, jadi
    selalu isi terbaru setelah Livewire memperbarui halaman.
--}}
@php
    $board = $this->board;
    $report = $this->report;
    $scope = $this->shareScope();
    $stats = $this->stats;
    $archived = $this->filter === 'archived';
    $priorityColor = ['high' => 'danger', 'medium' => 'warning', 'low' => 'success'];
@endphp

<x-filament-panels::page>
    <div class="meeting"
         x-data="{
             copied: null,
             importing: false,
             addingProject: false,
             async copy(text, key) {
                 try {
                     await navigator.clipboard.writeText(text)
                 } catch (e) {
                     // Clipboard API tidak tersedia di alamat http biasa.
                     const area = document.createElement('textarea')
                     area.value = text
                     area.style.position = 'fixed'
                     area.style.opacity = '0'
                     document.body.appendChild(area)
                     area.select()
                     document.execCommand('copy')
                     document.body.removeChild(area)
                 }
                 this.copied = key
                 setTimeout(() => this.copied = null, 1500)
             },
         }"
         x-on:meeting-imported.window="importing = false">

        {{-- Bagikan: salin teks, salin WA, kirim ke WhatsApp, PDF --}}
        <div class="meeting__toolbar">
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass" class="meeting__search">
                <x-filament::input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('meeting.field.search')" />
            </x-filament::input.wrapper>

            <div class="meeting__share">
                <x-filament::button size="sm" color="gray" icon="heroicon-m-arrow-down-tray" x-on:click="importing = ! importing">
                    {{ __('meeting.action.import') }}
                </x-filament::button>

                <x-filament::button size="sm" color="gray" icon="heroicon-m-document-text" tag="a" :href="$this->pdfUrl()" target="_blank">
                    {{ __('meeting.action.pdf') }}
                </x-filament::button>

                <x-filament::button size="sm" color="gray" icon="heroicon-m-clipboard-document"
                                    data-share="{{ $report->text($scope) }}"
                                    x-on:click="copy($el.dataset.share, 'text')">
                    <span x-text="copied === 'text' ? @js(__('meeting.action.copied')) : @js(__('meeting.action.copy_text'))"></span>
                </x-filament::button>

                <x-filament::button size="sm" color="gray" icon="heroicon-m-chat-bubble-left-ellipsis"
                                    data-share="{{ $report->whatsapp($scope) }}"
                                    x-on:click="copy($el.dataset.share, 'wa')">
                    <span x-text="copied === 'wa' ? @js(__('meeting.action.copied')) : @js(__('meeting.action.copy_wa'))"></span>
                </x-filament::button>

                <x-filament::button size="sm" color="success" icon="heroicon-m-paper-airplane" tag="a"
                                    :href="$report->whatsappUrl($scope)" target="_blank">
                    {{ __('meeting.action.send_wa') }}
                </x-filament::button>
            </div>
        </div>

        {{-- Impor dari teks: satu tugas per baris --}}
        <form class="meeting__panel" x-show="importing" x-cloak wire:submit="importTasks">
            <strong>{{ __('meeting.import.title') }}</strong>
            <p class="meeting__muted">{{ __('meeting.import.hint') }}</p>

            <textarea class="meeting__textarea" rows="6" wire:model="importText" placeholder="{{ __('meeting.import.placeholder') }}"></textarea>

            <div class="meeting__row">
                @include('filament.admin.pages.meeting.task-options', ['model' => 'draft'])

                <x-filament::button type="submit" size="sm">{{ __('meeting.action.import') }}</x-filament::button>
            </div>
        </form>

        {{-- Angka --}}
        <div class="stats">
            @foreach (['total', 'active', 'done'] as $key)
                <div class="stat">
                    <div class="stat__label">{{ __('meeting.stat.'.$key) }}</div>
                    <div class="stat__value">{{ $stats[$key] }}</div>
                </div>
            @endforeach

            <div class="stat">
                <div class="stat__label">{{ __('meeting.stat.progress') }}</div>
                <div class="stat__value">{{ $stats['progress'] }}%</div>
                <div class="meeting-bar"><span style="width: {{ $stats['progress'] }}%"></span></div>
            </div>
        </div>

        {{-- Proyek, dengan persentasenya --}}
        <div class="meeting__projects">
            <button type="button" class="pos__chip {{ $this->project === 'all' ? 'pos__chip--active' : '' }}" wire:click="setProject('all')">
                {{ __('meeting.all_projects') }}
            </button>

            @foreach ($this->projects as $project)
                @if ($this->renamingProject === $project->id)
                    <form wire:submit="saveRename" class="meeting__rename">
                        <x-filament::input.wrapper>
                            <x-filament::input type="text" wire:model="renameValue" x-init="$el.focus()" x-on:keydown.escape="$wire.set('renamingProject', null)" />
                        </x-filament::input.wrapper>
                    </form>
                @else
                    <button type="button"
                            class="pos__chip {{ $this->project === (string) $project->id ? 'pos__chip--active' : '' }}"
                            wire:click="setProject('{{ $project->id }}')"
                            wire:key="project-{{ $project->id }}">
                        {{ $project->name }} · {{ $this->projectProgress($project->id) }}%
                    </button>
                @endif
            @endforeach

            <button type="button" class="pos__chip {{ $this->project === 'none' ? 'pos__chip--active' : '' }}" wire:click="setProject('none')">
                {{ __('meeting.no_project') }}
            </button>

            <x-filament::icon-button icon="heroicon-m-plus" color="gray" size="sm" :label="__('meeting.action.add_project')" x-on:click="addingProject = ! addingProject" />

            @if (is_numeric($this->project))
                <x-filament::icon-button icon="heroicon-m-pencil-square" color="gray" size="sm" :label="__('meeting.action.rename')" wire:click="startRename({{ (int) $this->project }})" />
                <x-filament::icon-button icon="heroicon-m-trash" color="danger" size="sm" :label="__('meeting.action.delete')"
                                         wire:click="deleteProject({{ (int) $this->project }})" wire:confirm="{{ __('meeting.confirm.delete_project') }}" />
            @endif

            <form wire:submit="addProject" x-show="addingProject" x-cloak class="meeting__rename">
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="newProject" :placeholder="__('meeting.field.project_name')" />
                </x-filament::input.wrapper>
            </form>
        </div>

        {{-- Saringan --}}
        <x-filament::tabs>
            @foreach (['all', 'today', 'active', 'completed', 'overdue', 'archived'] as $key)
                <x-filament::tabs.item :active="$this->filter === $key" wire:click="setFilter('{{ $key }}')">
                    {{ __('meeting.filter.'.$key) }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        <div class="meeting__row">
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="category">
                    <option value="all">{{ __('meeting.filter.all_categories') }}</option>
                    @foreach (\App\Models\MeetingTask::CATEGORIES as $value)
                        <option value="{{ $value }}">{{ __('meeting.category.'.$value) }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>

            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="priority">
                    <option value="all">{{ __('meeting.filter.all_priorities') }}</option>
                    @foreach (\App\Models\MeetingTask::PRIORITIES as $value)
                        <option value="{{ $value }}">{{ __('meeting.priority.'.$value) }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>

            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="sort">
                    @foreach (['newest', 'oldest', 'priority', 'due'] as $value)
                        <option value="{{ $value }}">{{ __('meeting.sort.'.$value) }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        {{-- Tugas baru --}}
        @unless ($archived)
            <form class="meeting__panel meeting__add" wire:submit="addTask">
                <x-filament::input.wrapper class="meeting__add-text" :valid="! $errors->has('draft.text')">
                    <x-filament::input type="text" wire:model="draft.text" :placeholder="__('meeting.field.task')" maxlength="500" />
                </x-filament::input.wrapper>

                <div class="meeting__row">
                    @include('filament.admin.pages.meeting.task-options', ['model' => 'draft'])

                    <x-filament::input.wrapper :valid="! $errors->has('draft.deadline')">
                        <x-filament::input type="date" wire:model="draft.deadline" :title="__('meeting.field.deadline')" />
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper :valid="! $errors->has('draft.link')">
                        <x-filament::input type="url" wire:model="draft.link" placeholder="https://…" :title="__('meeting.field.link')" />
                    </x-filament::input.wrapper>

                    <x-filament::button type="submit" icon="heroicon-m-plus">{{ __('meeting.action.add') }}</x-filament::button>
                </div>

                @error('draft.*')
                    <p class="meeting__error">{{ $message }}</p>
                @enderror
            </form>
        @endunless

        {{-- Daftar tugas --}}
        <div class="meeting__list">
            @forelse ($this->tasks as $task)
                @php
                    $count = $board->count($task);
                    $progress = $board->progress($task);
                    $open = in_array($task->id, $this->expanded, true);
                @endphp

                <div class="meeting-task {{ $task->completed ? 'is-done' : '' }}" wire:key="task-{{ $task->id }}">
                    @if ($this->editingId === $task->id)
                        <form class="meeting-edit" wire:submit="saveEdit">
                            <x-filament::input.wrapper class="meeting-edit__text">
                                <x-filament::input type="text" wire:model="edit.text" required maxlength="500" />
                            </x-filament::input.wrapper>

                            <div class="meeting__row">
                                @include('filament.admin.pages.meeting.task-options', ['model' => 'edit'])

                                <x-filament::input.wrapper>
                                    <x-filament::input type="date" wire:model="edit.deadline" />
                                </x-filament::input.wrapper>

                                <x-filament::input.wrapper>
                                    <x-filament::input type="url" wire:model="edit.link" placeholder="https://…" />
                                </x-filament::input.wrapper>

                                <x-filament::button type="submit" size="sm">{{ __('meeting.action.save') }}</x-filament::button>
                                <x-filament::button type="button" size="sm" color="gray" wire:click="cancelEdit">{{ __('meeting.action.cancel') }}</x-filament::button>
                            </div>
                        </form>
                    @else
                        <div class="meeting-task__row">
                            <button type="button"
                                    class="meeting-check {{ $task->completed ? 'is-checked' : '' }}"
                                    wire:click="toggle({{ $task->id }})"
                                    @disabled($archived)
                                    aria-label="{{ $task->text }}">
                                <x-filament::icon icon="heroicon-m-check" />
                            </button>

                            <div class="meeting-task__body">
                                <div class="meeting-task__text">{{ $task->text }}</div>

                                <div class="meeting-task__meta">
                                    @if ($task->project)
                                        <x-filament::badge size="sm" color="info">{{ $task->project->name }}</x-filament::badge>
                                    @endif

                                    <x-filament::badge size="sm" color="gray">{{ __('meeting.category.'.$task->category) }}</x-filament::badge>

                                    <x-filament::badge size="sm" :color="$priorityColor[$task->priority] ?? 'gray'">
                                        {{ __('meeting.priority.'.$task->priority) }}
                                    </x-filament::badge>

                                    @include('filament.admin.pages.meeting.deadline', ['task' => $task])

                                    @if ($task->link)
                                        <a href="{{ $task->link }}" target="_blank" rel="noopener" class="meeting-link">
                                            <x-filament::icon icon="heroicon-m-link" />
                                        </a>
                                    @endif

                                    @if ($task->completed && ! $archived)
                                        <span class="meeting__muted">{{ __('meeting.archive_hint') }}</span>
                                    @endif
                                </div>

                                @if ($count['total'] > 0)
                                    <div class="meeting-task__progress">
                                        <div class="meeting-bar"><span style="width: {{ $progress }}%"></span></div>
                                        <span class="meeting__muted">{{ $count['done'] }}/{{ $count['total'] }} · {{ $progress }}%</span>
                                    </div>
                                @endif
                            </div>

                            <div class="meeting-task__actions">
                                <x-filament::button size="xs" color="gray" icon="heroicon-m-list-bullet"
                                                    wire:click="toggleExpand({{ $task->id }})">
                                    {{ __('meeting.action.subtasks') }} ({{ $count['total'] }})
                                </x-filament::button>

                                @if ($archived)
                                    <x-filament::icon-button icon="heroicon-m-arrow-uturn-left" color="gray" size="sm" :label="__('meeting.action.restore')" wire:click="restore({{ $task->id }})" />
                                @else
                                    <x-filament::icon-button icon="heroicon-m-pencil-square" color="gray" size="sm" :label="__('meeting.action.edit')" wire:click="startEdit({{ $task->id }})" />
                                    <x-filament::icon-button icon="heroicon-m-archive-box" color="gray" size="sm" :label="__('meeting.action.archive')" wire:click="archive({{ $task->id }})" />
                                @endif

                                <x-filament::icon-button icon="heroicon-m-trash" color="danger" size="sm" :label="__('meeting.action.delete')"
                                                         wire:click="delete({{ $task->id }})" wire:confirm="{{ __('meeting.confirm.delete_task') }}" />
                            </div>
                        </div>
                    @endif

                    @if ($open)
                        <div class="meeting-task__subtasks">
                            @include('filament.admin.pages.meeting.subtasks', ['board' => $board, 'parent' => $task, 'depth' => 0])

                            <div class="meeting-sub__add">
                                <x-filament::input.wrapper>
                                    <x-filament::input type="text"
                                                       wire:model="subDraft.{{ $task->id }}"
                                                       wire:keydown.enter.prevent="addSubtask({{ $task->id }})"
                                                       :placeholder="__('meeting.field.subtask')" />
                                </x-filament::input.wrapper>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="meeting__empty">
                    {{ $archived ? __('meeting.empty_archive') : __('meeting.empty') }}
                </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>

{{--
    Sub-tugas satu tugas, bertingkat tanpa batas: partial ini memanggil
    dirinya sendiri untuk tiap anak. Tambah sub-tugas di level mana pun
    lewat tombol + di barisnya.
--}}
@foreach ($board->children($parent) as $child)
    <div class="meeting-sub" style="--depth: {{ $depth }}" wire:key="sub-{{ $child->id }}" x-data="{ adding: false }">
        @if ($this->editingId === $child->id)
            <form class="meeting-edit meeting-edit--sub" wire:submit="saveEdit">
                <x-filament::input.wrapper class="meeting-edit__text">
                    <x-filament::input type="text" wire:model="edit.text" required maxlength="500" />
                </x-filament::input.wrapper>

                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model="edit.deadline" />
                </x-filament::input.wrapper>

                <x-filament::input.wrapper>
                    <x-filament::input type="url" wire:model="edit.link" placeholder="https://…" />
                </x-filament::input.wrapper>

                <x-filament::button type="submit" size="sm">{{ __('meeting.action.save') }}</x-filament::button>
                <x-filament::button type="button" size="sm" color="gray" wire:click="cancelEdit">{{ __('meeting.action.cancel') }}</x-filament::button>
            </form>
        @else
            <div class="meeting-sub__row">
                <button type="button"
                        class="meeting-check meeting-check--sm {{ $child->completed ? 'is-checked' : '' }}"
                        wire:click="toggle({{ $child->id }})"
                        aria-label="{{ $child->text }}">
                    <x-filament::icon icon="heroicon-m-check" />
                </button>

                <span class="meeting-sub__text {{ $child->completed ? 'is-done' : '' }}">{{ $child->text }}</span>

                @include('filament.admin.pages.meeting.deadline', ['task' => $child])

                @if ($child->link)
                    <a href="{{ $child->link }}" target="_blank" rel="noopener" class="meeting-link">
                        <x-filament::icon icon="heroicon-m-link" />
                    </a>
                @endif

                <span class="meeting-sub__actions">
                    <x-filament::icon-button icon="heroicon-m-plus" color="gray" size="sm" :label="__('meeting.action.add_subtask')" x-on:click="adding = ! adding" />
                    <x-filament::icon-button icon="heroicon-m-pencil-square" color="gray" size="sm" :label="__('meeting.action.edit')" wire:click="startEdit({{ $child->id }})" />
                    <x-filament::icon-button icon="heroicon-m-trash" color="danger" size="sm" :label="__('meeting.action.delete')"
                                             wire:click="delete({{ $child->id }})" wire:confirm="{{ __('meeting.confirm.delete_task') }}" />
                </span>
            </div>
        @endif

        <div class="meeting-sub__add" x-show="adding" x-cloak>
            <x-filament::input.wrapper>
                <x-filament::input type="text"
                                   wire:model="subDraft.{{ $child->id }}"
                                   wire:keydown.enter.prevent="addSubtask({{ $child->id }})"
                                   :placeholder="__('meeting.field.subtask')" />
            </x-filament::input.wrapper>
        </div>

        @include('filament.admin.pages.meeting.subtasks', ['parent' => $child, 'depth' => $depth + 1])
    </div>
@endforeach

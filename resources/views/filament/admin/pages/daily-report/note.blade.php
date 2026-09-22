{{--
    Satu catatan Daily Report, bertingkat tanpa batas: partial ini memanggil
    dirinya sendiri untuk tiap sub-catatan. Depth 0 adalah bagian utama
    (mis. "Sales") dan ditulis tebal; di bawah depth 0 hanya poin biasa,
    berapa pun tingkatnya.
--}}
@php
    $children = $board->children($note);
@endphp
<div class="note-item" wire:key="note-{{ $note->id }}" x-data="{ adding: false }">
    @if ($this->editingId === $note->id)
        <form class="meeting-edit meeting-edit--sub" wire:submit="saveEdit">
            <x-filament::input.wrapper class="meeting-edit__text">
                <x-filament::input type="text" wire:model="edit.text" required maxlength="500" />
            </x-filament::input.wrapper>

            <x-filament::input.wrapper class="note-form__nominal">
                <x-filament::input type="text" inputmode="numeric" wire:model="edit.nominal" placeholder="Rp" />
            </x-filament::input.wrapper>

            <x-filament::button type="submit" size="sm">{{ __('daily_report.action.save') }}</x-filament::button>
            <x-filament::button type="button" size="sm" color="gray" wire:click="cancelEdit">{{ __('daily_report.action.cancel') }}</x-filament::button>
        </form>
    @else
        <div class="note-item__row">
            <span class="note-item__text {{ $depth === 0 ? 'note-item__text--master' : '' }}">{{ $note->text }}</span>

            @if ($note->nominal !== null)
                <span class="note-item__nominal-badge">{{ \App\Support\Money::format($note->nominal) }}</span>
            @endif

            <span class="note-item__actions">
                <x-filament::icon-button icon="heroicon-m-plus" color="gray" size="sm" :label="__('daily_report.action.add_sub')" x-on:click="adding = ! adding" />
                <x-filament::icon-button icon="heroicon-m-pencil-square" color="gray" size="sm" :label="__('daily_report.action.edit')" wire:click="startEdit({{ $note->id }})" />
                <x-filament::icon-button icon="heroicon-m-trash" color="danger" size="sm" :label="__('daily_report.action.delete')"
                                         wire:click="delete({{ $note->id }})" wire:confirm="{{ __('daily_report.confirm.delete') }}" />
            </span>
        </div>
    @endif

    <div class="note-form note-item__add" x-show="adding" x-cloak>
        <x-filament::input.wrapper class="note-form__text">
            <x-filament::input type="text"
                               wire:model="subDraft.{{ $note->id }}.text"
                               wire:keydown.enter.prevent="addSub({{ $note->id }})"
                               :placeholder="__('daily_report.field.sub_placeholder')" />
        </x-filament::input.wrapper>

        <x-filament::input.wrapper class="note-form__nominal">
            <x-filament::input type="text" inputmode="numeric"
                               wire:model="subDraft.{{ $note->id }}.nominal"
                               placeholder="Rp" :title="__('daily_report.field.nominal')" />
        </x-filament::input.wrapper>

        <x-filament::button size="sm" wire:click="addSub({{ $note->id }})">{{ __('daily_report.action.add') }}</x-filament::button>
    </div>

    @if ($children->isNotEmpty())
        <div class="note-item__children">
            @foreach ($children as $child)
                @include('filament.admin.pages.daily-report.note', ['note' => $child, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif
</div>

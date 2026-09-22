{{--
    Satu catatan Daily Report, bertingkat tanpa batas: partial ini memanggil
    dirinya sendiri untuk tiap sub-catatan. Depth 0 adalah bagian utama
    (mis. "Operation") dan ditulis tebal dengan ikonnya.

    Isian di sisi kanan mengikuti jenis catatannya — lihat App\Models\DailyNote:
    pilihan dan status berupa dropdown dari master, angka dan rating berupa
    isian kecil yang langsung tersimpan, catatan biasa menampilkan nominalnya.
--}}
@php
    $children = $board->children($note);
    $childKind = $note->childKind();
    $options = $this->options;
    $stars = $note->kind === 'rating' ? (int) round((float) $note->value) : 0;
@endphp
<div class="note-item {{ $depth === 0 ? 'note-item--section' : '' }}" wire:key="note-{{ $note->id }}" x-data="{ adding: false }">
    @if ($this->editingId === $note->id)
        <form class="meeting-edit meeting-edit--sub" wire:submit="saveEdit">
            @if ($depth === 0)
                <x-filament::input.wrapper class="note-form__icon">
                    <x-filament::input type="text" wire:model="edit.icon" maxlength="16" :placeholder="__('daily_report.field.icon')" :title="__('daily_report.field.icon')" />
                </x-filament::input.wrapper>
            @endif

            <x-filament::input.wrapper class="meeting-edit__text">
                <x-filament::input type="text" wire:model="edit.text" required maxlength="500" />
            </x-filament::input.wrapper>

            @if ($note->kind === 'text')
                <x-filament::input.wrapper class="note-form__nominal">
                    <x-filament::input type="text" inputmode="numeric" wire:model="edit.nominal" placeholder="Rp" />
                </x-filament::input.wrapper>
            @endif

            <x-filament::button type="submit" size="sm">{{ __('daily_report.action.save') }}</x-filament::button>
            <x-filament::button type="button" size="sm" color="gray" wire:click="cancelEdit">{{ __('daily_report.action.cancel') }}</x-filament::button>
        </form>
    @else
        <div class="note-item__row">
            <span class="note-item__text {{ $depth === 0 ? 'note-item__text--master' : '' }}">
                @if ($depth === 0 && $note->icon)
                    <span class="note-item__icon">{{ $note->icon }}</span>
                @endif
                {{ $note->text }}

                @if ($depth === 0 && $note->kind !== 'text')
                    <x-filament::badge size="sm" color="gray" class="note-item__kind">{{ __('daily_report.kind.'.$note->kind) }}</x-filament::badge>
                @endif
            </span>

            {{-- Isian sesuai jenis. Judul bagian Angka/Status tidak punya
                 isian sendiri: isiannya di poin-poinnya. --}}
            @if ($note->kind === 'choice' || ($depth > 0 && $note->kind === 'status'))
                    <x-filament::input.wrapper class="note-item__select">
                        <x-filament::input.select wire:key="opt-{{ $note->id }}-{{ $note->option_id }}"
                                                  wire:change="setOption({{ $note->id }}, $event.target.value)">
                            <option value="">{{ __('daily_report.field.choose') }}</option>
                            @foreach ($options[$note->optionGroup()] as $option)
                                <option value="{{ $option->id }}" @selected($option->id === $note->option_id)>{{ $option->display() }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>

            @elseif ($note->kind === 'rating')
                    <span class="note-stars" role="radiogroup" aria-label="{{ $note->text }}">
                        @for ($i = 1; $i <= \App\Models\DailyNote::MAX_RATING; $i++)
                            <button type="button"
                                    class="note-stars__star {{ $i <= $stars ? 'is-on' : '' }}"
                                    wire:click="setValue({{ $note->id }}, {{ $i }})"
                                    aria-label="{{ $i }}">★</button>
                        @endfor
                    </span>

                    <x-filament::input.wrapper class="note-item__value">
                        <x-filament::input type="text" inputmode="decimal"
                                           wire:key="val-{{ $note->id }}-{{ $note->value }}"
                                           value="{{ $note->formattedValue() }}"
                                           wire:change="setValue({{ $note->id }}, $event.target.value)"
                                           placeholder="0–5" />
                    </x-filament::input.wrapper>

            @elseif ($depth > 0 && $note->kind === 'number')
                    <x-filament::input.wrapper class="note-item__value">
                        <x-filament::input type="text" inputmode="numeric"
                                           wire:key="val-{{ $note->id }}-{{ $note->value }}"
                                           value="{{ $note->formattedValue() }}"
                                           wire:change="setValue({{ $note->id }}, $event.target.value)"
                                           placeholder="0" />
                    </x-filament::input.wrapper>

            @elseif ($note->nominal !== null)
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

    {{-- Sub-catatan baru: isiannya ikut jenis anak catatan ini --}}
    <div class="note-form note-item__add" x-show="adding" x-cloak>
        <x-filament::input.wrapper class="note-form__text">
            <x-filament::input type="text"
                               wire:model="subDraft.{{ $note->id }}.text"
                               wire:keydown.enter.prevent="addSub({{ $note->id }})"
                               :placeholder="__('daily_report.field.sub_placeholder')" />
        </x-filament::input.wrapper>

        @if ($childKind === 'number')
            <x-filament::input.wrapper class="note-form__nominal">
                <x-filament::input type="text" inputmode="numeric" wire:model="subDraft.{{ $note->id }}.value"
                                   placeholder="0" :title="__('daily_report.field.value')" />
            </x-filament::input.wrapper>
        @elseif ($childKind === 'status')
            <x-filament::input.wrapper class="note-form__nominal">
                <x-filament::input.select wire:model="subDraft.{{ $note->id }}.option_id" :title="__('daily_report.field.status')">
                    <option value="">{{ __('daily_report.field.status') }}</option>
                    @foreach ($options['status'] as $option)
                        <option value="{{ $option->id }}">{{ $option->display() }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        @else
            <x-filament::input.wrapper class="note-form__nominal">
                <x-filament::input type="text" inputmode="numeric" wire:model="subDraft.{{ $note->id }}.nominal"
                                   placeholder="Rp" :title="__('daily_report.field.nominal')" />
            </x-filament::input.wrapper>
        @endif

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

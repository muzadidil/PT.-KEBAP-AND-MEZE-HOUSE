{{--
    Daily Report — lihat App\Filament\Admin\Pages\DailyReport.

    Teks WhatsApp dibaca dari atribut data tombol salin saat diklik, jadi
    selalu isi terbaru setelah Livewire memperbarui halaman — sama seperti
    Progres Rapat.
--}}
@php
    $board = $this->board;
    $report = $this->report;
    $top = $board->topLevel();
@endphp

<x-filament-panels::page>
    <div class="meeting"
         x-data="{
             copied: null,
             managing: false,
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
         }">

        {{-- Isian tanggal tidak ikut tercetak, jadi tanggalnya ditulis ulang untuk kertas --}}
        <p class="print-only note-print-date">
            {{ __('daily_report.share.date_label') }}: {{ \Illuminate\Support\Carbon::parse($this->date)->translatedFormat('l, j F Y') }}
        </p>

        {{-- Tanggal, dan bagikan: salin WA, kirim ke WhatsApp --}}
        <div class="meeting__toolbar">
            <div class="note-datebar">
                <x-filament::icon-button icon="heroicon-m-chevron-left" color="gray" :label="__('daily_report.action.prev_day')" wire:click="shiftDay(-1)" />

                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live="date" />
                </x-filament::input.wrapper>

                <x-filament::icon-button icon="heroicon-m-chevron-right" color="gray" :label="__('daily_report.action.next_day')" wire:click="shiftDay(1)" />

                <x-filament::button size="sm" color="gray" wire:click="goToday">{{ __('daily_report.action.today') }}</x-filament::button>
            </div>

            <div class="meeting__share">
                <x-filament::button size="sm" color="gray" icon="heroicon-m-adjustments-horizontal" x-on:click="managing = ! managing">
                    {{ __('daily_report.action.master') }}
                </x-filament::button>

                @if ($top->isEmpty())
                    <x-filament::button size="sm" color="gray" icon="heroicon-m-sparkles" wire:click="fillTemplate">
                        {{ __('daily_report.action.fill_template') }}
                    </x-filament::button>
                @endif

                <x-filament::button size="sm" color="gray" icon="heroicon-m-clipboard-document"
                                    data-share="{{ $report->whatsapp() }}"
                                    x-on:click="copy($el.dataset.share, 'wa')">
                    <span x-text="copied === 'wa' ? @js(__('daily_report.action.copied')) : @js(__('daily_report.action.copy_wa'))"></span>
                </x-filament::button>

                <x-filament::button size="sm" color="success" icon="heroicon-m-paper-airplane" tag="a"
                                    :href="$report->whatsappUrl()" target="_blank">
                    {{ __('daily_report.action.send_wa') }}
                </x-filament::button>
            </div>
        </div>

        {{-- Master pilihan: kondisi (untuk bagian jenis Pilihan) dan status --}}
        <div class="meeting__panel" x-show="managing" x-cloak>
            <strong class="note-master__title">
                <x-filament::icon icon="heroicon-m-adjustments-horizontal" class="note-master__title-icon" />
                {{ __('daily_report.master.title') }}
            </strong>
            <p class="meeting__muted">{{ __('daily_report.master.hint') }}</p>

            <div class="note-master">
                @foreach ($this->options as $group => $options)
                    <div class="note-master__group">
                        <div class="note-master__heading">{{ __('daily_report.master.'.$group) }}</div>

                        @foreach ($options as $option)
                            <div class="note-master__row" wire:key="option-{{ $option->id }}">
                                @if ($this->editingOptionId === $option->id)
                                    <form class="note-form" wire:submit="saveOption">
                                        <x-filament::input.wrapper class="note-form__icon">
                                            <x-filament::input type="text" wire:model="optionEdit.icon" maxlength="16" :placeholder="__('daily_report.field.icon')" />
                                        </x-filament::input.wrapper>
                                        <x-filament::input.wrapper class="note-form__text">
                                            <x-filament::input type="text" wire:model="optionEdit.label" maxlength="60" required />
                                        </x-filament::input.wrapper>
                                        <x-filament::button type="submit" size="sm">{{ __('daily_report.action.save') }}</x-filament::button>
                                        <x-filament::button type="button" size="sm" color="gray" wire:click="cancelOptionEdit">{{ __('daily_report.action.cancel') }}</x-filament::button>
                                    </form>
                                @else
                                    <span class="note-master__label">{{ $option->display() }}</span>
                                    <span class="note-item__actions">
                                        <x-filament::icon-button icon="heroicon-m-pencil-square" color="gray" size="sm" :label="__('daily_report.action.edit')" wire:click="startOptionEdit({{ $option->id }})" />
                                        <x-filament::icon-button icon="heroicon-m-trash" color="danger" size="sm" :label="__('daily_report.action.delete')"
                                                                 wire:click="deleteOption({{ $option->id }})" wire:confirm="{{ __('daily_report.confirm.delete_option') }}" />
                                    </span>
                                @endif
                            </div>
                        @endforeach

                        <form class="note-form note-master__add" wire:submit="addOption('{{ $group }}')">
                            <x-filament::input.wrapper class="note-form__icon">
                                <x-filament::input type="text" wire:model="newOption.{{ $group }}.icon" maxlength="16" :placeholder="__('daily_report.field.icon')" />
                            </x-filament::input.wrapper>
                            <x-filament::input.wrapper class="note-form__text" :valid="! $errors->has('newOption.'.$group.'.label')">
                                <x-filament::input type="text" wire:model="newOption.{{ $group }}.label" maxlength="60" :placeholder="__('daily_report.master.new_'.$group)" />
                            </x-filament::input.wrapper>
                            <x-filament::button type="submit" size="sm" icon="heroicon-m-plus">{{ __('daily_report.action.add') }}</x-filament::button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Bagian baru: jenisnya menentukan isian di sebelah kanan --}}
        <form class="meeting__panel" wire:submit="addNote">
            <div class="note-form">
                <x-filament::input.wrapper class="note-form__icon" :valid="! $errors->has('draft.icon')">
                    <x-filament::input type="text" wire:model="draft.icon" maxlength="16" :placeholder="__('daily_report.field.icon')" :title="__('daily_report.field.icon')" />
                </x-filament::input.wrapper>

                <x-filament::input.wrapper class="note-form__text" :valid="! $errors->has('draft.text')">
                    <x-filament::input type="text" wire:model="draft.text" :placeholder="__('daily_report.field.note_placeholder')" maxlength="500" />
                </x-filament::input.wrapper>

                <x-filament::input.wrapper class="note-form__kind" :title="__('daily_report.field.kind')">
                    <x-filament::input.select wire:model.live="draft.kind">
                        @foreach (\App\Models\DailyNote::SECTION_KINDS as $kind)
                            <option value="{{ $kind }}">{{ __('daily_report.kind.'.$kind) }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                @if (($this->draft['kind'] ?? 'text') === 'text')
                    <x-filament::input.wrapper class="note-form__nominal" :valid="! $errors->has('draft.nominal')">
                        <x-filament::input type="text" inputmode="numeric" wire:model="draft.nominal" placeholder="Rp" :title="__('daily_report.field.nominal')" />
                    </x-filament::input.wrapper>
                @endif

                <x-filament::button type="submit" icon="heroicon-m-plus">{{ __('daily_report.action.add') }}</x-filament::button>
            </div>

            <p class="meeting__muted">{{ __('daily_report.kind_hint.'.($this->draft['kind'] ?? 'text')) }}</p>

            @error('draft.*')
                <p class="meeting__error">{{ $message }}</p>
            @enderror
        </form>

        {{-- Daftar catatan --}}
        <div class="meeting__list">
            @forelse ($top as $note)
                @include('filament.admin.pages.daily-report.note', ['note' => $note, 'depth' => 0])
            @empty
                <div class="meeting__empty">{{ __('daily_report.empty_day') }}</div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>

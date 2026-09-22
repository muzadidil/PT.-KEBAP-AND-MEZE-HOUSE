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

        {{-- Bagian baru --}}
        <form class="meeting__panel" wire:submit="addNote">
            <div class="note-form">
                <x-filament::input.wrapper class="note-form__text" :valid="! $errors->has('draft.text')">
                    <x-filament::input type="text" wire:model="draft.text" :placeholder="__('daily_report.field.note_placeholder')" maxlength="500" />
                </x-filament::input.wrapper>

                <x-filament::input.wrapper class="note-form__nominal" :valid="! $errors->has('draft.nominal')">
                    <x-filament::input type="text" inputmode="numeric" wire:model="draft.nominal" placeholder="Rp" :title="__('daily_report.field.nominal')" />
                </x-filament::input.wrapper>

                <x-filament::button type="submit" icon="heroicon-m-plus">{{ __('daily_report.action.add') }}</x-filament::button>
            </div>

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

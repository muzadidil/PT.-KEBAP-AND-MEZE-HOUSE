{{--
    Isian rentang laporan dan presetnya; lihat Reports\Concerns\HasPeriod.
    Tombol tambahan halaman (CSV, Cetak) masuk lewat slot, di baris yang sama.
--}}
@props(['from', 'to'])

<div class="filters">
    <label class="filters__field">
        <span class="pos__label">{{ __('report.from') }}</span>
        <input type="date" wire:model.live="from" max="{{ $to }}">
    </label>

    <label class="filters__field">
        <span class="pos__label">{{ __('report.to') }}</span>
        <input type="date" wire:model.live="to" min="{{ $from }}">
    </label>

    <div class="filters__presets">
        @foreach (['today', 'last_7', 'last_30', 'this_month', 'last_month', 'this_year', 'last_year'] as $preset)
            <button type="button" class="pos__chip" wire:click="applyPreset('{{ $preset }}')">
                {{ __('report.preset.'.$preset) }}
            </button>
        @endforeach

        {{ $slot }}
    </div>
</div>

<x-filament-panels::page>
    {{-- Pintasan rentang di kiri, unduhan di kanan. Rentangnya tetap bisa
         diketik sendiri di isian tanggal yang tampil di atas tabel. --}}
    <div class="report__actions report__actions--split">
        <div class="filters__presets">
            <span class="report__actions-label">{{ __('report.period_shortcut') }}</span>

            @foreach (['today', 'yesterday', 'last_7', 'last_30', 'this_month', 'last_month', 'all'] as $preset)
                <button type="button" class="pos__chip" wire:click="applyPeriod('{{ $preset }}')">
                    {{ __('report.preset.'.$preset) }}
                </button>
            @endforeach
        </div>

        <div class="filters__presets">
            <button type="button" class="pos__chip" wire:click="exportExcel">
                {{ __('report.export_excel') }}
            </button>

            <a class="pos__chip" href="{{ $this->pdfUrl() }}" target="_blank" rel="noopener">
                {{ __('report.pdf') }}
            </a>

            <button type="button" class="pos__chip" onclick="window.print()">
                {{ __('report.print') }}
            </button>
        </div>
    </div>

    {{-- Asal angkanya ditulis di layar; lihat Reports\ExpenseReport. --}}
    <p class="report__note">{{ $this->source() }}</p>

    {{ $this->table }}
</x-filament-panels::page>

<x-filament-panels::page>
    {{-- Unduhan mengikuti penyaring, pencarian, dan urutan yang sedang
         dipakai di tabel; lihat Reports\ExpenseReport. --}}
    <div class="report__actions">
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

<x-filament-panels::page>
    {{-- Asal angkanya ditulis di layar; lihat Reports\ExpenseReport. --}}
    <p class="report__note">{{ $this->source() }}</p>

    {{ $this->table }}
</x-filament-panels::page>

{{-- Tenggat satu tugas: merah kalau lewat, kuning kalau hari ini atau besok. --}}
@if ($task->deadline)
    @php
        $status = $task->completed ? 'normal' : $task->deadlineStatus();
    @endphp

    <x-filament::badge size="sm" icon="heroicon-m-calendar" :color="match ($status) { 'overdue' => 'danger', 'soon' => 'warning', default => 'gray' }">
        {{ $task->deadline->translatedFormat('d M Y') }}
        @if ($status !== 'normal')
            · {{ __('meeting.deadline.'.$status) }}
        @endif
    </x-filament::badge>
@endif

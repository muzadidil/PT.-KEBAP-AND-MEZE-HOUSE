{{-- Proyek, kategori, prioritas: dipakai isian tugas baru, ubah, dan impor. --}}
<x-filament::input.wrapper :title="__('meeting.field.project')">
    <x-filament::input.select wire:model="{{ $model }}.project_id">
        <option value="">{{ __('meeting.no_project') }}</option>
        @foreach ($this->projects as $project)
            <option value="{{ $project->id }}">{{ $project->name }}</option>
        @endforeach
    </x-filament::input.select>
</x-filament::input.wrapper>

<x-filament::input.wrapper :title="__('meeting.field.category')">
    <x-filament::input.select wire:model="{{ $model }}.category">
        @foreach (\App\Models\MeetingTask::CATEGORIES as $value)
            <option value="{{ $value }}">{{ __('meeting.category.'.$value) }}</option>
        @endforeach
    </x-filament::input.select>
</x-filament::input.wrapper>

<x-filament::input.wrapper :title="__('meeting.field.priority')">
    <x-filament::input.select wire:model="{{ $model }}.priority">
        @foreach (\App\Models\MeetingTask::PRIORITIES as $value)
            <option value="{{ $value }}">{{ __('meeting.priority.'.$value) }}</option>
        @endforeach
    </x-filament::input.select>
</x-filament::input.wrapper>

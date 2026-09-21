<x-filament-panels::page>
    {{ $this->content }}

    <p class="report__note">{{ __('zeytin.import.safe') }}</p>
    <p class="report__note">{{ __('zeytin.template.hint') }}</p>

    @if ($results)
        {{-- Satu baris per sheet. Sheet yang gagal tetap muncul dengan
             alasannya, bukan hilang dari daftar. --}}
        <div class="report__wrap">
            <table class="report">
                <thead>
                    <tr>
                        <th>{{ __('zeytin.import.sheet') }}</th>
                        <th class="num">{{ __('zeytin.import.rows') }}</th>
                        <th>{{ __('zeytin.import.range') }}</th>
                        <th class="num">{{ __('zeytin.import.replaced') }}</th>
                        <th>{{ __('zeytin.field.status') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($results as $result)
                        <tr>
                            <td>{{ $result['sheet'] }}</td>
                            <td class="num">{{ $result['imported'] }}</td>
                            <td>{{ $result['range'] ?: '—' }}</td>
                            <td class="num">{{ $result['replaced'] ?: '—' }}</td>
                            <td class="{{ $result['error'] ? 'report__negative' : '' }}">
                                {{ $result['error']
                                    ? __('zeytin.import.error.'.$result['error'])
                                    : __('zeytin.import.ok') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>

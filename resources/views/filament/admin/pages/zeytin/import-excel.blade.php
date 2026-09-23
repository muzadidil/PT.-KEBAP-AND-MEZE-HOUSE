<x-filament-panels::page>
    {{ $this->content }}

    <p class="report__note">{{ __('zeytin.import.safe') }}</p>
    <p class="report__note">{{ __('zeytin.template.hint') }}</p>

    @if ($review)
        {{-- Langkah kedua: baris yang sama dengan ketikan manual. Belum ada
             yang disimpan sampai "Lanjutkan impor" ditekan. --}}
        <div class="meeting__panel import-review">
            <strong class="import-review__title">
                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="import-review__icon" />
                {{ __('zeytin.import.review.title') }}
            </strong>
            <p class="report__note">{{ __('zeytin.import.review.hint') }}</p>

            <div class="report__wrap">
                <table class="report">
                    <thead>
                        <tr>
                            <th>{{ __('zeytin.import.sheet') }}</th>
                            <th class="num">{{ __('zeytin.import.review.line') }}</th>
                            <th>{{ __('zeytin.import.review.date') }}</th>
                            <th>{{ __('zeytin.import.review.content') }}</th>
                            <th>{{ __('zeytin.import.review.include') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($review as $row)
                            <tr wire:key="review-{{ $row['id'] }}">
                                <td>{{ $row['sheet'] }}</td>
                                <td class="num">{{ $row['line'] }}</td>
                                <td>{{ $row['date'] ? \Illuminate\Support\Carbon::parse($row['date'])->translatedFormat('j M Y') : '—' }}</td>
                                <td>{{ $row['label'] }}</td>
                                <td>
                                    <label class="import-review__check">
                                        <input type="checkbox" value="{{ $row['id'] }}" wire:model="include">
                                        {{ __('zeytin.import.review.include') }}
                                    </label>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="meeting__row">
                <x-filament::button icon="heroicon-m-arrow-up-tray" wire:click="continueImport">
                    {{ __('zeytin.import.review.continue') }}
                </x-filament::button>
                <x-filament::button color="gray" wire:click="cancelImport">
                    {{ __('zeytin.import.review.cancel') }}
                </x-filament::button>
            </div>
        </div>
    @endif

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
                        <th class="num">{{ __('zeytin.import.skipped') }}</th>
                        <th>{{ __('zeytin.field.status') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($results as $result)
                        <tr>
                            <td>{{ $result['sheet'] }}</td>
                            <td class="num">{{ $result['error'] ? '—' : $result['imported'] }}</td>
                            <td>{{ $result['range'] ?: '—' }}</td>
                            <td class="num">{{ $result['replaced'] ?: '—' }}</td>
                            <td class="num">{{ ($result['skipped'] ?? 0) ?: '—' }}</td>
                            <td class="{{ $result['error'] ? 'report__negative' : '' }}">
                                {{ $result['error']
                                    ? __('zeytin.import.error.'.$result['error'])
                                    : __('zeytin.import.ok') }}

                                {{-- Baris yang tanggalnya di luar bulan, satu per satu --}}
                                @foreach ($result['rows'] ?? [] as $row)
                                    <div class="import-review__row">
                                        {{ __('zeytin.import.error.row_out_of_month', [
                                            'row' => $row['line'],
                                            'date' => \Illuminate\Support\Carbon::parse($row['date'])->translatedFormat('j M Y'),
                                            'month' => \Illuminate\Support\Carbon::parse($this->data['payroll_month'])->translatedFormat('F Y'),
                                        ]) }}
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>

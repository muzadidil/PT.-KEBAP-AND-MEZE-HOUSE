{{-- Laporan PDF Progres Rapat; lihat App\Support\Meetings\MeetingPdf. --}}
@php
    $summary = $pdf->summary();
    $progress = $report->progress($scope);
    $statusColor = ['done' => '#15803d', 'in_progress' => '#b45309', 'not_started' => '#64748b', 'pending' => '#64748b'];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('meeting.pdf.title') }} — {{ $report->title($scope) }}</title>
    <style>
        @page { margin: 36px 36px 44px 36px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #0f172a; }

        .head { background: #0f6e6e; color: #fff; padding: 14px 16px; margin: -36px -36px 16px -36px; }
        .head__title { font-size: 16px; font-weight: bold; }
        .head__meta { font-size: 9px; margin-top: 4px; }

        .bar { height: 8px; background: #e2e8f0; border-radius: 4px; }
        .bar span { display: block; height: 8px; background: #0f6e6e; border-radius: 4px; }

        table.stats { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 12px -6px 16px; }
        table.stats td { border: 1px solid #e2e8f0; padding: 8px; text-align: center; }
        .stats__value { font-size: 15px; font-weight: bold; }
        .stats__label { font-size: 8px; color: #64748b; text-transform: uppercase; }

        h2 { font-size: 11px; margin: 14px 0 6px; color: #0f6e6e; }

        /* Lebar tetap, supaya kolom tiap proyek sejajar satu sama lain. */
        table.tasks { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.tasks th { background: #0f6e6e; color: #fff; padding: 4px 5px; text-align: left; font-size: 8.5px; }
        table.tasks td { padding: 4px 5px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        table.tasks td.num { text-align: right; white-space: nowrap; }
        tr.sub td { color: #475569; font-size: 8.5px; }

        .foot { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 7px; color: #64748b; }
    </style>
</head>
<body>
    <div class="head">
        <div class="head__title">{{ __('meeting.pdf.title') }} — {{ $report->title($scope) }}</div>
        <div class="head__meta">{{ now()->translatedFormat('j F Y') }} · {{ __('meeting.share.progress', ['percent' => $progress]) }}</div>
    </div>

    <div class="bar"><span style="width: {{ $progress }}%"></span></div>

    <table class="stats">
        <tr>
            @foreach (['total', 'done', 'in_progress', 'not_started'] as $key)
                <td>
                    <div class="stats__value">{{ $summary[$key] }}</div>
                    <div class="stats__label">{{ __('meeting.pdf.'.$key) }}</div>
                </td>
            @endforeach
        </tr>
    </table>

    @forelse ($report->groups($scope) as $group)
        <h2>{{ $group['name'] }} — {{ $report->board()->mean($group['tasks']) }}%</h2>

        <table class="tasks">
            <thead>
                <tr>
                    <th style="width: 42%">{{ __('meeting.field.text') }}</th>
                    <th style="width: 11%">{{ __('meeting.field.category') }}</th>
                    <th style="width: 11%">{{ __('meeting.field.priority') }}</th>
                    <th style="width: 14%">{{ __('meeting.field.deadline') }}</th>
                    <th style="width: 14%">{{ __('meeting.pdf.status') }}</th>
                    <th style="width: 8%; text-align: right">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pdf->rows($group['tasks']) as $row)
                    <tr class="{{ $row['depth'] ? 'sub' : '' }}">
                        <td style="padding-left: {{ 5 + $row['depth'] * 12 }}px">
                            {{ $row['depth'] ? '◦ ' : '' }}{{ $row['text'] }}
                        </td>
                        <td>{{ $row['category'] }}</td>
                        <td>{{ $row['priority'] }}</td>
                        <td>{{ $row['deadline'] }}</td>
                        <td style="color: {{ $statusColor[$row['status']] }}">{{ __('meeting.pdf.'.$row['status']) }}</td>
                        <td class="num">{{ $row['progress'] !== null ? $row['progress'].'%' : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p>{{ __('meeting.share.empty') }}</p>
    @endforelse

    <div class="foot">{{ __('meeting.pdf.generated') }}: {{ now(config('zeytin.letterhead.timezone'))->translatedFormat('d M Y H:i T') }}</div>
</body>
</html>

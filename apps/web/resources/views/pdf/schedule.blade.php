@php
    use App\Models\MasterData\CourseLecturer;
    use App\Services\Scheduling\Export\ScheduleLine;
    /** @var \App\Services\Scheduling\Export\ScheduleExport $export */
    /** @var \App\Services\Scheduling\Export\ScheduleMatrix $matrix */
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $export->title }}</title>
    <style>
        @page { size: A4 landscape; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "DejaVu Sans", Arial, sans-serif; font-size: 8.5pt; color: #0f172a; }
        header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #1e3a8a; padding-bottom: 6px; margin-bottom: 10px; }
        h1 { margin: 0; font-size: 15pt; color: #1e3a8a; }
        .subtitle { margin-top: 2px; color: #475569; font-size: 9.5pt; }
        .meta { text-align: right; color: #64748b; font-size: 8pt; }
        .draft-badge { display: inline-block; margin-top: 4px; padding: 2px 8px; border-radius: 4px; background: #fee2e2; color: #b91c1c; font-weight: bold; }
        .watermark { position: fixed; top: 40%; left: 0; right: 0; text-align: center; font-size: 110pt; font-weight: bold; color: rgba(185, 28, 28, 0.08); transform: rotate(-20deg); z-index: -1; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 5px; vertical-align: top; }
        th { background: #e2e8f0; font-weight: bold; text-align: center; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        .matrix .session { width: 70px; background: #f8fafc; font-weight: bold; }
        .matrix .session small { display: block; font-weight: normal; color: #475569; }
        .entry { border-left: 3px solid #3b82f6; background: #eff6ff; padding: 3px 4px; margin-bottom: 3px; border-radius: 2px; }
        .entry:last-child { margin-bottom: 0; }
        .entry.overridden { border-left-color: #f59e0b; background: #fffbeb; }
        .entry b { display: block; }
        .entry span { color: #475569; }
        .cell-time { color: #64748b; font-size: 7.5pt; margin-bottom: 2px; }
        .list { break-before: page; }
        .list h2 { font-size: 11pt; margin: 0 0 6px; color: #1e3a8a; }
        .list td.num { text-align: center; }
        .list tbody tr:nth-child(even) td { background: #f8fafc; }
        .legend { margin-top: 6px; color: #64748b; font-size: 7.5pt; }
    </style>
</head>
<body>
@if ($export->isDraft)
    <div class="watermark">DRAF</div>
@endif

<header>
    <div>
        <h1>{{ $export->title }}</h1>
        <div class="subtitle">{{ $export->subtitle }}</div>
        @if ($export->isDraft)
            <div class="draft-badge">{{ __('DRAFT — not published yet') }}</div>
        @endif
    </div>
    <div class="meta">
        SIMJADWAL<br>
        {{ __('Printed :date', ['date' => $export->generatedAt->translatedFormat('j F Y H:i')]) }}
    </div>
</header>

<table class="matrix">
    <thead>
    <tr>
        <th class="session">{{ __('Session') }}</th>
        @foreach ($matrix->days as $day)
            <th>{{ $day->label() }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @foreach ($matrix->sessions as $session => $range)
        <tr>
            <td class="session">{{ __('Session') }} {{ $session }}<small>{{ $range }}</small></td>
            @foreach ($matrix->days as $day)
                @php($entries = $matrix->cell($session, $day))
                <td>
                    @if ($entries && ($time = $matrix->cellTime($session, $day)))
                        <div class="cell-time">{{ $time }}</div>
                    @endif
                    @foreach ($entries as $d)
                        <div class="entry {{ $d->is_overridden ? 'overridden' : '' }}">
                            <b>{{ ScheduleLine::course($d) }}</b>
                            <span>{{ ScheduleLine::lecturer($d) }} · {{ ScheduleLine::room($d) }}</span>
                        </div>
                    @endforeach
                </td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>
<div class="legend">{{ __('Orange border = changed manually by the faculty admin.') }}</div>

<section class="list">
    <h2>{{ __('Schedule list') }} ({{ $export->details->count() }})</h2>
    <table>
        <thead>
        <tr>
            <th style="width: 7%">{{ __('Day') }}</th>
            <th style="width: 9%">{{ __('Time') }}</th>
            <th style="width: 8%">{{ __('Code') }}</th>
            <th style="width: 24%">{{ __('Course') }}</th>
            <th style="width: 4%">SKS</th>
            <th style="width: 5%">{{ __('Class') }}</th>
            <th style="width: 20%">{{ __('Lecturer') }}</th>
            <th style="width: 8%">{{ __('Room') }}</th>
            <th style="width: 15%">{{ __('Study Program') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($export->details as $d)
            <tr>
                <td>{{ $d->timeSlot->day->label() }}</td>
                <td class="num">{{ $d->timeSlot->start_time }}–{{ $d->timeSlot->end_time }}</td>
                <td>{{ $d->course->code }}</td>
                <td>{{ $d->course->name }}</td>
                <td class="num">{{ $d->course->sks }}</td>
                <td class="num">{{ CourseLecturer::labelFor($d->class_number) }}</td>
                <td>{{ ScheduleLine::lecturer($d) }}</td>
                <td>{{ ScheduleLine::room($d) }}</td>
                <td>{{ $d->course->studyProgram?->name }} · {{ __('Smt') }} {{ $d->course->semester }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>
</body>
</html>

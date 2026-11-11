@php
    use App\Models\MasterData\CourseLecturer;
    use App\Services\Scheduling\Export\ScheduleLine;
    /** @var \App\Services\Scheduling\Export\ScheduleExport $export */
    /** @var \App\Services\Scheduling\Export\ScheduleMatrix $matrix */

    // A crowded schedule (TPB: dozens of parallel classes per slot) shows courses with class letters only.
    // Lecturers and rooms stay in the list that follows the matrix.
    $busiest = 0;
    foreach ($matrix->sessions as $session => $range) {
        foreach ($matrix->days as $day) {
            $busiest = max($busiest, count($matrix->cell($session, $day)));
        }
    }
    $compact = $busiest > 6;
    $showProgram = $export->details->contains(fn ($d) => $d->course->studyProgram !== null);
    $lastDay = null;
    // Same tones as the TPB schedule page: one per cohort.
    $cohortsShown = [];
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
        header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 12px; }
        h1 { margin: 0; font-size: 16pt; color: #1e3a8a; }
        .subtitle { margin-top: 3px; color: #475569; font-size: 9.5pt; }
        .meta { text-align: right; color: #64748b; font-size: 8pt; line-height: 1.5; }
        .draft-badge { display: inline-block; margin-top: 5px; padding: 2px 8px; border-radius: 4px; background: #fee2e2; color: #b91c1c; font-weight: bold; font-size: 8pt; }
        .watermark { position: fixed; top: 40%; left: 0; right: 0; text-align: center; font-size: 110pt; font-weight: bold; color: rgba(185, 28, 28, 0.07); transform: rotate(-20deg); z-index: -1; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 6px; vertical-align: top; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }

        .matrix thead th { background: #1e3a8a; border-color: #1e3a8a; color: #fff; font-weight: bold; text-align: center; padding: 6px; }
        .matrix .session { width: 76px; background: #f1f5f9; font-weight: bold; color: #1e3a8a; white-space: nowrap; }
        .matrix thead .session { background: #1e3a8a; color: #fff; }
        .matrix .session small { display: block; margin-top: 2px; font-weight: normal; color: #475569; font-size: 7.5pt; }
        .cell-time { color: #64748b; font-size: 7.5pt; margin-bottom: 3px; }

        .entry { padding: 3px 0 4px; border-bottom: 1px solid #e2e8f0; }
        .entry:last-child { border-bottom: 0; padding-bottom: 0; }
        .entry b { display: block; }
        .entry span { display: block; color: #475569; }
        .entry span.overridden { background: #fef3c7; }

        .compact .entry { margin-bottom: 3px; padding: 3px 5px; border: 1px solid #e2e8f0; border-radius: 3px; background: #f1f5f9; }
        .compact .entry.c-maba, .swatch.c-maba { background: #eff6ff; border-color: #bfdbfe; }
        .compact .entry.c-non_maba, .swatch.c-non_maba { background: #eef2ff; border-color: #c7d2fe; }
        .compact .entry.c-campuran, .swatch.c-campuran { background: #f0fdfa; border-color: #99f6e4; }
        .compact .entry.c-mengulang, .swatch.c-mengulang { background: #fffbeb; border-color: #fde68a; }
        .compact .entry.c-pilihan, .swatch.c-pilihan { background: #f3f4f6; border-color: #d1d5db; }
        .compact .entry:last-child { margin-bottom: 0; padding-bottom: 3px; }
        .compact .entry b { font-size: 8pt; line-height: 1.25; }
        .compact .entry span { font-size: 7pt; }
        .compact td { padding: 4px; }

        .list { break-before: page; }
        .list h2 { font-size: 11pt; margin: 0 0 8px; color: #1e3a8a; }
        .list th { background: #1e3a8a; border-color: #1e3a8a; color: #fff; text-align: left; font-size: 8pt; }
        .list td { font-size: 8pt; }
        .list td.num { text-align: center; white-space: nowrap; }
        .list tr.day td { background: #e2e8f0; color: #0f172a; font-weight: bold; font-size: 8.5pt; padding: 4px 6px; }
        .list tbody tr:not(.day):nth-child(even) td { background: #f8fafc; }
        .legend { margin-top: 6px; color: #64748b; font-size: 7.5pt; }
        .cohort-legend { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 8px; color: #475569; font-size: 7.5pt; }
        .cohort-legend span { display: inline-flex; align-items: center; gap: 5px; }
        .swatch { display: inline-block; width: 10px; height: 10px; border: 1px solid; border-radius: 2px; }
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

<table class="matrix {{ $compact ? 'compact' : '' }}">
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
                    @if ($compact)
                        @foreach (collect($entries)->groupBy(fn ($d) => $d->course_id.'|'.($export->cohortOf($d) ?? ''))->sortBy(fn ($group) => $group[0]->course->name) as $group)
                            @php($cohort = $export->cohortOf($group[0]))
                            @php($cohort && ($cohortsShown[$cohort] = true))
                            <div class="entry {{ $cohort ? 'c-'.$cohort : '' }}">
                                <b>{{ $group[0]->course->name }}</b>
                                <span>{{ $group[0]->course->code }} · {{ __('Class') }} {{ ScheduleLine::classRange($group->all()) }}</span>
                            </div>
                        @endforeach
                    @else
                        @foreach (ScheduleLine::groupByCourse($entries) as $group)
                            <div class="entry">
                                <b>{{ $group['title'] }}</b>
                                @foreach ($group['details'] as $d)
                                    <span class="{{ $export->showOverrideIndicators && $d->is_overridden ? 'overridden' : '' }}">{{ ScheduleLine::classLine($d) }}</span>
                                @endforeach
                            </div>
                        @endforeach
                    @endif
                </td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>
@if ($compact && $cohortsShown)
    <div class="cohort-legend">
        @foreach (\App\Enums\MasterData\TpbCohort::cases() as $case)
            @if (isset($cohortsShown[$case->value]))
                <span><i class="swatch c-{{ $case->value }}"></i>{{ $case->label() }}</span>
            @endif
        @endforeach
    </div>
@endif
@if ($export->showOverrideIndicators && $export->details->contains(fn ($d) => $d->is_overridden))
    <div class="legend">{{ __('Yellow background = changed manually by the study program admin.') }}</div>
@endif

<section class="list">
    <h2>{{ __('Schedule list') }} ({{ $export->details->count() }})</h2>
    <table>
        <thead>
        <tr>
            <th style="width: 9%">{{ __('Time') }}</th>
            <th style="width: 9%">{{ __('Code') }}</th>
            <th style="width: {{ $showProgram ? 24 : 30 }}%">{{ __('Course') }}</th>
            <th style="width: 5%">SKS</th>
            <th style="width: 6%">{{ __('Class') }}</th>
            <th style="width: {{ $showProgram ? 20 : 28 }}%">{{ __('Lecturer') }}</th>
            <th style="width: 8%">{{ __('Room') }}</th>
            @if ($showProgram)
                <th style="width: 19%">{{ __('Study Program') }}</th>
            @endif
        </tr>
        </thead>
        <tbody>
        @foreach ($export->details as $d)
            @if ($lastDay !== $d->timeSlot->day->value)
                @php($lastDay = $d->timeSlot->day->value)
                <tr class="day"><td colspan="{{ $showProgram ? 8 : 7 }}">{{ $d->timeSlot->day->label() }}</td></tr>
            @endif
            <tr>
                <td class="num">{{ $d->timeSlot->start_time }}–{{ $d->timeSlot->end_time }}</td>
                <td>{{ $d->course->code }}</td>
                <td>{{ $d->course->name }}</td>
                <td class="num">{{ $d->course->sks }}</td>
                <td class="num">{{ CourseLecturer::labelFor($d->class_number) }}</td>
                <td>{{ ScheduleLine::lecturer($d) }}</td>
                <td>{{ ScheduleLine::room($d) }}</td>
                @if ($showProgram)
                    <td>{{ collect([$d->course->studyProgram?->name, $d->course->semester ? __('Smt').' '.$d->course->semester : null])->filter()->implode(' · ') }}</td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>
</section>
</body>
</html>

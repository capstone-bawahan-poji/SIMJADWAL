@php
    use App\Models\MasterData\CourseLecturer;
    use App\Services\Scheduling\Export\ExportColors;
    use App\Services\Scheduling\Export\RoomGrid;
    use App\Services\Scheduling\Export\ScheduleMatrix;
    /** @var \App\Services\Scheduling\Export\RoomGridExport $export */
    /** @var \App\Services\Scheduling\Export\RoomGrid $grid */
    $superadmin = $grid->mode === ExportColors::MODE_SUPERADMIN;
    $clashes = $grid->clashCount();
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $export->title }}</title>
    <style>
        @page { size: A3 landscape; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "DejaVu Sans", Arial, sans-serif; font-size: 7pt; color: #1a1a1a; }
        header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #1e3a8a; padding-bottom: 6px; margin-bottom: 8px; }
        h1 { margin: 0; font-size: 15pt; color: #1e3a8a; }
        .subtitle { margin-top: 3px; color: #475569; font-size: 9pt; }
        .badge { display: inline-block; margin-top: 4px; padding: 1px 7px; border-radius: 8px; font-size: 6.5pt; font-weight: bold; }
        .badge.ok { background: #dcfce7; color: #166534; }
        .badge.bad { background: #dc2626; color: #fff; }
        .meta { text-align: right; color: #64748b; font-size: 8pt; line-height: 1.5; }
        .legend { display: flex; flex-wrap: wrap; gap: 4px 10px; margin-bottom: 8px; font-size: 7pt; color: #334155; }
        .legend span { display: inline-flex; align-items: center; gap: 4px; }
        .legend i { display: inline-block; width: 12px; height: 10px; border: 0.5pt solid #b0b8c8; border-radius: 2px; }
        .legend i.clash { background: #fff; box-shadow: inset 0 0 0 1.5px #dc2626; border-color: #dc2626; }
        .building { break-before: page; }
        .building:first-of-type { break-before: auto; }
        h2 { font-size: 11pt; color: #1e3a8a; margin: 0 0 5px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 0.5pt solid #b0b8c8; padding: 0; vertical-align: top; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        thead th { background: #1e3a8a; border-color: #1e3a8a; color: #fff; text-align: center; font-size: 8pt; padding: 3px 0; }
        th.label, td.label { width: 54px; }
        td.label { padding: 3px 4px; background: #f1f5f9; color: #1e3a8a; font-weight: bold; white-space: nowrap; }
        td.label small { display: block; font-weight: normal; color: #475569; font-size: 6pt; }
        tr.day td { background: #e2e8f0; font-weight: bold; font-size: 7.5pt; padding: 2px 5px; }
        /* Every room cell is the same size; what does not fit is cut off. */
        .cell { height: 33pt; overflow: hidden; padding: 2px 3px; color: #1a1a1a; }
        .cell.edge { padding-left: 3px; border-left: 3px solid; }
        .cell.clash { box-shadow: inset 0 0 0 2px #dc2626; }
        .cell b, .cell span { display: block; overflow: hidden; overflow-wrap: anywhere; word-break: break-word; }
        .cell b { font-size: 6pt; line-height: 1.15; max-height: 2.3em; }
        .cell span { margin-top: 1px; color: #1a1a1a; font-size: 5.5pt; line-height: 1.15; max-height: 2.3em; }
        .cell span.group { color: #555; font-size: 7pt; line-height: 1; margin-top: 0; }
        .cell .sep { border-top: 1px dashed #94a3b8; margin: 1px 0; }
    </style>
</head>
<body>
<header>
    <div>
        <h1>{{ $export->title }}</h1>
        <div class="subtitle">{{ $export->subtitle }}</div>
        <span class="badge {{ $clashes ? 'bad' : 'ok' }}">{{ $clashes ? __(':count clashes', ['count' => $clashes]) : __('No clash') }}</span>
    </div>
    <div class="meta">
        SIMJADWAL<br>
        {{ __('Printed :date', ['date' => $export->generatedAt->translatedFormat('j F Y H:i')]) }}
    </div>
</header>

<div class="legend">
    @foreach ($grid->legend as $entry)
        <span><i style="background: {{ $entry['hex'] }}; @if ($entry['leftBorder']) border-left: 3px solid {{ $entry['leftBorder'] }}; @endif"></i>{{ $entry['label'] }}</span>
    @endforeach
    @if ($clashes)
        <span><i class="clash"></i>{{ __('Clash') }}</span>
    @endif
</div>

@foreach ($grid->buildings as $building => $rooms)
    <section class="building">
        <h2>{{ RoomGrid::buildingLabel($building) }}</h2>
        <table>
            <thead>
            <tr>
                <th class="label">{{ __('Session') }}</th>
                @foreach ($rooms as $room)
                    <th>{{ $room->code }}</th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @php($day = null)
            @foreach ($grid->timeSlots as $slot)
                @if ($slot->day !== $day)
                    @php($day = $slot->day)
                    <tr class="day"><td colspan="{{ count($rooms) + 1 }}">{{ $day->label() }}</td></tr>
                @endif
                <tr>
                    <td class="label">{{ __('Session') }} {{ $slot->session }}<small>{{ ScheduleMatrix::range($slot) }}</small></td>
                    @foreach ($rooms as $room)
                        @php($genes = $grid->cell($slot, $room))
                        @php($color = $grid->color($slot, $room))
                        <td @if ($color) style="background: {{ $color['hex'] }}" @endif>
                            <div class="cell {{ $color && $color['leftBorder'] ? 'edge' : '' }} {{ $grid->isClash($slot, $room) ? 'clash' : '' }}" @if ($color && $color['leftBorder']) style="border-left-color: {{ $color['leftBorder'] }}" @endif>
                                @foreach ($genes as $i => $d)
                                    @if ($i > 0)<div class="sep"></div>@endif
                                    <b>{{ $d->course->name }} · {{ CourseLecturer::labelFor($d->class_number) }}</b>
                                    <span>{{ $d->lecturer->name }}</span>
                                    @if ($superadmin)<span class="group">{{ $grid->groupOf($d) }}</span>@endif
                                @endforeach
                            </div>
                        </td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
@endforeach
</body>
</html>

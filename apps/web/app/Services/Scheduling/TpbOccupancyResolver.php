<?php

namespace App\Services\Scheduling;

use App\Models\Scheduling\TpbOccupancyImport;

/**
 * What TPB holds in a period, seen from one faculty. A faculty may hand out cells only once this
 * is known, so its programs never collide with TPB. Sources, first match wins:
 *
 *   1. run: the TPB run in force (active and published), from the GA or from a TPB admin import.
 *      Gives rooms, lecturers and the program blocks of the TPB groups.
 *   2. import: the file this faculty imported itself. Gives rooms, and lecturers when the file names them.
 *
 * Neither: source null, and the faculty cannot hand out cells yet.
 *
 * Not cached: controllers keep their services between requests, and an import or a TPB publish
 * must show at once. Callers that need it twice keep the result.
 */
readonly class TpbOccupancyResolver
{
    public function __construct(
        private TpbRunResolver $tpbRuns,
        private TpbBlockService $tpbBlocks,
    ) {}

    /**
     * @return array{source: 'run'|'import'|null, run_id: int|null, import_id: int|null, rooms: array<int, list<int>>, lecturers: array<int, list<int>>, programs: array<int, array<int, list<int>>>, retake: array<int, list<int>>}
     */
    public function forFaculty(int $facultyId, string $period): array
    {
        return $this->build($facultyId, $period);
    }

    /**
     * "slot:room" keys of the cells TPB holds.
     *
     * @return array<string, true>
     */
    public function cells(int $facultyId, string $period): array
    {
        $cells = [];
        foreach ($this->forFaculty($facultyId, $period)['rooms'] as $roomId => $slots) {
            foreach ($slots as $slot) {
                $cells["{$slot}:{$roomId}"] = true;
            }
        }

        return $cells;
    }

    public function forget(): void
    {
        $this->tpbRuns->forget();
    }

    /**
     * @return array{source: 'run'|'import'|null, run_id: int|null, import_id: int|null, rooms: array<int, list<int>>, lecturers: array<int, list<int>>, programs: array<int, array<int, list<int>>>, retake: array<int, list<int>>}
     */
    private function build(int $facultyId, string $period): array
    {
        $this->tpbRuns->forget();
        $run = $this->tpbRuns->active($period);
        if ($run !== null) {
            $blocks = $this->tpbBlocks->fresh($period);

            return [
                'source' => 'run',
                'run_id' => $run->id,
                'import_id' => null,
                'rooms' => $blocks['rooms'],
                'lecturers' => $blocks['lecturers'],
                'programs' => $blocks['programs'],
                'retake' => $blocks['retake'],
            ];
        }

        $empty = ['source' => null, 'run_id' => null, 'import_id' => null, 'rooms' => [], 'lecturers' => [], 'programs' => [], 'retake' => []];
        $import = TpbOccupancyImport::query()->with('rows')->where('faculty_id', $facultyId)->where('period', $period)->first();
        if ($import === null) {
            return $empty;
        }

        $rooms = [];
        $lecturers = [];
        foreach ($import->rows as $row) {
            $rooms[$row->room_id][] = $row->time_slot_id;
            if ($row->lecturer_id !== null) {
                $lecturers[$row->lecturer_id][] = $row->time_slot_id;
            }
        }
        $unique = fn (array $slots) => collect($slots)->unique()->sort()->values()->all();
        ksort($rooms);
        ksort($lecturers);

        return [
            ...$empty,
            'source' => 'import',
            'import_id' => $import->id,
            'rooms' => array_map($unique, $rooms),
            'lecturers' => array_map($unique, $lecturers),
        ];
    }
}

<?php

namespace App\Services\Scheduling;

use App\Enums\Constraint\ConstraintCode;
use App\Exceptions\Shared\ApiException;
use App\Models\Constraint\ConstraintType;
use App\Models\Constraint\LecturerPreference;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\MasterData\TpbGroup;
use App\Models\MasterData\TpbOffering;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

/**
 * Everything the desktop GA needs for one schedule, plus a fingerprint of it. Two scopes:
 *
 *   - study_program: the active courses of one program. Needs the TPB data of its faculty
 *     (TpbOccupancyResolver) and the cells the faculty gave it: a gene goes to one cell (slot,
 *     room) of cells. tpb_blocks and lecturer_busy are slots the GA must keep away from.
 *   - tpb (build(null, ...)): every TPB offering of the period. One gene per class meeting,
 *     classes of one group share a slot, rooms are the shared ones.
 */
readonly class SchedulingDatasetService
{
    public function __construct(
        private DatabaseManager $db,
        private TpbRunResolver $tpbRuns,
        private TpbOccupancyResolver $tpbOccupancy,
        private RoomSlotAllocationService $allocations,
        private ProgramScheduleState $state,
    ) {}

    /**
     * @param  list<int>  $reviseGroupIds  TPB only: groups to place again after the TPB schedule is published
     * @param  bool  $checkEditable  false only to compare fingerprints of a program that handed its schedule in
     * @return array<string, mixed>
     */
    public function build(?StudyProgram $studyProgram, string $period, array $reviseGroupIds = [], bool $checkEditable = true): array
    {
        return $studyProgram === null
            ? $this->buildTpb($period, $reviseGroupIds)
            : $this->buildStudyProgram($studyProgram, $period, $checkEditable);
    }

    /**
     * SHA-256 over the parts that decide a schedule. Same data, same hash.
     *
     * @param  array<string, mixed>  $data
     */
    public function fingerprint(array $data): string
    {
        return 'sha256:'.hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStudyProgram(StudyProgram $program, string $period, bool $checkEditable): array
    {
        $tpb = $this->tpbOccupancy->forFaculty($program->faculty_id, $period);
        if ($tpb['source'] === null) {
            throw ApiException::invalidState(__('The TPB schedule of :period is not available yet. Programs are scheduled after TPB.', ['period' => Period::label($period)]));
        }
        if ($checkEditable) {
            $this->state->assertEditable($program, $period);
        }

        return $this->db->transaction(function () use ($program, $period, $tpb) {
            $cells = $this->allocations->cellsOf($program, $period);
            if ($cells === []) {
                throw ApiException::invalidState(__('The faculty has not given this program any room slots for :period yet.', ['period' => Period::label($period)]));
            }

            $courses = Course::query()
                ->where('study_program_id', $program->id)
                ->where('is_tpb', false)
                ->where('is_active', true)
                ->whereIn('semester', Period::semesters($period))
                ->orderBy('id')
                ->get();
            if ($courses->isEmpty()) {
                throw ApiException::invalidState(__('The program has no active course to schedule in :period.', ['period' => Period::label($period)]));
            }

            $classes = CourseLecturer::query()
                ->whereIn('course_id', $courses->pluck('id'))
                ->orderBy('course_id')
                ->orderBy('class_number')
                ->get();
            $assigned = $classes->whereNotNull('lecturer_id')->countBy('course_id');
            $unassigned = $classes->whereNull('lecturer_id')->countBy('course_id');
            $incomplete = $courses->filter(fn (Course $course) => ($assigned[$course->id] ?? 0) < $course->parallel_class_count || isset($unassigned[$course->id]))->count();
            if ($incomplete > 0) {
                throw ApiException::invalidState(__(':count courses still have classes without a lecturer. Map their lecturers before running the GA.', ['count' => $incomplete]));
            }
            $lecturerIds = $classes->pluck('lecturer_id')->unique()->sort()->values();
            $lecturerSet = $lecturerIds->flip();
            $roomIds = collect($cells)->pluck('room_id')->unique();

            $data = [
                'courses' => $courses->map(fn (Course $course) => [
                    'id' => $course->id,
                    'study_program_id' => $course->study_program_id,
                    'code' => $course->code,
                    'name' => $course->name,
                    'sks' => $course->sks,
                    'semester' => $course->semester,
                    'parallel_class_count' => $course->parallel_class_count,
                    'class_capacity' => $course->class_capacity,
                    'is_tpb' => false,
                ])->values()->all(),
                'classes' => $classes->map(fn (CourseLecturer $class) => [
                    'course_id' => $class->course_id,
                    'class_number' => $class->class_number,
                    'lecturer_id' => $class->lecturer_id,
                ])->values()->all(),
                'lecturers' => $this->lecturers($lecturerIds),
                'time_slots' => $this->timeSlots(),
                'rooms' => $this->rooms(fn ($query) => $query->whereIn('id', $roomIds)),
                'cells' => array_map(fn (array $cell) => ['time_slot_id' => $cell['time_slot_id'], 'room_id' => $cell['room_id']], $cells),
                'constraint_types' => $this->constraintTypes(),
                'tpb_source' => ['type' => $tpb['source'], 'run_id' => $tpb['run_id'], 'import_id' => $tpb['import_id']],
                'tpb_run_id' => $tpb['run_id'],
                'tpb_blocks' => [
                    'study_programs' => collect($tpb['programs'][$program->id] ?? [])
                        ->map(fn (array $slots, int $semester) => ['study_program_id' => $program->id, 'semester' => $semester, 'time_slot_ids' => $slots])
                        ->sortBy('semester')->values()->all(),
                    'retake' => isset($tpb['retake'][$program->id])
                        ? [['study_program_id' => $program->id, 'time_slot_ids' => $tpb['retake'][$program->id]]]
                        : [],
                    'lecturers' => collect($tpb['lecturers'])
                        ->filter(fn (array $slots, int $id) => isset($lecturerSet[$id]))
                        ->map(fn (array $slots, int $id) => ['lecturer_id' => $id, 'time_slot_ids' => $slots])
                        ->sortBy('lecturer_id')->values()->all(),
                ],
                'lecturer_busy' => $this->lecturerBusy($program, $period, $lecturerIds->all()),
            ];

            return [
                'fingerprint' => $this->fingerprint($data),
                'generated_at' => now()->toIso8601String(),
                'scope' => 'study_program',
                'period' => $period,
                'faculty' => ['id' => $program->faculty->id, 'code' => $program->faculty->code, 'name' => $program->faculty->name],
                'study_program' => ['id' => $program->id, 'code' => $program->code, 'name' => $program->name],
                'study_programs' => [[
                    'id' => $program->id,
                    'code' => $program->code,
                    'name' => $program->name,
                    'constraint_status' => $program->constraint_status->value,
                    'constraint_reviewed_at' => $program->constraint_reviewed_at?->toIso8601String(),
                    'lecturers_count' => $program->lecturers()->count(),
                    'courses_count' => $courses->count(),
                    'included' => true,
                ]],
                ...$data,
                'occupied_rooms' => [],
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTpb(string $period, array $reviseGroupIds): array
    {
        return $this->db->transaction(function () use ($period, $reviseGroupIds) {
            $offerings = TpbOffering::query()
                ->with('course')
                ->where('period', $period)
                ->whereHas('course')
                ->orderBy('course_id')
                ->get();

            $classes = CourseLecturer::query()
                ->whereIn('tpb_offering_id', $offerings->pluck('id'))
                ->orderBy('course_id')
                ->orderBy('class_number')
                ->get();
            $missing = $classes->whereNull('lecturer_id')->count();
            if ($missing > 0) {
                throw ApiException::invalidState(__(':count TPB classes have no lecturer yet. Pick their lecturers before running the GA.', ['count' => $missing]));
            }

            $groups = TpbGroup::query()
                ->with(['offering:id,course_id', 'studyPrograms:id,code,name'])
                ->whereIn('tpb_offering_id', $offerings->pluck('id'))
                ->orderBy('id')
                ->get();
            $programs = StudyProgram::query()
                ->whereKey($groups->flatMap->studyPrograms->pluck('id')->unique())
                ->withCount(['lecturers', 'courses'])
                ->orderBy('code')
                ->get();

            $revision = $this->revision($period, $reviseGroupIds);
            $frozen = $revision['frozen'];

            $data = [
                'courses' => $offerings->map(fn (TpbOffering $offering) => [
                    'id' => $offering->course_id,
                    'study_program_id' => null,
                    'code' => $offering->course->code,
                    'name' => $offering->course->name,
                    'sks' => $offering->course->sks,
                    'semester' => null,
                    'parallel_class_count' => $offering->parallel_class_count,
                    'class_capacity' => $offering->class_capacity,
                    'is_tpb' => true,
                ])->values()->all(),
                'classes' => $classes->map(fn (CourseLecturer $class) => [
                    'course_id' => $class->course_id,
                    'class_number' => $class->class_number,
                    'lecturer_id' => $class->lecturer_id,
                    'tpb_group_id' => $class->tpb_group_id,
                    ...($revision['active'] && ! in_array($class->tpb_group_id, $revision['revise_group_ids'], true) && isset($frozen["{$class->course_id}:{$class->class_number}"])
                        ? $frozen["{$class->course_id}:{$class->class_number}"]
                        : []),
                ])->values()->all(),
                'tpb_groups' => $groups->map(fn (TpbGroup $group) => [
                    'id' => $group->id,
                    'course_id' => $group->offering->course_id,
                    'code' => $group->code,
                    'cohort' => $group->cohort->value,
                    'study_programs' => $group->studyPrograms->sortBy('id')->map(fn (StudyProgram $program) => [
                        'id' => $program->id,
                        'target_semester' => $program->pivot->target_semester === null ? null : (int) $program->pivot->target_semester,
                    ])->values()->all(),
                ])->values()->all(),
                'lecturers' => $this->lecturers($classes->pluck('lecturer_id')->unique(), withPreferences: false),
                'time_slots' => $this->timeSlots(),
                'rooms' => $this->rooms(fn ($query) => $query->whereNull('faculty_id')),
                'constraint_types' => $this->constraintTypes(),
                'tpb_blocks' => $revision['active'] ? $revision['blocks'] : ['study_programs' => [], 'retake' => [], 'lecturers' => []],
            ];

            return [
                'fingerprint' => $this->fingerprint($data),
                'generated_at' => now()->toIso8601String(),
                'scope' => 'tpb',
                'revision' => [
                    'required' => $revision['required'],
                    'published_run_id' => $revision['published_run_id'],
                    'revise_group_ids' => $revision['revise_group_ids'],
                ],
                'period' => $period,
                'faculty' => null,
                'study_programs' => $programs->map(fn (StudyProgram $program) => [
                    'id' => $program->id,
                    'code' => $program->code,
                    'name' => $program->name,
                    'constraint_status' => $program->constraint_status->value,
                    'constraint_reviewed_at' => $program->constraint_reviewed_at?->toIso8601String(),
                    'lecturers_count' => $program->lecturers_count,
                    'courses_count' => $program->courses_count,
                    'included' => true,
                ])->values()->all(),
                ...$data,
                'occupied_rooms' => $revision['active'] ? $revision['occupied_rooms'] : [],
            ];
        });
    }

    /**
     * Revision of a published TPB schedule. Needed once faculties are scheduled against it: only
     * the chosen groups move, into slots no active schedule holds; the other groups stay put.
     *
     * @param  list<int>  $reviseGroupIds
     * @return array{required: bool, active: bool, published_run_id: int|null, revise_group_ids: list<int>, frozen: array<string, array<string, list<int|null>>>, blocks: array<string, mixed>, occupied_rooms: list<array{time_slot_id: int, room_id: int}>}
     */
    private function revision(string $period, array $reviseGroupIds): array
    {
        $published = $this->tpbRuns->active($period);
        $facultyRuns = $published === null ? collect() : SchedulingRun::query()->active()->whereNotNull('faculty_id')->where('period', $period)->pluck('id');
        $required = $published !== null && $facultyRuns->isNotEmpty();
        $active = $published !== null && $reviseGroupIds !== [];
        $empty = ['required' => $required, 'active' => false, 'published_run_id' => $published?->id, 'revise_group_ids' => [], 'frozen' => [], 'blocks' => [], 'occupied_rooms' => []];
        if (! $active) {
            return $empty;
        }

        $frozen = [];
        ScheduleDetail::query()->where('scheduling_run_id', $published->id)->orderBy('meeting_number')
            ->get(['course_id', 'class_number', 'meeting_number', 'time_slot_id', 'room_id'])
            ->each(function (ScheduleDetail $d) use (&$frozen) {
                $key = "{$d->course_id}:{$d->class_number}";
                $frozen[$key]['fixed_time_slot_ids'][] = $d->time_slot_id;
                $frozen[$key]['fixed_room_ids'][] = $d->room_id;
            });

        $details = ScheduleDetail::query()->with('course:id,study_program_id,semester')
            ->whereIn('scheduling_run_id', $facultyRuns)
            ->get(['course_id', 'lecturer_id', 'time_slot_id', 'room_id']);
        $byProgram = [];
        $byLecturer = [];
        foreach ($details as $d) {
            $byProgram["{$d->course->study_program_id}:{$d->course->semester}"][] = $d->time_slot_id;
            $byLecturer[$d->lecturer_id][] = $d->time_slot_id;
        }
        $unique = fn (array $slots) => collect($slots)->unique()->sort()->values()->all();
        ksort($byProgram);
        ksort($byLecturer);

        return [
            ...$empty,
            'active' => true,
            'revise_group_ids' => collect($reviseGroupIds)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all(),
            'frozen' => $frozen,
            'blocks' => [
                'study_programs' => collect($byProgram)->map(function (array $slots, string $key) use ($unique) {
                    [$programId, $semester] = array_map('intval', explode(':', $key));

                    return ['study_program_id' => $programId, 'semester' => $semester, 'time_slot_ids' => $unique($slots)];
                })->values()->all(),
                'retake' => [],
                'lecturers' => collect($byLecturer)->map(fn (array $slots, int $id) => ['lecturer_id' => $id, 'time_slot_ids' => $unique($slots)])->values()->all(),
            ],
            'occupied_rooms' => $details->whereNotNull('room_id')
                ->map(fn (ScheduleDetail $d) => ['time_slot_id' => $d->time_slot_id, 'room_id' => $d->room_id])
                ->unique(fn (array $row) => "{$row['time_slot_id']}:{$row['room_id']}")
                ->sortBy(['time_slot_id', 'room_id'])->values()->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function timeSlots(): array
    {
        return TimeSlot::query()->orderBy('day')->orderBy('session')->get()
            ->map(fn (TimeSlot $slot) => [
                'id' => $slot->id,
                'day' => $slot->day->value,
                'session' => $slot->session,
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'type' => $slot->type,
            ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rooms(\Closure $scope): array
    {
        return Room::query()
            ->where($scope)
            ->orderBy('id')
            ->get()
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'code' => $room->code,
                'name' => $room->name,
                'capacity' => $room->capacity,
                'faculty_id' => $room->faculty_id,
            ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function constraintTypes(): array
    {
        return ConstraintType::query()->orderBy('id')->get()
            ->map(fn (ConstraintType $type) => [
                'code' => $type->code->value,
                'category' => $type->category->value,
                'description' => $type->description,
                'weight' => (float) $type->weight,
            ])->values()->all();
    }

    /**
     * Lecturers teaching the classes, wherever their homebase is, with their slot preferences.
     *
     * @param  Collection<int, int>  $ids
     * @return list<array<string, mixed>>
     */
    private function lecturers(Collection $ids, bool $withPreferences = true): array
    {
        return Lecturer::query()
            ->whereIn('id', $ids)
            ->when($withPreferences, fn ($query) => $query->with(['preferences' => fn ($q) => $q->with('constraintType')->orderBy('time_slot_id')]))
            ->orderBy('id')
            ->get()
            ->map(fn (Lecturer $lecturer) => [
                'id' => $lecturer->id,
                'nip' => $lecturer->nip,
                'name' => $lecturer->name,
                'title' => $lecturer->title,
                'study_program_id' => $lecturer->study_program_id,
                'preferences' => ! $withPreferences ? [] : $lecturer->preferences->map(fn (LecturerPreference $row) => [
                    'time_slot_id' => $row->time_slot_id,
                    'type' => $row->constraintType->code === ConstraintCode::SC_INGIN ? 'want' : 'avoid',
                ])->values()->all(),
            ])->values()->all();
    }

    /**
     * Slots the program's lecturers already teach in the active runs of other programs (any
     * faculty) of the period. Programs run their GA one by one, so the later one works around them.
     *
     * @param  list<int>  $lecturerIds
     * @return list<array{lecturer_id: int, time_slot_ids: list<int>}>
     */
    private function lecturerBusy(StudyProgram $program, string $period, array $lecturerIds): array
    {
        return ScheduleDetail::query()
            ->join('scheduling_runs', 'scheduling_runs.id', '=', 'schedule_details.scheduling_run_id')
            ->where('scheduling_runs.is_active', true)
            ->where('scheduling_runs.period', $period)
            ->whereNotNull('scheduling_runs.faculty_id')
            ->where(fn ($q) => $q->whereNull('scheduling_runs.study_program_id')->orWhere('scheduling_runs.study_program_id', '!=', $program->id))
            ->whereIn('schedule_details.lecturer_id', $lecturerIds)
            ->distinct()
            ->orderBy('schedule_details.lecturer_id')
            ->orderBy('schedule_details.time_slot_id')
            ->get(['schedule_details.lecturer_id', 'schedule_details.time_slot_id'])
            ->groupBy('lecturer_id')
            ->map(fn ($rows, int $id) => ['lecturer_id' => $id, 'time_slot_ids' => $rows->pluck('time_slot_id')->values()->all()])
            ->values()
            ->all();
    }
}

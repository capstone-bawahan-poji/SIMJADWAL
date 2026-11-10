<?php

namespace App\Services\Scheduling;

use App\Enums\Constraint\ConstraintCode;
use App\Enums\Constraint\ConstraintStatus;
use App\Exceptions\Shared\ApiException;
use App\Models\Constraint\ConstraintType;
use App\Models\Constraint\LecturerPreference;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Faculty;
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
 *   - faculty: the accepted programs of a faculty. Needs the active TPB run of the period, whose
 *     slots come along as tpb_blocks and whose rooms as occupied_rooms.
 *   - tpb (build(null, ...)): every TPB offering of the period. One gene per class meeting,
 *     classes of one group share a slot, rooms are the shared ones.
 */
readonly class SchedulingDatasetService
{
    public function __construct(
        private DatabaseManager $db,
        private TpbBlockService $tpbBlocks,
        private TpbRunResolver $tpbRuns,
    ) {}

    /**
     * @param  list<int>  $reviseGroupIds  TPB only: groups to place again after the TPB schedule is published
     * @return array<string, mixed>
     */
    public function build(?Faculty $faculty, string $period, array $reviseGroupIds = []): array
    {
        return $faculty === null ? $this->buildTpb($period, $reviseGroupIds) : $this->buildFaculty($faculty, $period);
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
    private function buildFaculty(Faculty $faculty, string $period): array
    {
        $tpbRun = $this->tpbRuns->active($period);
        if ($tpbRun === null) {
            throw ApiException::invalidState(__('The TPB schedule of :period is not published yet. Faculties are scheduled after TPB.', ['period' => Period::label($period)]));
        }

        return $this->db->transaction(function () use ($faculty, $period, $tpbRun) {
            $programs = StudyProgram::query()
                ->where('faculty_id', $faculty->id)
                ->withCount(['lecturers', 'courses'])
                ->orderBy('code')
                ->get();

            $includedIds = $programs->where('constraint_status', ConstraintStatus::ACCEPTED)->pluck('id');

            $courses = Course::query()
                ->whereIn('study_program_id', $includedIds)
                ->where('is_tpb', false)
                ->where('is_active', true)
                ->whereIn('semester', Period::semesters($period))
                ->orderBy('id')
                ->get();

            $classes = CourseLecturer::query()
                ->whereIn('course_id', $courses->pluck('id'))
                ->orderBy('course_id')
                ->orderBy('class_number')
                ->get();
            $lecturerIds = $classes->pluck('lecturer_id')->unique()->values();

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
                'rooms' => $this->rooms(fn ($query) => $query->where('faculty_id', $faculty->id)->orWhereNull('faculty_id')),
                'constraint_types' => $this->constraintTypes(),
                'tpb_run_id' => $tpbRun->id,
                'tpb_blocks' => $this->tpbBlocks->forDataset($period, $includedIds->all(), $lecturerIds->all()),
            ];

            return [
                'fingerprint' => $this->fingerprint($data),
                'generated_at' => now()->toIso8601String(),
                'scope' => 'faculty',
                'period' => $period,
                'faculty' => ['id' => $faculty->id, 'code' => $faculty->code, 'name' => $faculty->name],
                'study_programs' => $programs->map(fn (StudyProgram $program) => [
                    'id' => $program->id,
                    'code' => $program->code,
                    'name' => $program->name,
                    'constraint_status' => $program->constraint_status->value,
                    'constraint_reviewed_at' => $program->constraint_reviewed_at?->toIso8601String(),
                    'lecturers_count' => $program->lecturers_count,
                    'courses_count' => $program->courses_count,
                    'included' => $includedIds->contains($program->id),
                ])->values()->all(),
                ...$data,
                'occupied_rooms' => $this->occupiedRooms($faculty, $period),
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
     * Rooms taken by the active schedules of other faculties and of TPB in the same period.
     *
     * @return list<array{time_slot_id: int, room_id: int}>
     */
    private function occupiedRooms(Faculty $faculty, string $period): array
    {
        return ScheduleDetail::query()
            ->join('scheduling_runs', 'scheduling_runs.id', '=', 'schedule_details.scheduling_run_id')
            ->where('scheduling_runs.is_active', true)
            ->where('scheduling_runs.period', $period)
            ->where(fn ($query) => $query->where('scheduling_runs.faculty_id', '!=', $faculty->id)->orWhereNull('scheduling_runs.faculty_id'))
            ->whereNotNull('schedule_details.room_id')
            ->distinct()
            ->orderBy('schedule_details.time_slot_id')
            ->orderBy('schedule_details.room_id')
            ->get(['schedule_details.time_slot_id', 'schedule_details.room_id'])
            ->map(fn (ScheduleDetail $row) => ['time_slot_id' => $row->time_slot_id, 'room_id' => $row->room_id])
            ->values()
            ->all();
    }
}

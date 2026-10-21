<?php

namespace App\Services\Scheduling;

use App\Enums\Constraint\ConstraintCode;
use App\Enums\Constraint\ConstraintStatus;
use App\Models\Constraint\ConstraintType;
use App\Models\Constraint\LecturerPreference;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\Scheduling\ScheduleDetail;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

readonly class SchedulingDatasetService
{
    public function __construct(private DatabaseManager $db) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Faculty $faculty, string $period): array
    {
        return $this->db->transaction(function () use ($faculty, $period) {
            $programs = StudyProgram::query()
                ->where('faculty_id', $faculty->id)
                ->withCount(['lecturers', 'courses'])
                ->orderBy('code')
                ->get();

            $includedIds = $programs->where('constraint_status', ConstraintStatus::ACCEPTED)->pluck('id');

            $courses = Course::query()
                ->whereIn('study_program_id', $includedIds)
                ->where('is_tpb', false)
                ->whereIn('semester', $this->semestersOf($period))
                ->orderBy('id')
                ->get();

            $classes = CourseLecturer::query()
                ->whereIn('course_id', $courses->pluck('id'))
                ->orderBy('course_id')
                ->orderBy('class_number')
                ->get();

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
                    'is_tpb' => $course->is_tpb,
                ])->values()->all(),
                'classes' => $classes->map(fn (CourseLecturer $class) => [
                    'course_id' => $class->course_id,
                    'class_number' => $class->class_number,
                    'lecturer_id' => $class->lecturer_id,
                    'fixed_room_id' => $class->room_id,
                ])->values()->all(),
                'lecturers' => $this->lecturers($classes->pluck('lecturer_id')->unique()),
                'time_slots' => TimeSlot::query()->orderBy('day')->orderBy('session')->get()
                    ->map(fn (TimeSlot $slot) => [
                        'id' => $slot->id,
                        'day' => $slot->day->value,
                        'session' => $slot->session,
                        'start_time' => $slot->start_time,
                        'end_time' => $slot->end_time,
                        'type' => $slot->type,
                    ])->values()->all(),
                'rooms' => Room::query()
                    ->where(fn ($query) => $query->where('faculty_id', $faculty->id)->orWhereNull('faculty_id'))
                    ->orderBy('id')
                    ->get()
                    ->map(fn (Room $room) => [
                        'id' => $room->id,
                        'code' => $room->code,
                        'name' => $room->name,
                        'capacity' => $room->capacity,
                        'faculty_id' => $room->faculty_id,
                    ])->values()->all(),
                'constraint_types' => ConstraintType::query()->orderBy('id')->get()
                    ->map(fn (ConstraintType $type) => [
                        'code' => $type->code->value,
                        'category' => $type->category->value,
                        'description' => $type->description,
                        'weight' => (float) $type->weight,
                    ])->values()->all(),
            ];

            return [
                'fingerprint' => $this->fingerprint($data),
                'generated_at' => now()->toIso8601String(),
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
     * SHA-256 over the parts that decide a schedule. Same data, same hash.
     *
     * @param  array<string, mixed>  $data
     */
    public function fingerprint(array $data): string
    {
        return 'sha256:'.hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * GANJIL schedules odd semesters, GENAP even
     *
     * @return list<int>
     */
    private function semestersOf(string $period): array
    {
        return str_ends_with($period, '-GANJIL') ? [1, 3, 5, 7] : [2, 4, 6, 8];
    }

    /**
     * Lecturers teaching the classes, wherever their homebase is, with their slot preferences.
     *
     * @param  Collection<int, int>  $ids
     * @return list<array<string, mixed>>
     */
    private function lecturers(Collection $ids): array
    {
        return Lecturer::query()
            ->whereIn('id', $ids)
            ->with(['preferences' => fn ($query) => $query->with('constraintType')->orderBy('time_slot_id')])
            ->orderBy('id')
            ->get()
            ->map(fn (Lecturer $lecturer) => [
                'id' => $lecturer->id,
                'nip' => $lecturer->nip,
                'name' => $lecturer->name,
                'title' => $lecturer->title,
                'study_program_id' => $lecturer->study_program_id,
                'preferences' => $lecturer->preferences->map(fn (LecturerPreference $row) => [
                    'time_slot_id' => $row->time_slot_id,
                    'type' => $row->constraintType->code === ConstraintCode::SC_INGIN ? 'want' : 'avoid',
                ])->values()->all(),
            ])->values()->all();
    }

    /**
     * Rooms taken by the active schedule of another faculty in the same period
     *
     * @return list<array{time_slot_id: int, room_id: int}>
     */
    private function occupiedRooms(Faculty $faculty, string $period): array
    {
        return ScheduleDetail::query()
            ->join('scheduling_runs', 'scheduling_runs.id', '=', 'schedule_details.scheduling_run_id')
            ->where('scheduling_runs.is_active', true)
            ->where('scheduling_runs.period', $period)
            ->where('scheduling_runs.faculty_id', '!=', $faculty->id)
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

<?php

namespace App\Services\Scheduling;

use App\Models\MasterData\CourseLecturer;
use App\Models\Scheduling\ScheduleDetail;

/**
 * One shape for a gene, shared by the run review, the schedule view and exports.
 */
class ScheduleDetailTransformer
{
    /**
     * Relations toArray() reads. Eager load them before transforming.
     *
     * @return list<string>
     */
    public static function relations(): array
    {
        return [
            'course:id,study_program_id,is_tpb,code,name,sks,semester,class_capacity',
            'course.studyProgram:id,code,name',
            'lecturer:id,name,title',
            'timeSlot',
            'room:id,code,name,capacity',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function toArray(ScheduleDetail $d): array
    {
        return [
            'id' => $d->id,
            'course' => [
                'id' => $d->course->id,
                'code' => $d->course->code,
                'name' => $d->course->name,
                'sks' => $d->course->sks,
                'semester' => $d->course->semester,
                'class_capacity' => $d->course->class_capacity,
                'is_tpb' => $d->course->is_tpb,
                'study_program' => $d->course->studyProgram ? [
                    'id' => $d->course->studyProgram->id,
                    'code' => $d->course->studyProgram->code,
                    'name' => $d->course->studyProgram->name,
                ] : null,
            ],
            'class_number' => $d->class_number,
            'meeting_number' => $d->meeting_number,
            'lecturer' => ['id' => $d->lecturer->id, 'name' => $d->lecturer->name, 'title' => $d->lecturer->title],
            'time_slot' => [
                'id' => $d->timeSlot->id,
                'day' => $d->timeSlot->day->value,
                'session' => $d->timeSlot->session,
                'start_time' => $d->timeSlot->start_time,
                'end_time' => $d->timeSlot->end_time,
            ],
            'room' => $d->room ? ['id' => $d->room->id, 'code' => $d->room->code, 'name' => $d->room->name, 'capacity' => $d->room->capacity] : null,
            'is_overridden' => $d->is_overridden,
            'overridden_at' => $d->overridden_at?->toIso8601String(),
            'tpb_group' => null,
        ];
    }

    /**
     * Fills tpb_group (code, cohort, programs) and the offering capacity of TPB genes.
     * TPB courses carry no capacity or program themselves: both live in the offering of the period.
     *
     * @param  list<array<string, mixed>>  $rows  toArray() output
     * @return list<array<string, mixed>>
     */
    public static function withTpb(array $rows, string $period): array
    {
        $courseIds = collect($rows)->filter(fn (array $row) => $row['course']['is_tpb'])->pluck('course.id')->unique();
        if ($courseIds->isEmpty()) {
            return $rows;
        }

        $classes = CourseLecturer::query()
            ->with(['tpbGroup.studyPrograms:id,code,name', 'tpbOffering:id,class_capacity'])
            ->whereIn('course_id', $courseIds)
            ->whereRelation('tpbOffering', 'period', $period)
            ->get()
            ->keyBy(fn (CourseLecturer $class) => "{$class->course_id}:{$class->class_number}");

        return array_map(function (array $row) use ($classes) {
            $class = $classes["{$row['course']['id']}:{$row['class_number']}"] ?? null;
            if ($class === null) {
                return $row;
            }
            $group = $class->tpbGroup;
            $row['course']['class_capacity'] = $class->tpbOffering->class_capacity;
            $row['tpb_group'] = [
                'id' => $group->id,
                'code' => $group->code,
                'cohort' => $group->cohort->value,
                'study_programs' => $group->studyPrograms->sortBy('code')->map(fn ($program) => [
                    'id' => $program->id,
                    'code' => $program->code,
                    'name' => $program->name,
                    'target_semester' => $program->pivot->target_semester === null ? null : (int) $program->pivot->target_semester,
                ])->values()->all(),
            ];

            return $row;
        }, $rows);
    }
}

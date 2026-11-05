<?php

namespace App\Services\Scheduling;

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
            'course:id,study_program_id,code,name,sks,semester,class_capacity',
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
        ];
    }
}

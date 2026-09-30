<?php

namespace App\Enums\MasterData;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Kinds of data that can keep a master data row from being deleted.
 * The value is the key in the RESOURCE_IN_USE error: errors.references.{value} = count.
 */
#[TypeScript]
enum ReferenceType: string
{
    case STUDY_PROGRAMS = 'study_programs';
    case LECTURERS = 'lecturers';
    case COURSES = 'courses';
    case TEACHING_ASSIGNMENTS = 'teaching_assignments';
    case ROOMS = 'rooms';
    case USERS = 'users';
    case LECTURER_PREFERENCES = 'lecturer_preferences';
    case SCHEDULES = 'schedules';
    case SCHEDULING_RUNS = 'scheduling_runs';
    case TPB_GROUPS = 'tpb_groups';

    public function label(): string
    {
        return match ($this) {
            self::STUDY_PROGRAMS => __('Study Program'),
            self::LECTURERS => __('Lecturer'),
            self::COURSES => __('Course'),
            self::TEACHING_ASSIGNMENTS => __('Teaching Assignment'),
            self::ROOMS => __('Room'),
            self::USERS => __('User'),
            self::LECTURER_PREFERENCES => __('Lecturer Preference'),
            self::SCHEDULES => __('Schedule'),
            self::SCHEDULING_RUNS => __('Scheduling Run'),
            self::TPB_GROUPS => __('TPB Group'),
        };
    }
}

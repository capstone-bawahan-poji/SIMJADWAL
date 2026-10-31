/*
 * Shapes of /api/internal responses. Each type mirrors an App\Data class; keep them in sync.
 */
import type { Role } from './index';

export interface Pagination {
    page: number;
    per_page: number;
    total: number;
    last_page: number;
}

export interface Paginated<T> {
    data: T[];
    meta: { pagination: Pagination };
}

export interface Collection<T> {
    data: T[];
}

export interface Single<T> {
    data: T;
}

/** App\Data\MasterData\FacultySummaryData */
export interface FacultySummary {
    id: number;
    code: string;
    name: string;
}

/** App\Data\MasterData\FacultyData */
export interface Faculty extends FacultySummary {
    departments_count: number;
    study_programs_count: number;
    can_update: boolean;
    can_delete: boolean;
}

/** App\Data\MasterData\DepartmentSummaryData */
export interface DepartmentSummary {
    id: number;
    faculty_id: number;
    code: string;
    name: string;
}

/** App\Data\MasterData\DepartmentData */
export interface Department extends DepartmentSummary {
    faculty: FacultySummary;
    study_programs_count: number;
    can_update: boolean;
    can_delete: boolean;
}

/** App\Data\Account\UserSummaryData */
export interface UserSummary {
    id: number;
    name: string;
    email: string;
    identity_number: string | null;
}

/** App\Data\MasterData\StudyProgramSummaryData */
export interface StudyProgramSummary {
    id: number;
    faculty_id: number;
    department_id: number | null;
    code: string;
    name: string;
}

/** draft = editable, submitted = waiting for the faculty admin, accepted = locked. */
export type ConstraintStatus = 'draft' | 'submitted' | 'accepted';

/** App\Data\MasterData\StudyProgramData */
export interface StudyProgram extends StudyProgramSummary {
    constraint_status: ConstraintStatus;
    constraint_submitted_at: string | null;
    constraint_reviewed_at: string | null;
    constraint_return_note: string | null;
    faculty: FacultySummary;
    department: DepartmentSummary | null;
    coordinator: UserSummary | null;
    can_update: boolean;
    can_delete: boolean;
}

/** App\Data\MasterData\LecturerSummaryData */
export interface LecturerSummary {
    id: number;
    study_program_id: number;
    nip: string;
    name: string;
    title: string | null;
}

/** App\Data\MasterData\LecturerData */
export interface Lecturer extends LecturerSummary {
    user_id: number | null;
    study_program: StudyProgramSummary;
    course_lecturers_count: number;
    can_update: boolean;
    can_delete: boolean;
}

/** App\Data\MasterData\RoomData. faculty_id null = shared room. */
export interface Room {
    id: number;
    faculty_id: number | null;
    code: string;
    name: string | null;
    building: string | null;
    floor: number | null;
    capacity: number;
    faculty: FacultySummary | null;
    is_shared: boolean;
    is_in_use: boolean;
    can_update: boolean;
    can_delete: boolean;
}

/** App\Data\MasterData\TimeSlotData. day 1 = Monday. type = slot length in SKS. */
export interface TimeSlot {
    id: number;
    day: number;
    day_label: string;
    session: number;
    start_time: string;
    end_time: string;
    type: 2 | 3;
    can_update: boolean;
    can_delete: boolean;
}

/** App\Data\Account\ActivityLogData */
export interface ActivityLog {
    id: number;
    event: string | null;
    description: string;
    causer: UserSummary | null;
    subject_type: string | null;
    subject_id: number | null;
    changes: { old?: Record<string, unknown>; attributes?: Record<string, unknown> };
    properties: Record<string, unknown>;
    created_at: string | null;
}

/** App\Services\MasterData\DashboardStatsService::get() */
export interface DashboardStats {
    faculties: number;
    departments: number;
    study_programs: number;
    users: { total: number; active: number; by_role: Record<Role, number> };
    rooms: { total: number; shared: number };
    time_slots: { total: number; days: number; sessions_per_day: number };
}

/** App\Data\MasterData\CourseSummaryData */
export interface CourseSummary {
    id: number;
    study_program_id: number | null;
    is_tpb: boolean;
    code: string;
    name: string;
    sks: number;
    semester: number;
}

/** App\Data\MasterData\CourseData. course_lecturers_count = classes that already have a lecturer. */
export interface Course extends CourseSummary {
    parallel_class_count: number;
    class_capacity: number;
    study_program: StudyProgramSummary | null;
    course_lecturers_count: number;
    can_update: boolean;
    can_delete: boolean;
}

/** App\Data\MasterData\TeachingAssignmentData: one class of a course and its lecturer. */
export interface TeachingAssignment {
    id: number;
    course_id: number;
    class_number: number;
    class_label: string;
    lecturer_id: number;
    tpb_group_id: number | null;
    room_id: number | null;
    course: CourseSummary;
    lecturer: LecturerSummary;
    room: { id: number; faculty_id: number | null; code: string; name: string | null; capacity: number } | null;
    can_update: boolean;
    can_delete: boolean;
}

/** App\Data\Constraint\ConstraintTypeData. Weights are global. Only soft constraints can change. */
export interface ConstraintType {
    id: number;
    code: 'HC1' | 'HC2' | 'HC3' | 'HC4' | 'SC_INGIN' | 'SC_HINDARI' | 'SC_SKS';
    category: 'HC' | 'SC';
    description: string;
    weight: number;
    can_update: boolean;
}

/** App\Data\Constraint\ConstraintSubmissionData */
export interface ConstraintSubmission {
    id: number;
    faculty_id: number;
    code: string;
    name: string;
    constraint_status: ConstraintStatus;
    constraint_submitted_at: string | null;
    constraint_reviewed_at: string | null;
    constraint_return_note: string | null;
    faculty: FacultySummary;
    lecturers_count: number;
    lecturers_with_preferences_count: number;
    courses_count: number;
    incomplete_courses_count: number;
    can_submit: boolean;
    can_review: boolean;
    is_locked: boolean;
}

export type PreferenceType = 'want' | 'avoid';

export interface SlotPreference {
    time_slot_id: number;
    type: PreferenceType;
}

/** ConstraintSubmissionService::getSubmission() */
export interface ConstraintSubmissionDetail {
    summary: ConstraintSubmission;
    incomplete_courses: { id: number; code: string; name: string; parallel_class_count: number; assigned_class_count: number }[];
    lecturers: { id: number; nip: string; name: string; title: string | null; preferences: SlotPreference[] }[];
}

/* Scheduling */

export type RunStatus = 'queued' | 'running' | 'done' | 'failed';

/** SchedulingRunQueryService summary. Draft = !is_active && !published_at, archived = !is_active && published_at. */
export interface SchedulingRunSummary {
    id: number;
    batch_id: string;
    faculty: { id: number; code: string; name: string } | null;
    period: string;
    preset: { id: number; name: string } | null;
    status: RunStatus;
    selection_operator: string | null;
    crossover_rate: number | null;
    mutation_rate: number | null;
    population_size: number | null;
    parallel_class_limit: number | null;
    seed: number | null;
    best_fitness: number | null;
    max_fitness: number | null;
    fitness_percent: number | null;
    best_generation: number | null;
    final_generation: number;
    schedule_details_count: number;
    hc_violations: number;
    sc_unmet: number;
    unassigned_rooms: number;
    is_active: boolean;
    triggered_by: { id: number; name: string } | null;
    started_at: string | null;
    finished_at: string | null;
    published_at: string | null;
}

/** GET /scheduling-runs/{id} */
export interface SchedulingRunDetail extends SchedulingRunSummary {
    parameter_snapshot: Record<string, unknown>;
    fitness_history: number[];
}

/** ScheduleDetailTransformer. class_number 1 = A. room null = no room assigned. */
export interface ScheduleDetailBase {
    id: number;
    course: {
        id: number;
        code: string;
        name: string;
        sks: number;
        semester: number;
        class_capacity: number;
        study_program: { id: number; code: string; name: string } | null;
    };
    class_number: number;
    meeting_number: number;
    lecturer: { id: number; name: string; title: string | null };
    time_slot: { id: number; day: number; session: number; start_time: string; end_time: string };
    room: { id: number; code: string; name: string | null; capacity: number } | null;
    is_overridden: boolean;
    overridden_at: string | null;
}

/** GET /scheduling-runs/{id}/schedule-details */
export interface RunScheduleDetail extends ScheduleDetailBase {
    violations_count: number;
}

/** GET /scheduling-runs/{id}/violations */
export interface Violation {
    id: number;
    constraint_code: string;
    category: 'HC' | 'SC';
    penalty: number;
    message: string | null;
    schedule_detail_id: number | null;
}

/** GET /schedules. runs[].published_at null = active but not published (faculty admin only). */
export interface Schedule {
    period: string;
    runs: { id: number; faculty: { id: number; code: string; name: string }; published_at: string | null }[];
    schedule_details: (ScheduleDetailBase & { scheduling_run_id: number })[];
}

/** GET /schedules query. Empty values are dropped by lib/api.ts. */
export interface ScheduleFilters {
    period?: string;
    faculty_id?: number;
    study_program_id?: number;
    semester?: number;
    class_number?: number;
    course_id?: number;
    lecturer_id?: number;
    room_id?: number;
    day?: number;
    unassigned?: 0 | 1;
    over_capacity?: 0 | 1;
}

export interface OptionRoom {
    id: number;
    code: string;
    name: string | null;
    capacity: number;
}

/** GET /schedule-details/{id}/options. rooms is empty when the slot is not available. */
export interface ScheduleOptions {
    current: { time_slot_id: number; room_id: number | null };
    time_slots: {
        id: number;
        day: number;
        session: number;
        start_time: string;
        end_time: string;
        available: boolean;
        reasons: string[];
        rooms: OptionRoom[];
    }[];
}

/** Body of PATCH /schedule-details/{id}. Both keys are required; room_id null = no room. */
export interface ScheduleOverridePayload {
    time_slot_id: number;
    room_id: number | null;
}

/*  Dashboards */
/** App\Services\Dashboard\FacultyDashboardService::get(), prop `dashboard` of Fakultas/Dashboard */
export interface FacultyDashboard {
    period: string;
    constraints: { total: number } & Record<ConstraintStatus, number>;
    active_run: Pick<
        SchedulingRunSummary,
        'id' | 'published_at' | 'best_fitness' | 'fitness_percent' | 'final_generation' | 'schedule_details_count' | 'hc_violations' | 'sc_unmet'
    > | null;
    rooms: {
        unassigned_count: number;
        over_capacity_count: number;
        issues: {
            schedule_detail_id: number;
            type: 'unassigned' | 'over_capacity';
            course: string;
            class_label: string;
            day: number;
            session: number;
            room: string | null;
        }[];
    };
}

/** App\Services\Dashboard\StudyProgramDashboardService::get(), prop `dashboard` of Prodi/Dashboard */
export interface StudyProgramDashboard {
    period: string;
    constraint: {
        status: ConstraintStatus;
        lecturers_count: number;
        lecturers_with_preferences_count: number;
        courses_count: number;
        incomplete_courses_count: number;
        completeness_percent: number;
    };
    /** none = no active schedule, active = not published yet (numbers null), published = numbers filled. */
    schedule: {
        status: 'none' | 'active' | 'published';
        classes_count: number | null;
        sks_total: number | null;
        hc_violations: number | null;
        unassigned_rooms: number | null;
        published_at: string | null;
    };
}
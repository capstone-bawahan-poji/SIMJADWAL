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

/** App\Data\MasterData\AcademicPeriodData. Dates are YYYY-MM-DD. is_current = default period of the apps. */
export interface AcademicPeriod {
    id: number;
    code: string;
    label: string;
    start_date: string;
    end_date: string;
    is_current: boolean;
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
    /** null for TPB courses: see TpbOffering and TpbParticipant. */
    semester: number | null;
}

/** App\Data\MasterData\CourseData. course_lecturers_count = classes that already have a lecturer. TPB courses have no class count or capacity. */
export interface Course extends CourseSummary {
    parallel_class_count: number | null;
    class_capacity: number | null;
    is_active: boolean;
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
    /** null only for a TPB class whose lecturer is not chosen yet. */
    lecturer_id: number | null;
    tpb_offering_id: number | null;
    tpb_group_id: number | null;
    course: CourseSummary;
    lecturer: LecturerSummary | null;
    can_update: boolean;
    can_delete: boolean;
}

/** App\Enums\MasterData\TpbCohort. maba, non_maba and campuran block programs hard; mengulang is soft (SC_ULANG). */
export type TpbCohort = 'maba' | 'non_maba' | 'campuran' | 'mengulang' | 'pilihan';

/** App\Data\MasterData\TpbOfferingData. A TPB course opened in one period. */
export interface TpbOffering {
    id: number;
    course_id: number;
    period: string;
    parallel_class_count: number;
    class_capacity: number;
    course: CourseSummary;
    groups_count: number;
    /** Classes placed in a group. */
    course_lecturers_count: number;
    /** Classes with a lecturer. */
    assigned_classes_count: number;
    can_update: boolean;
    can_delete: boolean;
}

/** App\Data\MasterData\TpbParticipantData. target_semester null for mengulang groups. */
export interface TpbParticipant {
    study_program_id: number;
    code: string;
    name: string;
    target_semester: number | null;
}

/** App\Data\MasterData\TpbGroupData. The slot comes from the active TPB run, not from the group. */
export interface TpbGroup {
    id: number;
    tpb_offering_id: number;
    code: string;
    cohort: TpbCohort;
    participants: TpbParticipant[];
    class_numbers: number[];
    can_update: boolean;
    can_delete: boolean;
}

/** TpbBlockService::placedGroupsFor (GET /study-programs/{id}/tpb-blocked-slots). */
export interface TpbPlacedGroup {
    course_code: string;
    course_name: string;
    group_code: string;
    cohort: TpbCohort;
    target_semester: number | null;
    time_slot_ids: number[];
}

/** App\Data\Constraint\ConstraintTypeData. Weights are global. Only soft constraints can change. */
export interface ConstraintType {
    id: number;
    code: 'HC1' | 'HC2' | 'HC3' | 'HC4' | 'SC_INGIN' | 'SC_HINDARI' | 'SC_SKS' | 'SC_ULANG';
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
/** Filter scope of GET /schedules and the room grid. */
export type ScheduleScope = 'faculty' | 'study_program' | 'tpb';
/** Owner of a run: TPB, one study program, or a whole faculty (runs from before the GA moved to the programs). */
export type RunScope = 'tpb' | 'study_program' | 'faculty';
export type RunSource = 'ga' | 'import';
export type SubmissionStatus = 'draft' | 'submitted' | 'returned' | 'accepted' | 'published';
export type SlotRequestStatus = 'pending' | 'fulfilled' | 'partially_fulfilled' | 'rejected';

/** SchedulingRunQueryService summary. Draft = !is_active && !published_at, archived = !is_active && published_at. */
export interface SchedulingRunSummary {
    id: number;
    batch_id: string;
    /** null for a TPB run. */
    faculty: { id: number; code: string; name: string } | null;
    /** null for a TPB run. */
    study_program: { id: number; code: string; name: string } | null;
    scope: RunScope;
    /** import = a TPB schedule entered from a file, not from the GA. */
    source: RunSource;
    /** Program runs: the TPB run their dataset was built against. */
    tpb_run_id: number | null;
    /** The data changed after the run was computed. A warning: activation re-checks what matters. */
    is_stale: boolean;
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
        semester: number | null;
        class_capacity: number | null;
        is_tpb: boolean;
        study_program: { id: number; code: string; name: string } | null;
    };
    class_number: number;
    meeting_number: number;
    lecturer: { id: number; name: string; title: string | null };
    time_slot: { id: number; day: number; session: number; start_time: string; end_time: string };
    room: { id: number; code: string; name: string | null; capacity: number } | null;
    is_overridden: boolean;
    overridden_at: string | null;
    /** TPB genes: the group of the class and the programs attending it. */
    tpb_group: {
        id: number;
        code: string;
        cohort: TpbCohort;
        study_programs: { id: number; code: string; name: string; target_semester: number | null }[];
    } | null;
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
    /** faculty null + scope tpb = the institute-wide TPB schedule. */
    runs: { id: number; faculty: { id: number; code: string; name: string } | null; scope: 'faculty' | 'tpb'; published_at: string | null }[];
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
    /** Scope for faculty, study-program, or TPB schedules. */
    scope?: ScheduleScope;
    /** Picked classes: "12" = every class of course 12, "12:1" = class A of course 12. axios sends classes[]=… */
    classes?: string[];
}

export type ScheduleExportFormat = 'xlsx' | 'pdf' | 'ics';

/** GET /schedules/export query: the same filters plus the format. */
export interface ScheduleExportParams extends ScheduleFilters {
    format: ScheduleExportFormat;
}

export interface OptionRoom {
    id: number;
    code: string;
    name: string | null;
    capacity: number;
}

/** GET /schedule-details/{id}/options. rooms = the program's free cells in the slot, empty when the slot is not available. */
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
    /** Meetings of the same run this one may swap cells with. */
    swap_candidates: {
        schedule_detail_id: number;
        course: { id: number; name: string };
        class_number: number;
        meeting_number: number;
        time_slot_id: number;
        room_id: number;
        room_code: string | null;
    }[];
}

/** Body of PATCH /schedule-details/{id}: the full new cell, one of the program's room slots. */
export interface ScheduleOverridePayload {
    time_slot_id: number;
    room_id: number;
    reason?: string | null;
}

/* Room slots, slot requests, review and publication (POS 040/IT10.1/OT.07/2026) */

export type CellStatus = 'allocated' | 'other_faculty' | 'tpb' | 'conflict';

export interface GridRoom {
    id: number;
    code: string;
    name: string | null;
    building: string | null;
    capacity: number;
    faculty_id: number | null;
}

/** GET room-slot-allocations. cells lists only the cells that are not free. */
export interface RoomSlotGrid {
    period: string;
    faculty: { id: number; code: string; name: string };
    tpb_source: { type: 'run' | 'import' | null; run_id: number | null; import_id: number | null };
    published: boolean;
    study_programs: {
        id: number;
        code: string;
        name: string;
        cells_count: number;
        submission_status: SubmissionStatus;
        required_meetings: number;
        sufficient: boolean;
    }[];
    rooms: GridRoom[];
    time_slots: TimeSlot[];
    cells: {
        time_slot_id: number;
        room_id: number;
        status: CellStatus;
        study_program_id: number | null;
        faculty: { id: number; code: string } | null;
        slot_request_id: number | null;
        /** The holder handed its schedule in: the cell cannot move. */
        locked: boolean;
    }[];
}

/** How one cell (room × slot) of CellGrid looks. The page decides; the grid only lays them out. */
export interface CellView {
    label: string;
    /** Tailwind classes for the cell body. */
    tone: string;
    title?: string;
    disabled?: boolean;
    /** A change not saved yet, outlined with a dashed line. */
    pending?: boolean;
}

/** One item of PUT room-slot-allocations. study_program_id null = take the cell back. */
export interface CellChange {
    time_slot_id: number;
    room_id: number;
    study_program_id: number | null;
}

/** POS step 3: do the program's cells hold all its class meetings. */
export interface SlotSufficiency {
    allocated_cells: number;
    required_meetings: number;
    sufficient: boolean;
    type_mismatches: number;
    shortages: { course_id: number; code: string; name: string; meetings: number; min_capacity: number; slot_type: number }[];
}

/** GET study-programs/{id}/room-slots */
export interface ProgramRoomSlots {
    period: string;
    cells: { time_slot_id: number; room_id: number; room_code: string; capacity: number; slot_type: number; day: number; session: number }[];
    sufficiency: SlotSufficiency;
}

export interface SlotNeed {
    course_id: number;
    code: string | null;
    name: string | null;
    meetings: number;
    min_capacity: number;
}

/** GET slot-requests */
export interface SlotRequest {
    id: number;
    period: string;
    study_program: { id: number; code: string; name: string };
    status: SlotRequestStatus;
    needs: SlotNeed[];
    note: string | null;
    response_note: string | null;
    allocations_count: number;
    requested_by: { id: number; name: string } | null;
    reviewed_by: { id: number; name: string } | null;
    reviewed_at: string | null;
    created_at: string | null;
}

export type SubmissionProblem = 'cell' | 'capacity' | 'tpb' | 'lecturer' | 'class';

/** GET study-programs/{id}/schedule-submission */
export interface ProgramScheduleSubmission {
    period: string;
    study_program: { id: number; code: string; name: string };
    status: SubmissionStatus;
    faculty_published: boolean;
    scheduling_run: { id: number; is_active: boolean; best_fitness: number | null; max_fitness: number | null; finished_at: string | null } | null;
    /** What would stop a submit now. Empty once handed in. */
    problems: Partial<Record<SubmissionProblem, number>>;
    cells_count: number;
    submitted_at: string | null;
    reviewed_at: string | null;
    return_note: string | null;
}

/** GET faculties/{id}/schedule-submissions */
export interface FacultySubmissionRow {
    study_program: { id: number; code: string; name: string };
    status: SubmissionStatus;
    scheduling_run_id: number | null;
    best_fitness: number | null;
    max_fitness: number | null;
    cells_count: number;
    has_pending_slot_request: boolean;
    submitted_at: string | null;
    reviewed_at: string | null;
    return_note: string | null;
}

/** GET faculties/{id}/schedule-publication */
export interface SchedulePublicationState {
    period: string;
    faculty: { id: number; code: string; name: string };
    published: boolean;
    published_at: string | null;
    published_by: { id: number; name: string } | null;
    checksum: string | null;
    can_publish: boolean;
    waiting_programs: { id: number; code: string; name: string; status: SubmissionStatus }[];
}

/** GET tpb-occupancy */
export interface TpbOccupancy {
    period: string;
    source: { type: 'run' | 'import' | null; run_id: number | null; import_id: number | null };
    cells: { time_slot_id: number; room_id: number }[];
    import: {
        id: number;
        period: string;
        file_name: string | null;
        imported_by: { id: number; name: string } | null;
        imported_at: string | null;
        rows: unknown[];
    } | null;
}

export interface ImportRowMessage {
    /** 0 = about the whole file. */
    row: number;
    message: string;
}

/** POST tpb-occupancy/import (rows, skipped) and POST tpb-schedule/import (run_id, meetings). */
export interface TpbImportResult {
    committed: boolean;
    errors: ImportRowMessage[];
    warnings: ImportRowMessage[];
    rows?: number;
    skipped?: number;
    run_id?: number | null;
    meetings?: number;
}

/*  Dashboards */
/** App\Services\Dashboard\FacultyDashboardService::get(), prop `dashboard` of Fakultas/Dashboard */
export interface FacultyDashboard {
    period: string;
    constraints: { total: number } & Record<ConstraintStatus, number>;
    schedules: {
        programs: FacultySubmissionRow[];
        by_status: Partial<Record<SubmissionStatus, number>>;
        pending_slot_requests: number;
        published: boolean;
        published_at: string | null;
        can_publish: boolean;
    };
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
        submission_status: SubmissionStatus;
    };
}
/** App\Services\Dashboard\TpbDashboardService (Inertia prop of Tpb/Dashboard). */
export interface TpbDashboard {
    period: string;
    schedule: {
        status: 'none' | 'draft' | 'active' | 'published';
        runs_count: number;
        active_run: {
            id: number;
            published_at: string | null;
            finished_at: string | null;
            fitness_percent: number | null;
            hc_violations: number;
            sc_unmet: number;
            unassigned_rooms: number;
            schedule_details_count: number;
        } | null;
    };
    offerings: { count: number; groups: number; classes: number; seats: number };
    classes: { total: number; ungrouped: number; without_lecturer: number };
    stale_faculties: { id: number; code: string; name: string }[];
    lecturers: { count: number; limit: number; over_limit: number; top: { name: string; sks: number } | null };
    rooms: { shared: number; peak: { day: number; session: number; used: number } | null };
    retake_programs: number;
    cohorts: Record<TpbCohort, number>;
}

/** GET /schedules/room-grid: the published schedule as a room grid, same data as the "Seluruh jadwal" export. */
export interface RoomGridData {
    title: string;
    subtitle: string;
    mode: 'superadmin' | 'tpb' | 'fakultas' | 'prodi';
    clash_count: number;
    without_room: number;
    legend: { label: string; color: string; left_border: string | null }[];
    buildings: { name: string; label: string; rooms: { id: number; code: string }[] }[];
    time_slots: { id: number; day: number; day_label: string; session: number; time: string }[];
    cells: {
        time_slot_id: number;
        room_id: number;
        color: string;
        left_border: string | null;
        clash: boolean;
        classes: { course: string; course_code: string; class_label: string; lecturer: string; group: string | null }[];
    }[];
}

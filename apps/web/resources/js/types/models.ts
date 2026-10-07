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

/** App\Data\MasterData\StudyProgramData */
export interface StudyProgram extends StudyProgramSummary {
    constraint_status: 'draft' | 'submitted';
    constraint_submitted_at: string | null;
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

import type { Role, User } from '@/types';
import type { CellStatus, FacultySummary, SlotRequestStatus, StudyProgramSummary, SubmissionProblem, SubmissionStatus, TimeSlot, TpbCohort } from '@/types/models';

export const ROLE_LABELS: Record<Role, string> = {
    superadmin: 'Superadmin',
    admin_fakultas: 'Admin Fakultas',
    admin_tpb: 'Admin TPB',
    admin_prodi: 'Koor Prodi',
    dosen: 'Dosen',
};

export const ROLES = Object.keys(ROLE_LABELS) as Role[];

/** Three-letter day prefix for slot codes (SEN-01). Index = TimeSlot.day (1 = Monday). */
const DAY_PREFIX = ['', 'SEN', 'SEL', 'RAB', 'KAM', 'JUM', 'SAB', 'MIN'];

export const SKS_MINUTES = 50;

export function slotCode(slot: Pick<TimeSlot, 'day' | 'session'>): string {
    return `${DAY_PREFIX[slot.day] ?? slot.day}-${String(slot.session).padStart(2, '0')}`;
}

/** "07:30" → "07.30" (Indonesian time notation). */
export function formatTime(time: string): string {
    return time.slice(0, 5).replace(':', '.');
}

export function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

/**
 * Faculty / program line of an account. Accounts without a faculty scope get a label from
 * their role (no unit table yet). A lecturer account's homebase is its lecturer's program.
 */
export function userUnit(
    user: User,
    programs: Map<number, StudyProgramSummary>,
    faculties: Map<number, FacultySummary>,
): { title: string; subtitle: string | null } {
    if (user.role === 'superadmin') {
        return { title: 'Institut', subtitle: 'Seluruh fakultas' };
    }

    if (user.role === 'admin_tpb') {
        return { title: 'Tingkat Institut (TPB)', subtitle: 'Mata kuliah bersama' };
    }

    const program = user.study_program ?? (user.lecturer ? programs.get(user.lecturer.study_program_id) ?? null : null);
    const faculty = user.faculty ?? (program ? faculties.get(program.faculty_id) ?? null : null);

    return {
        title: faculty ? faculty.name : '-',
        subtitle: program ? program.name : null,
    };
}

export const COHORT_LABELS: Record<TpbCohort, string> = {
    maba: 'Mahasiswa baru',
    non_maba: 'Mahasiswa lama',
    campuran: 'Baru dan lama',
    mengulang: 'Kelas mengulang',
    pilihan: 'Mata kuliah pilihan',
};

export const REGULAR_COHORTS: TpbCohort[] = ['maba', 'non_maba', 'campuran'];

export function classLabel(classNumber: number): string {
    let label = '';
    for (let n = classNumber; n > 0; n = Math.floor((n - 1) / 26)) {
        label = String.fromCharCode(65 + ((n - 1) % 26)) + label;
    }
    return label;
}

export const DAY_LABELS: Record<number, string> = { 1: 'Senin', 2: 'Selasa', 3: 'Rabu', 4: 'Kamis', 5: 'Jumat', 6: 'Sabtu', 7: 'Minggu' };

/** Where a study program's schedule stands on its way to publication by the faculty. */
export const SUBMISSION_STATUS_LABELS: Record<SubmissionStatus, string> = {
    draft: 'Draf',
    submitted: 'Diserahkan ke fakultas',
    returned: 'Dikembalikan',
    published: 'Dipublikasikan',
};

/** Badge colors per submission status: gray draft, amber returned, green handed in, solid green final. */
export const SUBMISSION_STATUS_TONES: Record<SubmissionStatus, string> = {
    draft: 'bg-gray-100 text-gray-700',
    submitted: 'bg-emerald-50 text-emerald-700',
    returned: 'bg-amber-50 text-amber-800',
    published: 'bg-emerald-600 text-white',
};

export const SLOT_REQUEST_STATUS_LABELS: Record<SlotRequestStatus, string> = {
    pending: 'Menunggu',
    fulfilled: 'Dipenuhi',
    partially_fulfilled: 'Dipenuhi sebagian',
    rejected: 'Ditolak',
};

export const CELL_STATUS_LABELS: Record<CellStatus | 'free', string> = {
    free: 'Kosong',
    allocated: 'Sudah dialokasikan',
    other_faculty: 'Dipakai fakultas lain',
    tpb: 'Dipakai TPB',
    conflict: 'Bentrok dengan TPB',
};

/** Counts of ProgramScheduleSubmission.problems, worded for "n …". */
export const SUBMISSION_PROBLEM_LABELS: Record<SubmissionProblem, string> = {
    cell: 'pertemuan di luar slot ruangan program studi',
    capacity: 'ruang lebih kecil dari kelas',
    tpb: 'pertemuan bentrok dengan TPB',
    lecturer: 'bentrok dosen',
    class: 'bentrok kelas',
};

/**
 * Identity color per study program, by its order in the faculty (sorted by code), so a program
 * keeps its color on every page. No red: red means a problem.
 */
export const PROGRAM_COLORS = [
    { bg: 'bg-blue-100', text: 'text-blue-900', ring: 'ring-blue-300', solid: 'bg-blue-600' },
    { bg: 'bg-emerald-100', text: 'text-emerald-900', ring: 'ring-emerald-300', solid: 'bg-emerald-600' },
    { bg: 'bg-violet-100', text: 'text-violet-900', ring: 'ring-violet-300', solid: 'bg-violet-600' },
    { bg: 'bg-amber-100', text: 'text-amber-900', ring: 'ring-amber-300', solid: 'bg-amber-500' },
    { bg: 'bg-cyan-100', text: 'text-cyan-900', ring: 'ring-cyan-300', solid: 'bg-cyan-600' },
    { bg: 'bg-fuchsia-100', text: 'text-fuchsia-900', ring: 'ring-fuchsia-300', solid: 'bg-fuchsia-600' },
    { bg: 'bg-lime-100', text: 'text-lime-900', ring: 'ring-lime-300', solid: 'bg-lime-600' },
    { bg: 'bg-sky-100', text: 'text-sky-900', ring: 'ring-sky-300', solid: 'bg-sky-600' },
    { bg: 'bg-teal-100', text: 'text-teal-900', ring: 'ring-teal-300', solid: 'bg-teal-600' },
    { bg: 'bg-indigo-100', text: 'text-indigo-900', ring: 'ring-indigo-300', solid: 'bg-indigo-600' },
    { bg: 'bg-orange-100', text: 'text-orange-900', ring: 'ring-orange-300', solid: 'bg-orange-500' },
    { bg: 'bg-stone-200', text: 'text-stone-900', ring: 'ring-stone-400', solid: 'bg-stone-600' },
] as const;

export function programColor(index: number): (typeof PROGRAM_COLORS)[number] {
    return PROGRAM_COLORS[((index % PROGRAM_COLORS.length) + PROGRAM_COLORS.length) % PROGRAM_COLORS.length];
}

export function formatDateTime(value: string | null): string {
    return value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
}

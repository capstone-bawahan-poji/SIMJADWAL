import type { Role, User } from '@/types';
import type { FacultySummary, StudyProgramSummary, TimeSlot } from '@/types/models';

export const ROLE_LABELS: Record<Role, string> = {
    superadmin: 'Superadmin',
    admin_fakultas: 'Admin Fakultas',
    admin_tpb: 'Admin TPB',
    admin_prodi: 'Koor Prodi',
    dosen: 'Dosen',
    mahasiswa: 'Mahasiswa',
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

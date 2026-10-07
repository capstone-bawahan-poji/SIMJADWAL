import type { FacultySummary, LecturerSummary, StudyProgramSummary } from './models';

export type Role =
    | 'superadmin'
    | 'admin_fakultas'
    | 'admin_tpb'
    | 'admin_prodi'
    | 'dosen'
    | 'mahasiswa';

/** Mirrors App\Data\Account\UserData. */
export interface User {
    id: number;
    name: string;
    email: string;
    /** NIP or NIM. Lecturer accounts show the NIP of their lecturer record. */
    identity_number: string | null;
    role: Role | null;
    faculty_id: number | null;
    study_program_id: number | null;
    lecturer_id: number | null;
    faculty: FacultySummary | null;
    study_program: StudyProgramSummary | null;
    lecturer: LecturerSummary | null;
    is_active: boolean;
    created_at: string | null;
    can_update: boolean;
    can_update_status: boolean;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        permissions: string[];
    };
    labels: {
        /** Badge text for pages that still render mock data. */
        sample_data: string;
    };
};

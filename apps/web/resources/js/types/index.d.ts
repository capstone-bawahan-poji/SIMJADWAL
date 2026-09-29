export type Role =
    | 'superadmin'
    | 'admin_fakultas'
    | 'admin_prodi'
    | 'dosen'
    | 'mahasiswa';

/** Mirrors App\Data\Account\UserData. */
export interface User {
    id: number;
    name: string;
    email: string;
    role: Role | null;
    faculty_id: number | null;
    study_program_id: number | null;
    lecturer_id: number | null;
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
};

import type { Role } from '@/types';

export interface NavItem {
    label: string;
    routeName: string;
    /** SVG inner markup (24x24 outline), rendered with v-html. */
    icon: string;
}

const icons = {
    dashboard: `<path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" stroke-linecap="round" stroke-linejoin="round"/>`,
    accounts: `<path d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" stroke-linecap="round" stroke-linejoin="round"/>`,
    institution: `<path d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.5h-15V21" stroke-linecap="round" stroke-linejoin="round"/>`,
    room: `<path d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" stroke-linecap="round" stroke-linejoin="round"/>`,
    clock: `<path d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round"/>`,
    book: `<path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" stroke-linecap="round" stroke-linejoin="round"/>`,
    person: `<path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" stroke-linecap="round" stroke-linejoin="round"/>`,
    mapping: `<path d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" stroke-linecap="round" stroke-linejoin="round"/>`,
    sliders: `<path d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" stroke-linecap="round" stroke-linejoin="round"/>`,
    matrix: `<path d="M3.75 5.25h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5M7.5 5.25v13.5m9-13.5v13.5" stroke-linecap="round" stroke-linejoin="round"/>`,
    bolt: `<path d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" stroke-linecap="round" stroke-linejoin="round"/>`,
};

const dashboard: NavItem = { label: 'Dashboard', routeName: 'dashboard', icon: icons.dashboard };

/**
 * Sidebar entries per role. Roles without an entry (dosen, mahasiswa, admin_tpb) get the
 * dashboard only. Every routeName must exist in routes/web.php.
 */
export const NAV_BY_ROLE: Partial<Record<Role, NavItem[]>> = {
    superadmin: [
        dashboard,
        { label: 'Kelola Akun & Role', routeName: 'superadmin.accounts.index', icon: icons.accounts },
        { label: 'Data Fakultas & Prodi', routeName: 'superadmin.faculty-program.index', icon: icons.institution },
        { label: 'Data Ruangan', routeName: 'superadmin.rooms.index', icon: icons.room },
        { label: 'Data Jadwal', routeName: 'superadmin.slots.index', icon: icons.clock },
    ],
    admin_prodi: [
        dashboard,
        { label: 'Data Mata Kuliah', routeName: 'prodi.courses.index', icon: icons.book },
        { label: 'Data Dosen', routeName: 'prodi.lecturers.index', icon: icons.person },
        { label: 'Mapping Dosen ke Matkul', routeName: 'prodi.mappings.index', icon: icons.mapping },
        { label: 'Constraint & Preferensi', routeName: 'prodi.constraints.index', icon: icons.sliders },
        { label: 'Matriks Jadwal Prodi', routeName: 'prodi.schedule-matrix.index', icon: icons.matrix },
    ],
    admin_fakultas: [
        dashboard,
        { label: 'Kelola Constraint', routeName: 'fakultas.constraints.index', icon: icons.sliders },
        { label: 'Penjadwalan GA', routeName: 'fakultas.ga.index', icon: icons.bolt },
        { label: 'Alokasi Ruangan', routeName: 'fakultas.rooms.index', icon: icons.room },
    ],
};

export const PORTAL_LABELS: Partial<Record<Role, string>> = {
    superadmin: 'Superadmin Portal',
};

export const PROFILE_ICON = icons.person;

export function navFor(role: Role | null | undefined): NavItem[] {
    return (role && NAV_BY_ROLE[role]) || [dashboard];
}

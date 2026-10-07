/** Sample data for the prodi schedule matrix. */

export type CellType = 'Teori' | 'Praktikum';

export interface ScheduleCell {
    type: CellType | 'Kosong';
    code?: string;
    sks?: number;
    className?: string;
    course?: string;
    lecturer?: string;
    room?: string;
    capacity?: number | null;
    message?: string;
}

export const scheduleDays = ['SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT'] as const;

export const scheduleSessions = [
    { id: 'Sesi 1', time: '07.30 - 10.00', duration: '150 mnt' },
    { id: 'Sesi 2', time: '10.20 - 12.00', duration: '140 mnt' },
    { id: 'Sesi 3', time: '13.00 - 15.30', duration: '150 mnt' },
    { id: 'Sesi 4', time: '15.50 - 17.30', duration: '100 mnt' },
];

const empty: ScheduleCell = { type: 'Kosong', message: 'Slot Riset Dosen' };

const cell = (type: CellType, code: string, className: string, course: string, lecturer: string, room: string, capacity: number | null = null): ScheduleCell => ({
    type, code, sks: 3, className, course, lecturer, room, capacity,
});

export const scheduleMatrix: Record<string, Record<(typeof scheduleDays)[number], ScheduleCell>> = {
    'Sesi 1': {
        SENIN: cell('Teori', 'IF2101', 'IF-3A', 'Struktur Data & Algoritma', 'Dr. Eng. Ir. Suryadi, M.T.', 'Ruang 302', 45),
        SELASA: cell('Praktikum', 'IF3102', 'IF-3B', 'Pemrograman Berorientasi Objek', 'Prof. Dr. Anita Wijaya', 'Lab Rekayasa Perangkat Lunak'),
        RABU: cell('Praktikum', 'IF1103', 'IF-1A', 'Jaringan Komputer', 'Budi Prasetyo, M.T.', 'Lab Jaringan & Siber'),
        KAMIS: cell('Teori', 'IF2204', 'IF-3C', 'Basis Data Relasional', 'Hendra Wijaya, M.T.', 'Ruang 401', 50),
        JUMAT: empty,
    },
    'Sesi 2': {
        SENIN: cell('Praktikum', 'IF3205', 'IF-5A', 'Kecerdasan Buatan (AI)', 'Dr. Eng. Ir. Suryadi, M.T.', 'Lab AI & Data Science'),
        SELASA: cell('Teori', 'IF1202', 'IF-3A', 'Rekayasa Perangkat Lunak', 'Prof. Dr. Anita Wijaya', 'Ruang 304', 45),
        RABU: cell('Praktikum', 'IF4101', 'IF-5P1', 'Pemrograman Web Modern', 'Rina Fitriani, M.Kom.', 'Lab Multimedia'),
        KAMIS: cell('Teori', 'IF2101', 'IF-3B', 'Struktur Data & Algoritma', 'Dr. Eng. Ir. Suryadi, M.T.', 'Ruang 302'),
        JUMAT: cell('Teori', 'IF1103', 'IF-1C', 'Jaringan Komputer Dasar', 'Budi Prasetyo, M.T.', 'Ruang 201', 40),
    },
    'Sesi 3': {
        SENIN: cell('Teori', 'IF2204', 'IF-3C', 'Basis Data Relasional', 'Hendra Wijaya, M.T.', 'Ruang 401', 50),
        SELASA: cell('Praktikum', 'IF3205', 'IF-5B', 'Kecerdasan Buatan (AI)', 'Dr. Eng. Ir. Suryadi, M.T.', 'Lab AI & Data Science'),
        RABU: cell('Teori', 'IF1202', 'IF-3B', 'Rekayasa Perangkat Lunak', 'Prof. Dr. Anita Wijaya', 'Ruang 304', 45),
        KAMIS: cell('Praktikum', 'IF4101', 'IF-5P2', 'Pemrograman Web Modern', 'Rina Fitriani, M.Kom.', 'Lab Multimedia'),
        JUMAT: cell('Teori', 'IF1103', 'IF-1C', 'Jaringan Komputer Dasar', 'Budi Prasetyo, M.T.', 'Ruang 201', 40),
    },
    'Sesi 4': {
        SENIN: cell('Teori', 'IF2101', 'IF-3A', 'Struktur Data & Algoritma', 'Dr. Eng. Ir. Suryadi, M.T.', 'Ruang 302', 45),
        SELASA: cell('Praktikum', 'IF3205', 'IF-5C', 'Kecerdasan Buatan (AI)', 'Dr. Eng. Ir. Suryadi, M.T.', 'Lab AI & Data Science'),
        RABU: cell('Teori', 'IF1202', 'IF-3C', 'Rekayasa Perangkat Lunak', 'Prof. Dr. Anita Wijaya', 'Ruang 304', 45),
        KAMIS: cell('Praktikum', 'IF4101', 'IF-5P3', 'Pemrograman Web Modern', 'Rina Fitriani, M.Kom.', 'Lab Multimedia'),
        JUMAT: empty,
    },
};

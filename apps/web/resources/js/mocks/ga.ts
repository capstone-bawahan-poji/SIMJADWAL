/** Sample data for the faculty GA review page. The GA engine itself runs elsewhere. */

export type RunStatus = 'Baru Masuk' | 'Sedang Direview' | 'Arsip';

export interface GaRun {
    id: string;
    date: string;
    recommended: boolean;
    fitness: number;
    hardConstraint: string;
    parameters: string;
    status: RunStatus;
    selected: boolean;
}

export interface GaCell {
    empty?: boolean;
    course?: string;
    code?: string;
    className?: string;
    room?: string;
    lecturer?: string;
    warning?: string;
}

export const gaRuns: GaRun[] = [
    { id: 'RUN-2024-004', date: '18 Okt 2024, 14:20 WIB', recommended: true, fitness: 0.988, hardConstraint: '0 Bentrok (Legal)', parameters: 'N=100 | Gen=500 | Pc=0.85 | Pm=0.02 | TS(k=5)', status: 'Baru Masuk', selected: true },
    { id: 'RUN-2024-003', date: '17 Okt 2024, 09:15 WIB', recommended: false, fitness: 0.962, hardConstraint: '0 Bentrok (Legal)', parameters: 'N=80 | Gen=400 | Pc=0.80 | Pm=0.03 | Roulette', status: 'Sedang Direview', selected: false },
    { id: 'RUN-2024-002', date: '15 Okt 2024, 11:30 WIB', recommended: false, fitness: 0.941, hardConstraint: '0 Bentrok (Lunak)', parameters: 'N=100 | Gen=300 | Pc=0.85 | Pm=0.05 | TS(k=3)', status: 'Sedang Direview', selected: false },
    { id: 'RUN-2024-001', date: '12 Okt 2024, 16:10 WIB', recommended: false, fitness: 0.91, hardConstraint: '0 Bentrok (Lunak)', parameters: 'N=50 | Gen=250 | Pc=0.75 | Pm=0.05 | Roulette', status: 'Arsip', selected: false },
];

export const gaDays = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'] as const;
export const gaSessions = ['Sesi 1', 'Sesi 2', 'Sesi 3', 'Sesi 4'] as const;

const c = (code: string, sks: number, course: string, room: string, lecturer: string, className: string, warning?: string): GaCell => ({
    code: `${code} • ${sks} SKS`, course, room, lecturer, className, warning,
});
const none: GaCell = { empty: true };

export const gaMatrix: Record<(typeof gaDays)[number], GaCell[]> = {
    Senin: [
        c('IF2101', 3, 'Struktur Data', 'Lab Komputer 1', 'Dr. Hendra Wijaya', 'IF-3A'),
        c('SI1202', 2, 'Dasar Sistem Informasi', 'Gedung B - R201', 'Nadia Pratiwi, M.T.', 'SI-1B'),
        c('TE3104', 3, 'Sinyal & Sistem', 'Gedung A - R102', 'Ir. Budi Santoso', 'EL-5A'),
        none,
    ],
    Selasa: [
        c('IF003', 2, 'Pengantar Bisnis Digital', 'Gedung C - R305', 'Farhan, S.Si., M.M.', 'BD-1A'),
        c('IF005', 3, 'Kecerdasan Buatan (AI)', 'Lab Komputer AI', 'Dr. Hendra Wijaya', 'IF-5A'),
        c('SD001', 3, 'Aljabar Linier Elementer', 'Gedung B - R101', 'Dewi Kartika, M.Sc.', 'MA-1A'),
        c('FI002', 2, 'Fisika Komputasi', 'Lab Fisika Lanjut', 'Agus Salim, Ph.D.', 'FI-3A'),
    ],
    Rabu: [
        c('IF010', 3, 'Basis Data Terdistribusi', 'Lab Basis Data', 'Siti Rahmah, M.Kom.', 'IF-3B'),
        c('SI008', 2, 'Analisis Proses Bisnis', 'Gedung B - R204', 'Nadia Pratiwi, M.T.', 'SI-3A'),
        c('TE012', 3, 'Mikrokontroler & IoT', 'Bengkel Elektro R12', 'Ir. Budi Santoso', 'EL-3B'),
        none,
    ],
    Kamis: [
        c('IF012', 3, 'Rekayasa Perangkat Lunak', 'Gedung A - R201', 'Dr. Hendra Wijaya', 'IF-5B'),
        c('SD005', 2, 'Manajemen Produk Digital', 'Gedung C - R302', 'Farhan, S.Si., M.M.', 'BD-3A'),
        c('MA002', 3, 'Statistika Terapan', 'Gedung B - R102', 'Dewi Kartika, M.Sc.', 'MA-3A'),
        c('TE004', 2, 'Rangkaian Listrik Dasar', 'Lab Rangkaian Listrik', 'Ir. Budi Santoso', 'EL-1A'),
    ],
    Jumat: [
        c('IF002', 3, 'Algoritma & Pemrograman', 'Lab Komputer 2', 'Siti Rahmah, M.Kom.', 'IF-1A'),
        none,
        c('IF020', 3, 'Tata Kelola TI', 'Gedung B - R202', 'Nadia Pratiwi, M.T.', 'SI-5A', 'Dosen meminta kelas pagi, sistem menjadwalkan kelas siang/sore karena keterbatasan ruang'),
        c('FI012', 2, 'Elektromagnetika', 'Gedung B - R104', 'Agus Salim, Ph.D.', 'FI-3B'),
    ],
};

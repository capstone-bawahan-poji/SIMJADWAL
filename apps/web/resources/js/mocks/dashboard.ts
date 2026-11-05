/** Sample data for the faculty dashboard. */

export interface ProblemRoom {
    id: number;
    code: string;
    course: string;
    status: string;
    tone: 'amber' | 'gray' | 'red';
}

export interface RoomConflict {
    id: number;
    room: string;
    time: string;
    detail: string;
}

export interface FakultasDashboardMock {
    period: string;
    constraints: { ready: number; total: number };
    ga: { fitness: number; classes: number; generations: number; hardConflicts: number };
    problemRooms: ProblemRoom[];
    conflicts: RoomConflict[];
}

export const fakultasDashboard: FakultasDashboardMock = {
    period: 'Semester Ganjil 2024/2025',
    constraints: { ready: 5, total: 6 },
    ga: { fitness: 0.98, classes: 166, generations: 42, hardConflicts: 0 },
    problemRooms: [
        { id: 1, code: 'IF-204', course: 'Pemrograman Lanjut', status: 'Over 15 Mhs', tone: 'amber' },
        { id: 2, code: 'SI-301', course: 'Analisis Proses', status: 'Slot Penuh', tone: 'gray' },
        { id: 3, code: 'SD-102', course: 'Aljabar Linier', status: 'Tanpa Ruang', tone: 'red' },
    ],
    conflicts: [
        { id: 1, room: 'Lab Komputasi 1', time: 'Kamis, 10:00', detail: 'TI-A (Pemrograman Web) vs SI-B (Basis Data)' },
        { id: 2, room: 'Ruangan A206', time: 'Selasa, 13:00', detail: 'SD-A (Machine Learning) vs RPL-C (Rekayasa Perangkat Lunak)' },
    ],
};

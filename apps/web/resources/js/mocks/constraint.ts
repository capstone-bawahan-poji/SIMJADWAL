/** Sample data for the constraint pages (prodi editor, shared preview, faculty overview). */

export type Preference = 'Disukai' | 'Netral' | 'Dihindari';

export interface HardConstraint {
    id: string;
    name: string;
    description: string;
}

export interface SoftConstraint {
    id: string;
    name: string;
    description: string;
    weight: number;
    level: string;
    tone: string;
}

export interface LecturerPreferenceSummary {
    id: number;
    name: string;
    identityNumber: string;
    role: string;
    note: string;
    liked: number;
    avoided: number;
    week: Record<'Senin' | 'Selasa' | 'Rabu' | 'Kamis' | 'Jumat', string>;
}

export interface ProgramStatus {
    id: number;
    name: string;
    state: 'ready' | 'warning';
}

export interface SessionRow {
    id: string;
    time: string;
}

export const hardConstraints: HardConstraint[] = [
    { id: 'HC-1', name: 'Bebas bentrok jadwal mengajar dosen', description: 'Maks 1 kelas/waktu' },
    { id: 'HC-2', name: 'Bebas bentrok pemakaian ruangan', description: 'Maks 1 kelas/ruang' },
    { id: 'HC-3', name: 'Kapasitas ruangan memadai', description: 'Kapasitas >= Peserta' },
    { id: 'HC-4', name: 'Kesesuaian jenis lab & praktikum', description: 'Spesifikasi Lab' },
    { id: 'HC-5', name: 'Batas beban SKS harian dosen', description: 'Maks. 12 SKS/Hari' },
];

export const softConstraints: SoftConstraint[] = [
    { id: 'SC-1', name: 'Preferensi Waktu Dosen', description: 'penempatan di slot waktu prioritas', weight: 40, level: 'Tinggi', tone: 'text-indigo-600' },
    { id: 'SC-2', name: 'Distribusi Hari Kuliah Merata', description: 'jarak antar sesi rombel seimbang', weight: 25, level: 'Sedang', tone: 'text-emerald-600' },
    { id: 'SC-3', name: 'Gedung Fakultas Utama', description: 'meminimalisir perpindahan gedung dosen', weight: 20, level: 'Sedang', tone: 'text-amber-600' },
    { id: 'SC-4', name: 'Sesi Berurutan Rombel', description: 'menghindari jam kosong terlalu lama', weight: 15, level: 'Standar', tone: 'text-gray-600' },
];

export const lecturerPreferences: LecturerPreferenceSummary[] = [
    {
        id: 1, name: 'Dr. Hendra Wijaya, M.T.', identityNumber: '197804152003121002', role: 'Koordinator Prodi',
        note: 'Maks. 3 SKS berturut-turut • Prioritas sesi pagi', liked: 8, avoided: 2,
        week: { Senin: 'Pagi & Siang', Selasa: 'Pagi & Siang', Rabu: 'Rapat Prodi', Kamis: 'Pagi & Siang', Jumat: 'Pagi Saja' },
    },
    {
        id: 2, name: 'Ratna Sari Dewi, Ph.D.', identityNumber: '198211092008012001', role: '',
        note: 'Fokus riset lab hari Selasa & Kamis (dihindari mengajar kelas)', liked: 6, avoided: 0,
        week: { Senin: 'Pagi & Siang', Selasa: 'Riset Lab', Rabu: 'Siang & Sore', Kamis: 'Riset Lab', Jumat: 'Pagi Saja' },
    },
    {
        id: 3, name: 'Bambang Wicaksono, S.T., M.T.', identityNumber: '196508221991031004', role: 'Guru Besar',
        note: 'Praktikum lab komputer diutamakan sesi siang (Sesi 2 & 3)', liked: 10, avoided: 4,
        week: { Senin: 'Pagi & Siang', Selasa: 'Tidak Bersedia', Rabu: 'Pagi & Siang', Kamis: 'Tidak Bersedia', Jumat: 'Tidak Bersedia' },
    },
    {
        id: 4, name: 'Prof. Dr. Ir. Gunawan, M.Eng.', identityNumber: '199002242019031011', role: '',
        note: 'Kuliah pagi hari maksimal 2 hari perkuliahan per pekan', liked: 4, avoided: 2,
        week: { Senin: 'Siang & Sore', Selasa: 'Pagi & Siang', Rabu: 'Pagi & Siang', Kamis: 'Siang & Sore', Jumat: 'Pagi Saja' },
    },
];

export const programStatuses: ProgramStatus[] = [
    { id: 1, name: 'S1 Informatika', state: 'ready' },
    { id: 2, name: 'S1 Sistem Informasi', state: 'ready' },
    { id: 3, name: 'S1 Teknik Elektro', state: 'ready' },
    { id: 4, name: 'S1 Sains Data', state: 'warning' },
    { id: 5, name: 'S1 Matematika', state: 'ready' },
    { id: 6, name: 'S1 Fisika', state: 'ready' },
];

// Prodi editor: availability matrix of one lecturer.
export const lecturerOptions = [
    { id: 1, label: 'Dr. Eng. Ir. Suryadi, M.T. — Kecerdasan Buatan' },
    { id: 2, label: 'Dr. Ir. Herman Susanto, M.T. — Struktur Data' },
    { id: 3, label: 'Budi Prasetyo, M.Kom. — Pemrograman PBO' },
];

export const preferenceDays = ['SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU'] as const;

export const preferenceSessions: SessionRow[] = [
    { id: 'Sesi 1', time: '07.30 - 10.00' },
    { id: 'Sesi 2', time: '10.15 - 12.45' },
    { id: 'Sesi 3', time: '13.15 - 15.45' },
    { id: 'Sesi 4', time: '16.00 - 18.30' },
];

export const preferenceMatrix: Record<string, Record<string, Preference>> = {
    'Sesi 1': { SENIN: 'Disukai', SELASA: 'Disukai', RABU: 'Netral', KAMIS: 'Disukai', JUMAT: 'Dihindari', SABTU: 'Dihindari' },
    'Sesi 2': { SENIN: 'Disukai', SELASA: 'Disukai', RABU: 'Netral', KAMIS: 'Disukai', JUMAT: 'Dihindari', SABTU: 'Dihindari' },
    'Sesi 3': { SENIN: 'Netral', SELASA: 'Netral', RABU: 'Disukai', KAMIS: 'Netral', JUMAT: 'Netral', SABTU: 'Dihindari' },
    'Sesi 4': { SENIN: 'Dihindari', SELASA: 'Dihindari', RABU: 'Netral', KAMIS: 'Dihindari', JUMAT: 'Netral', SABTU: 'Dihindari' },
};

export const reviewSummary = [
    { label: 'Preferensi Sesi Dosen', value: '38 / 38 Dosen', hint: 'Seluruh dosen telah memilih slot mengajar.' },
    { label: 'Preferensi SKS & Waktu', value: '100% Terkonfigurasi', hint: 'Maks. SKS per hari & jeda istirahat tervalidasi.' },
    { label: 'Bobot Penalti (Soft Constraint)', value: 'Total 100 Poin', hint: 'Sesuai standar default fakultas.' },
];

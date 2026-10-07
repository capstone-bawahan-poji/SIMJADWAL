/** Sample data for the faculty room allocation page. Conflict detection is a backend job. */

export interface RoomClash {
    id: number;
    time: string;
    session: string;
    room: string;
    existingClass: string;
    clashingClass: string;
    suggestion: string;
}

export interface UnmappedSlot {
    id: number;
    time: string;
    session: string;
    course: string;
    lecturer: string;
    need: string;
    capacity: string;
}

export const roomClashes: RoomClash[] = [
    { id: 1, time: 'Senin, 07:30', session: 'Sesi 1 • 3 SKS', room: 'R. A206', existingClass: 'IF2101 Struktur Data (IF-A)', clashingClass: 'TE3104 Sinyal & Sistem (TE-B)', suggestion: 'Pindah TE3104 ke R. B105' },
    { id: 2, time: 'Selasa, 10:20', session: 'Sesi 2 • 3 SKS', room: 'Lab Komputer 1', existingClass: 'IF3204 Kecerdasan Buatan (IF)', clashingClass: 'BD1101 Praktikum Bisnis (BD)', suggestion: 'Pindah BD1101 ke Lab Komputer AI' },
    { id: 3, time: 'Kamis, 13:00', session: 'Sesi 3 • 3 SKS', room: 'R. C302', existingClass: 'SI3301 Tata Kelola IT (SI-A)', clashingClass: 'MA2201 Statistika Terapan (MA-B)', suggestion: 'Pindah MA2201 ke R. C305' },
];

export const unmappedSlots: UnmappedSlot[] = [
    { id: 1, time: 'Rabu, 10:20 - 12:50', session: 'Sesi 2', course: 'FI1201 Fisika Komputasi', lecturer: 'Dr. Supriatna', need: 'Lab Komputasi Sains', capacity: '28 Mahasiswa' },
    { id: 2, time: 'Kamis, 07:30 - 10:00', session: 'Sesi 1', course: 'IF3102 Rekayasa Perangkat Lunak', lecturer: 'Anita Rahmawati, M.T.', need: 'Ruang Teori Besar', capacity: '50 Mahasiswa' },
    { id: 3, time: 'Jumat, 07:30 - 09:10', session: 'Sesi 1', course: 'BD2202 Manajemen Produk', lecturer: 'Farhan Kamil, M.B.A.', need: 'Ruang Teori Multimedia', capacity: '38 Mahasiswa' },
];

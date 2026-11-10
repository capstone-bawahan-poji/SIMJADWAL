export const FACULTY_PROGRAMS = [
    { id: 'IF', code: 'IF', name: 'Informatika' },
    { id: 'SI', code: 'SI', name: 'Sistem Informasi' },
    { id: 'TM', code: 'TM', name: 'Teknik Mesin' },
    { id: 'TE', code: 'TE', name: 'Teknik Elektro' },
    { id: 'TL', code: 'TL', name: 'Teknik Lingkungan' },
    { id: 'PWK', code: 'PWK', name: 'Perencanaan Wilayah dan Kota' },
] as const;

export const FACULTY_PROGRAM_SLOT_STORAGE_KEY = 'simjadwal:fakultas:program-slot-allocations';

export const FACULTY_PROGRAM_CLASSES = ['A', 'B', 'C', 'D', 'E', 'F'] as const;

export type FacultyProgramSlotAllocation = {
    programId: (typeof FACULTY_PROGRAMS)[number]['id'];
    classLabel: (typeof FACULTY_PROGRAM_CLASSES)[number];
};

export interface StudyProgram {
    id: number;
    code: string;
    name: string;
}

export interface Faculty {
    id: number;
    code: string;
    name: string;
    study_programs: StudyProgram[];
}

export interface CourseItem {
    id: number;
    code: string;
    name: string;
    sks: number;
    semester: number;
    faculty_id: number;
    faculty_code: string;
    study_program_id: number;
    study_program_name: string;
    classes: { id: number; name: string; capacity: number }[];
}

export const faculties: Faculty[] = [
    {
        id: 1,
        code: 'FSTI',
        name: 'Fakultas Sains dan Teknologi Informasi',
        study_programs: [
            { id: 1, code: 'IF', name: 'Informatika' },
            { id: 2, code: 'SI', name: 'Sistem Informasi' },
        ],
    },
    {
        id: 2,
        code: 'FTSP',
        name: 'Fakultas Teknik Sipil dan Perencanaan',
        study_programs: [{ id: 3, code: 'TS', name: 'Teknik Sipil' }],
    },
    {
        id: 3,
        code: 'FTI',
        name: 'Fakultas Teknologi Industri',
        study_programs: [{ id: 4, code: 'TI', name: 'Teknik Industri' }],
    },
];

const classes = [
    { id: 1, name: 'Kelas A', capacity: 40 },
    { id: 2, name: 'Kelas B', capacity: 40 },
];

const items: Array<[number, string, string, number, number]> = [
    [1, 'IF101', 'Dasar Pemrograman', 3, 1],
    [1, 'IF102', 'Matematika Diskrit', 3, 1],
    [1, 'IF201', 'Algoritma dan Struktur Data', 3, 2],
    [1, 'IF202', 'Basis Data', 3, 2],
    [1, 'IF301', 'Pemrograman Web', 3, 3],
    [2, 'SI101', 'Pengantar Sistem Informasi', 3, 1],
    [2, 'SI102', 'Dasar Manajemen', 2, 1],
    [2, 'SI201', 'Analisis Proses Bisnis', 3, 2],
    [2, 'SI202', 'Manajemen Basis Data', 3, 2],
    [2, 'SI301', 'Interaksi Manusia dan Komputer', 3, 3],
    [3, 'TS101', 'Pengantar Teknik Sipil', 2, 1],
    [3, 'TS201', 'Mekanika Teknik', 3, 2],
    [3, 'TS202', 'Ilmu Ukur Tanah', 3, 2],
    [4, 'TI101', 'Pengantar Teknik Industri', 2, 1],
    [4, 'TI201', 'Statistika Industri', 3, 2],
    [4, 'TI202', 'Perencanaan Produksi', 3, 2],
];

export const courses: CourseItem[] = items.map(([programId, code, name, sks, semester], index) => {
    const faculty = faculties.find((item) => item.study_programs.some((program) => program.id === programId));
    const program = faculty?.study_programs.find((item) => item.id === programId);
    if (!faculty || !program) throw new Error(`Unknown mock study program: ${programId}`);

    return {
        id: index + 1,
        code,
        name,
        sks,
        semester,
        faculty_id: faculty.id,
        faculty_code: faculty.code,
        study_program_id: program.id,
        study_program_name: program.name,
        classes,
    };
});

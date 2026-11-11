import type { SchedulingDataset, TpbCohort } from '@/types/scheduling';

export const COHORT_LABELS: Record<TpbCohort, string> = {
  maba: 'Mahasiswa baru',
  non_maba: 'Non-maba reguler',
  campuran: 'Maba dan non-maba',
  mengulang: 'Kelas mengulang',
  pilihan: 'MK pilihan',
};

export const TPB_CONSTRAINT_CODES = ['HC2', 'HC3', 'SC_SKS'] as const;

export const TPB_CONSTRAINT_NOTES: Record<string, string> = {
  HC2: 'Satu dosen tidak mengajar dua kelas pada slot yang sama (lintas grup dan lintas mata kuliah).',
  HC3: 'Satu program studi tidak mengikuti dua grup reguler dengan semester sasaran sama pada slot yang sama.',
  SC_SKS: 'Mata kuliah 3 SKS pada slot tipe 3, 2 SKS pada slot tipe 2. Mata kuliah 4 SKS dibagi dua pertemuan 2 SKS.',
};

export function tpbConstraintTypes(dataset: SchedulingDataset) {
  return dataset.constraint_types
    .filter((c) => (TPB_CONSTRAINT_CODES as readonly string[]).includes(c.code))
    .map((c) => ({ ...c, description: TPB_CONSTRAINT_NOTES[c.code] ?? c.description }));
}

export function groupOfClass(dataset: SchedulingDataset, courseId: number, classNumber: number) {
  const cls = dataset.classes.find((k) => k.course_id === courseId && k.class_number === classNumber);
  return dataset.tpb_groups?.find((g) => g.id === cls?.tpb_group_id) ?? null;
}

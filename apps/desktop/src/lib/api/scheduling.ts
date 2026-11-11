import { call } from '@/lib/api/client';
import type { FacultyOption, SchedulingDataset, StudyProgramOption } from '@/types/scheduling';

/** studyProgramId: superadmin only. admin_prodi gets their own program, admin_tpb the TPB dataset. */
export function fetchDataset(params: { period?: string; studyProgramId?: number | null; reviseGroupIds?: number[] }) {
  return call<SchedulingDataset>('api_dataset', {
    period: params.period ?? null,
    studyProgramId: params.studyProgramId ?? null,
    reviseGroupIds: params.reviseGroupIds?.length ? params.reviseGroupIds : null,
  });
}

export function fetchFaculties() {
  return call<FacultyOption[]>('api_faculties');
}

export function fetchStudyPrograms() {
  return call<StudyProgramOption[]>('api_study_programs');
}

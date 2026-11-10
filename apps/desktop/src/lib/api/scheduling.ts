import { call } from '@/lib/api/client';
import type { FacultyOption, SchedulingDataset } from '@/types/scheduling';

export function fetchDataset(params: { period?: string; facultyId?: number | null; reviseGroupIds?: number[] }) {
  return call<SchedulingDataset>('api_dataset', {
    period: params.period ?? null,
    facultyId: params.facultyId ?? null,
    reviseGroupIds: params.reviseGroupIds?.length ? params.reviseGroupIds : null,
  });
}

export function fetchFaculties() {
  return call<FacultyOption[]>('api_faculties');
}
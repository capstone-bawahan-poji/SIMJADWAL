import { api } from '@/lib/http';
import type { FacultyOption, SchedulingDataset } from '@/types/scheduling';

export function fetchDataset(params: { period?: string; facultyId?: number | null }) {
  const query = new URLSearchParams();
  if (params.period) query.set('period', params.period);
  if (params.facultyId) query.set('faculty_id', String(params.facultyId));
  const qs = query.toString();
  return api<SchedulingDataset>(`/scheduling/dataset${qs ? `?${qs}` : ''}`);
}

export function fetchFaculties() {
  return api<FacultyOption[]>('/faculties?per_page=100');
}

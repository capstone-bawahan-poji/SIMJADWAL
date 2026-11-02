import { call } from '@/lib/api/client';
import type { RunListItem } from '@/types/run';

export const uploadResult = (runId: string) => call<RunListItem>('ga_upload_result', { runId });

export function listServerRuns(facultyId: number | null, page: number) {
  return call<unknown>('api_scheduling_runs', { facultyId, page });
}
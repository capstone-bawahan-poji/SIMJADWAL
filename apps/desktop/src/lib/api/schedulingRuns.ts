import { call } from '@/lib/api/client';
import type { RunListItem } from '@/types/run';

export const uploadResult = (runId: string) => call<RunListItem>('ga_upload_result', { runId });
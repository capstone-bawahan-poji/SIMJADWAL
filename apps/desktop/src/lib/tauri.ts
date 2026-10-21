import { invoke } from '@tauri-apps/api/core';
import { listen, type UnlistenFn } from '@tauri-apps/api/event';
import type { GaParams, ScOverrides, SelectionOperator } from '@/types/ga';
import type { BatchFinishedEvent, BatchStarted, ProgressEvent, RunFinishedEvent, RunListItem, RunRecord } from '@/types/run';
import type { SchedulingDataset } from '@/types/scheduling';

export const greet = (name: string) => invoke<string>('greet', { name });

export interface BatchRequest {
  dataset: SchedulingDataset;
  config: {
    params: GaParams;
    weights: Record<string, number>;
    sc_weights_by_study_program: ScOverrides;
    preset_id: number | null;
  };
  operators: SelectionOperator[];
}

export const gaStartBatch = (request: BatchRequest) => invoke<BatchStarted>('ga_start_batch', { request });
export const gaCancel = (batchId: string) => invoke<boolean>('ga_cancel', { batchId });
export const gaRunningBatches = () => invoke<string[]>('ga_running_batches');
export const gaListResults = () => invoke<RunListItem[]>('ga_list_results');
export const gaGetResult = (runId: string) => invoke<RunRecord>('ga_get_result', { runId });
export const gaMarkUploaded = (runId: string, serverRunId: number) => invoke<RunListItem>('ga_mark_uploaded', { runId, serverRunId });
export const gaMarkUploadFailed = (runId: string, message: string) => invoke<RunListItem>('ga_mark_upload_failed', { runId, message });
export const gaDeleteResult = (runId: string) => invoke<void>('ga_delete_result', { runId });

export const onGaProgress = (fn: (e: ProgressEvent) => void): Promise<UnlistenFn> =>
  listen<ProgressEvent>('ga://progress', (event) => fn(event.payload));
export const onGaRunFinished = (fn: (e: RunFinishedEvent) => void): Promise<UnlistenFn> =>
  listen<RunFinishedEvent>('ga://run-finished', (event) => fn(event.payload));
export const onGaBatchFinished = (fn: (e: BatchFinishedEvent) => void): Promise<UnlistenFn> =>
  listen<BatchFinishedEvent>('ga://batch-finished', (event) => fn(event.payload));

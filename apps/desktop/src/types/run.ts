import type { SelectionOperator } from '@/types/ga';

export interface RunSpec {
  run_id: string;
  operator: SelectionOperator;
  run_index: number;
  seed: number;
}

export interface BatchStarted {
  batch_id: string;
  runs: RunSpec[];
}

export interface RunSummary {
  hc_violations: number;
  sc_unmet: number;
  unassigned_rooms: number;
  fitness_percent: number;
}

export interface ScheduleDetail {
  course_id: number;
  class_number: number;
  meeting_number: number;
  lecturer_id: number;
  time_slot_id: number;
  room_id: number | null;
}

export interface Violation {
  detail_index: number;
  constraint_code: string;
  penalty: number;
  message: string;
}

export interface RunResult {
  run_id: string;
  batch_id: string;
  faculty_id: number;
  period: string;
  dataset_fingerprint: string;
  preset_id: number | null;
  operator: SelectionOperator;
  run_index: number;
  parameter_snapshot: Record<string, unknown>;
  best_fitness: number;
  max_fitness: number;
  best_generation: number;
  best_chromosome_index: number;
  final_generation: number;
  fitness_history: number[];
  cancelled: boolean;
  started_at_ms: number;
  finished_at_ms: number;
  summary: RunSummary;
  schedule_details: ScheduleDetail[];
  violations: Violation[];
}

export type UploadStatus = 'pending' | 'uploaded' | 'failed';

export interface UploadState {
  status: UploadStatus;
  server_run_id: number | null;
  error: string | null;
}

export interface RunRecord {
  result: RunResult;
  upload: UploadState;
}

export interface RunListItem {
  run_id: string;
  batch_id: string;
  period: string;
  operator: SelectionOperator;
  run_index: number;
  best_fitness: number;
  max_fitness: number;
  best_generation: number;
  final_generation: number;
  cancelled: boolean;
  started_at_ms: number;
  finished_at_ms: number;
  summary: RunSummary;
  upload: UploadState;
}

export interface ProgressEvent {
  batch_id: string;
  run_id: string;
  generation: number;
  best_fitness: number;
  current_fitness: number;
}

export interface RunFinishedEvent {
  batch_id: string;
  run_id: string;
  best_fitness: number;
  max_fitness: number;
  final_generation: number;
  cancelled: boolean;
  summary: RunSummary;
  error: string | null;
}

export interface BatchFinishedEvent {
  batch_id: string;
  cancelled: boolean;
  error: string | null;
}
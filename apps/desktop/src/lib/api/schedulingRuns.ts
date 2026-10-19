import { api } from '@/lib/http';
import type { RunResult } from '@/types/run';

export interface ServerRun {
  id: number;
  status: string;
  best_fitness: number;
  hc_violations: number;
}

export function uploadRun(result: RunResult) {
  return api<ServerRun>('/scheduling-runs', {
    method: 'POST',
    body: JSON.stringify({
      client_run_id: result.run_id,
      batch_id: result.batch_id,
      faculty_id: result.faculty_id,
      period: result.period,
      dataset_fingerprint: result.dataset_fingerprint,
      preset_id: result.preset_id,
      parameter_snapshot: result.parameter_snapshot,
      best_fitness: result.best_fitness,
      max_fitness: result.max_fitness,
      best_generation: result.best_generation,
      final_generation: result.final_generation,
      fitness_history: result.fitness_history,
      started_at: new Date(result.started_at_ms).toISOString(),
      finished_at: new Date(result.finished_at_ms).toISOString(),
      schedule_details: result.schedule_details,
      violations: result.violations,
    }),
  });
}

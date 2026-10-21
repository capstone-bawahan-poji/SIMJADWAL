import { computed, ref } from 'vue';
import { useDataset } from '@/composables/useDataset';
import { useGaConfig } from '@/composables/useGaConfig';
import { useRunSync } from '@/composables/useRunSync';
import { gaCancel, gaStartBatch, onGaBatchFinished, onGaProgress, onGaRunFinished } from '@/lib/tauri';
import type { RunSpec, RunSummary } from '@/types/run';

export interface RunProgress extends RunSpec {
  generation: number;
  best_fitness: number | null;
  current_fitness: number | null;
  max_fitness: number | null;
  finished: boolean;
  cancelled: boolean;
  summary: RunSummary | null;
  error: string | null;
}

const batchId = ref<string | null>(null);
const runs = ref<RunProgress[]>([]);
const running = ref(false);
const error = ref('');
let listening: Promise<void> | null = null;
let pending: Array<{ batchId: string; apply: () => void }> = [];

function route(eventBatchId: string, apply: () => void) {
  if (batchId.value === eventBatchId) apply();
  else if (running.value && batchId.value === null) pending.push({ batchId: eventBatchId, apply });
}

function patch(runId: string, values: Partial<RunProgress>) {
  runs.value = runs.value.map((r) => (r.run_id === runId ? { ...r, ...values } : r));
}

function listenOnce() {
  listening ??= (async () => {
    const { load } = useRunSync();
    await onGaProgress((e) => route(e.batch_id, () => {
      patch(e.run_id, { generation: e.generation, best_fitness: e.best_fitness, current_fitness: e.current_fitness });
    }));
    await onGaRunFinished((e) => route(e.batch_id, () => {
      patch(e.run_id, {
        generation: e.final_generation,
        best_fitness: e.best_fitness,
        max_fitness: e.max_fitness,
        finished: true,
        cancelled: e.cancelled,
        summary: e.summary,
        error: e.error,
      });
    }));
    await onGaBatchFinished((e) => route(e.batch_id, () => {
      running.value = false;
      if (e.error) error.value = e.error;
      load();
    }));
  })();
  return listening;
}

export function useGaRunner() {
  const { dataset } = useDataset();
  const { params, scOverrides, presetId, runMode, multiOperators } = useGaConfig();

  async function start() {
    const d = dataset.value;
    if (!d) throw new Error('Dataset belum dimuat.');
    await listenOnce();
    error.value = '';
    batchId.value = null;
    pending = [];
    running.value = true;
    try {
      const started = await gaStartBatch({
        dataset: d,
        config: {
          params: { ...params },
          weights: Object.fromEntries(d.constraint_types.map((t) => [t.code, t.weight])),
          sc_weights_by_study_program: scOverrides.value,
          preset_id: presetId.value,
        },
        operators: runMode.value === 'multi' ? multiOperators.value : [params.selection_operator],
      });
      batchId.value = started.batch_id;
      runs.value = started.runs.map((spec) => ({
        ...spec,
        generation: 0,
        best_fitness: null,
        current_fitness: null,
        max_fitness: null,
        finished: false,
        cancelled: false,
        summary: null,
        error: null,
      }));
      const held = pending.filter((p) => p.batchId === started.batch_id);
      pending = [];
      held.forEach((p) => p.apply());
    } catch (e) {
      running.value = false;
      error.value = String(e);
      throw e;
    }
  }

  async function cancel() {
    if (batchId.value) await gaCancel(batchId.value);
  }

  return {
    batchId: computed(() => batchId.value),
    runs: computed(() => runs.value),
    running: computed(() => running.value),
    error: computed(() => error.value),
    maxGenerations: computed(() => params.max_generations),
    start, cancel, listen: listenOnce,
  };
}

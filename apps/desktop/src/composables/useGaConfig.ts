import { computed, reactive, ref, watch } from 'vue';
import { taDefaults, validateParams, type GaParams, type GaPreset, type ScOverrides, type SelectionOperator } from '@/types/ga';

const params = reactive<GaParams>(taDefaults(null));
const scOverrides = ref<ScOverrides>({});
const presetId = ref<number | null>(null);
const dirty = ref(false);
const runMode = ref<'single' | 'multi'>('single');
const multiOperators = ref<SelectionOperator[]>(['TS', 'TSR']);

let applying = false;
watch([params, scOverrides], () => {
  if (!applying) dirty.value = true;
}, { deep: true, flush: 'sync' });

function apply(values: GaParams, overrides: ScOverrides, id: number | null) {
  applying = true;
  Object.assign(params, values);
  scOverrides.value = JSON.parse(JSON.stringify(overrides));
  presetId.value = id;
  dirty.value = false;
  applying = false;
}

export function useGaConfig() {
  function applyPreset(preset: GaPreset) {
    const { population_size, min_generations, max_generations, stagnation_limit, crossover_rate, mutation_rate,
      tournament_size, selection_operator, parallel_class_limit, fitness_constant, base_seed, run_count } = preset;
    apply(
      { population_size, min_generations, max_generations, stagnation_limit, crossover_rate, mutation_rate,
        tournament_size, selection_operator, parallel_class_limit, fitness_constant, base_seed, run_count },
      Array.isArray(preset.sc_overrides) ? {} : preset.sc_overrides,
      preset.id,
    );
  }

  function resetDefault(period: string | null) {
    apply(taDefaults(period), {}, null);
  }

  function setOverride(programId: number, code: string, value: number | null) {
    const key = String(programId);
    const row = { ...(scOverrides.value[key] ?? {}) };
    if (value === null || !(value > 0)) delete row[code];
    else row[code] = value;
    const next = { ...scOverrides.value };
    if (Object.keys(row).length) next[key] = row;
    else delete next[key];
    scOverrides.value = next;
  }

  function clearOverrides(programId: number) {
    const next = { ...scOverrides.value };
    delete next[String(programId)];
    scOverrides.value = next;
  }

  function markSaved(id: number | null) {
    presetId.value = id;
    dirty.value = false;
  }

  return {
    params,
    scOverrides: computed(() => scOverrides.value),
    presetId: computed(() => presetId.value),
    dirty: computed(() => dirty.value),
    errors: computed(() => validateParams(params)),
    runMode,
    multiOperators,
    applyPreset, resetDefault, setOverride, clearOverrides, markSaved,
  };
}
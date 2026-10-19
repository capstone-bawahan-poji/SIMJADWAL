import { computed, readonly, ref } from 'vue';
import { fetchDataset } from '@/lib/api/scheduling';
import type { SchedulingDataset } from '@/types/scheduling';

const dataset = ref<SchedulingDataset | null>(null);
const period = ref<string | null>(null);
const facultyId = ref<number | null>(null);
const loading = ref(false);
const error = ref('');

/** Odd/even terms of the loaded academic year and the next one. */
function periodOptions(current: string | null): string[] {
  const start = Number(current?.slice(0, 4)) || new Date().getFullYear();
  return [start, start + 1].flatMap((year) => [`${year}/${year + 1}-GANJIL`, `${year}/${year + 1}-GENAP`]);
}

export function useDataset() {
  async function load() {
    loading.value = true;
    error.value = '';
    try {
      dataset.value = await fetchDataset({ period: period.value ?? undefined, facultyId: facultyId.value });
      period.value = dataset.value.period;
    } catch (e) {
      error.value = (e as Error).message;
    } finally {
      loading.value = false;
    }
  }

  function setPeriod(value: string) {
    period.value = value;
    return load();
  }

  function setFaculty(value: number | null) {
    facultyId.value = value;
    dataset.value = null;
    return value ? load() : Promise.resolve();
  }

  function clear() {
    dataset.value = null;
    period.value = null;
    facultyId.value = null;
    error.value = '';
  }

  return {
    dataset: computed(() => dataset.value),
    period: readonly(period),
    facultyId: readonly(facultyId),
    loading: readonly(loading),
    error: readonly(error),
    periodOptions: computed(() => periodOptions(period.value)),
    load, setPeriod, setFaculty, clear,
  };
}

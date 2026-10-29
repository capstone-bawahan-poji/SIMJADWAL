import { computed, readonly, ref } from 'vue';
import { fetchDataset } from '@/lib/api/scheduling';
import { onChanges } from '@/lib/echo';
import type { SchedulingDataset } from '@/types/scheduling';

const dataset = ref<SchedulingDataset | null>(null);
const period = ref<string | null>(null);
const facultyId = ref<number | null>(null);
const loading = ref(false);
const error = ref('');
const stale = ref(false);

const DATASET_TABLES = [
  'faculties', 'study_programs', 'courses', 'course_lecturers', 'lecturers', 'rooms',
  'time_slots', 'tpb_groups', 'lecturer_preferences', 'constraint_types',
];

onChanges(DATASET_TABLES, () => {
  if (dataset.value) stale.value = true;
});

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
      stale.value = false;
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
    stale.value = false;
  }

  return {
    dataset: computed(() => dataset.value),
    period: readonly(period),
    facultyId: readonly(facultyId),
    loading: readonly(loading),
    error: readonly(error),
    stale: readonly(stale),
    periodOptions: computed(() => periodOptions(period.value)),
    load, setPeriod, setFaculty, clear,
  };
}
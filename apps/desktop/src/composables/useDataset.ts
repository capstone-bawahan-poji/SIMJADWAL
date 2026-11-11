import { computed, readonly, ref } from 'vue';
import { fetchDataset } from '@/lib/api/scheduling';
import { onChanges } from '@/lib/echo';
import type { SchedulingDataset } from '@/types/scheduling';

const dataset = ref<SchedulingDataset | null>(null);
const period = ref<string | null>(null);
/** superadmin: the program picked in the context bar. Others work on their own scope. */
const studyProgramId = ref<number | null>(null);
const loading = ref(false);
const error = ref('');
const stale = ref(false);
const reviseGroupIds = ref<number[]>([]);

const DATASET_TABLES = [
  'faculties', 'study_programs', 'courses', 'course_lecturers', 'lecturers', 'rooms',
  'time_slots', 'tpb_groups', 'lecturer_preferences', 'constraint_types',
  // Program datasets also change when the faculty moves cells or another program activates a run.
  'room_slot_allocations', 'scheduling_runs', 'program_schedule_submissions', 'tpb_occupancy_imports',
];

onChanges(DATASET_TABLES, () => {
  if (dataset.value) stale.value = true;
});

function periodOptions(current: string | null): string[] {
  const start = Number(current?.slice(0, 4)) || new Date().getFullYear();
  return [start, start + 1].flatMap((year) => [`${year}/${year + 1}-GANJIL`, `${year}/${year + 1}-GENAP`]);
}

/** A dataset belongs to the signed-in account's scope (TPB or one study program): drop it on sign-out. */
export function resetDataset() {
  dataset.value = null;
  period.value = null;
  studyProgramId.value = null;
  error.value = '';
  stale.value = false;
  reviseGroupIds.value = [];
}

export function useDataset() {
  async function load() {
    loading.value = true;
    error.value = '';
    try {
      dataset.value = await fetchDataset({ period: period.value ?? undefined, studyProgramId: studyProgramId.value, reviseGroupIds: reviseGroupIds.value });
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
    reviseGroupIds.value = [];
    return load();
  }

  function setStudyProgram(value: number | null) {
    studyProgramId.value = value;
    dataset.value = null;
    return value ? load() : Promise.resolve();
  }

  function setRevision(ids: number[]) {
    reviseGroupIds.value = ids;
    return load();
  }

  function clear() {
    reviseGroupIds.value = [];
    dataset.value = null;
    period.value = null;
    studyProgramId.value = null;
    error.value = '';
    stale.value = false;
  }

  return {
    dataset: computed(() => dataset.value),
    period: readonly(period),
    studyProgramId: readonly(studyProgramId),
    loading: readonly(loading),
    error: readonly(error),
    stale: readonly(stale),
    periodOptions: computed(() => periodOptions(period.value)),
    reviseGroupIds: readonly(reviseGroupIds),
    load, setPeriod, setStudyProgram, setRevision, clear,
  };
}
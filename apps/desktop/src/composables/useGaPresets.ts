import { ref } from 'vue';
import { createPreset, deletePreset, listPresets, updatePreset } from '@/lib/api/gaPresets';
import { onChanges } from '@/lib/echo';
import type { GaPreset, GaPresetPayload } from '@/types/ga';

const presets = ref<GaPreset[]>([]);
const loadedFor = ref<number | null | undefined>(undefined);
const loading = ref(false);
const error = ref('');

export function resetGaPresets() {
  presets.value = [];
  loadedFor.value = undefined;
  error.value = '';
}

async function fetchPresets(studyProgramId: number | null) {
  loading.value = true;
  error.value = '';
  try {
    presets.value = await listPresets(studyProgramId);
    loadedFor.value = studyProgramId;
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    loading.value = false;
  }
}

onChanges(['ga_parameter_presets'], () => {
  if (loadedFor.value !== undefined) void fetchPresets(loadedFor.value);
});

export function useGaPresets() {
  async function load(studyProgramId: number | null, force = false) {
    if (!force && loadedFor.value === studyProgramId) return;
    await fetchPresets(studyProgramId);
  }

  async function create(payload: GaPresetPayload) {
    const preset = await createPreset(payload);
    presets.value = [...presets.value, preset].sort((a, b) => a.name.localeCompare(b.name));
    return preset;
  }

  async function update(id: number, payload: GaPresetPayload) {
    const preset = await updatePreset(id, payload);
    presets.value = presets.value.map((p) => (p.id === id ? preset : p)).sort((a, b) => a.name.localeCompare(b.name));
    return preset;
  }

  async function remove(id: number) {
    await deletePreset(id);
    presets.value = presets.value.filter((p) => p.id !== id);
  }

  return { presets, loading, error, load, create, update, remove };
}
import { ref } from 'vue';
import { createPreset, deletePreset, listPresets, updatePreset } from '@/lib/api/gaPresets';
import type { GaPreset, GaPresetPayload } from '@/types/ga';

const presets = ref<GaPreset[]>([]);
const loadedFor = ref<number | null>(null);
const loading = ref(false);
const error = ref('');

export function useGaPresets() {
  async function load(facultyId: number, force = false) {
    if (!force && loadedFor.value === facultyId) return;
    loading.value = true;
    error.value = '';
    try {
      presets.value = await listPresets(facultyId);
      loadedFor.value = facultyId;
    } catch (e) {
      error.value = (e as Error).message;
    } finally {
      loading.value = false;
    }
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

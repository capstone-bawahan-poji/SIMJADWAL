import { api } from '@/lib/http';
import type { GaPreset, GaPresetPayload } from '@/types/ga';

export const listPresets = (facultyId: number) => api<GaPreset[]>(`/ga-presets?faculty_id=${facultyId}`);

export const createPreset = (payload: GaPresetPayload) =>
  api<GaPreset>('/ga-presets', { method: 'POST', body: JSON.stringify(payload) });

export const updatePreset = (id: number, payload: GaPresetPayload) =>
  api<GaPreset>(`/ga-presets/${id}`, { method: 'PUT', body: JSON.stringify(payload) });

export const deletePreset = (id: number) => api<void>(`/ga-presets/${id}`, { method: 'DELETE' });
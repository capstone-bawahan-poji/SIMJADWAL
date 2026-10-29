import { call } from '@/lib/api/client';
import type { GaPreset, GaPresetPayload } from '@/types/ga';

export const listPresets = (facultyId: number) => call<GaPreset[]>('api_ga_presets', { facultyId });
export const createPreset = (payload: GaPresetPayload) => call<GaPreset>('api_create_ga_preset', { input: payload });
export const updatePreset = (id: number, payload: GaPresetPayload) => call<GaPreset>('api_update_ga_preset', { id, input: payload });
export const deletePreset = (id: number) => call<void>('api_delete_ga_preset', { id });
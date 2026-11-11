import { call } from '@/lib/api/client';
import type { GaPreset, GaPresetPayload } from '@/types/ga';

/** studyProgramId null = the caller's own scope (their program, or TPB). */
export const listPresets = (studyProgramId: number | null) => call<GaPreset[]>('api_ga_presets', { studyProgramId });
export const createPreset = (payload: GaPresetPayload) => call<GaPreset>('api_create_ga_preset', { input: payload });
export const updatePreset = (id: number, payload: GaPresetPayload) => call<GaPreset>('api_update_ga_preset', { id, input: payload });
export const deletePreset = (id: number) => call<void>('api_delete_ga_preset', { id });
export type SelectionOperator = 'TS' | 'TSR';

export interface GaParams {
  population_size: number;
  min_generations: number;
  max_generations: number;
  stagnation_limit: number;
  crossover_rate: number;
  mutation_rate: number;
  tournament_size: number;
  selection_operator: SelectionOperator;
  parallel_class_limit: number;
  fitness_constant: number;
  base_seed: number;
  run_count: number;
}

/** {"<study_program_id>": {"SC_INGIN": 2}}. Soft constraint codes only, weights above 0. */
export type ScOverrides = Record<string, Record<string, number>>;

export interface GaPreset extends GaParams {
  id: number;
  faculty_id: number;
  name: string;
  sc_overrides: ScOverrides | [];
  created_by: number | null;
  updated_at: string | null;
  can_update: boolean;
  can_delete: boolean;
}

export type GaPresetPayload = GaParams & { name: string; sc_overrides: ScOverrides; faculty_id?: number };

export function taDefaults(period: string | null): GaParams {
  const even = period?.endsWith('-GENAP') ?? false;
  return {
    population_size: 100,
    min_generations: 500,
    max_generations: 1000,
    stagnation_limit: 200,
    crossover_rate: even ? 0.4 : 0.9,
    mutation_rate: even ? 0.3 : 0.5,
    tournament_size: 2,
    selection_operator: even ? 'TSR' : 'TS',
    parallel_class_limit: 5,
    fitness_constant: 500,
    base_seed: 12345,
    run_count: 10,
  };
}

export function validateParams(p: GaParams): Partial<Record<keyof GaParams, string>> {
  const errors: Partial<Record<keyof GaParams, string>> = {};
  const int = (v: number) => Number.isInteger(v);
  if (!int(p.population_size) || p.population_size < 2) errors.population_size = 'Minimal 2.';
  if (!int(p.min_generations) || p.min_generations < 1) errors.min_generations = 'Minimal 1.';
  if (!int(p.max_generations) || p.max_generations < p.min_generations) errors.max_generations = 'Tidak boleh kurang dari generasi minimum.';
  if (!int(p.stagnation_limit) || p.stagnation_limit < 1) errors.stagnation_limit = 'Minimal 1.';
  if (!(p.crossover_rate >= 0 && p.crossover_rate <= 1)) errors.crossover_rate = 'Nilai harus antara 0 dan 1.';
  if (!(p.mutation_rate >= 0 && p.mutation_rate <= 1)) errors.mutation_rate = 'Nilai harus antara 0 dan 1.';
  if (!int(p.tournament_size) || p.tournament_size < 2) errors.tournament_size = 'Minimal 2.';
  else if (p.tournament_size > p.population_size) errors.tournament_size = 'Tidak boleh melebihi ukuran populasi.';
  if (!int(p.parallel_class_limit) || p.parallel_class_limit < 1) errors.parallel_class_limit = 'Minimal 1.';
  if (!int(p.fitness_constant) || p.fitness_constant < 1) errors.fitness_constant = 'Minimal 1.';
  if (!int(p.base_seed) || p.base_seed < 0) errors.base_seed = 'Bilangan bulat, minimal 0.';
  if (!int(p.run_count) || p.run_count < 1 || p.run_count > 10) errors.run_count = 'Antara 1 dan 10.';
  return errors;
}
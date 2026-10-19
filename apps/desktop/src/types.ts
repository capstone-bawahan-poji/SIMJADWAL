/**
 * GA Parameter Preset — shape sesuai ERD ga_parameter_preset.
 * Kolom yg sudah dihapus dari ERD (run_count, base_seed, elitism_count) tidak dimasukkan.
 */
export interface GaPreset {
  id: number;
  nama_preset: string;
  pop_size: number;
  generasi_min: number;
  generasi_maks: number;
  batas_stagnasi: number;
  crossover_rate: number;   // 0.0 – 1.0
  mutation_rate: number;    // 0.0 – 1.0
  selection_size: number;   // tournament size (k)
  operator_seleksi: 'TS' | 'TSR';
}

/**
 * Working copy of parameter form — same fields as GaPreset minus id/nama.
 * Used for the manual configuration panel.
 */
export interface GaParams {
  pop_size: number;
  generasi_min: number;
  generasi_maks: number;
  batas_stagnasi: number;
  crossover_rate: number;
  mutation_rate: number;
  selection_size: number;
  operator_seleksi: 'TS' | 'TSR';
}

/** Default recommended parameters (Rossada 2026 baseline). */
export const DEFAULT_PARAMS: GaParams = {
  pop_size: 100,
  generasi_min: 500,
  generasi_maks: 1000,
  batas_stagnasi: 200,
  crossover_rate: 0.85,
  mutation_rate: 0.02,
  selection_size: 5,
  operator_seleksi: 'TS',
};

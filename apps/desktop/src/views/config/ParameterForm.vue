<script setup lang="ts">
import Card from '@/components/ui/Card.vue';
import NumberInput from '@/components/ui/NumberInput.vue';
import { useGaConfig } from '@/composables/useGaConfig';
import type { GaParams, SelectionOperator } from '@/types/ga';
import ChoiceCard from '@/views/config/ChoiceCard.vue';
import SettingRow from '@/views/config/SettingRow.vue';

const { params, errors, runMode, multiOperators } = useGaConfig();

type NumberField = { key: Exclude<keyof GaParams, 'selection_operator'>; label: string; hint: string; step?: string };

const groups: { title: string; description: string; fields: NumberField[] }[] = [
  {
    title: 'Populasi dan generasi',
    description: 'Setelah generasi minimum tercapai, proses berhenti apabila fitness tidak meningkat selama batas stagnasi.',
    fields: [
      { key: 'population_size', label: 'Ukuran populasi', hint: 'Nilai acuan TA: 100' },
      { key: 'stagnation_limit', label: 'Batas stagnasi', hint: 'Nilai acuan TA: 200' },
      { key: 'min_generations', label: 'Generasi minimum', hint: 'Nilai acuan TA: 500' },
      { key: 'max_generations', label: 'Generasi maksimum', hint: 'Nilai acuan TA: 1000' },
    ],
  },
  {
    title: 'Crossover dan mutasi',
    description: 'Nilai probabilitas dalam rentang 0 sampai 1. Crossover dan mutasi menggunakan metode satu titik (one-point).',
    fields: [
      { key: 'crossover_rate', label: 'Crossover rate (Cr)', hint: 'Rentang 0 sampai 1', step: '0.01' },
      { key: 'mutation_rate', label: 'Mutation rate (Mr)', hint: 'Rentang 0 sampai 1', step: '0.01' },
    ],
  },
  {
    title: 'Fitness dan constraint',
    description: 'Konstanta C mencegah nilai fitness negatif. Jumlah kelas paralel menjadi batas HC4 untuk setiap program studi pada satu slot waktu.',
    fields: [
      { key: 'fitness_constant', label: 'Konstanta fitness (C)', hint: 'Nilai acuan TA: 500' },
      { key: 'parallel_class_limit', label: 'Jumlah kelas paralel', hint: 'Nilai acuan TA: 4 dan 5' },
    ],
  },
  {
    title: 'Pengulangan',
    description: 'Seed setiap run adalah base seed ditambah nomor urut run sehingga hasilnya dapat direproduksi.',
    fields: [
      { key: 'run_count', label: 'Jumlah run per operator', hint: 'Rentang 1 sampai 10' },
      { key: 'base_seed', label: 'Base seed', hint: 'Nilai acuan TA: 12345' },
    ],
  },
];

const OPERATORS: { value: SelectionOperator; title: string; text: string }[] = [
  { value: 'TS', title: 'Tournament selection (TS)', text: 'Kromosom dipilih secara acak lalu dibandingkan. Kromosom dengan fitness tertinggi terpilih sebagai induk.' },
  { value: 'TSR', title: 'Tournament dengan roulette (TSR)', text: 'Kandidat dipilih melalui roulette wheel, kemudian dibandingkan dalam turnamen.' },
];

function toggleOperator(op: SelectionOperator) {
  multiOperators.value = multiOperators.value.includes(op)
    ? multiOperators.value.filter((o) => o !== op)
    : [...multiOperators.value, op];
}
</script>

<template>
  <Card no-padding>
    <SettingRow title="Mode run" description="Single-run menggunakan satu operator seleksi. Multi-run membandingkan beberapa operator seleksi dalam satu batch.">
      <div class="grid grid-cols-2 gap-4">
        <ChoiceCard :selected="runMode === 'single'" title="Single-run" text="Satu operator seleksi, diulang sesuai jumlah run.">
          <input v-model="runMode" type="radio" value="single" name="run-mode" />
        </ChoiceCard>
        <ChoiceCard :selected="runMode === 'multi'" title="Multi-run" text="Setiap operator seleksi diulang sesuai jumlah run.">
          <input v-model="runMode" type="radio" value="multi" name="run-mode" />
        </ChoiceCard>
      </div>
      <div v-if="runMode === 'multi'" class="mt-4 flex items-center gap-6">
        <span class="text-xs font-semibold text-ink-muted">Operator yang dibandingkan</span>
        <label v-for="op in OPERATORS" :key="op.value" class="flex cursor-pointer items-center gap-2 text-sm text-ink-secondary">
          <input type="checkbox" :checked="multiOperators.includes(op.value)" @change="toggleOperator(op.value)" />
          {{ op.value }}
        </label>
        <span v-if="!multiOperators.length" class="text-xs text-danger-ink">Pilih minimal satu operator seleksi.</span>
      </div>
    </SettingRow>

    <SettingRow
      title="Operator seleksi"
      :description="runMode === 'multi' ? 'Berlaku pada single-run. Multi-run menggunakan operator yang dipilih di atas.' : 'Metode pemilihan kromosom induk untuk proses crossover.'"
    >
      <div class="grid grid-cols-2 gap-4">
        <ChoiceCard v-for="op in OPERATORS" :key="op.value" :selected="params.selection_operator === op.value" :title="op.title" :text="op.text">
          <input v-model="params.selection_operator" type="radio" :value="op.value" name="selection-operator" />
        </ChoiceCard>
      </div>
      <div class="mt-4 grid grid-cols-2 gap-4">
        <div class="flex flex-col gap-2">
          <label for="tournament_size" class="text-xs font-semibold text-ink-secondary">Ukuran turnamen (tournament size)</label>
          <NumberInput id="tournament_size" v-model="params.tournament_size" min="2" :invalid="!!errors.tournament_size" />
          <p class="text-xs" :class="errors.tournament_size ? 'text-danger-ink' : 'text-ink-muted'">{{ errors.tournament_size ?? 'Nilai acuan TA: 2' }}</p>
        </div>
      </div>
    </SettingRow>

    <SettingRow v-for="group in groups" :key="group.title" :title="group.title" :description="group.description">
      <div class="grid grid-cols-2 gap-x-4 gap-y-6">
        <div v-for="field in group.fields" :key="field.key" class="flex flex-col gap-2">
          <label :for="field.key" class="text-xs font-semibold text-ink-secondary">{{ field.label }}</label>
          <NumberInput :id="field.key" v-model="params[field.key]" :step="field.step ?? '1'" min="0" :invalid="!!errors[field.key]" />
          <p class="text-xs" :class="errors[field.key] ? 'text-danger-ink' : 'text-ink-muted'">{{ errors[field.key] ?? field.hint }}</p>
        </div>
      </div>
    </SettingRow>
  </Card>
</template>

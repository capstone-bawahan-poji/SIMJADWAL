<script setup lang="ts">
import Card from '@/components/ui/Card.vue';
import { useGaConfig } from '@/composables/useGaConfig';
import type { GaParams, SelectionOperator } from '@/types/ga';

const { params, errors, runMode, multiOperators } = useGaConfig();

type NumberField = { key: Exclude<keyof GaParams, 'selection_operator'>; label: string; hint: string; step?: string };

const groups: { title: string; fields: NumberField[] }[] = [
  {
    title: 'Populasi & Generasi',
    fields: [
      { key: 'population_size', label: 'Ukuran populasi (Pop size)', hint: 'Referensi TA Kak Amrina: 100' },
      { key: 'min_generations', label: 'Generasi minimum', hint: 'Referensi TA Kak Amrina: 500' },
      { key: 'max_generations', label: 'Generasi maksimum', hint: 'Referensi TA Kak Amrina: 1000' },
      { key: 'stagnation_limit', label: 'Batas stagnasi', hint: 'Berhenti bila tidak naik sekian generasi setelah minimum. Referensi TA Kak Amrina: 200' },
    ],
  },
  {
    title: 'Crossover & Mutasi',
    fields: [
      { key: 'crossover_rate', label: 'Crossover rate (Cr)', hint: '0-1, 1-point crossover', step: '0.01' },
      { key: 'mutation_rate', label: 'Mutation rate (Mr)', hint: '0-1, 1-point mutation', step: '0.01' },
    ],
  },
  {
    title: 'Fitness & Constraint',
    fields: [
      { key: 'fitness_constant', label: 'Konstanta fitness (C)', hint: 'Mencegah fitness negatif. Referensi TA Kak Amrina: 500' },
      { key: 'parallel_class_limit', label: 'Jumlah kelas paralel', hint: 'Batas HC4 per prodi per slot. Referensi TA Kak Amrina: 4 dan 5' },
    ],
  },
  {
    title: 'Pengulangan',
    fields: [
      { key: 'run_count', label: 'Jumlah run per skenario', hint: '1-10. Referensi TA Kak Amrina: 10' },
      { key: 'base_seed', label: 'Base seed', hint: 'Seed run = base seed + urutan run. Referensi TA Kak Amrina: 12345' },
    ],
  },
];

const OPERATORS: { value: SelectionOperator; title: string; text: string }[] = [
  { value: 'TS', title: 'TS', text: 'Tournament Selection. Kromosom dipilih acak lalu diadu, fitness lebih tinggi menang.' },
  { value: 'TSR', title: 'TSR', text: 'Tournament Selection dengan Roulette Wheel. Kandidat dipilih lewat roulette, lalu diadu.' },
];

const inputClass = (key: keyof GaParams) => [
  'w-full h-10 rounded-lg border px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary',
  errors.value[key] ? 'border-red-400' : 'border-slate-300',
];

function toggleOperator(op: SelectionOperator) {
  multiOperators.value = multiOperators.value.includes(op)
    ? multiOperators.value.filter((o) => o !== op)
    : [...multiOperators.value, op];
}
</script>

<template>
  <div class="space-y-5">
    <Card>
      <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-3">Mode Run</p>
      <div class="grid grid-cols-2 gap-3">
        <label
          v-for="mode in (['single', 'multi'] as const)"
          :key="mode"
          class="flex flex-col gap-1 p-4 rounded-xl border-2 cursor-pointer transition"
          :class="runMode === mode ? 'border-primary bg-primary/[0.06]' : 'border-slate-200 hover:border-slate-300'"
        >
          <span class="flex items-center gap-2">
            <input v-model="runMode" type="radio" :value="mode" class="text-primary focus:ring-primary" />
            <span class="text-sm font-bold" :class="runMode === mode ? 'text-primary' : 'text-gray-700'">
              {{ mode === 'single' ? 'Single-run' : 'Multi-run' }}
            </span>
          </span>
          <span class="text-xs text-gray-500 pl-6">
            {{ mode === 'single' ? 'Satu operator, diulang sebanyak jumlah run.' : 'Bandingkan operator, masing-masing diulang sebanyak jumlah run.' }}
          </span>
        </label>
      </div>
      <div v-if="runMode === 'multi'" class="mt-4 pt-4 border-t border-gray-100 flex items-center gap-4">
        <span class="text-xs font-semibold text-gray-600">Operator dibandingkan:</span>
        <label v-for="op in OPERATORS" :key="op.value" class="flex items-center gap-2 text-sm cursor-pointer">
          <input type="checkbox" :checked="multiOperators.includes(op.value)" class="rounded text-primary focus:ring-primary" @change="toggleOperator(op.value)" />
          {{ op.title }}
        </label>
      </div>
    </Card>

    <Card>
      <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-3">Operator Seleksi</p>
      <div class="grid grid-cols-2 gap-3 mb-4">
        <label
          v-for="op in OPERATORS"
          :key="op.value"
          class="flex flex-col gap-1.5 p-4 rounded-xl border-2 cursor-pointer transition"
          :class="params.selection_operator === op.value ? 'border-primary bg-primary/[0.06]' : 'border-slate-200 hover:border-slate-300'"
        >
          <span class="flex items-center gap-2">
            <input v-model="params.selection_operator" type="radio" :value="op.value" class="text-primary focus:ring-primary" />
            <span class="text-sm font-bold" :class="params.selection_operator === op.value ? 'text-primary' : 'text-gray-700'">{{ op.title }}</span>
          </span>
          <span class="text-xs text-gray-500 leading-relaxed">{{ op.text }}</span>
        </label>
      </div>
      <div class="max-w-xs space-y-1">
        <label for="tournament_size" class="text-xs font-semibold text-gray-700">Ukuran seleksi (tournament size)</label>
        <input id="tournament_size" v-model.number="params.tournament_size" type="number" min="2" :class="inputClass('tournament_size')" />
        <p v-if="errors.tournament_size" class="text-xs text-red-600">{{ errors.tournament_size }}</p>
        <p v-else class="text-[11px] text-gray-400">Referensi TA Kak Amrina: 2</p>
      </div>
    </Card>

    <Card v-for="group in groups" :key="group.title">
      <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-3">{{ group.title }}</p>
      <div class="grid grid-cols-2 gap-4">
        <div v-for="field in group.fields" :key="field.key" class="space-y-1">
          <label :for="field.key" class="text-xs font-semibold text-gray-700">{{ field.label }}</label>
          <input :id="field.key" v-model.number="params[field.key]" type="number" :step="field.step ?? '1'" min="0" :class="inputClass(field.key)" />
          <p v-if="errors[field.key]" class="text-xs text-red-600">{{ errors[field.key] }}</p>
          <p v-else class="text-[11px] text-gray-400">{{ field.hint }}</p>
        </div>
      </div>
    </Card>
  </div>
</template>
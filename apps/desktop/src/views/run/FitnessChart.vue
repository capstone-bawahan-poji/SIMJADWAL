<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ history: number[]; max: number; bestGeneration: number }>();

const W = 800;
const H = 280;
const PAD_X = 48;
const PAD_Y = 24;

const bounds = computed(() => {
  const values = props.history.length ? props.history : [0];
  const lo = Math.min(...values);
  const hi = Math.max(props.max, ...values);
  return { lo: Math.floor(lo), hi: Math.ceil(hi === lo ? lo + 1 : hi) };
});

const x = (i: number) => PAD_X + (i / Math.max(1, props.history.length - 1)) * (W - PAD_X * 2);
const y = (v: number) => H - PAD_Y - ((v - bounds.value.lo) / (bounds.value.hi - bounds.value.lo)) * (H - PAD_Y * 2);

const points = computed(() => props.history.map((v, i) => `${x(i).toFixed(1)},${y(v).toFixed(1)}`).join(' '));
const ticks = computed(() => {
  const { lo, hi } = bounds.value;
  return [0, 0.25, 0.5, 0.75, 1].map((t) => lo + (hi - lo) * t);
});
</script>

<template>
  <svg :viewBox="`0 0 ${W} ${H}`" class="tabular h-auto w-full" role="img" :aria-label="`Grafik fitness, terbaik di generasi ${bestGeneration}`">
    <g v-for="t in ticks" :key="t">
      <line :x1="PAD_X" :x2="W - PAD_X" :y1="y(t)" :y2="y(t)" stroke="#eef0f3" />
      <text :x="PAD_X - 8" :y="y(t) + 4" text-anchor="end" fill="#98a2b3" font-size="12">{{ t.toFixed(0) }}</text>
    </g>
    <line :x1="PAD_X" :x2="W - PAD_X" :y1="y(max)" :y2="y(max)" stroke="#5d6679" stroke-dasharray="4 4" />
    <text :x="W - PAD_X" :y="y(max) - 8" text-anchor="end" fill="#5d6679" font-size="12">maks {{ max }}</text>
    <text :x="W - PAD_X" :y="H - 4" text-anchor="end" fill="#98a2b3" font-size="12">generasi {{ history.length - 1 }}</text>
    <polyline :points="points" fill="none" stroke="#1e3a8a" stroke-width="2" stroke-linejoin="round" />
    <circle v-if="history.length" :cx="x(bestGeneration)" :cy="y(history[bestGeneration] ?? 0)" r="4" fill="#1e3a8a" stroke="#ffffff" stroke-width="2" />
  </svg>
</template>

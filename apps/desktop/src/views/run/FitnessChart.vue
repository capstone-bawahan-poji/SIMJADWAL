<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ history: number[]; max: number; bestGeneration: number }>();

const W = 600;
const H = 180;
const PAD = 28;

const bounds = computed(() => {
  const values = props.history.length ? props.history : [0];
  const lo = Math.min(...values);
  const hi = Math.max(props.max, ...values);
  return { lo: Math.floor(lo), hi: Math.ceil(hi === lo ? lo + 1 : hi) };
});

const x = (i: number) => PAD + (i / Math.max(1, props.history.length - 1)) * (W - PAD * 2);
const y = (v: number) => H - PAD - ((v - bounds.value.lo) / (bounds.value.hi - bounds.value.lo)) * (H - PAD * 2);

const points = computed(() => props.history.map((v, i) => `${x(i).toFixed(1)},${y(v).toFixed(1)}`).join(' '));
</script>

<template>
  <svg :viewBox="`0 0 ${W} ${H}`" class="w-full h-auto" role="img" :aria-label="`Grafik fitness, terbaik di generasi ${bestGeneration}`">
    <line :x1="PAD" :x2="W - PAD" :y1="y(max)" :y2="y(max)" stroke="currentColor" class="text-gray-300" stroke-dasharray="4 4" />
    <text :x="W - PAD" :y="y(max) - 4" text-anchor="end" class="fill-gray-400 text-[10px]">maks {{ max }}</text>
    <line :x1="PAD" :x2="PAD" :y1="PAD" :y2="H - PAD" stroke="currentColor" class="text-gray-200" />
    <line :x1="PAD" :x2="W - PAD" :y1="H - PAD" :y2="H - PAD" stroke="currentColor" class="text-gray-200" />
    <text :x="PAD - 4" :y="y(bounds.lo)" text-anchor="end" class="fill-gray-400 text-[10px]">{{ bounds.lo }}</text>
    <text :x="W - PAD" :y="H - PAD + 14" text-anchor="end" class="fill-gray-400 text-[10px]">generasi {{ history.length - 1 }}</text>
    <polyline :points="points" fill="none" stroke="currentColor" stroke-width="2" class="text-primary" />
    <circle v-if="history.length" :cx="x(bestGeneration)" :cy="y(history[bestGeneration] ?? 0)" r="4" class="fill-primary" />
  </svg>
</template>

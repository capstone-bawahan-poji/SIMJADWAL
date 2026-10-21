<script setup lang="ts">
import type { RunProgress } from '@/composables/useGaRunner';

defineProps<{ runs: RunProgress[]; maxGenerations: number }>();
</script>

<template>
  <ul class="divide-y divide-gray-50">
    <li v-for="run in runs" :key="run.run_id" class="flex items-center gap-4 py-2.5">
      <span class="w-28 shrink-0 text-sm font-semibold text-gray-800">{{ run.operator }} · run {{ run.run_index + 1 }}</span>
      <div class="flex-1">
        <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
          <div
            class="h-full rounded-full transition-all"
            :class="run.cancelled ? 'bg-gray-400' : run.finished ? 'bg-green-500' : 'bg-primary'"
            :style="{ width: `${run.finished ? 100 : Math.min(100, (run.generation / maxGenerations) * 100)}%` }"
          />
        </div>
      </div>
      <span class="w-32 shrink-0 text-right text-xs text-gray-500">generasi {{ run.generation }}</span>
      <span class="w-28 shrink-0 text-right text-sm font-semibold text-gray-900">{{ run.best_fitness?.toFixed(0) ?? '–' }}</span>
      <span class="w-20 shrink-0 text-right text-xs" :class="run.error ? 'text-red-600' : 'text-gray-500'">
        {{ run.error ? 'gagal simpan' : run.cancelled ? 'dibatalkan' : run.finished ? 'selesai' : 'berjalan' }}
      </span>
    </li>
  </ul>
</template>

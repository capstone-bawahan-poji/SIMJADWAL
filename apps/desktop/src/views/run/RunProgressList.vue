<script setup lang="ts">
import type { RunProgress } from '@/composables/useGaRunner';
import { runLabel } from '@/lib/format';

defineProps<{ runs: RunProgress[]; maxGenerations: number }>();

function state(run: RunProgress) {
  if (run.error) return { text: 'Gagal disimpan', class: 'text-danger-ink' };
  if (run.cancelled) return { text: 'Dibatalkan', class: 'text-ink-muted' };
  if (run.finished) return { text: 'Selesai', class: 'text-success-ink' };
  return { text: 'Berjalan', class: 'text-primary' };
}
</script>

<template>
  <ul class="divide-y divide-line-soft">
    <li v-for="run in runs" :key="run.run_id" class="flex items-center gap-6 px-6 py-3">
      <span class="w-24 shrink-0 text-sm font-semibold text-ink">{{ runLabel(run) }}</span>
      <div class="h-2 flex-1 overflow-hidden rounded-full bg-line-soft">
        <div
          class="h-full rounded-full transition-all"
          :class="run.cancelled ? 'bg-ink-faint' : run.finished ? 'bg-success-dot' : 'bg-primary'"
          :style="{ width: `${run.finished ? 100 : Math.min(100, (run.generation / maxGenerations) * 100)}%` }"
        />
      </div>
      <span class="tabular w-32 shrink-0 text-right text-xs text-ink-muted">Generasi {{ run.generation }}</span>
      <span class="tabular w-24 shrink-0 text-right text-sm font-semibold text-ink">{{ run.best_fitness?.toFixed(1) ?? '-' }}</span>
      <span class="w-24 shrink-0 text-right text-xs font-semibold" :class="state(run).class">{{ state(run).text }}</span>
    </li>
  </ul>
</template>

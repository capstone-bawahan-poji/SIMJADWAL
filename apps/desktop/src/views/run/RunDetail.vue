<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import SegmentedControl from '@/components/ui/SegmentedControl.vue';
import Skeleton from '@/components/ui/Skeleton.vue';
import Tabs from '@/components/ui/Tabs.vue';
import { useDataset } from '@/composables/useDataset';
import { runLabel } from '@/lib/format';
import { gaGetResult } from '@/lib/tauri';
import type { RunRecord } from '@/types/run';
import FitnessChart from '@/views/run/FitnessChart.vue';
import ScheduleMatrix from '@/views/run/ScheduleMatrix.vue';

const props = defineProps<{ runId: string; recommended: boolean; hasCleanRun: boolean; serverRunId: number | null }>();

const { dataset } = useDataset();
const record = ref<RunRecord | null>(null);
const error = ref('');
const tab = ref<'matrix' | 'violations' | 'chart'>('matrix');
const violationFilter = ref<'all' | 'HC' | 'SC'>('all');

watch(() => props.runId, async (id) => {
  record.value = null;
  error.value = '';
  try { record.value = await gaGetResult(id); } catch (e) { error.value = String(e); }
}, { immediate: true });

const allViolations = computed(() => record.value?.result.violations ?? []);
const violations = computed(() =>
  allViolations.value.filter((v) => violationFilter.value === 'all' || v.constraint_code.startsWith(violationFilter.value)),
);
const violationOptions = computed(() => [
  { value: 'all' as const, label: 'Semua', count: allViolations.value.length },
  { value: 'HC' as const, label: 'HC', count: allViolations.value.filter((v) => v.constraint_code.startsWith('HC')).length },
  { value: 'SC' as const, label: 'SC', count: allViolations.value.filter((v) => v.constraint_code.startsWith('SC')).length },
]);

const tabs = computed(() => [
  { key: 'matrix' as const, label: 'Jadwal' },
  { key: 'violations' as const, label: `Pelanggaran (${allViolations.value.length})` },
  { key: 'chart' as const, label: 'Grafik fitness' },
]);
</script>

<template>
  <div>
    <div v-if="error" class="p-6"><Alert title="Hasil run tidak dapat dibuka">{{ error }}</Alert></div>
    <div v-else-if="!record" class="p-6"><Skeleton :lines="5" /></div>

    <template v-else>
      <div class="flex flex-wrap items-start justify-between gap-4 px-6 pt-6">
        <div class="flex items-center gap-3">
          <slot name="lead" />
          <h2 class="text-base font-semibold text-ink">{{ runLabel(record.result) }}</h2>
          <Badge v-if="recommended" :tone="hasCleanRun ? 'primary' : 'warning'">{{ hasCleanRun ? 'Rekomendasi' : 'Terbaik, melanggar HC' }}</Badge>
          <Badge v-if="serverRunId !== null" tone="success">Terkirim #{{ serverRunId }}</Badge>
        </div>
        <dl class="tabular flex gap-6 text-xs">
          <div><dt class="text-ink-muted">Seed</dt><dd class="tabular mt-1 font-semibold text-ink">{{ record.result.parameter_snapshot.seed ?? '-' }}</dd></div>
          <div><dt class="text-ink-muted">Generasi terbaik</dt><dd class="tabular mt-1 font-semibold text-ink">{{ record.result.best_generation }} dari {{ record.result.final_generation }}</dd></div>
          <div><dt class="text-ink-muted">Indeks kromosom</dt><dd class="tabular mt-1 font-semibold text-ink">{{ record.result.best_chromosome_index + 1 }}</dd></div>
        </dl>
      </div>

      <div class="mt-4 px-6"><Tabs v-model="tab" :tabs="tabs" /></div>

      <div v-if="tab === 'matrix'" class="p-6">
        <ScheduleMatrix :result="record.result" :dataset="dataset" />
      </div>

      <div v-else-if="tab === 'violations'" class="flex flex-col gap-4 p-6">
        <SegmentedControl v-model="violationFilter" label="Jenis pelanggaran" :options="violationOptions" />
        <EmptyState v-if="!violations.length" title="Tidak terdapat pelanggaran" dashed />
        <div v-else class="overflow-hidden rounded-control border border-line">
          <div class="grid grid-cols-[96px_minmax(0,1fr)_96px] gap-4 border-b border-line bg-canvas px-4 py-3 text-xs font-semibold text-ink-muted">
            <span>Kode</span><span>Keterangan</span><span class="text-right">Penalti</span>
          </div>
          <ul class="max-h-[480px] divide-y divide-line-soft overflow-y-auto">
            <li v-for="(v, i) in violations" :key="i" class="grid grid-cols-[96px_minmax(0,1fr)_96px] gap-4 px-4 py-3 text-sm">
              <span class="font-mono text-xs font-semibold" :class="v.constraint_code.startsWith('HC') ? 'text-danger-ink' : 'text-ink-secondary'">{{ v.constraint_code }}</span>
              <span class="text-ink-secondary">{{ v.message }}</span>
              <span class="tabular text-right text-xs text-ink-muted">-{{ v.penalty }}</span>
            </li>
          </ul>
        </div>
      </div>

      <div v-else class="p-6">
        <FitnessChart :history="record.result.fitness_history" :max="record.result.max_fitness" :best-generation="record.result.best_generation" />
      </div>
    </template>
  </div>
</template>

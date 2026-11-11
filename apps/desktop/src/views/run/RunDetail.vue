<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import SegmentedControl from '@/components/ui/SegmentedControl.vue';
import Skeleton from '@/components/ui/Skeleton.vue';
import Tabs from '@/components/ui/Tabs.vue';
import { useDataset } from '@/composables/useDataset';
import { classLetter, runLabel } from '@/lib/format';
import { gaGetResult } from '@/lib/tauri';
import type { RunRecord, Violation } from '@/types/run';
import FitnessChart from '@/views/run/FitnessChart.vue';
import ScheduleMatrix from '@/views/run/ScheduleMatrix.vue';
import TpbRunTable from '@/views/run/TpbRunTable.vue';

const props = defineProps<{ runId: string; recommended: boolean; hasCleanRun: boolean; serverRunId: number | null }>();

const { dataset } = useDataset();
const record = ref<RunRecord | null>(null);
const error = ref('');
const tab = ref<'matrix' | 'courses' | 'violations' | 'chart'>('matrix');
const isTpb = computed(() => dataset.value?.scope === 'tpb');
const violationFilter = ref<'all' | 'HC' | 'SC'>('all');

watch(() => props.runId, async (id) => {
  record.value = null;
  error.value = '';
  try { record.value = await gaGetResult(id); } catch (e) { error.value = String(e); }
}, { immediate: true });

const DAYS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

/**
 * Program datasets replace the parallel class limit (HC4) with the program's cells: a meeting over
 * the cells of its slot counts as an HC4 in the summary and ends up without a room. The GA lists no
 * violation row for it (the upload carries none), so rows are made here and the HC count and this
 * list agree.
 */
const unplacedRows = computed<Violation[]>(() => {
  const details = record.value?.result.schedule_details ?? [];
  return details
    .map((d, index) => ({ d, index }))
    .filter(({ d }) => d.room_id === null)
    .map(({ d, index }) => {
      const course = dataset.value?.courses.find((c) => c.id === d.course_id);
      const slot = dataset.value?.time_slots.find((t) => t.id === d.time_slot_id);
      const meeting = d.meeting_number > 1 ? ` pertemuan ${d.meeting_number}` : '';
      const when = slot ? ` pada ${DAYS[slot.day - 1] ?? '?'} sesi ${slot.session}` : '';
      return {
        detail_index: index,
        constraint_code: 'HC4',
        penalty: 0,
        message: `${course?.code ?? d.course_id} ${course?.name ?? ''} kelas ${classLetter(d.class_number)}${meeting}${when} melebihi sel ruangan prodi pada slot itu`.replace(/\s+/g, ' '),
      };
    });
});

const allViolations = computed(() => [...(record.value?.result.violations ?? []), ...unplacedRows.value]);
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
  ...(isTpb.value ? [{ key: 'courses' as const, label: 'Per mata kuliah' }] : []),
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

      <div v-if="record.result.study_program_id && record.result.summary.unassigned_rooms > 0" class="mt-4 px-6">
        <Alert variant="warning" title="Sebagian pertemuan tidak mendapat slot ruangan">
          {{ record.result.summary.unassigned_rooms }} pertemuan tidak muat di slot ruangan program studi, sehingga hasil ini akan ditolak server.
          Ajukan tambahan slot ruangan ke fakultas melalui aplikasi web, lalu jalankan GA lagi.
        </Alert>
      </div>

      <div class="mt-4 px-6"><Tabs v-model="tab" :tabs="tabs" /></div>

      <div v-if="tab === 'matrix'" class="p-6">
        <ScheduleMatrix :result="record.result" :dataset="dataset" />
      </div>

      <div v-else-if="tab === 'courses'" class="p-6">
        <TpbRunTable :result="record.result" :dataset="dataset" />
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
              <span class="tabular text-right text-xs text-ink-muted">{{ v.penalty ? `-${v.penalty}` : '' }}</span>
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

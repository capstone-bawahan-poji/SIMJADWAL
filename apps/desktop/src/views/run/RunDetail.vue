<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import { useDataset } from '@/composables/useDataset';
import { gaGetResult } from '@/lib/tauri';
import type { RunRecord } from '@/types/run';
import FitnessChart from '@/views/run/FitnessChart.vue';

const props = defineProps<{ runId: string }>();

const { dataset } = useDataset();
const record = ref<RunRecord | null>(null);
const error = ref('');
const tab = ref<'matrix' | 'violations' | 'chart'>('matrix');
const programFilter = ref<number | null>(null);
const violationFilter = ref<'all' | 'HC' | 'SC'>('all');

watch(() => props.runId, async (id) => {
  record.value = null;
  error.value = '';
  try { record.value = await gaGetResult(id); } catch (e) { error.value = String(e); }
}, { immediate: true });

const DAYS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

const lookup = computed(() => {
  const d = dataset.value;
  return {
    course: new Map(d?.courses.map((c) => [c.id, c]) ?? []),
    lecturer: new Map(d?.lecturers.map((l) => [l.id, l.name]) ?? []),
    room: new Map(d?.rooms.map((r) => [r.id, r.code]) ?? []),
    slot: new Map(d?.time_slots.map((s) => [s.id, s]) ?? []),
    programs: d?.study_programs.filter((p) => p.included) ?? [],
  };
});

const sessions = computed(() => [...new Set(dataset.value?.time_slots.map((s) => s.session) ?? [1, 2, 3, 4])].sort());

const flagged = computed(() => {
  const hard = new Set<number>();
  record.value?.result.violations.forEach((v) => { if (v.constraint_code.startsWith('HC')) hard.add(v.detail_index); });
  return hard;
});

const cells = computed(() => {
  const map = new Map<string, { label: string; sub: string; hard: boolean }[]>();
  record.value?.result.schedule_details.forEach((d, i) => {
    const course = lookup.value.course.get(d.course_id);
    if (programFilter.value && course?.study_program_id !== programFilter.value) return;
    const slot = lookup.value.slot.get(d.time_slot_id);
    if (!slot) return;
    const key = `${slot.day}-${slot.session}`;
    const list = map.get(key) ?? [];
    list.push({
      label: `${course?.code ?? d.course_id} ${String.fromCharCode(64 + d.class_number)}${d.meeting_number > 1 ? ` (${d.meeting_number})` : ''}`,
      sub: `${lookup.value.lecturer.get(d.lecturer_id) ?? d.lecturer_id} · ${d.room_id ? lookup.value.room.get(d.room_id) ?? d.room_id : 'tanpa ruangan'}`,
      hard: flagged.value.has(i),
    });
    map.set(key, list);
  });
  return map;
});

const violations = computed(() =>
  (record.value?.result.violations ?? []).filter((v) => violationFilter.value === 'all' || v.constraint_code.startsWith(violationFilter.value)),
);
</script>

<template>
  <div>
    <Alert v-if="error" title="Hasil run tidak dapat dibuka">{{ error }}</Alert>
    <p v-else-if="!record" class="text-sm text-gray-500">Memuat…</p>

    <template v-else>
      <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="text-lg font-bold text-gray-900">{{ record.result.operator }} · run {{ record.result.run_index + 1 }}</h2>
        <p class="text-xs text-gray-500">
          seed {{ record.result.parameter_snapshot.seed }} · terbaik di generasi {{ record.result.best_generation }},
          kromosom ke-{{ record.result.best_chromosome_index + 1 }}
        </p>
      </div>

      <div class="mt-3 flex gap-1 border-b border-gray-100">
        <button
          v-for="t in ([['matrix', 'Jadwal'], ['violations', `Pelanggaran (${record.result.violations.length})`], ['chart', 'Grafik Fitness']] as const)"
          :key="t[0]"
          type="button"
          class="px-3 py-2 text-sm font-semibold -mb-px border-b-2 transition-colors"
          :class="tab === t[0] ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'"
          @click="tab = t[0]"
        >
          {{ t[1] }}
        </button>
      </div>

      <div v-if="tab === 'matrix'" class="mt-4">
        <select
          v-model="programFilter"
          class="mb-3 h-9 rounded-lg border border-slate-300 bg-white px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
          aria-label="Filter prodi"
        >
          <option :value="null">Semua prodi</option>
          <option v-for="p in lookup.programs" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <div class="overflow-x-auto">
          <table class="w-full text-xs border-separate border-spacing-1">
            <thead>
              <tr>
                <th class="w-16 text-left font-semibold text-gray-500">Sesi</th>
                <th v-for="day in DAYS" :key="day" class="font-semibold text-gray-500">{{ day }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="session in sessions" :key="session" class="align-top">
                <td class="pt-1 text-gray-600">Sesi {{ session }}</td>
                <td v-for="(_, d) in DAYS" :key="d" class="min-w-36 rounded-md bg-gray-50 p-1.5">
                  <div
                    v-for="(cell, i) in cells.get(`${d + 1}-${session}`) ?? []"
                    :key="i"
                    class="mb-1 rounded border px-1.5 py-1"
                    :class="cell.hard ? 'border-red-300 bg-red-50' : 'border-gray-100 bg-white'"
                  >
                    <p class="font-semibold text-gray-800">{{ cell.label }}</p>
                    <p class="text-[10px] text-gray-500">{{ cell.sub }}</p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-else-if="tab === 'violations'" class="mt-4">
        <div class="mb-3 flex gap-2">
          <button
            v-for="f in (['all', 'HC', 'SC'] as const)"
            :key="f"
            type="button"
            class="rounded-lg border px-3 py-1 text-xs font-semibold"
            :class="violationFilter === f ? 'border-primary text-primary bg-primary/[0.06]' : 'border-slate-200 text-gray-600'"
            @click="violationFilter = f"
          >
            {{ f === 'all' ? 'Semua' : f }}
          </button>
        </div>
        <p v-if="!violations.length" class="text-sm text-gray-500">Tidak ada pelanggaran.</p>
        <ul v-else class="max-h-96 overflow-y-auto divide-y divide-gray-50">
          <li v-for="(v, i) in violations" :key="i" class="flex gap-3 py-2 text-sm">
            <span class="w-24 shrink-0 font-mono text-xs font-bold" :class="v.constraint_code.startsWith('HC') ? 'text-red-600' : 'text-gray-600'">{{ v.constraint_code }}</span>
            <span class="flex-1 text-gray-700">{{ v.message }}</span>
            <span class="text-xs text-gray-400">−{{ v.penalty }}</span>
          </li>
        </ul>
      </div>

      <div v-else class="mt-4">
        <FitnessChart :history="record.result.fitness_history" :max="record.result.max_fitness" :best-generation="record.result.best_generation" />
      </div>
    </template>
  </div>
</template>

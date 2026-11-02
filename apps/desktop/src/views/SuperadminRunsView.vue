<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import { fetchFaculties } from '@/lib/api/scheduling';
import { listServerRuns } from '@/lib/api/schedulingRuns';
import type { FacultyOption } from '@/types/scheduling';

type RunSummary = {
  id: number;
  batch_id: string;
  faculty: FacultyOption | null;
  period: string;
  status: string;
  selection_operator: string | null;
  best_fitness: number | null;
  fitness_percent: number | null;
  hc_violations: number;
  sc_unmet: number;
  unassigned_rooms: number;
  is_active: boolean;
  triggered_by: { id: number; name: string } | null;
  started_at: string | null;
  finished_at: string | null;
  published_at: string | null;
};

type PageResult = {
  data: RunSummary[];
  meta: { pagination: { page: number; per_page: number; total: number; last_page: number } };
};

const faculties = ref<FacultyOption[]>([]);
const facultyId = ref('');
const page = ref(1);
const result = ref<PageResult | null>(null);
const loading = ref(false);
const error = ref('');
let requestId = 0;

function isObject(value: unknown): value is Record<string, unknown> {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function isRunSummary(value: unknown): value is RunSummary {
  if (!isObject(value)) return false;
  const faculty = value.faculty;
  const triggeredBy = value.triggered_by;
  return typeof value.id === 'number'
    && typeof value.batch_id === 'string'
    && (faculty === null || isObject(faculty)
      && typeof faculty.id === 'number'
      && typeof faculty.code === 'string'
      && typeof faculty.name === 'string')
    && typeof value.period === 'string'
    && typeof value.status === 'string'
    && (typeof value.selection_operator === 'string' || value.selection_operator === null)
    && (typeof value.best_fitness === 'number' || value.best_fitness === null)
    && (typeof value.fitness_percent === 'number' || value.fitness_percent === null)
    && typeof value.hc_violations === 'number'
    && typeof value.sc_unmet === 'number'
    && typeof value.unassigned_rooms === 'number'
    && typeof value.is_active === 'boolean'
    && (triggeredBy === null || isObject(triggeredBy)
      && typeof triggeredBy.id === 'number'
      && typeof triggeredBy.name === 'string')
    && (typeof value.started_at === 'string' || value.started_at === null)
    && (typeof value.finished_at === 'string' || value.finished_at === null)
    && (typeof value.published_at === 'string' || value.published_at === null);
}

function parsePage(value: unknown): PageResult {
  if (
    !isObject(value)
    || !Array.isArray(value.data)
    || !value.data.every(isRunSummary)
    || !isObject(value.meta)
    || !isObject(value.meta.pagination)
  ) {
    throw new Error('Format riwayat run dari server tidak sesuai.');
  }
  const pagination = value.meta.pagination;
  if (
    typeof pagination.page !== 'number'
    || typeof pagination.per_page !== 'number'
    || typeof pagination.total !== 'number'
    || typeof pagination.last_page !== 'number'
  ) {
    throw new Error('Informasi halaman riwayat run dari server tidak lengkap.');
  }
  return {
    data: value.data,
    meta: {
      pagination: {
        page: pagination.page,
        per_page: pagination.per_page,
        total: pagination.total,
        last_page: pagination.last_page,
      },
    },
  };
}

async function load(): Promise<void> {
  const request = ++requestId;
  loading.value = true;
  error.value = '';
  try {
    const response = await listServerRuns(facultyId.value ? Number(facultyId.value) : null, page.value);
    if (request === requestId) result.value = parsePage(response);
  } catch (cause) {
    if (request === requestId) error.value = cause instanceof Error ? cause.message : 'Riwayat run gagal dimuat.';
  } finally {
    if (request === requestId) loading.value = false;
  }
}

function timestamp(value: string | null): string {
  return value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}

function runState(run: RunSummary): string {
  if (run.is_active && run.published_at) return 'Dipublikasikan';
  if (run.is_active) return 'Aktif';
  if (run.published_at) return 'Arsip';
  return run.status === 'done' ? 'Draft' : run.status;
}

onMounted(async () => {
  try {
    faculties.value = await fetchFaculties();
    await load();
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Daftar fakultas gagal dimuat.';
    loading.value = false;
  }
});

watch(facultyId, () => {
  page.value = 1;
  void load();
});
</script>

<template>
  <div class="mx-auto max-w-screen-xl space-y-5 px-6 py-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Riwayat Run Fakultas</h1>
        <p class="mt-1 text-sm text-gray-500">Lihat run GA yang sudah tersimpan di server, dari fakultas mana pun.</p>
      </div>
      <label class="text-sm font-medium text-gray-700">
        Fakultas
        <select
          v-model="facultyId"
          class="mt-1 block min-w-64 rounded-lg border-gray-200 bg-white text-sm"
          aria-label="Filter fakultas untuk riwayat run"
        >
          <option value="">Semua fakultas</option>
          <option v-for="faculty in faculties" :key="faculty.id" :value="String(faculty.id)">
            {{ faculty.code }} · {{ faculty.name }}
          </option>
        </select>
      </label>
    </header>

    <Alert v-if="error" title="Riwayat run gagal dimuat">{{ error }}</Alert>

    <Card no-padding>
      <div v-if="loading && !result" class="p-10 text-center text-sm text-gray-500">Memuat riwayat run dari server…</div>
      <div v-else-if="!loading && !result?.data.length" class="p-10 text-center text-sm text-gray-500">
        Belum ada riwayat run untuk fakultas ini.
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[1050px] text-left text-sm" :class="{ 'opacity-60': loading }">
          <thead class="border-b border-gray-100 bg-gray-50 text-xs font-semibold text-gray-500">
            <tr>
              <th class="px-4 py-3">Fakultas</th>
              <th class="px-4 py-3">Run / Periode</th>
              <th class="px-4 py-3">Tanggal</th>
              <th class="px-4 py-3">Operator</th>
              <th class="px-4 py-3 text-right">Fitness</th>
              <th class="px-4 py-3 text-right">HC / SC</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3">Dijalankan oleh</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-gray-700">
            <tr v-for="run in result?.data ?? []" :key="run.id">
              <td class="px-4 py-3">{{ run.faculty?.code ?? '—' }}<span class="block text-xs text-gray-500">{{ run.faculty?.name ?? 'Fakultas tidak diketahui' }}</span></td>
              <td class="px-4 py-3"><span class="font-semibold text-primary">Run #{{ run.id }}</span><span class="block text-xs text-gray-500">{{ run.period.replace('-', ' ') }}</span></td>
              <td class="whitespace-nowrap px-4 py-3">{{ timestamp(run.finished_at ?? run.started_at) }}</td>
              <td class="px-4 py-3">{{ run.selection_operator ?? '—' }}</td>
              <td class="px-4 py-3 text-right tabular-nums">{{ run.best_fitness?.toFixed(3) ?? '—' }}<span class="block text-xs text-gray-500">{{ run.fitness_percent ?? 0 }}%</span></td>
              <td class="px-4 py-3 text-right tabular-nums">{{ run.hc_violations }} / {{ run.sc_unmet }}<span class="block text-xs text-gray-500">{{ run.unassigned_rooms }} tanpa ruang</span></td>
              <td class="px-4 py-3">{{ runState(run) }}</td>
              <td class="px-4 py-3">{{ run.triggered_by?.name ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="result && result.meta.pagination.last_page > 1" class="flex items-center justify-between border-t border-gray-100 px-4 py-3">
        <span class="text-xs text-gray-500">{{ result.meta.pagination.total }} run · halaman {{ result.meta.pagination.page }} dari {{ result.meta.pagination.last_page }}</span>
        <div class="flex gap-2">
          <Button variant="ghost" :disabled="page <= 1 || loading" @click="page--; load()">Sebelumnya</Button>
          <Button variant="ghost" :disabled="page >= result.meta.pagination.last_page || loading" @click="page++; load()">Berikutnya</Button>
        </div>
      </div>
      <div v-else-if="result" class="border-t border-gray-100 px-4 py-3 text-xs text-gray-500">{{ result.meta.pagination.total }} run</div>
    </Card>
  </div>
</template>

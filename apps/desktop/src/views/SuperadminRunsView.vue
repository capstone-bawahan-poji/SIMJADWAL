<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Select from '@/components/ui/Select.vue';
import Skeleton from '@/components/ui/Skeleton.vue';
import { periodLabel } from '@/lib/format';
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
  return value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
}

function runState(run: RunSummary): { label: string; tone: 'neutral' | 'primary' | 'success' | 'warning' } {
  if (run.is_active && run.published_at) return { label: 'Dipublikasikan', tone: 'success' };
  if (run.is_active) return { label: 'Aktif', tone: 'primary' };
  if (run.published_at) return { label: 'Arsip', tone: 'neutral' };
  return run.status === 'done' ? { label: 'Draft', tone: 'neutral' } : { label: run.status, tone: 'warning' };
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
  <div class="flex flex-col gap-6 p-8">
    <PageHeader title="Riwayat run fakultas" description="Daftar run GA yang tersimpan di server untuk seluruh fakultas.">
      <template #actions>
        <label for="history-faculty" class="text-xs font-semibold text-ink-muted">Fakultas</label>
        <Select id="history-faculty" v-model="facultyId" class="w-72" aria-label="Filter fakultas untuk riwayat run">
          <option value="">Semua fakultas</option>
          <option v-for="faculty in faculties" :key="faculty.id" :value="String(faculty.id)">
            {{ faculty.code }} {{ faculty.name }}
          </option>
        </Select>
      </template>
    </PageHeader>

    <Alert v-if="error" title="Riwayat run gagal dimuat">{{ error }}</Alert>

    <Card no-padding>
      <div v-if="loading && !result" class="p-6"><Skeleton :lines="5" /></div>
      <EmptyState v-else-if="!loading && !result?.data.length" title="Belum ada riwayat run">Belum terdapat run yang tersimpan untuk fakultas ini.</EmptyState>
      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[1056px] text-left text-sm" :class="{ 'opacity-60': loading }">
          <thead>
            <tr class="border-b border-line text-xs text-ink-muted">
              <th class="py-3 pl-6 pr-4 font-semibold">Fakultas</th>
              <th class="py-3 pr-4 font-semibold">Run dan periode</th>
              <th class="py-3 pr-4 font-semibold">Tanggal</th>
              <th class="py-3 pr-4 font-semibold">Operator</th>
              <th class="py-3 pr-4 text-right font-semibold">Fitness</th>
              <th class="py-3 pr-4 text-right font-semibold">HC dan SC</th>
              <th class="py-3 pr-4 font-semibold">Status</th>
              <th class="py-3 pr-6 font-semibold">Dijalankan oleh</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line-soft text-ink-secondary">
            <tr v-for="run in result?.data ?? []" :key="run.id" class="align-top">
              <td class="py-3 pl-6 pr-4">
                <span class="font-mono text-xs font-semibold text-ink">{{ run.faculty?.code ?? '-' }}</span>
                <span class="block text-xs text-ink-muted">{{ run.faculty?.name ?? 'Fakultas tidak diketahui' }}</span>
              </td>
              <td class="py-3 pr-4">
                <span class="tabular font-semibold text-primary">#{{ run.id }}</span>
                <span class="block text-xs text-ink-muted">{{ periodLabel(run.period) }}</span>
              </td>
              <td class="tabular whitespace-nowrap py-3 pr-4">{{ timestamp(run.finished_at ?? run.started_at) }}</td>
              <td class="py-3 pr-4">{{ run.selection_operator ?? '-' }}</td>
              <td class="tabular py-3 pr-4 text-right">
                {{ run.best_fitness?.toFixed(1) ?? '-' }}
                <span class="block text-xs text-ink-muted">{{ run.fitness_percent ?? 0 }}%</span>
              </td>
              <td class="tabular py-3 pr-4 text-right">
                <span :class="run.hc_violations ? 'font-semibold text-danger-ink' : ''">{{ run.hc_violations }}</span> dan {{ run.sc_unmet }}
                <span class="block font-sans text-xs text-ink-muted">{{ run.unassigned_rooms }} kelas tanpa ruangan</span>
              </td>
              <td class="py-3 pr-4"><Badge :tone="runState(run).tone">{{ runState(run).label }}</Badge></td>
              <td class="py-3 pr-6">{{ run.triggered_by?.name ?? '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="result && result.meta.pagination.last_page > 1" class="flex items-center justify-between border-t border-line px-6 py-4">
        <span class="tabular text-xs text-ink-muted">{{ result.meta.pagination.total }} run, halaman {{ result.meta.pagination.page }} dari {{ result.meta.pagination.last_page }}</span>
        <div class="flex gap-2">
          <Button size="sm" :disabled="page <= 1 || loading" @click="page--; load()">Sebelumnya</Button>
          <Button size="sm" :disabled="page >= result.meta.pagination.last_page || loading" @click="page++; load()">Berikutnya</Button>
        </div>
      </div>
      <div v-else-if="result" class="tabular border-t border-line px-6 py-4 text-xs text-ink-muted">{{ result.meta.pagination.total }} run</div>
    </Card>
  </div>
</template>

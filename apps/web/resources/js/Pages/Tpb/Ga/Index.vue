<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { useCollection } from '@/composables/useApiList';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import { api, toApiError, type ApiError } from '@/lib/api';
import { classLabel, COHORT_LABELS, formatTime } from '@/lib/labels';
import type { TpbCohort, AcademicPeriod, Collection, Paginated, Pagination as PaginationMeta, RunScheduleDetail, SchedulingRunDetail, SchedulingRunSummary, TimeSlot, Violation } from '@/types/models';
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type ConfirmAction = 'activate' | 'publish';

// The TPB run review. The faculty version lives in Fakultas/Ga/Index.vue.
const controlClass = 'h-10 w-full rounded-lg border-gray-200 bg-white text-sm text-gray-700 focus:border-primary focus:ring-primary';
const DAYS: Record<number, string> = { 1: 'Senin', 2: 'Selasa', 3: 'Rabu', 4: 'Kamis', 5: 'Jumat', 6: 'Sabtu', 7: 'Minggu' };
const { items: periods, error: periodError } = useCollection<AcademicPeriod>('academic-periods');
const { items: timeSlots, error: timeSlotError } = useCollection<TimeSlot>('time-slots');
const period = ref('');
const runs = ref<SchedulingRunSummary[]>([]);
const pagination = ref<PaginationMeta>({ page: 1, per_page: 20, total: 0, last_page: 1 });
const page = ref(1);
const loadingRuns = ref(false);
const runError = ref<ApiError | null>(null);
const selectedRunId = ref<number | null>(null);
const selectedRun = ref<SchedulingRunDetail | null>(null);
const details = ref<RunScheduleDetail[]>([]);
const violations = ref<Violation[]>([]);
const category = ref<'HC' | 'SC' | ''>('');
const programFilter = ref('');
const dayFilter = ref('');
const detailLoading = ref(false);
const detailError = ref<ApiError | null>(null);
const actionError = ref<string | null>(null);
const flash = ref<string | null>(null);
const pendingAction = ref<ConfirmAction | null>(null);
const actionTarget = ref<SchedulingRunSummary | null>(null);
const processing = ref(false);
const compareRuns = ref<SchedulingRunSummary[]>([]);
const compareBatchId = ref<string | null>(null);
const compareLoading = ref(false);
const openedBlock = ref<Block | null>(null);
let listRequestId = 0;

const programs = computed(() => {
    const unique = new Map<number, { id: number; code: string; name: string }>();
    for (const detail of details.value) {
        const program = detail.course.study_program;
        if (program) unique.set(program.id, program);
        for (const participant of detail.tpb_group?.study_programs ?? []) unique.set(participant.id, participant);
    }

    return [...unique.values()].sort((a, b) => a.name.localeCompare(b.name));
});
const days = computed(() => [...new Set(timeSlots.value.map((slot) => slot.day))].sort((a, b) => a - b));
const shownDays = computed(() => (dayFilter.value ? days.value.filter((d) => String(d) === dayFilter.value) : days.value));
const sessions = computed(() => [...new Set(timeSlots.value.map((slot) => slot.session))].sort((a, b) => a - b));
const visibleDetails = computed(() =>
    details.value.filter((detail) =>
        (!programFilter.value
            || detail.course.study_program?.id === Number(programFilter.value)
            || detail.tpb_group?.study_programs.some((program) => program.id === Number(programFilter.value)))
        && (!dayFilter.value || detail.time_slot.day === Number(dayFilter.value)),
    ),
);
const detailsByCell = computed(() => {
    const cells = new Map<string, RunScheduleDetail[]>();
    for (const detail of visibleDetails.value) {
        const key = `${detail.time_slot.day}-${detail.time_slot.session}`;
        cells.set(key, [...(cells.get(key) ?? []), detail]);
    }
    return cells;
});
const bestRun = computed(() => [...runs.value].filter((run) => run.best_fitness !== null).sort((a, b) => (b.best_fitness ?? 0) - (a.best_fitness ?? 0))[0] ?? null);
const activeRun = computed(() => runs.value.find((run) => run.is_active) ?? null);

function timeForSession(session: number): string {
    const slot = timeSlots.value.find((item) => item.session === session);
    return slot ? `${formatTime(slot.start_time)}–${formatTime(slot.end_time)}` : '';
}

function stateLabel(run: SchedulingRunSummary): string {
    if (run.is_active && run.published_at) return 'Dipublikasikan';
    if (run.is_active) return 'Aktif, belum dipublikasikan';
    if (run.published_at) return 'Arsip';
    return run.status === 'done' ? 'Draf' : run.status;
}

function stateClass(run: SchedulingRunSummary): string {
    if (run.is_active && run.published_at) return 'bg-green-50 text-green-700';
    if (run.is_active) return 'bg-blue-50 text-blue-700';
    return run.status === 'done' || run.published_at ? 'bg-gray-100 text-gray-600' : 'bg-amber-50 text-amber-700';
}

// Fitness history chart: 640x240 viewBox with room for the axis labels.
const CHART = { width: 640, height: 240, left: 60, right: 20, top: 24, bottom: 36 };
const chart = computed(() => {
    const history = selectedRun.value?.fitness_history ?? [];
    if (history.length < 2) return null;
    const min = Math.min(...history);
    const max = Math.max(...history);
    const span = max - min || 1;
    const plotWidth = CHART.width - CHART.left - CHART.right;
    const plotHeight = CHART.height - CHART.top - CHART.bottom;
    const xAt = (index: number) => CHART.left + (index / (history.length - 1)) * plotWidth;
    const yAt = (value: number) => CHART.top + plotHeight - ((value - min) / span) * plotHeight;
    const bestIndex = history.indexOf(max);
    const baseline = CHART.top + plotHeight;
    const line = history.map((value, index) => `${xAt(index).toFixed(1)},${yAt(value).toFixed(1)}`).join(' ');

    return {
        first: history[0],
        min,
        max,
        count: history.length,
        gain: history[0] ? ((max - history[0]) / Math.abs(history[0])) * 100 : null,
        line,
        area: `${xAt(0).toFixed(1)},${baseline} ${line} ${xAt(history.length - 1).toFixed(1)},${baseline}`,
        start: { x: xAt(0), y: yAt(history[0]) },
        best: { x: xAt(bestIndex), y: yAt(max) },
        baseline,
        yTicks: [0, 1 / 3, 2 / 3, 1].map((f) => ({ y: CHART.top + plotHeight - f * plotHeight, value: min + f * span })),
        xTicks: [0, 0.25, 0.5, 0.75, 1].map((f) => ({ x: CHART.left + f * plotWidth, label: Math.round(1 + f * (history.length - 1)) })),
    };
});

const number = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const dateTime = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

function formatNumber(value: number | null | undefined): string {
    return value === null || value === undefined ? '-' : number.format(value);
}

function formatDate(value: string | null): string {
    return value ? dateTime.format(new Date(value)) : '';
}

function periodLabel(code: string): string {
    return periods.value.find((item) => item.code === code)?.label ?? code;
}

const COHORT_TONE: Record<TpbCohort, string> = {
    maba: 'border-blue-200 bg-blue-50 hover:bg-blue-100/70',
    non_maba: 'border-indigo-200 bg-indigo-50 hover:bg-indigo-100/70',
    campuran: 'border-teal-200 bg-teal-50 hover:bg-teal-100/70',
    mengulang: 'border-amber-200 bg-amber-50 hover:bg-amber-100/70',
    pilihan: 'border-gray-300 bg-gray-50 hover:bg-gray-100',
};
const COHORT_SWATCH: Record<TpbCohort, string> = {
    maba: 'border-blue-200 bg-blue-50',
    non_maba: 'border-indigo-200 bg-indigo-50',
    campuran: 'border-teal-200 bg-teal-50',
    mengulang: 'border-amber-200 bg-amber-50',
    pilihan: 'border-gray-300 bg-gray-50',
};

type Block = { key: string; cohort: TpbCohort | null; courseName: string; courseCode: string; groupCode: string | null; meeting: number; details: RunScheduleDetail[]; violations: number; overridden: boolean };

/** One block per TPB group meeting (its classes share the slot). A faculty class is a block of its own. */
function blocksOf(cell: RunScheduleDetail[] | undefined): Block[] {
    const map = new Map<string, Block>();
    for (const detail of cell ?? []) {
        const key = detail.tpb_group ? `g${detail.tpb_group.id}:${detail.meeting_number}` : `d${detail.id}`;
        const block = map.get(key) ?? { key, cohort: detail.tpb_group?.cohort ?? null, courseName: detail.course.name, courseCode: detail.course.code, groupCode: detail.tpb_group?.code ?? null, meeting: detail.meeting_number, details: [], violations: 0, overridden: false };
        block.details.push(detail);
        block.violations += detail.violations_count;
        block.overridden ||= detail.is_overridden;
        map.set(key, block);
    }
    return [...map.values()].sort((a, b) => a.courseName.localeCompare(b.courseName));
}

function classesOf(block: Block): string {
    return `Kelas ${block.details.map((d) => classLabel(d.class_number)).join(', ')}`;
}

function openBlock(block: Block): void {
    openedBlock.value = block;
}

async function loadRuns(): Promise<void> {
    const requestId = ++listRequestId;
    loadingRuns.value = true;
    runError.value = null;
    try {
        const response = await api.get<Paginated<SchedulingRunSummary>>('scheduling-runs', {
            params: { period: period.value, page: page.value, per_page: 20, scope: 'tpb' },
        });
        if (requestId !== listRequestId) return;
        runs.value = response.data.data;
        pagination.value = response.data.meta.pagination;
        if (!runs.value.some((run) => run.id === selectedRunId.value)) {
            selectedRunId.value = runs.value[0]?.id ?? null;
        }
    } catch (error) {
        if (requestId === listRequestId) runError.value = toApiError(error);
    } finally {
        if (requestId === listRequestId) loadingRuns.value = false;
    }
}

async function loadSelectedRun(): Promise<void> {
    const id = selectedRunId.value;
    if (!id) {
        selectedRun.value = null;
        details.value = [];
        violations.value = [];
        return;
    }
    detailLoading.value = true;
    detailError.value = null;
    try {
        const [runResponse, detailResponse, violationResponse] = await Promise.all([
            api.get<{ data: SchedulingRunDetail }>(`scheduling-runs/${id}`),
            api.get<Collection<RunScheduleDetail>>(`scheduling-runs/${id}/schedule-details`, {
                // TPB courses have no program: their participants are filtered here, not on the server.
                params: { day: dayFilter.value },
            }),
            api.get<Collection<Violation>>(`scheduling-runs/${id}/violations`, {
                params: category.value ? { category: category.value } : {},
            }),
        ]);
        if (selectedRunId.value !== id) return;
        selectedRun.value = runResponse.data.data;
        details.value = detailResponse.data.data;
        violations.value = violationResponse.data.data;
    } catch (error) {
        if (selectedRunId.value === id) detailError.value = toApiError(error);
    } finally {
        if (selectedRunId.value === id) detailLoading.value = false;
    }
}

async function loadCompare(batchId: string): Promise<void> {
    compareBatchId.value = batchId;
    compareLoading.value = true;
    actionError.value = null;
    try {
        const response = await api.get<Paginated<SchedulingRunSummary>>('scheduling-runs', {
            params: { period: period.value, batch_id: batchId, per_page: 100, scope: 'tpb' },
        });
        compareRuns.value = response.data.data;
    } catch (error) {
        actionError.value = toApiError(error).message;
        compareBatchId.value = null;
    } finally {
        compareLoading.value = false;
    }
}

function confirm(action: ConfirmAction, run: SchedulingRunSummary): void {
    pendingAction.value = action;
    actionTarget.value = run;
    actionError.value = null;
}

async function performAction(): Promise<void> {
    if (!pendingAction.value || !actionTarget.value) return;
    const action = pendingAction.value;
    const target = actionTarget.value;
    processing.value = true;
    actionError.value = null;
    try {
        const response = await api.post<{ data: SchedulingRunDetail }>(`scheduling-runs/${target.id}/${action}`);
        flash.value = action === 'activate' ? `Run #${target.id} sekarang menjadi jadwal aktif.` : `Run #${target.id} sudah dipublikasikan.`;
        pendingAction.value = null;
        actionTarget.value = null;
        await loadRuns();
        selectedRun.value = response.data.data;
        await loadSelectedRun();
    } catch (error) {
        actionError.value = toApiError(error).message;
    } finally {
        processing.value = false;
    }
}

watch(periods, (items) => {
    if (!period.value && items.length) {
        period.value = items.find((item) => item.is_current)?.code ?? items[0].code;
    }
}, { immediate: true });
watch(period, () => {
    page.value = 1;
    compareBatchId.value = null;
    void loadRuns();
});
watch(page, () => void loadRuns());
watch(selectedRunId, () => {
    programFilter.value = '';
    dayFilter.value = '';
    category.value = '';
    void loadSelectedRun();
});
watch([programFilter, dayFilter, category], () => void loadSelectedRun());
onResourcesChanged(['scheduling_runs', 'schedule_details', 'violation_logs'], () => {
    void loadRuns();
    void loadSelectedRun();
});
</script>

<template>
    <Head title="Penjadwalan GA TPB" />
    <AppLayout>
        <div class="mx-auto w-full max-w-7xl space-y-6 p-6 md:p-8">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Penjadwalan GA TPB</h1>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">
                        Periksa hasil run, tetapkan satu sebagai jadwal aktif, lalu publikasikan. Fakultas baru bisa menjalankan GA setelah jadwal TPB aktif.
                    </p>
                </div>
                <label class="text-sm font-medium text-gray-700">Periode
                    <select v-model="period" :class="[controlClass, 'mt-1 block sm:w-56']">
                        <option v-for="item in periods" :key="item.id" :value="item.code">{{ item.label }}</option>
                    </select>
                </label>
            </header>

            <div v-if="periodError || timeSlotError || flash || runError || actionError" class="space-y-3">
                <Alert v-if="periodError" :title="periodError.message" />
                <Alert v-if="timeSlotError" :title="timeSlotError.message" />
                <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />
                <Alert v-if="runError" :title="runError.message" />
                <Alert v-if="actionError" :title="actionError" />
            </div>

            <dl class="grid gap-4 sm:grid-cols-3" aria-label="Ringkasan run">
                <div class="rounded-lg border border-gray-200 bg-white p-5">
                    <dt class="text-xs font-semibold text-gray-500">Jadwal aktif</dt>
                    <dd class="mt-2 text-2xl font-bold text-gray-900">{{ activeRun ? `Run #${activeRun.id}` : 'Belum ada' }}</dd>
                    <p class="mt-1 text-xs text-gray-500">{{ activeRun ? (activeRun.published_at ? 'Sudah dipublikasikan' : 'Belum dipublikasikan') : 'Tetapkan salah satu run di bawah.' }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5">
                    <dt class="text-xs font-semibold text-gray-500">Fitness terbaik</dt>
                    <dd class="mt-2 text-2xl font-bold tabular-nums text-gray-900">{{ formatNumber(bestRun?.best_fitness) }}</dd>
                    <p class="mt-1 text-xs text-gray-500">{{ bestRun ? `Dari Run #${bestRun.id}` : 'Belum ada run selesai.' }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5">
                    <dt class="text-xs font-semibold text-gray-500">Jumlah run</dt>
                    <dd class="mt-2 text-2xl font-bold tabular-nums text-gray-900">{{ pagination.total }}</dd>
                    <p class="mt-1 text-xs text-gray-500">Pada {{ periodLabel(period) }}</p>
                </div>
            </dl>

            <section aria-labelledby="runs-heading">
                <div class="mb-3 flex items-center justify-between">
                    <h2 id="runs-heading" class="text-base font-bold text-gray-900">Hasil run</h2>
                    <span v-if="loadingRuns" class="text-sm text-gray-500">Memuat…</span>
                </div>
                <p v-if="!loadingRuns && runs.length === 0" class="rounded-lg border border-gray-200 bg-white p-8 text-center text-sm text-gray-500">Belum ada hasil run untuk periode ini. Jalankan GA dari aplikasi desktop.</p>
                <div v-if="runs.length" class="divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200 bg-white" :class="{ 'opacity-60': loadingRuns }">
                    <div v-for="run in runs" :key="run.id" class="grid gap-4 p-5 lg:grid-cols-[15rem_minmax(0,1fr)_auto] lg:items-center" :class="run.id === selectedRunId ? 'bg-primary/5' : ''">
                        <div class="min-w-0">
                            <button type="button" class="text-base font-bold text-gray-900 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary" :aria-pressed="run.id === selectedRunId" @click="selectedRunId = run.id">Run #{{ run.id }}</button>
                            <p class="mt-0.5 text-xs text-gray-500">{{ run.finished_at ? formatDate(run.finished_at) : run.status }}</p>
                            <p class="mt-1.5 flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="stateClass(run)">{{ stateLabel(run) }}</span>
                                <span v-if="run.is_stale" class="text-xs font-medium text-amber-700" title="Jadwal TPB berubah setelah run ini dibuat">Dibuat dari jadwal TPB lama</span>
                            </p>
                        </div>

                        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-4">
                            <div>
                                <dt class="text-xs text-gray-500">Fitness</dt>
                                <dd class="font-semibold tabular-nums text-gray-900">{{ formatNumber(run.best_fitness) }}<span v-if="run.fitness_percent !== null" class="font-normal text-gray-500"> ({{ formatNumber(run.fitness_percent) }}%)</span></dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Pelanggaran hard</dt>
                                <dd class="font-semibold tabular-nums" :class="run.hc_violations ? 'text-red-700' : 'text-gray-900'">{{ run.hc_violations }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Soft belum terpenuhi</dt>
                                <dd class="font-semibold tabular-nums text-gray-900">{{ run.sc_unmet }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Kelas tanpa ruang</dt>
                                <dd class="font-semibold tabular-nums" :class="run.unassigned_rooms ? 'text-amber-700' : 'text-gray-900'">{{ run.unassigned_rooms }}</dd>
                            </div>
                        </dl>

                        <div class="flex items-center gap-4 lg:justify-end">
                            <button v-if="compareBatchId !== run.batch_id" type="button" class="text-sm font-semibold text-primary hover:underline" @click="loadCompare(run.batch_id)">Bandingkan batch</button>
                            <Button v-if="run.status === 'done' && !run.is_active" @click="confirm('activate', run)">Tetapkan aktif</Button>
                            <Button v-else-if="run.is_active && !run.published_at" @click="confirm('publish', run)">Publikasikan</Button>
                        </div>
                    </div>
                </div>
                <Pagination v-if="pagination.last_page > 1" v-model:page="page" :pagination="pagination" label="run" />
            </section>

            <section v-if="compareBatchId" aria-labelledby="compare-heading">
                <div class="mb-3 flex items-center justify-between">
                    <h2 id="compare-heading" class="text-base font-bold text-gray-900">Perbandingan run satu batch</h2>
                    <Button variant="secondary" size="sm" @click="compareBatchId = null">Tutup</Button>
                </div>
                <p v-if="compareLoading" class="text-sm text-gray-500">Memuat perbandingan…</p>
                <div v-else class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                    <table class="w-full min-w-[760px] border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-xs font-semibold text-gray-500">
                                <th class="px-5 py-3">Run</th>
                                <th class="px-5 py-3">Seleksi</th>
                                <th class="px-5 py-3">Crossover / mutasi</th>
                                <th class="px-5 py-3">Seed</th>
                                <th class="px-5 py-3">Fitness</th>
                                <th class="px-5 py-3">Hard</th>
                                <th class="px-5 py-3">Soft</th>
                                <th class="px-5 py-3">Tanpa ruang</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 tabular-nums text-gray-700">
                            <tr v-for="run in [...compareRuns].sort((a, b) => (b.best_fitness ?? -1) - (a.best_fitness ?? -1))" :key="run.id">
                                <td class="px-5 py-3 font-semibold text-gray-900">#{{ run.id }}<span v-if="run.id === bestRun?.id" class="ml-2 rounded-full bg-green-50 px-2 py-0.5 text-xs font-semibold text-green-700">Terbaik</span></td>
                                <td class="px-5 py-3">{{ run.selection_operator ?? '-' }}</td>
                                <td class="px-5 py-3">{{ run.crossover_rate ?? '-' }} / {{ run.mutation_rate ?? '-' }}</td>
                                <td class="px-5 py-3">{{ run.seed ?? '-' }}</td>
                                <td class="px-5 py-3 font-semibold text-gray-900">{{ formatNumber(run.best_fitness) }}</td>
                                <td class="px-5 py-3">{{ run.hc_violations }}</td>
                                <td class="px-5 py-3">{{ run.sc_unmet }}</td>
                                <td class="px-5 py-3">{{ run.unassigned_rooms }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="selectedRun" class="space-y-6" aria-labelledby="detail-heading">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 id="detail-heading" class="text-xl font-bold tracking-tight text-gray-900">Run #{{ selectedRun.id }}</h2>
                        <p class="mt-1 text-sm text-gray-500">TPB, {{ periodLabel(selectedRun.period) }} · {{ selectedRun.schedule_details_count }} jadwal</p>
                    </div>
                    <select v-model="programFilter" :class="[controlClass, 'lg:w-72']" aria-label="Filter program studi">
                        <option value="">Semua program studi</option>
                        <option v-for="program in programs" :key="program.id" :value="String(program.id)">{{ program.name }}</option>
                    </select>
                </div>

                <Alert v-if="detailError" :title="detailError.message" />
                <p v-if="detailLoading" class="py-8 text-center text-sm text-gray-500">Memuat detail run…</p>

                <template v-else>
                    <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-lg bg-blue-50 p-4">
                            <dt class="text-xs font-medium text-blue-800">Fitness terbaik</dt>
                            <dd class="mt-1 text-2xl font-bold tabular-nums text-blue-900">{{ formatNumber(selectedRun.best_fitness) }}</dd>
                        </div>
                        <div class="rounded-lg bg-indigo-50 p-4">
                            <dt class="text-xs font-medium text-indigo-800">Generasi terbaik</dt>
                            <dd class="mt-1 text-2xl font-bold tabular-nums text-indigo-900">{{ selectedRun.best_generation ?? '-' }}<span class="text-sm font-medium"> dari {{ selectedRun.final_generation }}</span></dd>
                        </div>
                        <div class="rounded-lg bg-teal-50 p-4">
                            <dt class="text-xs font-medium text-teal-800">Seed acak</dt>
                            <dd class="mt-1 text-2xl font-bold tabular-nums text-teal-900">{{ selectedRun.seed ?? '-' }}</dd>
                        </div>
                        <div class="rounded-lg bg-gray-100 p-4">
                            <dt class="text-xs font-medium text-gray-600">Parameter GA</dt>
                            <dd class="mt-1 text-sm font-semibold leading-6 text-gray-900">Seleksi {{ selectedRun.selection_operator ?? '-' }}<br />Crossover {{ selectedRun.crossover_rate ?? '-' }}, mutasi {{ selectedRun.mutation_rate ?? '-' }}</dd>
                        </div>
                    </dl>

                    <div class="space-y-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="inline-flex flex-wrap gap-1 rounded-lg bg-gray-100 p-1" role="tablist" aria-label="Filter hari">
                                <button
                                    v-for="option in [{ value: '', label: 'Semua hari' }, ...days.map((day) => ({ value: String(day), label: DAYS[day] }))]"
                                    :key="option.value"
                                    type="button"
                                    role="tab"
                                    :aria-selected="dayFilter === option.value"
                                    class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                    :class="dayFilter === option.value ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                                    @click="dayFilter = option.value"
                                >{{ option.label }}</button>
                            </div>

                        </div>

                        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                            <table class="w-full min-w-[1000px] table-fixed border-collapse text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 bg-gray-50 text-xs font-semibold text-gray-500">
                                        <th class="w-32 px-4 py-3 text-left">Sesi</th>
                                        <th v-for="day in shownDays" :key="day" class="border-l border-gray-200 px-4 py-3 text-left">{{ DAYS[day] }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y-2 divide-gray-200">
                                    <tr v-for="session in sessions" :key="session" class="align-top">
                                        <th class="px-4 py-3 text-left font-semibold text-gray-900">
                                            Sesi {{ session }}
                                            <span class="mt-0.5 block text-xs font-semibold tabular-nums text-gray-600">{{ timeForSession(session) }}</span>
                                        </th>
                                        <td v-for="day in shownDays" :key="day" class="border-l border-gray-200 p-2">
                                            <p v-if="!detailsByCell.get(`${day}-${session}`)?.length" class="px-2 py-1 text-xs text-gray-400">Kosong</p>
                                            <ul v-else class="space-y-1.5">
                                                <li v-for="block in blocksOf(detailsByCell.get(`${day}-${session}`))" :key="block.key">
                                                    <button
                                                        type="button"
                                                        class="block w-full rounded-lg border px-3 py-2 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                                        :class="block.violations ? 'border-red-300 bg-red-50 hover:bg-red-100/70' : block.cohort ? COHORT_TONE[block.cohort] : 'border-gray-200 bg-gray-50 hover:bg-gray-100'"
                                                        @click="openBlock(block)"
                                                    >
                                                        <span class="block text-sm font-semibold leading-snug text-gray-900">{{ block.courseName }}</span>
                                                        <span class="mt-0.5 block text-xs text-gray-600">
                                                            {{ classesOf(block) }}<template v-if="block.groupCode"> · Grup {{ block.groupCode }}</template><template v-if="block.meeting > 1"> · Pertemuan {{ block.meeting }}</template>
                                                        </span>
                                                        <span v-if="block.details.length === 1" class="mt-1 block text-xs text-gray-500">{{ block.details[0].lecturer.name }} · {{ block.details[0].room?.code ?? 'Belum ada ruang' }}</span>
                                                        <span v-if="block.violations" class="mt-1 block text-xs font-semibold text-red-700">{{ block.violations }} pelanggaran</span>
                                                        <span v-else-if="block.overridden" class="mt-1 block text-xs font-semibold text-amber-700">Diubah manual</span>
                                                    </button>
                                                </li>
                                            </ul>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <ul class="flex flex-wrap gap-x-5 gap-y-2 text-xs text-gray-600" aria-label="Keterangan warna">
                            <li v-for="(label, value) in COHORT_LABELS" :key="value" class="flex items-center gap-1.5"><span class="size-3 rounded border" :class="COHORT_SWATCH[value]" />{{ label }}</li>
                            <li class="flex items-center gap-1.5"><span class="size-3 rounded border border-red-300 bg-red-50" />Ada pelanggaran</li>
                        </ul>
                    </div>

                    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,6fr)_minmax(0,5fr)]">
                        <div class="rounded-lg border border-gray-200 bg-white">
                            <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-sm font-bold text-gray-900">Pelanggaran</h3>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ violations.length }} temuan{{ category ? (category === 'HC' ? ' hard constraint' : ' soft constraint') : '' }}</p>
                                </div>
                                <select v-model="category" :class="[controlClass, 'sm:w-52']" aria-label="Filter jenis pelanggaran">
                                    <option value="">Semua jenis</option>
                                    <option value="HC">Hard constraint</option>
                                    <option value="SC">Soft constraint</option>
                                </select>
                            </div>
                            <ul v-if="violations.length" class="max-h-96 divide-y divide-gray-100 overflow-y-auto">
                                <li v-for="violation in violations" :key="violation.id" class="flex gap-3 px-5 py-3 text-sm">
                                    <span class="mt-0.5 h-fit w-14 shrink-0 rounded-md px-2 py-0.5 text-center text-xs font-semibold" :class="violation.category === 'HC' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-800'">{{ violation.category === 'HC' ? 'Hard' : 'Soft' }}</span>
                                    <div class="min-w-0">
                                        <p class="text-gray-900">{{ violation.message ?? 'Tidak ada rincian.' }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500">{{ violation.constraint_code }} · penalti {{ violation.penalty }}</p>
                                    </div>
                                </li>
                            </ul>
                            <p v-else class="px-5 py-8 text-center text-sm text-gray-500">{{ category ? 'Tidak ada pelanggaran pada jenis ini.' : 'Tidak ada pelanggaran. Jadwal memenuhi semua constraint.' }}</p>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white">
                            <div class="border-b border-gray-200 px-5 py-4">
                                <h3 class="text-sm font-bold text-gray-900">Perkembangan fitness</h3>
                                <p class="mt-0.5 text-xs text-gray-500">{{ chart ? 'Fitness terbaik pada tiap generasi.' : 'Run ini belum punya riwayat fitness.' }}</p>
                            </div>
                            <div v-if="chart" class="space-y-4 p-5">
                                <dl class="grid grid-cols-3 gap-3 text-sm">
                                    <div>
                                        <dt class="text-xs text-gray-500">Generasi pertama</dt>
                                        <dd class="font-semibold tabular-nums text-gray-900">{{ formatNumber(chart.first) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-gray-500">Nilai tertinggi</dt>
                                        <dd class="font-semibold tabular-nums text-gray-900">{{ formatNumber(chart.max) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-gray-500">Kenaikan</dt>
                                        <dd class="font-semibold tabular-nums text-green-700">{{ chart.gain === null ? '-' : `+${formatNumber(chart.gain)}%` }}</dd>
                                    </div>
                                </dl>
                                <svg :viewBox="`0 0 ${CHART.width} ${CHART.height}`" class="h-auto w-full text-primary" role="img" :aria-label="`Fitness naik dari ${formatNumber(chart.first)} ke ${formatNumber(chart.max)} selama ${chart.count} generasi`">
                                    <g stroke="#e5e7eb" stroke-width="1">
                                        <line v-for="tick in chart.yTicks" :key="tick.y" :x1="CHART.left" :x2="CHART.width - CHART.right" :y1="tick.y" :y2="tick.y" />
                                    </g>
                                    <g font-size="11" fill="#4b5563" text-anchor="end">
                                        <text v-for="tick in chart.yTicks" :key="`y${tick.y}`" :x="CHART.left - 8" :y="tick.y + 4">{{ formatNumber(tick.value) }}</text>
                                    </g>
                                    <g font-size="11" fill="#4b5563" text-anchor="middle">
                                        <text v-for="tick in chart.xTicks" :key="tick.x" :x="tick.x" :y="CHART.height - 12">{{ tick.label }}</text>
                                    </g>
                                    <polygon :points="chart.area" fill="currentColor" fill-opacity="0.07" />
                                    <polyline :points="chart.line" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linejoin="round" stroke-linecap="round" />
                                    <line :x1="chart.best.x" :x2="chart.best.x" :y1="chart.best.y" :y2="chart.baseline" stroke="currentColor" stroke-opacity="0.35" stroke-dasharray="3 3" />
                                    <circle :cx="chart.start.x" :cy="chart.start.y" r="3.5" fill="#fff" stroke="currentColor" stroke-width="2" />
                                    <circle :cx="chart.best.x" :cy="chart.best.y" r="4.5" fill="currentColor" />
                                </svg>
                                <p class="text-xs text-gray-500">
                                    Sumbu bawah: nomor generasi.
                                    <template v-if="selectedRun.best_generation !== null && selectedRun.best_generation < selectedRun.final_generation">Nilai tertinggi dicapai di generasi {{ selectedRun.best_generation }} dan tidak naik lagi sampai generasi {{ selectedRun.final_generation }}.</template>
                                    <template v-else>Nilai tertinggi dicapai di generasi terakhir.</template>
                                </p>
                            </div>
                        </div>
                    </div>
                </template>
            </section>
        </div>

        <Modal :show="openedBlock !== null" max-width="2xl" close-button centered @close="openedBlock = null">
            <template v-if="openedBlock">
                <div class="rounded-t-lg border-b border-gray-100 bg-gray-50 py-5 pl-6 pr-14">
                    <p class="text-xs text-gray-500"><span class="tabular-nums">{{ openedBlock.courseCode }}</span><template v-if="openedBlock.groupCode"> · Grup {{ openedBlock.groupCode }}</template><template v-if="openedBlock.meeting > 1"> · Pertemuan {{ openedBlock.meeting }}</template></p>
                    <h2 class="mt-1 text-lg font-bold text-gray-900">{{ openedBlock.courseName }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ DAYS[openedBlock.details[0].time_slot.day] }}, sesi {{ openedBlock.details[0].time_slot.session }} ({{ formatTime(openedBlock.details[0].time_slot.start_time) }}–{{ formatTime(openedBlock.details[0].time_slot.end_time) }})</p>
                </div>
                <div class="p-6">
                    <table class="w-full text-left text-sm">
                        <thead><tr class="border-b text-xs font-semibold text-gray-500"><th class="py-2">Kelas</th><th class="py-2">Dosen pengampu</th><th class="py-2">Ruang</th><th class="py-2 text-right">Pelanggaran</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="d in openedBlock.details" :key="d.id">
                                <td class="py-2 font-semibold">{{ classLabel(d.class_number) }}</td>
                                <td class="py-2">{{ d.lecturer.name }}</td>
                                <td class="py-2">{{ d.room?.code ?? 'Belum ada ruang' }}</td>
                                <td class="py-2 text-right tabular-nums" :class="d.violations_count ? 'font-semibold text-red-700' : 'text-gray-500'">{{ d.violations_count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </Modal>

        <ConfirmDialog
            :show="pendingAction !== null"
            :title="pendingAction === 'activate' ? 'Tetapkan run ini sebagai jadwal aktif?' : 'Publikasikan jadwal ini?'"
            :confirm-label="pendingAction === 'activate' ? 'Tetapkan aktif' : 'Publikasikan'"
            :danger="false"
            :processing="processing"
            :error="actionError"
            @confirm="performAction"
            @close="pendingAction = null; actionTarget = null"
        >
            <template v-if="pendingAction === 'activate'">
                Jadwal aktif yang lama akan digantikan. Dosen dan mahasiswa baru melihat run ini setelah dipublikasikan.
                <p class="mt-2">Slot TPB baru terkunci untuk fakultas setelah jadwal ini dipublikasikan. Jadwal fakultas yang sudah ada tetap berjalan dan hanya diberi peringatan.</p>
                <p v-if="actionTarget && actionTarget.hc_violations > 0" class="mt-2 font-semibold text-amber-700">Run ini punya {{ actionTarget.hc_violations }} pelanggaran hard constraint. Sistem tetap mengizinkan aktivasi.</p>
            </template>
            <template v-else>Setelah dipublikasikan, dosen dan mahasiswa bisa melihat jadwal ini.</template>
        </ConfirmDialog>

    </AppLayout>
</template>

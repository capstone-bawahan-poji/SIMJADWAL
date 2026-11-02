<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { useCollection } from '@/composables/useApiList';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import { api, toApiError, type ApiError } from '@/lib/api';
import { formatTime } from '@/lib/labels';
import type { AcademicPeriod, Collection, Paginated, Pagination as PaginationMeta, RunScheduleDetail, SchedulingRunDetail, SchedulingRunSummary, TimeSlot, Violation } from '@/types/models';
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type ConfirmAction = 'activate' | 'publish';
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
let listRequestId = 0;

const programs = computed(() => {
    const unique = new Map<number, { id: number; code: string; name: string }>();
    for (const detail of details.value) {
        const program = detail.course.study_program;
        if (program) unique.set(program.id, program);
    }

    return [...unique.values()].sort((a, b) => a.code.localeCompare(b.code));
});
const days = computed(() => [...new Set(timeSlots.value.map((slot) => slot.day))].sort((a, b) => a - b));
const sessions = computed(() => [...new Set(timeSlots.value.map((slot) => slot.session))].sort((a, b) => a - b));
const visibleDetails = computed(() =>
    details.value.filter((detail) =>
        (!programFilter.value || detail.course.study_program?.id === Number(programFilter.value))
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

function classLabel(value: number): string {
    return value > 0 && value <= 26 ? String.fromCharCode(64 + value) : String(value);
}

function timeForSession(session: number): string {
    const slot = timeSlots.value.find((item) => item.session === session);
    return slot ? `${formatTime(slot.start_time)}–${formatTime(slot.end_time)}` : '';
}

function stateLabel(run: SchedulingRunSummary): string {
    if (run.is_active && run.published_at) return 'Dipublikasikan';
    if (run.is_active) return 'Aktif · belum publish';
    if (run.published_at) return 'Arsip';
    return run.status === 'done' ? 'Draft' : run.status;
}

function stateDot(run: SchedulingRunSummary): string {
    if (run.is_active && run.published_at) return 'bg-green-500';
    if (run.is_active) return 'bg-blue-500';
    if (run.published_at) return 'bg-gray-300';
    return run.status === 'done' ? 'bg-gray-400' : 'bg-amber-500';
}

const sparkPoints = computed(() => {
    const history = selectedRun.value?.fitness_history ?? [];
    if (history.length < 2) return '';
    const min = Math.min(...history);
    const span = Math.max(...history) - min || 1;
    return history
        .map((value, index) => `${((index / (history.length - 1)) * 300).toFixed(1)},${(38 - ((value - min) / span) * 34).toFixed(1)}`)
        .join(' ');
});

async function loadRuns(): Promise<void> {
    const requestId = ++listRequestId;
    loadingRuns.value = true;
    runError.value = null;
    try {
        const response = await api.get<Paginated<SchedulingRunSummary>>('scheduling-runs', {
            params: { period: period.value, page: page.value, per_page: 20 },
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
                params: { study_program_id: programFilter.value, day: dayFilter.value },
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
            params: { period: period.value, batch_id: batchId, per_page: 100 },
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
        flash.value = action === 'activate' ? `Run #${target.id} ditetapkan sebagai jadwal aktif.` : `Run #${target.id} berhasil dipublikasikan.`;
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
    <Head title="Review Hasil Jadwal GA" />
    <AppLayout>
        <div class="mx-auto w-full max-w-7xl space-y-10 p-6 md:p-8">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Review &amp; tetapkan hasil jadwal GA</h1>
                    <p class="mt-1 text-sm text-gray-600">Tinjau hasil run, cek pelanggaran, lalu aktifkan dan publikasikan jadwal.</p>
                </div>
                <label class="text-sm font-medium text-gray-700">
                    Periode
                    <select v-model="period" class="mt-1 block min-w-56 rounded-xl border-gray-200 bg-white text-sm focus:border-primary focus:ring-primary">
                        <option v-for="item in periods" :key="item.id" :value="item.code">{{ item.label }}</option>
                    </select>
                </label>
            </header>

            <div class="space-y-3">
                <Alert v-if="periodError" :title="periodError.message" />
                <Alert v-if="timeSlotError" :title="timeSlotError.message" />
                <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />
                <Alert v-if="runError" :title="runError.message" />
                <Alert v-if="actionError" :title="actionError" />
            </div>

            <p class="flex flex-wrap items-baseline gap-x-8 gap-y-2 border-y border-gray-100 py-4 text-sm text-gray-600">
                <span>
                    Jadwal aktif
                    <strong class="ml-1 text-base font-semibold text-gray-900">{{ activeRun ? `Run #${activeRun.id}` : 'belum ditetapkan' }}</strong>
                    <span v-if="activeRun" class="ml-1">· {{ activeRun.published_at ? 'sudah dipublikasikan' : 'belum dipublikasikan' }}</span>
                </span>
                <span>
                    Fitness terbaik
                    <strong class="ml-1 text-base font-semibold tabular-nums text-gray-900">{{ bestRun?.best_fitness?.toFixed(3) ?? '—' }}</strong>
                    <span v-if="bestRun" class="ml-1">(Run #{{ bestRun.id }})</span>
                </span>
                <span>
                    <strong class="text-base font-semibold tabular-nums text-gray-900">{{ pagination.total }}</strong> run di periode ini
                </span>
            </p>

            <section>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900">Daftar hasil run</h2>
                    <span v-if="loadingRuns" class="text-sm text-gray-500">Memuat…</span>
                </div>
                <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white">
                    <table class="w-full min-w-[900px] border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-xs font-medium text-gray-500">
                                <th class="px-4 py-3 font-medium">Run</th>
                                <th class="px-4 py-3 font-medium">Fitness</th>
                                <th class="px-4 py-3 font-medium">HC / SC</th>
                                <th class="px-4 py-3 font-medium">Tanpa ruang</th>
                                <th class="px-4 py-3 font-medium">Parameter GA</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700" :class="{ 'opacity-60': loadingRuns }">
                            <tr v-if="!loadingRuns && runs.length === 0">
                                <td colspan="7" class="p-8 text-center text-gray-500">Belum ada hasil run untuk periode ini.</td>
                            </tr>
                            <tr v-for="run in runs" :key="run.id" :class="run.id === selectedRunId ? 'bg-primary/5' : ''">
                                <td class="px-4 py-3">
                                    <button class="font-semibold text-primary hover:underline" @click="selectedRunId = run.id">Run #{{ run.id }}</button>
                                    <span class="block text-xs text-gray-500">{{ run.finished_at ? new Date(run.finished_at).toLocaleString('id-ID') : run.status }}</span>
                                </td>
                                <td class="px-4 py-3 tabular-nums">
                                    <span class="font-semibold text-gray-900">{{ run.best_fitness?.toFixed(3) ?? '—' }}</span>
                                    <span class="block text-xs text-gray-500">{{ run.fitness_percent ?? 0 }}%</span>
                                </td>
                                <td class="px-4 py-3 tabular-nums">{{ run.hc_violations }} / {{ run.sc_unmet }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ run.unassigned_rooms }}</td>
                                <td class="px-4 py-3 text-xs text-gray-600">
                                    {{ run.selection_operator ?? '—' }} · Cr {{ run.crossover_rate ?? '—' }} · Mr {{ run.mutation_rate ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-2">
                                        <span class="size-2 rounded-full" :class="stateDot(run)" aria-hidden="true" />
                                        {{ stateLabel(run) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-4">
                                        <button
                                            v-if="!compareBatchId || compareBatchId !== run.batch_id"
                                            class="text-sm text-gray-500 hover:text-gray-900 hover:underline"
                                            @click="loadCompare(run.batch_id)"
                                        >Bandingkan batch</button>
                                        <Button v-if="run.status === 'done' && !run.is_active" @click="confirm('activate', run)">Tetapkan aktif</Button>
                                        <Button v-else-if="run.is_active && !run.published_at" @click="confirm('publish', run)">Publikasikan</Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <Pagination v-model:page="page" :pagination="pagination" label="run" />
                </div>
            </section>

            <section v-if="compareBatchId">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900">Perbandingan run dalam batch</h2>
                    <button class="text-sm text-gray-500 hover:text-gray-900 hover:underline" @click="compareBatchId = null">Tutup</button>
                </div>
                <p v-if="compareLoading" class="text-sm text-gray-500">Memuat perbandingan…</p>
                <div v-else class="overflow-x-auto rounded-xl border border-gray-100 bg-white">
                    <table class="w-full min-w-[760px] border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-xs text-gray-500">
                                <th class="px-4 py-3 font-medium">Run</th>
                                <th class="px-4 py-3 font-medium">Operator</th>
                                <th class="px-4 py-3 font-medium">Cr / Mr</th>
                                <th class="px-4 py-3 font-medium">Seed</th>
                                <th class="px-4 py-3 font-medium">Fitness</th>
                                <th class="px-4 py-3 font-medium">HC</th>
                                <th class="px-4 py-3 font-medium">SC</th>
                                <th class="px-4 py-3 font-medium">Tanpa ruang</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 tabular-nums">
                            <tr v-for="run in [...compareRuns].sort((a, b) => (b.best_fitness ?? -1) - (a.best_fitness ?? -1))" :key="run.id" :class="run.id === bestRun?.id ? 'bg-emerald-50/60' : ''">
                                <td class="px-4 py-3 font-semibold">#{{ run.id }}<span v-if="run.id === bestRun?.id" class="ml-2 text-xs font-normal text-emerald-700">terbaik</span></td>
                                <td class="px-4 py-3">{{ run.selection_operator ?? '—' }}</td>
                                <td class="px-4 py-3">{{ run.crossover_rate ?? '—' }} / {{ run.mutation_rate ?? '—' }}</td>
                                <td class="px-4 py-3">{{ run.seed ?? '—' }}</td>
                                <td class="px-4 py-3 font-semibold">{{ run.best_fitness?.toFixed(3) ?? '—' }}</td>
                                <td class="px-4 py-3">{{ run.hc_violations }}</td>
                                <td class="px-4 py-3">{{ run.sc_unmet }}</td>
                                <td class="px-4 py-3">{{ run.unassigned_rooms }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="selectedRun" class="space-y-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Detail Run #{{ selectedRun.id }}</h2>
                        <p class="mt-1 text-sm text-gray-600">{{ selectedRun.faculty?.code }} · {{ selectedRun.period }} · {{ selectedRun.schedule_details_count }} jadwal</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <select v-model="programFilter" class="rounded-lg border-gray-200 text-sm" aria-label="Filter prodi">
                            <option value="">Semua prodi</option>
                            <option v-for="program in programs" :key="program.id" :value="String(program.id)">{{ program.code }} · {{ program.name }}</option>
                        </select>
                        <select v-model="category" class="rounded-lg border-gray-200 text-sm" aria-label="Filter pelanggaran">
                            <option value="">Semua pelanggaran</option>
                            <option value="HC">Hard constraint</option>
                            <option value="SC">Soft constraint</option>
                        </select>
                    </div>
                </div>

                <Alert v-if="detailError" :title="detailError.message" />
                <p v-if="detailLoading" class="py-8 text-center text-sm text-gray-500">Memuat detail run…</p>

                <template v-else>
                    <dl class="flex flex-wrap gap-x-10 gap-y-3 text-sm text-gray-600">
                        <div><dt class="inline">Fitness terbaik</dt> <dd class="ml-1 inline text-base font-semibold tabular-nums text-gray-900">{{ selectedRun.best_fitness?.toFixed(3) ?? '—' }}</dd></div>
                        <div><dt class="inline">Generasi terbaik / akhir</dt> <dd class="ml-1 inline text-base font-semibold tabular-nums text-gray-900">{{ selectedRun.best_generation ?? '—' }} / {{ selectedRun.final_generation }}</dd></div>
                        <div><dt class="inline">Seed</dt> <dd class="ml-1 inline text-base font-semibold tabular-nums text-gray-900">{{ selectedRun.seed ?? '—' }}</dd></div>
                    </dl>

                    <div class="flex flex-wrap gap-1 border-b border-gray-100" role="tablist" aria-label="Filter hari">
                        <button
                            role="tab"
                            :aria-selected="dayFilter === ''"
                            class="-mb-px border-b-2 px-3 py-2 text-sm"
                            :class="dayFilter === '' ? 'border-primary font-semibold text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                            @click="dayFilter = ''"
                        >Semua hari</button>
                        <button
                            v-for="day in days"
                            :key="day"
                            role="tab"
                            :aria-selected="dayFilter === String(day)"
                            class="-mb-px border-b-2 px-3 py-2 text-sm"
                            :class="dayFilter === String(day) ? 'border-primary font-semibold text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                            @click="dayFilter = String(day)"
                        >{{ DAYS[day] }}</button>
                    </div>

                    <!-- Grid jadwal -->
                    <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white">
                        <table class="w-full min-w-[1100px] border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50/60 text-xs text-gray-500">
                                    <th class="w-36 px-5 py-4 text-left font-medium">Sesi</th>
                                    <th
                                        v-for="day in (dayFilter ? days.filter((d) => String(d) === dayFilter) : days)"
                                        :key="day"
                                        class="min-w-60 border-l border-gray-200 px-5 py-4 text-left font-medium"
                                    >{{ DAYS[day] }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y-2 divide-gray-300">
                                <tr v-for="session in sessions" :key="session" class="align-top">
                                    <th class="whitespace-nowrap px-5 py-5 text-left font-medium text-gray-900">
                                        Sesi {{ session }}
                                        <span class="mt-1 block text-xs font-normal text-gray-500">{{ timeForSession(session) }}</span>
                                    </th>
                                    <td
                                        v-for="day in (dayFilter ? days.filter((d) => String(d) === dayFilter) : days)"
                                        :key="day"
                                        class="border-l border-gray-200 px-5 py-5"
                                    >
                                        <p v-if="!detailsByCell.get(`${day}-${session}`)?.length" class="text-gray-300" aria-label="Kosong">—</p>
                                        <ul v-else class="divide-y divide-gray-300">
                                            <li
                                                v-for="detail in detailsByCell.get(`${day}-${session}`)"
                                                :key="detail.id"
                                                class="py-5 first:pt-0 last:pb-0"
                                                :title="detail.course.code"
                                            >
                                                <div class="border-l-2 pl-3" :class="detail.violations_count ? 'border-red-400' : 'border-transparent'">
                                                    <p class="font-medium leading-snug text-gray-900">{{ detail.course.name }}</p>
                                                    <p class="mt-1 text-xs text-gray-500">
                                                        {{ detail.course.code }} · Kelas {{ classLabel(detail.class_number) }}
                                                    </p>
                                                    <p class="mt-2 text-xs leading-relaxed text-gray-600">
                                                        {{ detail.lecturer.name }}
                                                        <span class="text-gray-300"> / </span>
                                                        {{ detail.room?.code ?? 'tanpa ruang' }}
                                                    </p>
                                                    <p v-if="detail.violations_count" class="mt-2 text-xs font-medium text-red-700">
                                                        {{ detail.violations_count }} pelanggaran
                                                    </p>
                                                    <p v-if="detail.is_overridden" class="mt-1 text-xs text-amber-700">Diubah manual</p>
                                                </div>
                                            </li>
                                        </ul>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Pelanggaran <span class="font-normal tabular-nums text-gray-500">({{ violations.length }})</span></h3>
                        <ul v-if="violations.length" class="mt-3 divide-y divide-gray-100 rounded-xl border border-gray-100 bg-white">
                            <li v-for="violation in violations" :key="violation.id" class="px-4 py-3 text-sm">
                                <p class="flex flex-wrap items-baseline gap-x-3">
                                    <span class="font-mono font-semibold text-gray-900">{{ violation.constraint_code }}</span>
                                    <span class="text-xs text-gray-500">{{ violation.category }} · penalti {{ violation.penalty }}</span>
                                </p>
                                <p class="mt-1 text-gray-700">{{ violation.message ?? 'Tidak ada rincian pesan.' }}</p>
                            </li>
                        </ul>
                        <p v-else class="mt-2 text-sm text-gray-500">Tidak ada pelanggaran untuk filter ini.</p>
                    </div>

                    <div v-if="selectedRun.fitness_history.length > 1">
                        <h3 class="text-base font-semibold text-gray-900">Riwayat fitness</h3>
                        <svg viewBox="0 0 300 42" class="mt-3 h-20 w-full max-w-xl overflow-visible text-primary" preserveAspectRatio="none" role="img" :aria-label="`Fitness naik dari ${selectedRun.fitness_history[0]} ke ${selectedRun.fitness_history[selectedRun.fitness_history.length - 1]} selama ${selectedRun.fitness_history.length} generasi`">
                            <polyline :points="sparkPoints" fill="none" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke" stroke-linejoin="round" />
                        </svg>
                    </div>
                </template>
            </section>
        </div>

        <ConfirmDialog
            :show="pendingAction !== null"
            :title="pendingAction === 'activate' ? 'Tetapkan run sebagai jadwal aktif?' : 'Publikasikan jadwal ini?'"
            :confirm-label="pendingAction === 'activate' ? 'Tetapkan aktif' : 'Publikasikan'"
            :danger="false"
            :processing="processing"
            :error="actionError"
            @confirm="performAction"
            @close="pendingAction = null; actionTarget = null"
        >
            <template v-if="pendingAction === 'activate'">
                Jadwal aktif yang lama akan digantikan. Run baru belum langsung terlihat mahasiswa dan dosen sampai dipublikasikan.
                <p v-if="actionTarget && actionTarget.hc_violations > 0" class="mt-2 font-semibold text-amber-700">Peringatan: run ini memiliki {{ actionTarget.hc_violations }} pelanggaran hard constraint. Server tetap mengizinkan aktivasi.</p>
            </template>
            <template v-else>Setelah dipublikasikan, dosen dan mahasiswa dapat melihat jadwal ini.</template>
        </ConfirmDialog>
    </AppLayout>
</template>

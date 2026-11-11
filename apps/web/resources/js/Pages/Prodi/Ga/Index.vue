<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import ScheduleOverrideModal from '@/Components/scheduling/ScheduleOverrideModal.vue';
import SubmissionStatusBadge from '@/Components/scheduling/SubmissionStatusBadge.vue';
import { useCollection } from '@/composables/useApiList';
import { useProgramSubmission } from '@/composables/useProgramSubmission';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import { api, toApiError, type ApiError } from '@/lib/api';
import { DOWNLOAD_APP_ITEM } from '@/lib/navigation';
import { formatDateTime, formatTime, SUBMISSION_PROBLEM_LABELS } from '@/lib/labels';
import { programTone } from '@/lib/programTone';
import type { AcademicPeriod, Collection, Paginated, Pagination as PaginationMeta, RunScheduleDetail, SchedulingRunDetail, SchedulingRunSummary, SubmissionProblem, TimeSlot, Violation } from '@/types/models';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

/**
 * POS 040/IT10.1/OT.07/2026 step 2 and 8: the program runs the GA in the desktop app inside the
 * room slots its faculty gave it, compares the runs, picks one, adjusts it by hand, then hands
 * the schedule to the faculty. The faculty publishes it.
 */
type ConfirmAction = 'activate' | 'submit' | 'withdraw';

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
const dayFilter = ref('');
const detailLoading = ref(false);
const detailError = ref<ApiError | null>(null);
const actionError = ref<string | null>(null);
const flash = ref<string | null>(null);
const pendingAction = ref<ConfirmAction | null>(null);
const actionTarget = ref<SchedulingRunSummary | null>(null);
const processing = ref(false);
const compareLoading = ref(false);
const selectedComparisonIds = ref<number[]>([]);
const comparedDetails = ref<SchedulingRunDetail[]>([]);
const comparisonLoading = ref(false);
const comparisonError = ref<string | null>(null);
const comparisonSection = ref<HTMLElement | null>(null);
const comparisonMode = ref<'selected' | 'batch'>('selected');
let comparisonRequestId = 0;
const overrideTarget = ref<RunScheduleDetail | null>(null);
let listRequestId = 0;

const user = computed(() => usePage().props.auth.user);
const programId = computed(() => user.value.study_program_id);
const { submission, editable, problemCount, processing: stepProcessing, step } = useProgramSubmission(programId, period);
const canOverride = computed(() => selectedRun.value?.is_active === true && editable.value);
const problems = computed(() => Object.entries(submission.value?.problems ?? {}).filter(([, count]) => (count ?? 0) > 0) as [SubmissionProblem, number][]);
const days = computed(() => [...new Set(timeSlots.value.map((slot) => slot.day))].sort((a, b) => a - b));
const sessions = computed(() => [...new Set(timeSlots.value.map((slot) => slot.session))].sort((a, b) => a - b));
const visibleDetails = computed(() => details.value.filter((detail) => !dayFilter.value || detail.time_slot.day === Number(dayFilter.value)));
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
const comparisonBatchRun = computed(() => runs.value.find((run) => run.id === selectedRunId.value) ?? runs.value[0] ?? null);
const bestComparedRun = computed(() => [...comparedDetails.value]
    .filter((run) => run.best_fitness !== null)
    .sort((a, b) => (b.best_fitness ?? 0) - (a.best_fitness ?? 0))[0] ?? null);

function toggleComparison(id: number): void {
    comparisonRequestId++;
    comparedDetails.value = [];
    comparisonError.value = null;
    comparisonLoading.value = false;
    compareLoading.value = false;
    selectedComparisonIds.value = selectedComparisonIds.value.includes(id)
        ? selectedComparisonIds.value.filter((selected) => selected !== id)
        : [...selectedComparisonIds.value, id];
}

async function compareSelected(): Promise<void> {
    if (selectedComparisonIds.value.length < 2) return;
    const requestId = ++comparisonRequestId;
    const ids = [...selectedComparisonIds.value];
    comparisonMode.value = 'selected';
    compareLoading.value = false;
    comparisonLoading.value = true;
    comparisonError.value = null;
    comparedDetails.value = [];
    await nextTick();
    if (requestId !== comparisonRequestId) return;
    comparisonSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    try {
        const responses = await Promise.all(ids.map((id) => api.get<{ data: SchedulingRunDetail }>(`scheduling-runs/${id}`)));
        if (requestId === comparisonRequestId) comparedDetails.value = responses.map((response) => response.data.data);
    } catch (error) {
        if (requestId === comparisonRequestId) comparisonError.value = toApiError(error).message;
    } finally {
        if (requestId === comparisonRequestId) comparisonLoading.value = false;
    }
}

function classLabel(value: number): string {
    return value > 0 && value <= 26 ? String.fromCharCode(64 + value) : String(value);
}

function timeForSession(session: number): string {
    const slot = timeSlots.value.find((item) => item.session === session);
    return slot ? `${formatTime(slot.start_time)}–${formatTime(slot.end_time)}` : '';
}

function stateLabel(run: SchedulingRunSummary): string {
    if (run.is_active && run.published_at) return 'Dipublikasikan';
    if (run.is_active) return 'Jadwal aktif';
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
    const requestId = ++comparisonRequestId;
    const requestedPeriod = period.value;
    comparisonMode.value = 'batch';
    compareLoading.value = true;
    comparisonLoading.value = true;
    comparisonError.value = null;
    comparedDetails.value = [];
    await nextTick();
    if (requestId !== comparisonRequestId) return;
    comparisonSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    try {
        const first = await api.get<Paginated<SchedulingRunSummary>>('scheduling-runs', {
            params: { period: requestedPeriod, batch_id: batchId, per_page: 100, page: 1 },
        });
        const remaining = await Promise.all(Array.from({ length: Math.max(0, first.data.meta.pagination.last_page - 1) }, (_, index) =>
            api.get<Paginated<SchedulingRunSummary>>('scheduling-runs', {
                params: { period: requestedPeriod, batch_id: batchId, per_page: 100, page: index + 2 },
            })));
        const batch = [first, ...remaining].flatMap((response) => response.data.data);
        if (!batch.length) {
            if (requestId === comparisonRequestId) comparisonError.value = 'Tidak ada run dalam batch ini.';
            return;
        }
        const responses = await Promise.all(batch.map((run) => api.get<{ data: SchedulingRunDetail }>(`scheduling-runs/${run.id}`)));
        if (requestId !== comparisonRequestId) return;
        comparedDetails.value = responses.map((response) => response.data.data);
    } catch (error) {
        if (requestId === comparisonRequestId) comparisonError.value = toApiError(error).message;
    } finally {
        if (requestId === comparisonRequestId) {
            compareLoading.value = false;
            comparisonLoading.value = false;
        }
    }
}

function confirm(action: ConfirmAction, run: SchedulingRunSummary): void {
    pendingAction.value = action;
    actionTarget.value = run;
    actionError.value = null;
}

function confirmStep(action: 'submit' | 'withdraw'): void {
    pendingAction.value = action;
    actionTarget.value = null;
    actionError.value = null;
}

async function performAction(): Promise<void> {
    const action = pendingAction.value;
    if (!action) return;
    actionError.value = null;
    if (action !== 'activate') {
        const failure = await step(action);
        if (failure) {
            actionError.value = failure.message;
            return;
        }
        flash.value = action === 'submit' ? 'Jadwal diserahkan ke fakultas.' : 'Jadwal ditarik kembali dan dapat diubah lagi.';
        pendingAction.value = null;
        return;
    }
    const target = actionTarget.value;
    if (!target) return;
    processing.value = true;
    try {
        const response = await api.post<{ data: SchedulingRunDetail }>(`scheduling-runs/${target.id}/activate`);
        flash.value = `Run #${target.id} ditetapkan sebagai jadwal program studi.`;
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

// Violations of the moved entry are dropped server-side, so reload the run instead of patching one row.
function overrideSaved(): void {
    overrideTarget.value = null;
    flash.value = 'Jadwal berhasil diubah.';
    void loadRuns();
    void loadSelectedRun();
}

watch(periods, (items) => {
    if (!period.value && items.length) {
        period.value = items.find((item) => item.is_current)?.code ?? items[0].code;
    }
}, { immediate: true });
watch(period, () => {
    page.value = 1;
    comparisonRequestId++;
    selectedComparisonIds.value = [];
    comparedDetails.value = [];
    comparisonLoading.value = false;
    compareLoading.value = false;
    comparisonError.value = null;
    void loadRuns();
});
watch(page, () => void loadRuns());
watch(selectedRunId, () => {
    dayFilter.value = '';
    category.value = '';
    void loadSelectedRun();
});
watch([dayFilter, category], () => void loadSelectedRun());
onResourcesChanged(['scheduling_runs', 'schedule_details', 'violation_logs'], () => {
    void loadRuns();
    void loadSelectedRun();
});
</script>

<template>
    <Head title="Proses GA Program Studi" />
    <AppLayout>
        <div class="space-y-10">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Proses GA Program Studi</h1>
                    <p class="mt-1 max-w-3xl text-sm text-gray-600">
                        Jalankan GA di <a :href="DOWNLOAD_APP_ITEM.href" target="_blank" rel="noopener" class="font-medium text-primary hover:underline">aplikasi desktop</a>
                        di dalam slot ruangan yang diberikan fakultas. Bandingkan hasil run, tetapkan satu sebagai jadwal, sesuaikan bila perlu, lalu kirim ke fakultas.
                    </p>
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
                <Alert v-if="actionError && !pendingAction" :title="actionError" />
            </div>

            <section v-if="submission" class="rounded-xl border border-gray-200 bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-1">
                        <p class="flex flex-wrap items-center gap-3 text-sm text-gray-600">
                            Status jadwal program studi
                            <SubmissionStatusBadge :status="submission.status" />
                        </p>
                        <p class="text-sm text-gray-600">
                            {{ submission.cells_count }} slot ruangan dari fakultas.
                            <Link href="/prodi/rooms" class="font-medium text-primary hover:underline">Lihat slot ruangan</Link>
                        </p>
                        <p v-if="submission.status === 'submitted'" class="text-sm text-gray-600">Diserahkan {{ formatDateTime(submission.submitted_at) }}. Menunggu publikasi oleh fakultas.</p>
                        <p v-if="submission.faculty_published" class="text-sm text-gray-600">Jadwal sudah dipublikasikan fakultas dan bersifat final.</p>
                    </div>
                    <div class="flex gap-2">
                        <Button v-if="submission.status === 'submitted'" variant="secondary" :loading="stepProcessing === 'withdraw'" @click="confirmStep('withdraw')">Tarik kembali</Button>
                        <Button
                            v-if="editable"
                            :disabled="!submission.scheduling_run?.is_active || problemCount > 0"
                            :loading="stepProcessing === 'submit'"
                            @click="confirmStep('submit')"
                        >Kirim ke fakultas</Button>
                    </div>
                </div>
                <div v-if="submission.status === 'returned' && submission.return_note" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm">
                    <p class="font-semibold text-amber-800">Dikembalikan fakultas {{ formatDateTime(submission.reviewed_at) }}</p>
                    <p class="mt-1 whitespace-pre-line text-amber-800">{{ submission.return_note }}</p>
                </div>
                <div v-if="editable && problems.length" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-semibold">Jadwal aktif belum bisa dikirim</p>
                    <ul class="mt-1 list-disc pl-5">
                        <li v-for="[key, count] in problems" :key="key">{{ count }} {{ SUBMISSION_PROBLEM_LABELS[key] }}</li>
                    </ul>
                    <p class="mt-1">Pindahkan atau tukar pertemuan tersebut, atau jalankan GA lagi.</p>
                </div>
                <p v-else-if="editable && !submission.scheduling_run?.is_active" class="mt-4 text-sm text-gray-500">Tetapkan satu run sebagai jadwal sebelum mengirim ke fakultas.</p>
            </section>

            <p class="flex flex-wrap items-baseline gap-x-8 gap-y-2 border-y border-gray-100 py-4 text-sm text-gray-600">
                <span>
                    Jadwal program studi
                    <strong class="ml-1 text-base font-semibold text-gray-900">{{ activeRun ? `Run #${activeRun.id}` : 'belum ditetapkan' }}</strong>
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
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Daftar hasil run</h2>
                        <p class="mt-1 text-sm text-gray-500">Pilih minimal dua run untuk membandingkan hasilnya.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span v-if="loadingRuns" class="text-sm text-gray-500">Memuat…</span>
                        <Button variant="secondary" :disabled="!comparisonBatchRun" :loading="compareLoading" :title="comparisonBatchRun ? `Bandingkan semua run dalam batch Run #${comparisonBatchRun.id}` : 'Belum ada run'" @click="comparisonBatchRun && loadCompare(comparisonBatchRun.batch_id)">Bandingkan batch</Button>
                        <span class="text-sm text-gray-600">{{ selectedComparisonIds.length }} dipilih</span>
                        <Button :disabled="selectedComparisonIds.length < 2" :loading="comparisonLoading" @click="compareSelected">Bandingkan run</Button>
                    </div>
                </div>
                <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white">
                    <table class="w-full min-w-[900px] border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-xs font-medium text-gray-500">
                                <th class="px-4 py-3 font-medium"><span class="sr-only">Pilih untuk perbandingan</span></th>
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
                                <td colspan="8" class="p-8 text-center text-gray-500">Belum ada hasil run untuk periode ini. Jalankan GA dari aplikasi desktop.</td>
                            </tr>
                            <tr v-for="run in runs" :key="run.id" :class="run.id === selectedRunId ? 'bg-primary/5' : ''">
                                <td class="px-4 py-3"><input type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary" :checked="selectedComparisonIds.includes(run.id)" :aria-label="`Pilih Run #${run.id} untuk perbandingan`" @change="toggleComparison(run.id)" /></td>
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
                                        <Button variant="secondary" size="sm" :disabled="compareLoading" @click="loadCompare(run.batch_id)">Bandingkan batch</Button>
                                        <span v-if="run.is_stale" class="text-xs text-amber-700" title="Data penjadwalan (slot ruangan, mata kuliah, dosen, atau jadwal TPB) berubah setelah run ini dibuat">Data sudah berubah</span>
                                        <Button v-if="run.status === 'done' && !run.is_active && editable" size="sm" @click="confirm('activate', run)">Tetapkan sebagai jadwal</Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <Pagination v-model:page="page" :pagination="pagination" label="run" />
                </div>
            </section>

            <section v-if="comparisonLoading || comparisonError || comparedDetails.length" ref="comparisonSection" class="scroll-mt-6 space-y-4 rounded-xl border border-gray-200 bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ comparisonMode === 'batch' ? 'Perbandingan fitness satu batch' : 'Perbandingan run terpilih' }}</h2>
                        <p class="mt-1 text-sm text-gray-600">Run dengan fitness tertinggi ditandai sebagai rekomendasi terbaik.</p>
                    </div>
                    <Button variant="secondary" @click="comparisonRequestId++; comparedDetails = []; comparisonError = null; comparisonLoading = false; compareLoading = false">Tutup</Button>
                </div>
                <p v-if="comparisonLoading" class="text-sm text-gray-500">Memuat perbandingan…</p>
                <Alert v-if="comparisonError" :title="comparisonError" />
                <template v-if="comparedDetails.length">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] border-collapse text-sm">
                            <thead><tr class="border-b border-gray-200 bg-gray-50 text-left text-xs text-gray-600">
                                <th scope="col" class="px-3 py-3">Run</th><th scope="col" class="px-3 py-3">Fitness terbaik</th><th scope="col" class="px-3 py-3">Fitness (%)</th><th scope="col" class="px-3 py-3">HC / SC</th><th scope="col" class="px-3 py-3">Tanpa ruang</th><th scope="col" class="px-3 py-3">Generasi terbaik / akhir</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="run in comparedDetails" :key="run.id" :class="run.id === bestComparedRun?.id ? 'bg-emerald-50/60' : ''">
                                    <th scope="row" class="px-3 py-3 text-left">
                                        Run #{{ run.id }}
                                        <span v-if="run.id === bestComparedRun?.id" class="ml-2 inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Rekomendasi terbaik</span>
                                    </th>
                                    <td class="px-3 py-3 font-semibold tabular-nums">{{ run.best_fitness?.toFixed(3) ?? '—' }}</td>
                                    <td class="px-3 py-3 tabular-nums">{{ run.fitness_percent !== null ? `${run.fitness_percent}%` : '—' }}</td>
                                    <td class="px-3 py-3 tabular-nums">{{ run.hc_violations }} / {{ run.sc_unmet }}</td>
                                    <td class="px-3 py-3 tabular-nums">{{ run.unassigned_rooms }}</td>
                                    <td class="px-3 py-3 tabular-nums">{{ run.best_generation ?? '—' }} / {{ run.final_generation }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </section>

            <section v-if="selectedRun" class="space-y-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Detail Run #{{ selectedRun.id }}</h2>
                        <p class="mt-1 text-sm text-gray-600">{{ selectedRun.study_program?.name ?? 'Program studi' }}, {{ selectedRun.schedule_details_count }} pertemuan</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
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

                    <p class="text-sm text-gray-600">
                        <template v-if="canOverride">Klik pertemuan di grid untuk memindahkannya ke slot ruangan kosong atau menukarnya dengan pertemuan lain.</template>
                        <template v-else-if="!editable">Jadwal sudah dikirim ke fakultas dan tidak dapat diubah.</template>
                        <template v-else>Jadwal hanya bisa diubah manual setelah run ini ditetapkan sebagai jadwal.</template>
                    </p>

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
                                                <component
                                                    :is="canOverride ? 'button' : 'div'"
                                                    :type="canOverride ? 'button' : undefined"
                                                    class="block w-full rounded-lg border-l-4 px-3 py-3 text-left"
                                                    :class="[
                                                        programTone(detail.course.study_program?.code ?? selectedRun.study_program?.code).card,
                                                        canOverride ? 'hover:brightness-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary' : '',
                                                    ]"
                                                    @click="canOverride && (overrideTarget = detail)"
                                                >
                                                    <p class="font-medium leading-snug text-gray-900">{{ detail.course.name }}</p>
                                                    <p class="mt-1 text-xs text-gray-500">
                                                        {{ detail.course.code }} · Kelas {{ classLabel(detail.class_number) }}
                                                    </p>
                                                    <p class="mt-2 text-xs leading-relaxed text-gray-600">
                                                        {{ detail.lecturer.name }}
                                                        <span class="text-gray-300"> / </span>
                                                        <span class="font-semibold" :class="detail.room ? 'text-gray-800' : 'text-amber-800'">{{ detail.room?.code ?? 'tanpa ruang' }}</span>
                                                    </p>
                                                    <p v-if="detail.violations_count" class="mt-2 text-xs font-medium text-red-700">
                                                        {{ detail.violations_count }} pelanggaran
                                                    </p>
                                                    <p v-if="detail.is_overridden" class="mt-1 text-xs text-amber-700">Diubah manual</p>
                                                    <span v-if="canOverride" class="mt-2 block text-[10px] font-medium text-primary">Klik untuk ubah jadwal</span>
                                                </component>
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
                        <svg viewBox="0 0 300 42" class="mt-3 h-20 w-full max-w-xl overflow-visible text-primary" preserveAspectRatio="none" role="img" :aria-label="`Riwayat fitness dari ${selectedRun.fitness_history[0]} ke ${selectedRun.fitness_history[selectedRun.fitness_history.length - 1]} selama ${selectedRun.fitness_history.length - 1} generasi`">
                            <polyline :points="sparkPoints" fill="none" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke" stroke-linejoin="round" />
                        </svg>
                    </div>
                </template>
            </section>
        </div>

        <ConfirmDialog
            :show="pendingAction !== null"
            :title="pendingAction === 'activate' ? 'Tetapkan run sebagai jadwal program studi?' : pendingAction === 'submit' ? 'Kirim jadwal ke fakultas?' : 'Tarik kembali jadwal?'"
            :confirm-label="pendingAction === 'activate' ? 'Tetapkan' : pendingAction === 'submit' ? 'Kirim' : 'Tarik kembali'"
            :danger="false"
            :processing="processing || stepProcessing !== null"
            :error="actionError"
            @confirm="performAction"
            @close="pendingAction = null; actionTarget = null; actionError = null"
        >
            <template v-if="pendingAction === 'activate'">
                Run yang ditetapkan sebelumnya akan digantikan. Server memeriksa ulang slot ruangan, bentrok dosen dengan program studi lain, dan jadwal TPB.
                <p v-if="actionTarget?.is_stale" class="mt-2 text-amber-700">Data penjadwalan sudah berubah sejak run ini dibuat. Run tetap bisa ditetapkan bila masih lolos pemeriksaan.</p>
                <p v-if="actionTarget && actionTarget.hc_violations > 0" class="mt-2 font-semibold text-amber-700">Run ini memiliki {{ actionTarget.hc_violations }} pelanggaran hard constraint.</p>
            </template>
            <template v-else-if="pendingAction === 'submit'">Setelah diserahkan, jadwal tidak dapat diubah sampai fakultas mengembalikannya atau Anda menariknya kembali sebelum fakultas mempublikasikannya.</template>
            <template v-else>Jadwal kembali menjadi draf dan dapat diubah lagi.</template>
        </ConfirmDialog>

        <ScheduleOverrideModal
            v-if="overrideTarget"
            :schedule-detail="overrideTarget"
            :show="true"
            @close="overrideTarget = null"
            @updated="overrideSaved"
        />
    </AppLayout>
</template>

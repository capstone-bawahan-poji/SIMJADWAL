<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import SubmissionStatusBadge from '@/Components/scheduling/SubmissionStatusBadge.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { useCollection } from '@/composables/useApiList';
import { usePeriodPicker } from '@/composables/usePeriodPicker';
import { useProgramSubmission } from '@/composables/useProgramSubmission';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import AppLayout from '@/Layouts/AppLayout.vue';
import { api, toApiError, type ApiError } from '@/lib/api';
import { classLabel, DAY_LABELS, formatDateTime, formatTime } from '@/lib/labels';
import { queryParam } from '@/lib/query';
import type { Collection, FacultySubmissionRow, RunScheduleDetail, SchedulePublicationState, TimeSlot } from '@/types/models';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

/**
 * POS 040/IT10.1/OT.07/2026 steps 9 and 10: the faculty reviews the schedule each program handed
 * in, accepts it or returns it with a note, then publishes every program at once. Final.
 */
const facultyId = computed(() => usePage().props.auth.user.faculty_id);
const { periods, period, error: periodError } = usePeriodPicker();
const { items: timeSlots } = useCollection<TimeSlot>('time-slots');
const rows = ref<FacultySubmissionRow[]>([]);
const publication = ref<SchedulePublicationState | null>(null);
const loadError = ref<ApiError | null>(null);
const selectedId = ref<number | null>(Number(queryParam('program')) || null);
const details = ref<RunScheduleDetail[]>([]);
const detailsLoading = ref(false);
const detailsError = ref<ApiError | null>(null);
const flash = ref<string | null>(null);
const returnOpen = ref(false);
const returnNote = ref('');
const actionError = ref<string | null>(null);
const publishOpen = ref(false);
const publishing = ref(false);
const publishError = ref<string | null>(null);

const selectedRow = computed(() => rows.value.find((row) => row.study_program.id === selectedId.value) ?? null);
const { submission, processing, step } = useProgramSubmission(selectedId, period);
const days = computed(() => [...new Set(timeSlots.value.map((slot) => slot.day))].sort((a, b) => a - b));
const sessions = computed(() => [...new Set(timeSlots.value.map((slot) => slot.session))].sort((a, b) => a - b));
const detailsByCell = computed(() => {
    const cells = new Map<string, RunScheduleDetail[]>();
    for (const detail of details.value) {
        const key = `${detail.time_slot.day}-${detail.time_slot.session}`;
        cells.set(key, [...(cells.get(key) ?? []), detail]);
    }
    return cells;
});
const violationCount = computed(() => details.value.reduce((sum, detail) => sum + detail.violations_count, 0));
const canReview = computed(() => !publication.value?.published && ['submitted', 'accepted'].includes(submission.value?.status ?? ''));

function timeForSession(session: number): string {
    const slot = timeSlots.value.find((item) => item.session === session);
    return slot ? `${formatTime(slot.start_time)}–${formatTime(slot.end_time)}` : '';
}

async function load(): Promise<void> {
    if (!facultyId.value || !period.value) return;
    try {
        const [rowsResponse, publicationResponse] = await Promise.all([
            api.get<Collection<FacultySubmissionRow>>(`faculties/${facultyId.value}/schedule-submissions`, { params: { period: period.value } }),
            api.get<{ data: SchedulePublicationState }>(`faculties/${facultyId.value}/schedule-publication`, { params: { period: period.value } }),
        ]);
        rows.value = rowsResponse.data.data;
        publication.value = publicationResponse.data.data;
        loadError.value = null;
        if (!selectedRow.value) selectedId.value = rows.value.find((row) => row.status === 'submitted')?.study_program.id ?? rows.value[0]?.study_program.id ?? null;
    } catch (error) {
        loadError.value = toApiError(error);
    }
}

async function loadDetails(): Promise<void> {
    const runId = selectedRow.value?.scheduling_run_id;
    details.value = [];
    detailsError.value = null;
    if (!runId || selectedRow.value?.status === 'draft' || selectedRow.value?.status === 'returned') return;
    detailsLoading.value = true;
    try {
        const response = await api.get<Collection<RunScheduleDetail>>(`scheduling-runs/${runId}/schedule-details`);
        details.value = response.data.data;
    } catch (error) {
        detailsError.value = toApiError(error);
    } finally {
        detailsLoading.value = false;
    }
}

async function accept(): Promise<void> {
    actionError.value = null;
    const failure = await step('accept');
    if (failure) actionError.value = failure.message;
    else flash.value = `Jadwal ${selectedRow.value?.study_program.code} diterima.`;
    await load();
}

async function sendBack(): Promise<void> {
    actionError.value = null;
    const failure = await step('return', returnNote.value.trim());
    if (failure) {
        actionError.value = failure.message;
        return;
    }
    flash.value = `Jadwal ${selectedRow.value?.study_program.code} dikembalikan ke program studi.`;
    returnOpen.value = false;
    returnNote.value = '';
    await load();
}

async function publish(): Promise<void> {
    publishing.value = true;
    publishError.value = null;
    try {
        const response = await api.post<{ data: SchedulePublicationState }>(`faculties/${facultyId.value}/schedule-publication`, {}, { params: { period: period.value } });
        publication.value = response.data.data;
        publishOpen.value = false;
        flash.value = 'Jadwal fakultas dipublikasikan. Dosen dan mahasiswa kini dapat melihatnya.';
        await load();
    } catch (error) {
        publishError.value = toApiError(error).message;
    } finally {
        publishing.value = false;
    }
}

watch(period, () => void load(), { immediate: true });
watch(() => [selectedRow.value?.scheduling_run_id, selectedRow.value?.status], () => void loadDetails(), { immediate: true });
onResourcesChanged(['program_schedule_submissions', 'schedule_publications', 'room_slot_allocations', 'slot_requests'], () => void load());
onResourcesChanged(['schedule_details'], () => void loadDetails());
</script>

<template>
    <Head title="Review & Publikasi" />
    <AppLayout>
        <div class="mx-auto w-full max-w-[1400px] space-y-6 p-6 md:p-8">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Review &amp; Publikasi</h1>
                    <p class="mt-1 max-w-3xl text-sm text-gray-600">
                        Tinjau jadwal yang dikirim setiap program studi. Terima bila sudah sesuai, atau kembalikan dengan catatan.
                        Setelah semua diterima, publikasikan jadwal fakultas.
                    </p>
                </div>
                <label class="text-sm font-medium text-gray-700">
                    Periode
                    <select v-model="period" class="mt-1 block min-w-56 rounded-lg border-gray-200 bg-white text-sm focus:border-primary focus:ring-primary">
                        <option v-for="item in periods" :key="item.id" :value="item.code">{{ item.label }}</option>
                    </select>
                </label>
            </header>

            <div class="space-y-3">
                <Alert v-if="periodError" :title="periodError.message" />
                <Alert v-if="loadError" :title="loadError.message" />
                <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />
            </div>

            <section v-if="publication" class="rounded-xl border p-5" :class="publication.published ? 'border-emerald-200 bg-emerald-50' : 'border-gray-200 bg-white'">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-1 text-sm">
                        <h2 class="text-base font-semibold text-gray-900">Publikasi jadwal fakultas</h2>
                        <template v-if="publication.published">
                            <p class="text-emerald-800">
                                Dipublikasikan {{ formatDateTime(publication.published_at) }} oleh {{ publication.published_by?.name ?? '-' }}. Jadwal bersifat final.
                            </p>
                            <p class="text-gray-600">
                                Sistem kampus lain dapat membaca jadwal ini melalui
                                <code class="rounded bg-white px-1 font-mono text-xs">GET /api/v1/published-schedules?faculty_code={{ publication.faculty.code }}</code>.
                            </p>
                        </template>
                        <template v-else-if="publication.waiting_programs.length">
                            <p class="text-gray-600">Menunggu {{ publication.waiting_programs.length }} program studi diterima:</p>
                            <p class="flex flex-wrap gap-2">
                                <span v-for="program in publication.waiting_programs" :key="program.id" class="inline-flex items-center gap-1.5 text-gray-700">
                                    {{ program.code }} <SubmissionStatusBadge :status="program.status" />
                                </span>
                            </p>
                        </template>
                        <p v-else class="text-gray-600">Semua program studi sudah diterima.</p>
                    </div>
                    <Button v-if="!publication.published" :disabled="!publication.can_publish" @click="publishOpen = true">Publikasikan jadwal fakultas</Button>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
                <aside>
                    <h2 class="mb-3 text-base font-semibold text-gray-900">Program studi</h2>
                    <ul class="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white">
                        <li v-if="!rows.length" class="px-4 py-6 text-center text-sm text-gray-500">Belum ada program studi.</li>
                        <li v-for="row in rows" :key="row.study_program.id">
                            <button
                                type="button"
                                class="block w-full px-4 py-3 text-left text-sm transition-colors hover:bg-gray-50"
                                :class="row.study_program.id === selectedId ? 'bg-primary/5' : ''"
                                :aria-pressed="row.study_program.id === selectedId"
                                @click="selectedId = row.study_program.id"
                            >
                                <span class="flex items-center justify-between gap-2">
                                    <span class="font-semibold text-gray-900">{{ row.study_program.code }}</span>
                                    <SubmissionStatusBadge :status="row.status" />
                                </span>
                                <span class="mt-0.5 block truncate text-gray-600">{{ row.study_program.name }}</span>
                                <span class="mt-1 block text-xs text-gray-500">
                                    {{ row.cells_count }} slot ruangan<template v-if="row.submitted_at">, dikirim {{ formatDateTime(row.submitted_at) }}</template>
                                </span>
                                <span v-if="row.has_pending_slot_request" class="mt-1 block text-xs text-amber-700">Ada permintaan tambahan slot</span>
                            </button>
                        </li>
                    </ul>
                </aside>

                <section v-if="selectedRow" class="space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ selectedRow.study_program.code }} {{ selectedRow.study_program.name }}</h2>
                            <p class="mt-1 text-sm text-gray-600">
                                <template v-if="selectedRow.scheduling_run_id">Run #{{ selectedRow.scheduling_run_id }}, fitness {{ selectedRow.best_fitness?.toFixed(3) ?? '-' }}</template>
                                <template v-else>Belum ada run yang ditetapkan.</template>
                            </p>
                        </div>
                        <div v-if="canReview" class="flex gap-2">
                            <Button variant="secondary" :disabled="processing !== null" @click="returnOpen = true; actionError = null">Kembalikan</Button>
                            <Button v-if="submission?.status === 'submitted'" :loading="processing === 'accept'" @click="accept">Terima</Button>
                        </div>
                    </div>

                    <Alert v-if="actionError && !returnOpen" :title="actionError" />
                    <Alert v-if="selectedRow.status === 'returned' && selectedRow.return_note" variant="warning" title="Dikembalikan ke program studi">{{ selectedRow.return_note }}</Alert>
                    <p v-if="selectedRow.status === 'draft' || selectedRow.status === 'returned'" class="rounded-xl border border-gray-200 bg-white px-4 py-8 text-center text-sm text-gray-500">
                        Program studi belum mengirim jadwal. Jadwal tampil di sini setelah dikirim.
                    </p>
                    <template v-else>
                        <Alert v-if="detailsError" :title="detailsError.message" />
                        <p v-if="detailsLoading" class="py-8 text-center text-sm text-gray-500">Memuat jadwal…</p>
                        <template v-else>
                            <p class="text-sm text-gray-600">{{ details.length }} pertemuan, {{ violationCount }} pelanggaran tercatat dari GA.</p>
                            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                                <table class="w-full min-w-[960px] border-collapse text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-200 bg-gray-50/60 text-xs text-gray-500">
                                            <th class="w-32 px-4 py-3 text-left font-medium">Sesi</th>
                                            <th v-for="day in days" :key="day" class="min-w-48 border-l border-gray-200 px-4 py-3 text-left font-medium">{{ DAY_LABELS[day] }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr v-for="session in sessions" :key="session" class="align-top">
                                            <th class="whitespace-nowrap px-4 py-4 text-left font-medium text-gray-900">
                                                Sesi {{ session }}
                                                <span class="mt-1 block text-xs font-normal text-gray-500">{{ timeForSession(session) }}</span>
                                            </th>
                                            <td v-for="day in days" :key="day" class="border-l border-gray-200 px-4 py-4">
                                                <p v-if="!detailsByCell.get(`${day}-${session}`)?.length" class="text-gray-300" aria-label="Kosong">-</p>
                                                <ul v-else class="space-y-3">
                                                    <li
                                                        v-for="detail in detailsByCell.get(`${day}-${session}`)"
                                                        :key="detail.id"
                                                        class="border-l-2 pl-3"
                                                        :class="detail.violations_count ? 'border-red-400' : detail.is_overridden ? 'border-amber-400' : 'border-transparent'"
                                                    >
                                                        <p class="font-medium leading-snug text-gray-900">{{ detail.course.name }}</p>
                                                        <p class="text-xs text-gray-500">{{ detail.course.code }}, kelas {{ classLabel(detail.class_number) }}</p>
                                                        <p class="text-xs text-gray-600">{{ detail.lecturer.name }}, ruang {{ detail.room?.code ?? '-' }}</p>
                                                        <p v-if="detail.is_overridden" class="text-xs text-amber-700">Diubah manual</p>
                                                    </li>
                                                </ul>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                    </template>
                </section>
            </div>
        </div>

        <Modal :show="returnOpen" max-width="md" @close="returnOpen = false">
            <div class="space-y-4 p-6">
                <h2 class="text-lg font-bold text-gray-900">Kembalikan jadwal {{ selectedRow?.study_program.code }}?</h2>
                <p class="text-sm text-gray-600">Program studi dapat mengubah jadwal lagi lalu mengirimkannya kembali. Tulis apa yang perlu diperbaiki.</p>
                <Alert v-if="actionError" :title="actionError" />
                <textarea v-model="returnNote" rows="4" maxlength="1000" class="block w-full rounded-lg border-gray-200 text-sm focus:border-primary focus:ring-primary" aria-label="Catatan pengembalian" />
                <div class="flex justify-end gap-3">
                    <Button variant="secondary" @click="returnOpen = false">Batal</Button>
                    <Button :disabled="!returnNote.trim()" :loading="processing === 'return'" @click="sendBack">Kembalikan</Button>
                </div>
            </div>
        </Modal>

        <ConfirmDialog
            :show="publishOpen"
            title="Publikasikan jadwal fakultas?"
            confirm-label="Publikasikan"
            :processing="publishing"
            :error="publishError"
            @confirm="publish"
            @close="publishOpen = false"
        >
            Jadwal semua program studi akan dipublikasikan sekaligus dan bersifat final: tidak dapat diubah lagi di sistem.
            Dosen dan mahasiswa langsung dapat melihatnya, dan sistem kampus lain dapat membacanya melalui API.
        </ConfirmDialog>

    </AppLayout>
</template>

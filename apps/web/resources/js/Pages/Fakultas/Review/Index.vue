<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import FacultyScheduleGrid from '@/Components/scheduling/FacultyScheduleGrid.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { useCollection } from '@/composables/useApiList';
import { usePeriodPicker } from '@/composables/usePeriodPicker';
import { useProgramSubmission } from '@/composables/useProgramSubmission';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import AppLayout from '@/Layouts/AppLayout.vue';
import { api, toApiError, type ApiError } from '@/lib/api';
import { formatDateTime } from '@/lib/labels';
import { queryParam } from '@/lib/query';
import type { Collection, FacultySubmissionRow, RunScheduleDetail, SchedulePublicationState, SubmissionStatus, TimeSlot } from '@/types/models';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

/**
 * POS 040/IT10.1/OT.07/2026 steps 8 to 10: each program hands its final schedule to the faculty, who
 * publishes every program at once. Final. The faculty has no acceptance step; it may only return a
 * schedule with a note when something is wrong.
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
const detailSection = ref<HTMLElement | null>(null);
let detailsRequestId = 0;
const flash = ref<string | null>(null);
const returnOpen = ref(false);
const returnNote = ref('');
const actionError = ref<string | null>(null);
const publishOpen = ref(false);
const publishing = ref(false);
const publishError = ref<string | null>(null);

const STATUS: Record<SubmissionStatus, { label: string; dot: string; text: string }> = {
    draft: { label: 'Draf', dot: 'bg-gray-400', text: 'text-gray-500' },
    submitted: { label: 'Diserahkan', dot: 'bg-green-600', text: 'text-green-700' },
    returned: { label: 'Dikembalikan', dot: 'bg-amber-500', text: 'text-amber-700' },
    published: { label: 'Dipublikasikan', dot: 'bg-primary', text: 'text-primary' },
};
const countOf = (status: SubmissionStatus) => rows.value.filter((row) => row.status === status).length;
const completedCount = computed(() => countOf('submitted') + countOf('published'));
const selectedRow = computed(() => rows.value.find((row) => row.study_program.id === selectedId.value) ?? null);
const { submission, processing, step } = useProgramSubmission(selectedId, period);
const days = computed(() => [...new Set(timeSlots.value.map((slot) => slot.day))].sort((a, b) => a - b));
const canReturn = computed(() => !publication.value?.published
    && submission.value?.study_program.id === selectedId.value
    && submission.value?.period === period.value
    && submission.value.status === 'submitted');

async function selectProgram(id: number): Promise<void> {
    selectedId.value = id;
    await nextTick();
    detailSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
    const requestId = ++detailsRequestId;
    const runId = selectedRow.value?.scheduling_run_id;
    details.value = [];
    detailsError.value = null;
    detailsLoading.value = false;
    if (!runId || selectedRow.value?.status === 'draft' || selectedRow.value?.status === 'returned') return;
    detailsLoading.value = true;
    try {
        const response = await api.get<Collection<RunScheduleDetail>>(`scheduling-runs/${runId}/schedule-details`);
        if (requestId === detailsRequestId) details.value = response.data.data;
    } catch (error) {
        if (requestId === detailsRequestId) detailsError.value = toApiError(error);
    } finally {
        if (requestId === detailsRequestId) detailsLoading.value = false;
    }
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
watch(() => [selectedId.value, selectedRow.value?.scheduling_run_id, selectedRow.value?.status], () => void loadDetails(), { immediate: true });
onResourcesChanged(['program_schedule_submissions', 'schedule_publications', 'room_slot_allocations', 'slot_requests'], () => void load());
onResourcesChanged(['schedule_details'], () => void loadDetails());
</script>

<template>
    <Head title="Jadwal Prodi & Publikasi" />
    <AppLayout>
        <div class="space-y-6">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Jadwal Prodi &amp; Publikasi</h1>
                    <p class="mt-1 max-w-3xl text-sm text-gray-600">
                        Jadwal final yang diserahkan setiap program studi. Setelah semua program menyerahkan jadwal, publikasikan jadwal fakultas.
                        Kembalikan satu jadwal dengan catatan bila ada yang salah.
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

            <section v-if="publication" class="rounded-xl border border-gray-200 bg-white p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-1 text-sm">
                        <h2 class="text-base font-semibold text-gray-900">Publikasi jadwal fakultas</h2>
                        <p class="text-gray-600"><strong class="text-base font-semibold tabular-nums text-gray-900">{{ completedCount }}</strong> dari {{ rows.length }} program studi menyerahkan jadwal</p>
                        <template v-if="publication.published">
                            <p class="text-primary">
                                Dipublikasikan {{ formatDateTime(publication.published_at) }} oleh {{ publication.published_by?.name ?? '-' }}. Jadwal bersifat final.
                            </p>
                        </template>
                    </div>
                    <Button v-if="!publication.published" :disabled="!publication.can_publish" @click="publishOpen = true">Publikasikan jadwal fakultas</Button>
                </div>
                <dl class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-600">
                    <div v-for="(meta, status) in STATUS" :key="status" class="inline-flex items-center gap-1.5">
                        <span class="size-2.5 rounded-sm" :class="meta.dot" aria-hidden="true" />
                        <dt>{{ meta.label }}:</dt>
                        <dd class="font-semibold tabular-nums text-gray-900">{{ countOf(status) }}</dd>
                    </div>
                </dl>
                <p v-if="!publication.published && !publication.can_publish" class="mt-3 text-sm text-gray-600">Publikasi tersedia setelah semua program studi menyerahkan jadwal.</p>
            </section>

            <div class="space-y-6">
                <section>
                    <h2 class="mb-3 text-base font-semibold text-gray-900">Program studi</h2>
                    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                        <table class="w-full min-w-[560px] text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-primary/5 text-xs text-gray-600">
                                    <th scope="col" class="py-3 pl-5 pr-3 text-left font-medium">Program studi</th>
                                    <th scope="col" class="px-3 py-3 text-center font-medium">Slot ruangan</th>
                                    <th scope="col" class="px-3 py-3 text-center font-medium">Status</th>
                                    <th scope="col" class="py-3 pl-3 pr-5"><span class="sr-only">Aksi</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!rows.length"><td colspan="4" class="px-4 py-6 text-center text-gray-500">Belum ada program studi.</td></tr>
                                <tr v-for="row in rows" :key="row.study_program.id" class="cursor-pointer border-b border-gray-100 last:border-0 hover:bg-gray-50" :class="row.study_program.id === selectedId ? 'bg-primary/5 shadow-[inset_3px_0_0_0] shadow-primary' : ''" @click="selectProgram(row.study_program.id)">
                                    <td class="py-3 pl-5 pr-3">
                                        <span class="font-semibold text-gray-900">{{ row.study_program.code }}</span>
                                        <span class="ml-3 text-gray-700">{{ row.study_program.name }}</span>
                                        <span v-if="row.submitted_at" class="mt-1 block text-xs text-gray-500">Diserahkan {{ formatDateTime(row.submitted_at) }}</span>
                                        <span v-if="row.has_pending_slot_request" class="mt-1 block text-xs text-amber-700">Ada permintaan tambahan slot</span>
                                    </td>
                                    <td class="px-3 py-3 text-center tabular-nums text-gray-600">{{ row.cells_count }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-center font-medium">
                                        <span class="inline-flex items-center gap-2" :class="STATUS[row.status].text">
                                            <span class="h-2 w-2 rounded-full" :class="STATUS[row.status].dot" aria-hidden="true"></span>{{ STATUS[row.status].label }}
                                        </span>
                                    </td>
                                    <td class="py-3 pl-3 pr-5 text-right">
                                        <button type="button" class="font-medium text-primary hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary" :aria-label="`Lihat jadwal ${row.study_program.code} ${row.study_program.name}`" aria-controls="detail-review-prodi" :aria-pressed="row.study_program.id === selectedId" @click.stop="selectProgram(row.study_program.id)">Lihat</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section v-if="selectedRow" id="detail-review-prodi" ref="detailSection" class="scroll-mt-6 space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ selectedRow.study_program.code }} {{ selectedRow.study_program.name }}</h2>
                            <p class="mt-1 text-sm text-gray-600">
                                <template v-if="selectedRow.scheduling_run_id">Run #{{ selectedRow.scheduling_run_id }}</template>
                                <template v-else>Belum ada run yang ditetapkan.</template>
                            </p>
                        </div>
                        <div v-if="canReturn" class="flex gap-2">
                            <Button variant="secondary" :disabled="processing !== null" @click="returnOpen = true; actionError = null">Kembalikan</Button>
                        </div>
                    </div>

                    <Alert v-if="actionError && !returnOpen" :title="actionError" />
                    <Alert v-if="selectedRow.status === 'returned' && selectedRow.return_note" variant="warning" title="Dikembalikan ke program studi">{{ selectedRow.return_note }}</Alert>
                    <p v-if="selectedRow.status === 'draft' || selectedRow.status === 'returned'" class="rounded-xl border border-gray-200 bg-white px-4 py-8 text-center text-sm text-gray-500">
                        Program studi belum menyerahkan jadwal. Jadwal tampil di sini setelah diserahkan.
                    </p>
                    <template v-else>
                        <Alert v-if="detailsError" :title="detailsError.message" />
                        <p v-if="detailsLoading" class="py-8 text-center text-sm text-gray-500">Memuat jadwal…</p>
                        <template v-else>
                            <div v-if="!detailsError && selectedRow.scheduling_run_id" class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-gray-600">
                                <span><strong class="font-semibold tabular-nums text-gray-900">{{ details.length }}</strong> pertemuan</span>
                            </div>
                            <FacultyScheduleGrid v-if="!detailsError && selectedRow.scheduling_run_id" :details="details" :time-slots="timeSlots" :days="days" :fallback-program="selectedRow.study_program" />
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

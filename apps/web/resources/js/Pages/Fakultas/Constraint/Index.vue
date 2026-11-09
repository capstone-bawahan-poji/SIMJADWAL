<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ConstraintPreview from '@/Components/constraint/ConstraintPreview.vue';
import Modal from '@/Components/Modal.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import Panel from '@/Components/ui/Panel.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { useConstraintDetail } from '@/composables/useConstraintDetail';
import { runSubmissionAction } from '@/lib/constraintSubmission';
import { useCollection, usePaginatedList } from '@/composables/useApiList';
import { api, toApiError } from '@/lib/api';
import { queryParam } from '@/lib/query';
import type { ConstraintStatus, ConstraintSubmission, ConstraintType } from '@/types/models';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const { filters, items: submissions, pagination, page, loading, error, reload } = usePaginatedList<
    ConstraintSubmission,
    { q: string; status: ConstraintStatus | '' }
>('constraint-submissions', { q: queryParam('q'), status: '' }, 20, ['courses', 'course_lecturers', 'lecturers', 'lecturer_preferences']);

const STATUS_LABEL: Record<ConstraintStatus, string> = { draft: 'Draft', submitted: 'Menunggu review', accepted: 'Diterima' };
const STATUS_STYLE: Record<ConstraintStatus, { text: string }> = {
    draft: { text: '#6b7280' },
    submitted: { text: '#b45309' },
    accepted: { text: '#15803d' },
};
const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
const dayFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' });
const formatDate = (value: string | null) => (value ? dateTimeFormat.format(new Date(value)) : '-');
const formatDay = (value: string | null) => (value ? dayFormat.format(new Date(value)) : '-');

const controlClass = 'rounded-lg border-gray-200 bg-white text-sm text-gray-700 focus:border-primary focus:ring-primary';
const waitingCount = computed(() => submissions.value.filter((row) => row.constraint_status === 'submitted').length);
const flash = ref<string | null>(null);

const { items: constraintTypes, loading: constraintTypesLoading, error: constraintTypesError, reload: reloadTypes } = useCollection<ConstraintType>('constraint-types');
const softTypes = computed(() => constraintTypes.value.filter((type) => type.category === 'SC'));
const weightDrafts = ref<Record<number, number | ''>>({});
const weightError = ref<string | null>(null);
const savingWeight = ref<number | null>(null);

function weightOf(type: ConstraintType): number | '' {
    return weightDrafts.value[type.id] ?? type.weight;
}

async function saveWeight(type: ConstraintType): Promise<void> {
    const value = weightOf(type);

    if (value === '') return;

    savingWeight.value = type.id;
    weightError.value = null;

    try {
        await api.patch(`constraint-types/${type.id}`, { weight: value });
        delete weightDrafts.value[type.id];
        flash.value = `Bobot ${type.code} diperbarui.`;
        await reloadTypes();
    } catch (error) {
        weightError.value = toApiError(error).message;
    } finally {
        savingWeight.value = null;
    }
}

const { detail, constraintTypes: detailTypes, timeSlots, loading: detailLoading, error: detailError, load } = useConstraintDetail();
const reviewing = ref<ConstraintSubmission | null>(null);
const returning = ref(false);
const note = ref('');
const actionError = ref<string | null>(null);
const processing = ref(false);

async function openReview(row: ConstraintSubmission): Promise<void> {
    reviewing.value = row;
    returning.value = false;
    note.value = '';
    actionError.value = null;
    detail.value = null;
    await load(row.id);
}

async function act(action: 'accept' | 'return'): Promise<void> {
    if (!reviewing.value) return;

    processing.value = true;
    actionError.value = null;
    const result = await runSubmissionAction(reviewing.value.id, action, action === 'return' ? { note: note.value } : {});
    processing.value = false;

    if ('error' in result) {
        actionError.value = result.error.errors.note ? String((result.error.errors.note as string[])[0]) : result.error.message;

        return;
    }

    flash.value = `${result.row.name}: ${action === 'accept' ? 'constraint diterima' : 'dikembalikan ke prodi'}.`;
    reviewing.value = null;
    void reload();
}
</script>

<template>
    <Head title="Kelola Constraint" />

    <AppLayout>
        <div class="w-full space-y-6 p-6 md:p-8">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">Kelola Constraint</h1>
            </div>

            <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />

            <Panel>
                <div class="flex flex-col gap-3 border-b border-gray-200 p-4 md:flex-row md:items-center">
                    <input
                        v-model="filters.q"
                        type="search"
                        placeholder="Cari kode atau nama program studi..."
                        aria-label="Cari program studi"
                        :class="[controlClass, 'w-full placeholder-gray-400 md:w-72']"
                    />
                    <select v-model="filters.status" :class="[controlClass, 'md:w-48']" aria-label="Filter status">
                        <option value="">Semua Status</option>
                        <option v-for="(label, value) in STATUS_LABEL" :key="value" :value="value">{{ label }}</option>
                    </select>
                    <p class="text-sm text-gray-500 md:ml-auto">
                        <template v-if="waitingCount > 0">
                            <span class="font-semibold tabular-nums" style="color: #b45309">{{ waitingCount }}</span> menunggu review ·
                        </template>
                        <span class="font-semibold tabular-nums text-gray-900">{{ pagination.total }}</span> program studi
                    </p>
                </div>

                <Alert v-if="error" :title="error.message" class="m-4" />

                <div class="w-full overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-6 py-3">Program studi</th>
                                <th class="whitespace-nowrap px-6 py-3">Status</th>
                                <th class="whitespace-nowrap px-6 py-3">Submit</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">Dosen berpreferensi</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">Matkul belum lengkap</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" :class="{ 'opacity-60': loading }">
                            <tr v-if="!loading && submissions.length === 0">
                                <td colspan="6" class="px-6 py-10 text-center text-gray-500">Tidak ada program studi yang cocok dengan filter.</td>
                            </tr>
                            <tr v-for="row in submissions" :key="row.id" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-3.5">
                                    <span class="font-semibold text-gray-900">{{ row.name }}</span>
                                    <span class="ml-2 text-xs text-gray-500">{{ row.code }}</span>
                                </td>
                                <td class="px-6 py-3.5">
                                    <span class="whitespace-nowrap font-medium" :style="{ color: STATUS_STYLE[row.constraint_status].text }">
                                        {{ STATUS_LABEL[row.constraint_status] }}
                                    </span>
                                    <p v-if="row.constraint_status === 'draft' && row.constraint_return_note" class="mt-0.5 text-xs" style="color: #b45309">
                                        Dikembalikan: {{ row.constraint_return_note }}
                                    </p>
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-gray-700" :title="formatDate(row.constraint_submitted_at)">{{ formatDay(row.constraint_submitted_at) }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-right tabular-nums text-gray-900">
                                    {{ row.lecturers_with_preferences_count }} <span class="text-gray-400">/ {{ row.lecturers_count }}</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-right tabular-nums" :class="row.incomplete_courses_count ? 'font-semibold text-red-600' : 'text-gray-900'">
                                    {{ row.incomplete_courses_count }} <span class="font-normal text-gray-400">/ {{ row.courses_count }}</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-right">
                                    <Button
                                        v-if="row.constraint_status === 'submitted' && row.can_review"
                                        type="button"
                                        size="sm"
                                        @click="openReview(row)"
                                    >
                                        Review
                                    </Button>
                                    <Button
                                        v-else
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        @click="openReview(row)"
                                    >
                                        Lihat
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-model:page="page" :pagination="pagination" label="program studi" />
            </Panel>

            <Panel>
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">Bobot soft constraint</h2>
                    <p class="mt-0.5 text-sm text-gray-500">Berlaku untuk semua program studi. Hard constraint tidak dapat diubah.</p>
                </div>
                <Alert v-if="constraintTypesError" :title="constraintTypesError.message" class="m-4" />
                <p v-else-if="constraintTypesLoading && softTypes.length === 0" class="px-6 py-4 text-sm text-gray-500">Memuat bobot constraint...</p>
                <p v-else-if="softTypes.length === 0" class="px-6 py-4 text-sm text-gray-500">Tidak ada soft constraint.</p>
                <Alert v-if="weightError" :title="weightError" class="m-4" />

                <ul class="divide-y divide-gray-100">
                    <li v-for="type in softTypes" :key="type.id" class="flex flex-col gap-3 px-6 py-3.5 md:flex-row md:items-center md:justify-between">
                        <p class="min-w-0 text-sm text-gray-800" :title="type.code">{{ type.description }}</p>
                        <div class="flex shrink-0 items-center gap-2">
                            <input
                                :id="`weight-${type.id}`"
                                :aria-label="`Bobot ${type.code}`"
                                :value="weightOf(type)"
                                type="number"
                                min="0"
                                max="100"
                                step="0.5"
                                :disabled="!type.can_update"
                                :class="[controlClass, 'w-20 text-right tabular-nums disabled:bg-gray-50 disabled:text-gray-500']"
                                @input="weightDrafts[type.id] = ($event.target as HTMLInputElement).value === '' ? '' : Number(($event.target as HTMLInputElement).value)"
                            />
                            <Button
                                type="button"
                                size="sm"
                                :disabled="!type.can_update || weightDrafts[type.id] === undefined"
                                :loading="savingWeight === type.id"
                                @click="saveWeight(type)"
                            >
                                Simpan
                            </Button>
                        </div>
                    </li>
                </ul>
            </Panel>
        </div>

        <Modal :show="reviewing !== null" max-width="5xl" close-button @close="reviewing = null">
            <!-- 4.5rem = Modal.vue's py-6 + mb-6, so the panel never overflows the viewport (no outer scroll). -->
            <div class="flex max-h-[calc(100vh-4.5rem)] flex-col">
                <div class="shrink-0 border-b border-gray-200 py-4 pl-6 pr-14">
                    <h2 class="text-lg font-bold text-gray-900">{{ reviewing?.name }}</h2>
                    <p v-if="reviewing" class="text-sm text-gray-500">{{ STATUS_LABEL[reviewing.constraint_status] }} · Submit {{ formatDate(reviewing.constraint_submitted_at) }}</p>
                </div>

                <div class="max-h-[75vh] min-h-0 space-y-4 overflow-y-auto p-6">
                    <Alert v-if="detailError" :title="detailError.message" />
                    <p v-if="detailLoading" class="text-sm text-gray-500">Memuat...</p>
                    <ConstraintPreview v-if="detail" :detail="detail" :constraint-types="detailTypes" :time-slots="timeSlots" />
                </div>

                <div v-if="reviewing?.can_review && ['submitted', 'accepted'].includes(reviewing.constraint_status)" class="shrink-0 space-y-3 border-t border-gray-200 p-6">
                    <Alert v-if="actionError" :title="actionError" />
                    <div v-if="returning">
                        <label for="return-note" class="mb-1.5 block text-sm font-semibold text-gray-900">Alasan pengembalian</label>
                        <textarea
                            id="return-note"
                            v-model="note"
                            rows="3"
                            maxlength="1000"
                            class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm focus:border-primary focus:ring-primary"
                            placeholder="Jelaskan apa yang perlu diperbaiki prodi"
                        ></textarea>
                    </div>
                    <div class="flex justify-end gap-3">
                        <template v-if="returning">
                            <Button type="button" variant="secondary" @click="returning = false">Batal</Button>
                            <Button type="button" :loading="processing" :disabled="note.trim() === ''" @click="act('return')">Kembalikan ke Prodi</Button>
                        </template>
                        <template v-else>
                            <Button type="button" variant="secondary" @click="returning = true">Kembalikan</Button>
                            <Button v-if="reviewing.constraint_status === 'submitted'" type="button" :loading="processing" @click="act('accept')">Terima</Button>
                        </template>
                    </div>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ConstraintPreview from '@/Components/constraint/ConstraintPreview.vue';
import Modal from '@/Components/Modal.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
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
>('constraint-submissions', { q: queryParam('q'), status: '' });

const STATUS_LABEL: Record<ConstraintStatus, string> = { draft: 'Draft', submitted: 'Menunggu Review', accepted: 'Diterima' };
const STATUS_TONE: Record<ConstraintStatus, string> = {
    draft: 'bg-gray-100 text-gray-600 border-gray-200',
    submitted: 'bg-amber-50 text-amber-700 border-amber-100',
    accepted: 'bg-green-50 text-green-700 border-green-100',
};
const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
const formatDate = (value: string | null) => (value ? dateFormat.format(new Date(value)) : '-');

const flash = ref<string | null>(null);

// Global soft constraint weights.
const { items: constraintTypes, reload: reloadTypes } = useCollection<ConstraintType>('constraint-types');
const softTypes = computed(() => constraintTypes.value.filter((c) => c.category === 'SC'));
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
    } catch (e) {
        weightError.value = toApiError(e).message;
    } finally {
        savingWeight.value = null;
    }
}

// Review dialog.
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
        <div class="p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Kelola Constraint</h1>
                <p class="mt-1 text-sm text-gray-500">Review constraint yang di-submit tiap program studi, lalu terima atau kembalikan.</p>
            </div>

            <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />

            <Card class="!p-0 border border-gray-100 shadow-sm">
                <div class="border-b border-gray-100 p-5">
                    <h2 class="text-sm font-bold text-gray-900">Bobot Soft Constraint</h2>
                    <p class="text-[11px] text-gray-400">Berlaku global untuk semua program studi. Hard constraint tidak dapat diubah.</p>
                </div>
                <Alert v-if="weightError" :title="weightError" class="m-5" />
                <ul class="divide-y divide-gray-100">
                    <li v-for="type in softTypes" :key="type.id" class="flex flex-col gap-3 px-5 py-4 md:flex-row md:items-center md:justify-between">
                        <div class="min-w-0">
                            <span class="font-mono text-[11px] font-bold text-gray-500">{{ type.code }}</span>
                            <p class="text-xs text-gray-700">{{ type.description }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <input
                                :value="weightOf(type)"
                                type="number"
                                min="0"
                                max="100"
                                step="0.5"
                                :disabled="!type.can_update"
                                :aria-label="`Bobot ${type.code}`"
                                class="w-24 rounded-xl border-gray-200 bg-gray-50 text-sm focus:border-primary focus:ring-primary disabled:opacity-60"
                                @input="weightDrafts[type.id] = ($event.target as HTMLInputElement).value === '' ? '' : Number(($event.target as HTMLInputElement).value)"
                            />
                            <Button type="button" class="h-9 px-3 text-xs" :disabled="!type.can_update || weightDrafts[type.id] === undefined" :loading="savingWeight === type.id" @click="saveWeight(type)">Simpan</Button>
                        </div>
                    </li>
                </ul>
            </Card>

            <Card class="!p-0 border border-gray-100 shadow-sm">
                <div class="flex flex-col gap-3 border-b border-gray-100 p-5 md:flex-row md:items-center md:justify-between">
                    <select v-model="filters.status" class="w-48 rounded-xl border-gray-200 bg-gray-50 text-xs font-semibold text-gray-700 focus:border-primary focus:ring-primary" aria-label="Filter status">
                        <option value="">Semua Status</option>
                        <option v-for="(label, value) in STATUS_LABEL" :key="value" :value="value">{{ label }}</option>
                    </select>
                    <input v-model="filters.q" type="search" placeholder="Cari kode atau nama program studi..." aria-label="Cari program studi" class="w-full rounded-xl border-gray-200 bg-gray-50 text-xs placeholder-gray-400 focus:border-primary focus:ring-primary md:w-72" />
                </div>

                <Alert v-if="error" :title="error.message" class="m-5" />

                <div class="w-full overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/70 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                <th class="px-6 py-4">Program Studi</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Submit</th>
                                <th class="px-6 py-4 text-center">Dosen Berpreferensi</th>
                                <th class="px-6 py-4 text-center">Matkul Belum Lengkap</th>
                                <th class="px-6 py-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-xs text-gray-700" :class="{ 'opacity-60': loading }">
                            <tr v-if="!loading && submissions.length === 0">
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">Tidak ada program studi yang cocok dengan filter.</td>
                            </tr>
                            <tr v-for="row in submissions" :key="row.id" class="transition-colors hover:bg-gray-50/60">
                                <td class="px-6 py-4"><span class="font-bold text-gray-900">{{ row.name }}</span><span class="block text-[11px] text-gray-400">{{ row.code }}</span></td>
                                <td class="px-6 py-4">
                                    <span class="rounded-full border px-2.5 py-0.5 text-[11px] font-semibold" :class="STATUS_TONE[row.constraint_status]">{{ STATUS_LABEL[row.constraint_status] }}</span>
                                    <span v-if="row.constraint_status === 'draft' && row.constraint_return_note" class="mt-1 block text-[11px] text-amber-700">Dikembalikan: {{ row.constraint_return_note }}</span>
                                </td>
                                <td class="px-6 py-4">{{ formatDate(row.constraint_submitted_at) }}</td>
                                <td class="px-6 py-4 text-center font-semibold">{{ row.lecturers_with_preferences_count }} / {{ row.lecturers_count }}</td>
                                <td class="px-6 py-4 text-center font-semibold" :class="row.incomplete_courses_count ? 'text-red-600' : ''">{{ row.incomplete_courses_count }} / {{ row.courses_count }}</td>
                                <td class="px-6 py-4 text-center">
                                    <button type="button" class="rounded-lg border border-gray-200 px-3 py-1.5 font-semibold text-gray-700 hover:bg-gray-50" @click="openReview(row)">
                                        {{ row.constraint_status === 'submitted' && row.can_review ? 'Review' : 'Lihat' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-model:page="page" :pagination="pagination" label="program studi" />
            </Card>
        </div>

        <Modal :show="reviewing !== null" max-width="2xl" @close="reviewing = null">
            <div class="flex max-h-[85vh] flex-col">
                <div class="flex items-center justify-between rounded-t-lg border-b border-gray-100 bg-gray-50 p-6">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">{{ reviewing?.name }}</h2>
                        <p v-if="reviewing" class="text-xs text-gray-500">{{ STATUS_LABEL[reviewing.constraint_status] }} • Submit {{ formatDate(reviewing.constraint_submitted_at) }}</p>
                    </div>
                    <button type="button" class="text-gray-400 transition hover:text-gray-900" @click="reviewing = null">Tutup</button>
                </div>

                <div class="space-y-4 overflow-y-auto p-6">
                    <Alert v-if="detailError" :title="detailError.message" />
                    <p v-if="detailLoading" class="text-sm text-gray-500">Memuat...</p>
                    <ConstraintPreview v-if="detail" :detail="detail" :constraint-types="detailTypes" :time-slots="timeSlots" />
                </div>

                <div v-if="reviewing?.can_review && ['submitted', 'accepted'].includes(reviewing.constraint_status)" class="space-y-3 border-t border-gray-100 p-6">
                    <Alert v-if="actionError" :title="actionError" />
                    <div v-if="returning">
                        <label for="return-note" class="mb-1.5 block text-sm font-semibold text-gray-900">Alasan pengembalian</label>
                        <textarea id="return-note" v-model="note" rows="3" maxlength="1000" class="w-full rounded-xl border-gray-200 bg-gray-50 text-sm focus:border-primary focus:ring-primary" placeholder="Jelaskan apa yang perlu diperbaiki prodi"></textarea>
                    </div>
                    <div class="flex justify-end gap-3">
                        <template v-if="returning">
                            <Button type="button" variant="ghost" class="bg-gray-100 hover:bg-gray-200" @click="returning = false">Batal</Button>
                            <Button type="button" :loading="processing" :disabled="note.trim() === ''" @click="act('return')">Kembalikan ke Prodi</Button>
                        </template>
                        <template v-else>
                            <Button type="button" variant="ghost" class="bg-gray-100 hover:bg-gray-200" @click="returning = true">Kembalikan</Button>
                            <Button v-if="reviewing.constraint_status === 'submitted'" type="button" :loading="processing" @click="act('accept')">Terima</Button>
                        </template>
                    </div>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

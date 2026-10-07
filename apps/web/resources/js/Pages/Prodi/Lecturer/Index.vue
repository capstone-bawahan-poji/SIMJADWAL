<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ProgramLockBanner from '@/Components/constraint/ProgramLockBanner.vue';
import Modal from '@/Components/Modal.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import { useApiForm } from '@/composables/useApiForm';
import { usePaginatedList } from '@/composables/useApiList';
import { useOwnSubmission } from '@/composables/useOwnSubmission';
import { destroy } from '@/lib/api';
import { initials } from '@/lib/labels';
import { queryParam } from '@/lib/query';
import type { Lecturer } from '@/types/models';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const fieldClass = 'w-full rounded-xl border-gray-200 bg-gray-50 text-gray-900 text-sm focus:border-primary focus:ring-primary';
const studyProgramId = computed(() => usePage().props.auth.user.study_program_id);
const { submission, locked } = useOwnSubmission();

const { filters, items: lecturers, pagination, page, loading, error, reload } = usePaginatedList<Lecturer, { q: string; study_program_id: number | null }>(
    'lecturers',
    { q: queryParam('q'), study_program_id: studyProgramId.value },
);

const flash = ref<string | null>(null);

function refresh(message: string): void {
    flash.value = message;
    void reload();
}

const isModalOpen = ref(false);
const editing = ref<Lecturer | null>(null);
const form = useApiForm(() => ({ nip: '', name: '', title: '' }));

function openCreate(): void {
    editing.value = null;
    form.reset();
    isModalOpen.value = true;
}

function openEdit(lecturer: Lecturer): void {
    editing.value = lecturer;
    form.reset({ nip: lecturer.nip, name: lecturer.name, title: lecturer.title ?? '' });
    isModalOpen.value = true;
}

async function save(): Promise<void> {
    const payload = { nip: form.data.nip, name: form.data.name, title: form.data.title || null, study_program_id: studyProgramId.value };
    const saved = editing.value
        ? await form.submit<Lecturer>('patch', `lecturers/${editing.value.id}`, payload)
        : await form.submit<Lecturer>('post', 'lecturers', payload);

    if (saved) {
        isModalOpen.value = false;
        refresh(`Dosen ${saved.name} ${editing.value ? 'diperbarui' : 'ditambahkan'}.`);
    }
}

// Delete; refused with 409 while the lecturer has teaching assignments, preferences or schedules.
const deleteTarget = ref<Lecturer | null>(null);
const deleteProcessing = ref(false);
const deleteError = ref<string | null>(null);

function askDelete(lecturer: Lecturer): void {
    deleteTarget.value = lecturer;
    deleteError.value = null;
}

async function confirmDelete(): Promise<void> {
    const lecturer = deleteTarget.value;

    if (!lecturer) return;

    deleteProcessing.value = true;
    const failure = await destroy(`lecturers/${lecturer.id}`);
    deleteProcessing.value = false;

    if (failure) {
        deleteError.value = failure.message;

        return;
    }

    deleteTarget.value = null;
    refresh(`Dosen ${lecturer.name} dihapus.`);
}
</script>

<template>
    <Head title="Data Dosen" />

    <AppLayout>
        <div class="p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Data Dosen</h1>
                    <p class="mt-1 text-sm text-gray-500">Dosen program studi Anda dan jumlah kelas yang diampu.</p>
                </div>
                <Button type="button" :disabled="locked" @click="openCreate">Tambah Dosen</Button>
            </div>

            <ProgramLockBanner :submission="submission" />
            <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <StatCard label="Total Dosen Prodi" :value="pagination.total" />
            </div>

            <Card class="!p-0 border border-gray-100 shadow-sm">
                <div class="flex justify-end border-b border-gray-100 p-5">
                    <input v-model="filters.q" type="search" placeholder="Cari nama atau NIP dosen..." aria-label="Cari dosen" class="w-full rounded-xl border-gray-200 bg-gray-50 text-xs placeholder-gray-400 focus:border-primary focus:ring-primary md:w-72" />
                </div>

                <Alert v-if="error" :title="error.message" class="m-5" />

                <div class="w-full overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/70 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                <th class="px-6 py-4">Nama Dosen</th>
                                <th class="px-6 py-4">Gelar</th>
                                <th class="px-6 py-4">NIP</th>
                                <th class="px-6 py-4 text-center">Kelas Diampu</th>
                                <th class="px-6 py-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-xs text-gray-700" :class="{ 'opacity-60': loading }">
                            <tr v-if="!loading && lecturers.length === 0">
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">Tidak ada dosen yang cocok dengan pencarian.</td>
                            </tr>
                            <tr v-for="lecturer in lecturers" :key="lecturer.id" class="transition-colors hover:bg-gray-50/60">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-[10px] font-bold text-primary">{{ initials(lecturer.name) }}</div>
                                        <span class="font-bold text-gray-900">{{ lecturer.name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">{{ lecturer.title ?? '-' }}</td>
                                <td class="px-6 py-4 font-mono">{{ lecturer.nip }}</td>
                                <td class="px-6 py-4 text-center font-semibold">{{ lecturer.course_lecturers_count }}</td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button v-if="lecturer.can_update" type="button" class="rounded p-1.5 text-gray-400 transition-colors hover:bg-primary/10 hover:text-primary" @click="openEdit(lecturer)">Edit</button>
                                        <button v-if="lecturer.can_delete" type="button" class="rounded p-1.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600" @click="askDelete(lecturer)">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-model:page="page" :pagination="pagination" label="dosen" />
            </Card>
        </div>

        <Modal :show="isModalOpen" max-width="2xl" @close="isModalOpen = false">
            <div class="flex items-center justify-between rounded-t-lg border-b border-gray-100 bg-gray-50 p-6">
                <h2 class="text-lg font-bold text-gray-900">{{ editing ? `Ubah Dosen ${editing.name}` : 'Tambah Dosen' }}</h2>
                <button type="button" class="text-gray-400 transition hover:text-gray-900" @click="isModalOpen = false">Tutup</button>
            </div>
            <form class="space-y-6 p-6" @submit.prevent="save">
                <Alert v-if="form.failure.value" :title="form.failure.value.message" />

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <label for="lecturer-nip" class="mb-1.5 block text-sm font-semibold text-gray-900">NIP</label>
                        <input id="lecturer-nip" v-model="form.data.nip" type="text" inputmode="numeric" :class="fieldClass" required />
                        <p v-if="form.errors.value.nip" class="mt-1 text-xs text-red-600">{{ form.errors.value.nip }}</p>
                    </div>
                    <div>
                        <label for="lecturer-title" class="mb-1.5 block text-sm font-semibold text-gray-900">Gelar (opsional)</label>
                        <input id="lecturer-title" v-model="form.data.title" type="text" maxlength="50" placeholder="Contoh: S.Kom., M.T." :class="fieldClass" />
                        <p v-if="form.errors.value.title" class="mt-1 text-xs text-red-600">{{ form.errors.value.title }}</p>
                    </div>
                </div>
                <div>
                    <label for="lecturer-name" class="mb-1.5 block text-sm font-semibold text-gray-900">Nama Dosen</label>
                    <input id="lecturer-name" v-model="form.data.name" type="text" :class="fieldClass" required />
                    <p v-if="form.errors.value.name" class="mt-1 text-xs text-red-600">{{ form.errors.value.name }}</p>
                </div>
                <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                    <Button type="button" variant="ghost" class="bg-gray-100 hover:bg-gray-200" @click="isModalOpen = false">Batal</Button>
                    <Button type="submit" :loading="form.processing.value">Simpan Dosen</Button>
                </div>
            </form>
        </Modal>

        <ConfirmDialog :show="deleteTarget !== null" title="Hapus dosen?" :processing="deleteProcessing" :error="deleteError" @confirm="confirmDelete" @close="deleteTarget = null">
            Dosen <strong>{{ deleteTarget?.name }}</strong> akan dihapus. Penghapusan ditolak selama dosen masih punya penugasan mengajar, preferensi, atau jadwal.
        </ConfirmDialog>
    </AppLayout>
</template>

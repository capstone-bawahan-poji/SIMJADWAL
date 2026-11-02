<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ProgramLockBanner from '@/Components/constraint/ProgramLockBanner.vue';
import Modal from '@/Components/Modal.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Panel from '@/Components/ui/Panel.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { useApiForm } from '@/composables/useApiForm';
import { usePaginatedList } from '@/composables/useApiList';
import { useOwnSubmission } from '@/composables/useOwnSubmission';
import { destroy } from '@/lib/api';
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
    20,
    ['course_lecturers'],
);

const controlClass = 'rounded-lg border-gray-200 bg-white text-sm text-gray-700 focus:border-primary focus:ring-primary';
const hasActions = computed(() => lecturers.value.some((lecturer) => lecturer.can_update || lecturer.can_delete));
const flash = ref<string | null>(null);

function fullName(lecturer: Lecturer): string {
    return lecturer.title ? `${lecturer.name}, ${lecturer.title}` : lecturer.name;
}

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
        <div class="w-full space-y-6 p-6 md:p-8">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Data Dosen</h1>
                    <p class="mt-1 text-sm text-gray-500">Dosen prodi dan jumlah kelas yang diampu.</p>
                </div>
                <Button type="button" :disabled="locked" @click="openCreate">Tambah Dosen</Button>
            </div>

            <ProgramLockBanner :submission="submission" />
            <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />

            <Panel>
                <div class="flex flex-col gap-3 border-b border-gray-200 p-4 md:flex-row md:items-center">
                    <input
                        v-model="filters.q"
                        type="search"
                        placeholder="Cari nama atau NIP dosen..."
                        aria-label="Cari dosen"
                        :class="[controlClass, 'w-full placeholder-gray-400 md:w-72']"
                    />
                    <p class="text-sm text-gray-500 md:ml-auto"><span class="font-semibold tabular-nums text-gray-900">{{ pagination.total }}</span> dosen</p>
                </div>

                <Alert v-if="error" :title="error.message" class="m-4" />

                <div class="w-full overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-6 py-3">Nama Dosen</th>
                                <th class="whitespace-nowrap px-6 py-3">NIP</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">Kelas diampu</th>
                                <th v-if="hasActions" class="whitespace-nowrap px-6 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" :class="{ 'opacity-60': loading }">
                            <tr v-if="!loading && lecturers.length === 0">
                                <td :colspan="hasActions ? 4 : 3" class="px-6 py-10 text-center text-gray-500">Tidak ada dosen yang cocok dengan pencarian.</td>
                            </tr>
                            <tr v-for="lecturer in lecturers" :key="lecturer.id" class="hover:bg-gray-50">
                                <td class="px-6 py-3.5 font-medium text-gray-900">{{ fullName(lecturer) }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5 tabular-nums text-gray-600">{{ lecturer.nip }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-right tabular-nums" :class="lecturer.course_lecturers_count === 0 ? 'text-gray-400' : 'text-gray-900'">
                                    {{ lecturer.course_lecturers_count }}
                                </td>
                                <td v-if="hasActions" class="whitespace-nowrap px-6 py-3.5 text-right">
                                    <div class="flex justify-end gap-4">
                                        <button v-if="lecturer.can_update" type="button" class="font-medium text-gray-700 hover:text-primary hover:underline" @click="openEdit(lecturer)">Edit</button>
                                        <button v-if="lecturer.can_delete" type="button" class="font-medium text-red-600 hover:underline" @click="askDelete(lecturer)">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-model:page="page" :pagination="pagination" label="dosen" />
            </Panel>
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

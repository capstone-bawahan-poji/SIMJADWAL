<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Panel from '@/Components/ui/Panel.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { usePaginatedList } from '@/composables/useApiList';
import { useApiForm } from '@/composables/useApiForm';
import { destroy } from '@/lib/api';
import { queryParam } from '@/lib/query';
import type { Course } from '@/types/models';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

// Course identity only (code, name, SKS). Class count, capacity, groups and programs belong
// to the offering of each period, managed in Mapping Dosen Pengampu.
const fieldClass = 'h-10 w-full rounded-lg border-gray-200 bg-white text-sm text-gray-900 focus:border-primary focus:ring-primary';
const controlClass = 'h-10 rounded-lg border-gray-200 bg-white text-sm text-gray-700 focus:border-primary focus:ring-primary';

const { filters, items: courses, pagination, page, loading, error, reload } = usePaginatedList<Course, { q: string; is_tpb: boolean }>(
    'courses',
    { q: queryParam('q'), is_tpb: true },
    20,
);

const flash = ref<string | null>(null);
const modalOpen = ref(false);
const editing = ref<Course | null>(null);
const form = useApiForm(() => ({ code: '', name: '', sks: 2 as number }));

function openCreate(): void {
    editing.value = null;
    form.reset();
    modalOpen.value = true;
}

function openEdit(course: Course): void {
    editing.value = course;
    form.reset({ code: course.code, name: course.name, sks: course.sks });
    modalOpen.value = true;
}

async function save(): Promise<void> {
    const saved = editing.value
        ? await form.submit<Course>('patch', `courses/${editing.value.id}`, { ...form.data })
        : await form.submit<Course>('post', 'courses', { ...form.data, is_tpb: true });
    if (!saved) return;

    modalOpen.value = false;
    flash.value = `Mata kuliah ${saved.code} ${editing.value ? 'diperbarui' : 'ditambahkan'}.`;
    await reload();
}

const deleteTarget = ref<Course | null>(null);
const deleting = ref(false);
const deleteError = ref<string | null>(null);

async function confirmDelete(): Promise<void> {
    if (!deleteTarget.value) return;
    deleting.value = true;
    const failure = await destroy(`courses/${deleteTarget.value.id}`);
    deleting.value = false;
    if (failure) {
        deleteError.value = failure.message;
        return;
    }
    flash.value = `Mata kuliah ${deleteTarget.value.code} dihapus.`;
    deleteTarget.value = null;
    await reload();
}
</script>

<template>
    <Head title="Kelola Mata Kuliah TPB" />
    <AppLayout>
        <div class="mx-auto w-full max-w-7xl space-y-6 p-6 md:p-8">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Kelola Mata Kuliah</h1>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Kode, nama, dan SKS mata kuliah TPB. Jumlah kelas, kapasitas, dan grup tiap periode diatur di <Link href="/tpb/mappings" class="font-semibold text-primary hover:underline">Mapping Dosen Pengampu</Link>.</p>
                </div>
                <Button type="button" @click="openCreate">Tambah Mata Kuliah</Button>
            </header>

            <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />
            <Alert v-if="error" :title="error.message" />
            
            <Panel>
                <div class="flex flex-col gap-3 border-b border-gray-200 p-4 md:flex-row md:items-center">
                    <input v-model="filters.q" type="search" placeholder="Cari kode atau nama..." aria-label="Cari mata kuliah" :class="[controlClass, 'w-full md:w-72']" />
                    <p class="text-sm text-gray-500 md:ml-auto"><span class="font-semibold tabular-nums text-gray-900">{{ pagination.total }}</span> mata kuliah {{ filters.q ? 'ditemukan' : 'terdaftar' }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="whitespace-nowrap px-6 py-3">Kode</th>
                                <th class="px-6 py-3">Mata Kuliah</th>
                                <th class="px-6 py-3 text-right">SKS</th>
                                <th class="px-6 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" :class="{ 'opacity-60': loading }">
                            <tr v-if="!loading && courses.length === 0">
                                <td colspan="4" class="px-6 py-10 text-center text-gray-500">{{ filters.q ? `Tidak ada mata kuliah yang cocok dengan "${filters.q}".` : 'Belum ada mata kuliah TPB. Pilih Tambah Mata Kuliah untuk memulai.' }}</td>
                            </tr>
                            <tr v-for="course in courses" :key="course.id" class="align-top hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-3.5 font-medium tabular-nums text-gray-900">{{ course.code }}</td>
                                <td class="px-6 py-3.5 text-gray-900">{{ course.name }}</td>
                                <td class="px-6 py-3.5 text-right tabular-nums text-gray-700">{{ course.sks }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-right">
                                    <div class="flex justify-end gap-3">
                                        <Button v-if="course.can_update" variant="secondary" size="sm" @click="openEdit(course)">Edit</Button>
                                        <Button v-if="course.can_delete" size="sm" @click="deleteTarget = course; deleteError = null">Hapus</Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Pagination v-model:page="page" :pagination="pagination" label="mata kuliah" />
            </Panel>
        </div>

        <Modal :show="modalOpen" max-width="xl" close-button centered @close="modalOpen = false">
            <div class="rounded-t-lg border-b border-gray-100 bg-gray-50 py-6 pl-6 pr-14">
                <h2 class="text-lg font-bold text-gray-900">{{ editing ? 'Ubah mata kuliah' : 'Tambah mata kuliah TPB' }}</h2>
            </div>
            <form class="space-y-5 p-6" @submit.prevent="save">
                <Alert v-if="form.failure.value" :title="form.failure.value.message" />
                <label class="block space-y-1 text-sm font-semibold text-gray-700">Kode mata kuliah
                    <input v-model="form.data.code" type="text" maxlength="20" :class="fieldClass" required />
                    <span v-if="form.errors.value.code" class="block text-xs font-normal text-red-600">{{ form.errors.value.code }}</span>
                </label>
                <label class="block space-y-1 text-sm font-semibold text-gray-700">Nama mata kuliah
                    <input v-model="form.data.name" type="text" maxlength="150" :class="fieldClass" required />
                    <span v-if="form.errors.value.name" class="block text-xs font-normal text-red-600">{{ form.errors.value.name }}</span>
                </label>
                <label class="block space-y-1 text-sm font-semibold text-gray-700">SKS
                    <select v-model.number="form.data.sks" :class="fieldClass">
                        <option :value="2">2 SKS</option><option :value="3">3 SKS</option><option :value="4">4 SKS (2 pertemuan)</option>
                    </select>
                </label>
                <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                    <Button type="button" variant="secondary" @click="modalOpen = false">Batal</Button>
                    <Button type="submit" :loading="form.processing.value">Simpan</Button>
                </div>
            </form>
        </Modal>

        <ConfirmDialog :show="deleteTarget !== null" title="Hapus mata kuliah TPB?" :danger="false" :processing="deleting" :error="deleteError" @confirm="confirmDelete" @close="deleteTarget = null">
            Mata kuliah <strong>{{ deleteTarget?.code }} {{ deleteTarget?.name }}</strong> akan dihapus. Mata kuliah yang masih dipakai di suatu periode tidak bisa dihapus.
        </ConfirmDialog>
    </AppLayout>
</template>

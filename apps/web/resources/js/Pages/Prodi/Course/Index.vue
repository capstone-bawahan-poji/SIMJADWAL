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
import { queryParam } from '@/lib/query';
import type { Course } from '@/types/models';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const fieldClass = 'w-full rounded-xl border-gray-200 bg-gray-50 text-gray-900 text-sm focus:border-primary focus:ring-primary';
const studyProgramId = computed(() => usePage().props.auth.user.study_program_id);
const { submission, locked } = useOwnSubmission();

const { filters, items: courses, pagination, page, loading, error, reload } = usePaginatedList<
    Course,
    { q: string; semester: number | ''; study_program_id: number | null; is_tpb: boolean }
>('courses', { q: queryParam('q'), semester: '', study_program_id: studyProgramId.value, is_tpb: false });

const semesters = [1, 2, 3, 4, 5, 6, 7, 8];
const flash = ref<string | null>(null);

function refresh(message: string): void {
    flash.value = message;
    void reload();
}

// Create / edit modal.
const isModalOpen = ref(false);
const editing = ref<Course | null>(null);
const form = useApiForm(() => ({
    code: '',
    name: '',
    sks: 3 as number,
    semester: 1 as number,
    parallel_class_count: 1 as number,
    class_capacity: 40 as number,
}));

function openCreate(): void {
    editing.value = null;
    form.reset();
    isModalOpen.value = true;
}

function openEdit(course: Course): void {
    editing.value = course;
    form.reset({
        code: course.code,
        name: course.name,
        sks: course.sks,
        semester: course.semester,
        parallel_class_count: course.parallel_class_count,
        class_capacity: course.class_capacity,
    });
    isModalOpen.value = true;
}

async function save(): Promise<void> {
    const saved = editing.value
        ? await form.submit<Course>('patch', `courses/${editing.value.id}`)
        : await form.submit<Course>('post', 'courses', { ...form.data, study_program_id: studyProgramId.value });

    if (saved) {
        isModalOpen.value = false;
        refresh(`Mata kuliah ${saved.code} ${editing.value ? 'diperbarui' : 'ditambahkan'}.`);
    }
}

// Delete; refused with 409 while classes are mapped or scheduled.
const deleteTarget = ref<Course | null>(null);
const deleteProcessing = ref(false);
const deleteError = ref<string | null>(null);

function askDelete(course: Course): void {
    deleteTarget.value = course;
    deleteError.value = null;
}

async function confirmDelete(): Promise<void> {
    const course = deleteTarget.value;

    if (!course) return;

    deleteProcessing.value = true;
    const failure = await destroy(`courses/${course.id}`);
    deleteProcessing.value = false;

    if (failure) {
        deleteError.value = failure.message;

        return;
    }

    deleteTarget.value = null;
    refresh(`Mata kuliah ${course.code} dihapus.`);
}
</script>

<template>
    <Head title="Data Mata Kuliah" />

    <AppLayout>
        <div class="p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Data Mata Kuliah</h1>
                    <p class="mt-1 text-sm text-gray-500">Mata kuliah program studi Anda beserta kelas paralel dan kelengkapan dosen pengampu.</p>
                </div>
                <Button type="button" :disabled="locked" @click="openCreate">Tambah Mata Kuliah</Button>
            </div>

            <ProgramLockBanner :submission="submission" />
            <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <StatCard label="Total Mata Kuliah" :value="pagination.total" />
            </div>

            <Card class="!p-0 border border-gray-100 shadow-sm">
                <div class="flex flex-col gap-3 border-b border-gray-100 p-5 md:flex-row md:items-center md:justify-between">
                    <select v-model="filters.semester" class="w-44 rounded-xl border-gray-200 bg-gray-50 text-xs font-semibold text-gray-700 focus:border-primary focus:ring-primary" aria-label="Filter semester">
                        <option value="">Semua Semester</option>
                        <option v-for="s in semesters" :key="s" :value="s">Semester {{ s }}</option>
                    </select>
                    <input v-model="filters.q" type="search" placeholder="Cari kode atau nama mata kuliah..." aria-label="Cari mata kuliah" class="w-full rounded-xl border-gray-200 bg-gray-50 text-xs placeholder-gray-400 focus:border-primary focus:ring-primary md:w-72" />
                </div>

                <Alert v-if="error" :title="error.message" class="m-5" />

                <div class="w-full overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/70 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                <th class="px-6 py-4">Kode &amp; Mata Kuliah</th>
                                <th class="px-6 py-4 text-center">SKS</th>
                                <th class="px-6 py-4 text-center">Semester</th>
                                <th class="px-6 py-4">Kelas Paralel</th>
                                <th class="px-6 py-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-xs text-gray-700" :class="{ 'opacity-60': loading }">
                            <tr v-if="!loading && courses.length === 0">
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">Tidak ada mata kuliah yang cocok dengan filter.</td>
                            </tr>
                            <tr v-for="course in courses" :key="course.id" class="transition-colors hover:bg-gray-50/60">
                                <td class="px-6 py-4">
                                    <div class="flex items-start gap-3">
                                        <span class="mt-0.5 rounded border border-blue-100 bg-blue-50 px-2 py-0.5 font-mono text-[10px] font-bold text-blue-700">{{ course.code }}</span>
                                        <span class="font-bold text-gray-900">{{ course.name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center font-semibold">{{ course.sks }}</td>
                                <td class="px-6 py-4 text-center text-gray-500">{{ course.semester }}</td>
                                <td class="px-6 py-4">
                                    <span class="font-semibold" :class="course.course_lecturers_count < course.parallel_class_count ? 'text-red-600' : 'text-gray-700'">
                                        {{ course.course_lecturers_count }} / {{ course.parallel_class_count }} kelas berdosen
                                    </span>
                                    <span class="block text-[11px] text-gray-400">Kapasitas {{ course.class_capacity }} mahasiswa/kelas</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button v-if="course.can_update" type="button" class="rounded p-1.5 text-gray-400 transition-colors hover:bg-primary/10 hover:text-primary" @click="openEdit(course)">Edit</button>
                                        <button v-if="course.can_delete" type="button" class="rounded p-1.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600" @click="askDelete(course)">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-model:page="page" :pagination="pagination" label="mata kuliah" />
            </Card>
        </div>

        <Modal :show="isModalOpen" max-width="2xl" @close="isModalOpen = false">
            <div class="flex items-center justify-between rounded-t-lg border-b border-gray-100 bg-gray-50 p-6">
                <h2 class="text-lg font-bold text-gray-900">{{ editing ? `Ubah Mata Kuliah ${editing.code}` : 'Tambah Mata Kuliah' }}</h2>
                <button type="button" class="text-gray-400 transition hover:text-gray-900" @click="isModalOpen = false">Tutup</button>
            </div>
            <form class="space-y-6 p-6" @submit.prevent="save">
                <Alert v-if="form.failure.value" :title="form.failure.value.message" />

                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <label for="course-code" class="mb-1.5 block text-sm font-semibold text-gray-900">Kode</label>
                        <input id="course-code" v-model="form.data.code" type="text" maxlength="20" :class="fieldClass" required />
                        <p v-if="form.errors.value.code" class="mt-1 text-xs text-red-600">{{ form.errors.value.code }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <label for="course-name" class="mb-1.5 block text-sm font-semibold text-gray-900">Nama Mata Kuliah</label>
                        <input id="course-name" v-model="form.data.name" type="text" :class="fieldClass" required />
                        <p v-if="form.errors.value.name" class="mt-1 text-xs text-red-600">{{ form.errors.value.name }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-6 md:grid-cols-4">
                    <div>
                        <label for="course-sks" class="mb-1.5 block text-sm font-semibold text-gray-900">SKS</label>
                        <select id="course-sks" v-model.number="form.data.sks" :class="fieldClass">
                            <option :value="2">2</option>
                            <option :value="3">3</option>
                            <option :value="4">4</option>
                        </select>
                        <p v-if="form.errors.value.sks" class="mt-1 text-xs text-red-600">{{ form.errors.value.sks }}</p>
                    </div>
                    <div>
                        <label for="course-semester" class="mb-1.5 block text-sm font-semibold text-gray-900">Semester</label>
                        <select id="course-semester" v-model.number="form.data.semester" :class="fieldClass">
                            <option v-for="s in semesters" :key="s" :value="s">{{ s }}</option>
                        </select>
                        <p v-if="form.errors.value.semester" class="mt-1 text-xs text-red-600">{{ form.errors.value.semester }}</p>
                    </div>
                    <div>
                        <label for="course-classes" class="mb-1.5 block text-sm font-semibold text-gray-900">Kelas Paralel</label>
                        <input id="course-classes" v-model.number="form.data.parallel_class_count" type="number" min="1" :class="fieldClass" required />
                        <p v-if="form.errors.value.parallel_class_count" class="mt-1 text-xs text-red-600">{{ form.errors.value.parallel_class_count }}</p>
                    </div>
                    <div>
                        <label for="course-capacity" class="mb-1.5 block text-sm font-semibold text-gray-900">Kapasitas Kelas</label>
                        <input id="course-capacity" v-model.number="form.data.class_capacity" type="number" min="1" :class="fieldClass" required />
                        <p v-if="form.errors.value.class_capacity" class="mt-1 text-xs text-red-600">{{ form.errors.value.class_capacity }}</p>
                    </div>
                </div>
                <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                    <Button type="button" variant="ghost" class="bg-gray-100 hover:bg-gray-200" @click="isModalOpen = false">Batal</Button>
                    <Button type="submit" :loading="form.processing.value">Simpan Mata Kuliah</Button>
                </div>
            </form>
        </Modal>

        <ConfirmDialog :show="deleteTarget !== null" title="Hapus mata kuliah?" :processing="deleteProcessing" :error="deleteError" @confirm="confirmDelete" @close="deleteTarget = null">
            Mata kuliah <strong>{{ deleteTarget?.code }}</strong> akan dihapus. Penghapusan ditolak selama masih ada dosen pengampu atau jadwal.
        </ConfirmDialog>
    </AppLayout>
</template>

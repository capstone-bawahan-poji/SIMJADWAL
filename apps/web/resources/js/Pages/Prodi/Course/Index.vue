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
import type { Course } from '@/types/models';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

type CourseStatus = 'active' | 'inactive';
type StoredCourseStatuses = Record<number, CourseStatus>;

const fieldClass = 'w-full rounded-xl border-gray-200 bg-gray-50 text-gray-900 text-sm focus:border-primary focus:ring-primary';
const studyProgramId = computed(() => usePage().props.auth.user.study_program_id);
const { submission, locked } = useOwnSubmission();
const courseStatusStorageKey = computed(() => `simjadwal:prodi:${studyProgramId.value}:course-statuses:v1`);
const courseStatuses = ref<StoredCourseStatuses>({});
const statusStorageError = ref<string | null>(null);

onMounted(() => {
    try {
        const stored = window.localStorage.getItem(courseStatusStorageKey.value);

        if (stored) {
            const parsed: unknown = JSON.parse(stored);

            if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
                throw new Error('Invalid course status data');
            }

            const statuses: StoredCourseStatuses = {};

            for (const [id, status] of Object.entries(parsed)) {
                const courseId = Number(id);

                if (Number.isInteger(courseId) && courseId > 0 && (status === 'active' || status === 'inactive')) {
                    statuses[courseId] = status;
                }
            }

            courseStatuses.value = statuses;
        }
    } catch {
        statusStorageError.value = 'Status mata kuliah lokal tidak dapat dibaca dari browser.';
    }
});

const { filters, items: courses, pagination, page, loading, error, reload } = usePaginatedList<
    Course,
    { q: string; semester: number | ''; study_program_id: number | null; is_tpb: boolean }
>('courses', { q: queryParam('q'), semester: '', study_program_id: studyProgramId.value, is_tpb: false }, 20, ['course_lecturers']);

const semesters = [1, 2, 3, 4, 5, 6, 7, 8];
const controlClass = 'rounded-lg border-gray-200 bg-white text-sm text-gray-700 focus:border-primary focus:ring-primary';
const flash = ref<string | null>(null);

function refresh(message: string): void {
    flash.value = message;
    void reload();
}

function statusOf(courseId: number): CourseStatus {
    return courseStatuses.value[courseId] ?? 'active';
}

// Create / edit modal.
const isModalOpen = ref(false);
const editing = ref<Course | null>(null);
const editStatus = ref<CourseStatus>('active');
const statusSaveError = ref<string | null>(null);
const form = useApiForm(() => ({
    code: '',
    name: '',
    sks: 3 as number,
    semester: 1 as number,
    parallel_class_count: 1 as number,
    class_capacity: 50 as number,
}));

function openCreate(): void {
    editing.value = null;
    editStatus.value = 'active';
    statusSaveError.value = null;
    form.reset();
    isModalOpen.value = true;
}

function openEdit(course: Course): void {
    editing.value = course;
    editStatus.value = statusOf(course.id);
    statusSaveError.value = null;
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
    const courseBeingEdited = editing.value;
    const saved = courseBeingEdited
        ? await form.submit<Course>('patch', `courses/${courseBeingEdited.id}`, {
              code: form.data.code,
              name: form.data.name,
              sks: form.data.sks,
              semester: form.data.semester,
          })
        : await form.submit<Course>('post', 'courses', { ...form.data, study_program_id: studyProgramId.value });

    if (saved) {
        if (courseBeingEdited && editStatus.value !== statusOf(courseBeingEdited.id)) {
            const updatedStatuses: StoredCourseStatuses = {
                ...courseStatuses.value,
                [courseBeingEdited.id]: editStatus.value,
            };

            try {
                window.localStorage.setItem(courseStatusStorageKey.value, JSON.stringify(updatedStatuses));
                courseStatuses.value = updatedStatuses;
                statusStorageError.value = null;
                statusSaveError.value = null;
            } catch {
                statusSaveError.value = 'Data mata kuliah tersimpan, tetapi status gagal disimpan di browser.';

                return;
            }
        }

        isModalOpen.value = false;
        refresh(`Mata kuliah ${saved.code} ${courseBeingEdited ? 'diperbarui' : 'ditambahkan'}.`);
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
        <div class="w-full space-y-6 p-6 md:p-8">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Data Mata Kuliah</h1>
                    <p class="mt-1 text-sm text-gray-600">Kelola daftar mata kuliah program studi.</p>
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <ProgramLockBanner v-if="locked" :submission="submission" />
                    <Button type="button" :disabled="locked" @click="openCreate">Tambah Mata Kuliah</Button>
                </div>
            </header>

            <ProgramLockBanner v-if="!locked" :submission="submission" />
            <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />

            <Panel>
                <div class="flex flex-col gap-3 border-b border-gray-200 p-4 md:flex-row md:items-center">
                    <input
                        v-model="filters.q"
                        type="search"
                        placeholder="Cari kode atau nama mata kuliah..."
                        aria-label="Cari mata kuliah"
                        :class="[controlClass, 'w-full placeholder-gray-400 md:w-72']"
                    />
                    <select v-model="filters.semester" :class="[controlClass, 'md:w-44']" aria-label="Filter semester">
                        <option value="">Semua Semester</option>
                        <option v-for="s in semesters" :key="s" :value="s">Semester {{ s }}</option>
                    </select>
                    <p class="text-sm text-gray-500 md:ml-auto"><span class="font-semibold tabular-nums text-gray-900">{{ pagination.total }}</span> mata kuliah</p>
                </div>

                <Alert v-if="error" :title="error.message" class="m-4" />
                <Alert v-if="statusStorageError" :title="statusStorageError" class="m-4" />

                <div class="w-full overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="whitespace-nowrap px-6 py-3">Kode</th>
                                <th class="px-6 py-3">Mata Kuliah</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">SKS</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">Semester</th>
                                <th class="whitespace-nowrap px-6 py-3 text-center">Status</th>
                                <th class="whitespace-nowrap px-6 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" :class="{ 'opacity-60': loading }">
                            <tr v-if="!loading && courses.length === 0">
                                <td colspan="6" class="px-6 py-10 text-center text-gray-500">Tidak ada mata kuliah yang cocok dengan filter.</td>
                            </tr>
                            <tr v-for="course in courses" :key="course.id" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-3.5 font-semibold tabular-nums text-gray-900">{{ course.code }}</td>
                                <td class="px-6 py-3.5 text-gray-900">{{ course.name }}</td>
                                <td class="px-6 py-3.5 text-right tabular-nums text-gray-700">{{ course.sks }}</td>
                                <td class="px-6 py-3.5 text-right tabular-nums text-gray-700">{{ course.semester }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-center">
                                    <span v-if="statusOf(course.id) === 'active'" class="inline-flex items-center gap-1.5 text-xs font-medium text-green-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-green-600"></span>
                                        Aktif
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500">
                                        <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                                        Nonaktif
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-center">
                                    <div class="flex justify-center gap-2">
                                        <Button variant="secondary" size="sm" @click="openEdit(course)">Edit</Button>
                                        <Button variant="primary" size="sm" @click="askDelete(course)">Hapus</Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-model:page="page" :pagination="pagination" label="mata kuliah" />
            </Panel>
        </div>

        <Modal :show="isModalOpen" max-width="2xl" close-button @close="isModalOpen = false">
            <div class="flex items-center justify-between rounded-t-lg border-b border-gray-100 bg-gray-50 py-6 pl-6 pr-14">
                <h2 class="text-lg font-bold text-gray-900">{{ editing ? `Ubah Mata Kuliah ${editing.code}` : 'Tambah Mata Kuliah' }}</h2>
            </div>
            <form class="space-y-6 p-6" @submit.prevent="save">
                <Alert v-if="form.failure.value" :title="form.failure.value.message" />
                <Alert v-if="statusSaveError" :title="statusSaveError" />

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
                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
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
                    <div v-if="editing">
                        <label for="course-status" class="mb-1.5 block text-sm font-semibold text-gray-900">Status</label>
                        <select id="course-status" v-model="editStatus" :class="fieldClass">
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                    <Button type="button" variant="secondary" @click="isModalOpen = false">Batal</Button>
                    <Button type="submit" :loading="form.processing.value">Simpan Mata Kuliah</Button>
                </div>
            </form>
        </Modal>

        <ConfirmDialog :show="deleteTarget !== null" title="Hapus mata kuliah?" :processing="deleteProcessing" :error="deleteError" @confirm="confirmDelete" @close="deleteTarget = null">
            Mata kuliah <strong>{{ deleteTarget?.code }}</strong> akan dihapus. Penghapusan ditolak selama masih ada dosen pengampu atau jadwal.
        </ConfirmDialog>
    </AppLayout>
</template>
<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ProgramLockBanner from '@/Components/constraint/ProgramLockBanner.vue';
import Alert from '@/Components/ui/Alert.vue';
import Card from '@/Components/ui/Card.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import { fetchAll, usePaginatedList } from '@/composables/useApiList';
import { useOwnSubmission } from '@/composables/useOwnSubmission';
import { api, toApiError } from '@/lib/api';
import { queryParam } from '@/lib/query';
import type { Course, Lecturer, TeachingAssignment } from '@/types/models';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const inertiaPage = usePage();
const studyProgramId = computed(() => inertiaPage.props.auth.user.study_program_id);
const { submission, locked, reload: reloadSubmission } = useOwnSubmission();

const { filters, items: courses, pagination, page, loading, error } = usePaginatedList<Course, { q: string; study_program_id: number | null; is_tpb: boolean }>(
    'courses',
    { q: queryParam('q'), study_program_id: studyProgramId.value, is_tpb: false },
);

// One lecturer per class. Lecturers and assignments of one program are few, so load them whole.
const lecturers = ref<Lecturer[]>([]);
const assignments = ref<TeachingAssignment[]>([]);
const loadError = ref<string | null>(null);
const flash = ref<string | null>(null);
const failure = ref<string | null>(null);
const saving = ref<string | null>(null);

const fetchAssignments = () => fetchAll<TeachingAssignment>('teaching-assignments', { study_program_id: studyProgramId.value });

async function loadMapping(): Promise<void> {
    try {
        [lecturers.value, assignments.value] = await Promise.all([
            fetchAll<Lecturer>('lecturers', { study_program_id: studyProgramId.value }),
            fetchAssignments(),
        ]);
        loadError.value = null;
    } catch (e) {
        loadError.value = toApiError(e).message;
    }
}

// Lecturers do not change while mapping, so a save only refreshes the assignments.
async function reloadAssignments(): Promise<void> {
    try {
        assignments.value = await fetchAssignments();
        loadError.value = null;
    } catch (e) {
        loadError.value = toApiError(e).message;
    }
}

onMounted(loadMapping);

const classKey = (courseId: number, classNumber: number) => `${courseId}-${classNumber}`;
const assignmentByClass = computed(() => new Map(assignments.value.map((a) => [classKey(a.course_id, a.class_number), a])));

function assignmentOf(course: Course, classNumber: number): TeachingAssignment | undefined {
    return assignmentByClass.value.get(classKey(course.id, classNumber));
}

async function assign(course: Course, classNumber: number, value: string): Promise<void> {
    const current = assignmentOf(course, classNumber);
    saving.value = classKey(course.id, classNumber);
    failure.value = null;

    try {
        if (value === '') {
            if (current) await api.delete(`teaching-assignments/${current.id}`);
        } else if (current) {
            await api.patch(`teaching-assignments/${current.id}`, { lecturer_id: Number(value) });
        } else {
            await api.post('teaching-assignments', { course_id: course.id, class_number: classNumber, lecturer_id: Number(value) });
        }

        flash.value = `Kelas ${course.code} ${classNumber} diperbarui.`;
    } catch (e) {
        failure.value = toApiError(e).message;
    } finally {
        saving.value = null;
        await reloadAssignments();
        void reloadSubmission();
    }
}

// Info only: no hard limit on SKS per lecturer.
const loads = computed(() => {
    const totals = new Map<number, { classes: number; sks: number }>();

    for (const a of assignments.value) {
        const row = totals.get(a.lecturer_id) ?? { classes: 0, sks: 0 };
        row.classes++;
        row.sks += a.course.sks;
        totals.set(a.lecturer_id, row);
    }

    return lecturers.value
        .map((l) => ({ lecturer: l, ...(totals.get(l.id) ?? { classes: 0, sks: 0 }) }))
        .sort((a, b) => b.sks - a.sks);
});

const mappedClasses = computed(() => assignments.value.length);
const fieldClass = 'w-full rounded-xl border-gray-200 bg-gray-50 text-gray-900 text-xs focus:border-primary focus:ring-primary';
</script>

<template>
    <Head title="Mapping Dosen ke Mata Kuliah" />

    <AppLayout>
        <div class="p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Mapping Dosen ke Mata Kuliah</h1>
                <p class="mt-1 text-sm text-gray-500">Pilih satu dosen pengampu untuk tiap kelas paralel. Perubahan tersimpan saat dosen dipilih.</p>
            </div>

            <ProgramLockBanner :submission="submission" />
            <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />
            <Alert v-if="failure" :title="failure" dismissible @dismiss="failure = null" />
            <Alert v-if="loadError || error" :title="loadError ?? error?.message ?? ''" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <StatCard label="Kelas Terpetakan" :value="mappedClasses" />
                <StatCard label="Mata Kuliah Belum Lengkap" :value="submission?.incomplete_courses_count ?? '-'" />
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">
                    <div class="flex justify-end">
                        <input v-model="filters.q" type="search" placeholder="Cari kode atau nama mata kuliah..." aria-label="Cari mata kuliah" class="w-full rounded-xl border-gray-200 bg-white text-xs placeholder-gray-400 focus:border-primary focus:ring-primary md:w-72" />
                    </div>

                    <p v-if="!loading && courses.length === 0" class="rounded-2xl border border-gray-100 bg-white p-8 text-center text-sm text-gray-500">Tidak ada mata kuliah.</p>

                    <Card v-for="course in courses" :key="course.id" class="!p-0 border border-gray-100 shadow-sm" :class="{ 'opacity-60': loading }">
                        <div class="flex items-start justify-between gap-3 border-b border-gray-100 p-5">
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">
                                    <span class="mr-2 rounded border border-blue-100 bg-blue-50 px-2 py-0.5 font-mono text-[10px] text-blue-700">{{ course.code }}</span>{{ course.name }}
                                </h3>
                                <p class="mt-1 text-[11px] text-gray-500">{{ course.sks }} SKS • Semester {{ course.semester }} • {{ course.parallel_class_count }} kelas paralel</p>
                            </div>
                        </div>
                        <ul class="divide-y divide-gray-100">
                            <li v-for="n in course.parallel_class_count" :key="n" class="flex items-center gap-4 px-5 py-3">
                                <span class="w-16 shrink-0 text-xs font-semibold text-gray-700">Kelas {{ n }}</span>
                                <select
                                    :value="assignmentOf(course, n)?.lecturer_id ?? ''"
                                    :disabled="locked || saving === classKey(course.id, n)"
                                    :class="fieldClass"
                                    :aria-label="`Dosen ${course.code} kelas ${n}`"
                                    @change="assign(course, n, ($event.target as HTMLSelectElement).value)"
                                >
                                    <option value="">Belum ada dosen</option>
                                    <option v-for="lecturer in lecturers" :key="lecturer.id" :value="lecturer.id">{{ lecturer.name }}</option>
                                </select>
                            </li>
                        </ul>
                    </Card>

                    <Pagination v-model:page="page" :pagination="pagination" label="mata kuliah" />
                </div>

                <Card class="h-fit !p-0 border border-gray-100 shadow-sm">
                    <div class="border-b border-gray-100 p-5">
                        <h2 class="text-sm font-bold text-gray-900">Beban SKS Dosen</h2>
                        <p class="text-[11px] text-gray-400">Informasi saja, tanpa batas.</p>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="row in loads" :key="row.lecturer.id" class="flex items-center justify-between gap-3 px-5 py-3 text-xs">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-gray-900">{{ row.lecturer.name }}</p>
                                <p class="text-[11px] text-gray-400">{{ row.classes }} kelas</p>
                            </div>
                            <span class="shrink-0 font-bold text-gray-800">{{ row.sks }} SKS</span>
                        </li>
                        <li v-if="loads.length === 0" class="px-5 py-6 text-center text-xs text-gray-500">Belum ada dosen.</li>
                    </ul>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

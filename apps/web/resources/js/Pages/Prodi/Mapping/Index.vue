<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ProgramLockBanner from '@/Components/constraint/ProgramLockBanner.vue';
import Modal from '@/Components/Modal.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import { fetchAll, usePaginatedList } from '@/composables/useApiList';
import { useOwnSubmission } from '@/composables/useOwnSubmission';
import { api, toApiError } from '@/lib/api';
import { queryParam } from '@/lib/query';
import type { Course, Lecturer, TeachingAssignment } from '@/types/models';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const inertiaPage = usePage();
const studyProgramId = computed(() => inertiaPage.props.auth.user.study_program_id);
const { submission, locked, reload: reloadSubmission } = useOwnSubmission();

const { filters, items: courses, pagination, page, loading, error } = usePaginatedList<Course, { q: string; study_program_id: number | null; is_tpb: boolean }>(
    'courses',
    { q: queryParam('q'), study_program_id: studyProgramId.value, is_tpb: false },
    20,
    ['course_lecturers'],
);

// One lecturer per class. Lecturers and assignments of one program are few, so load them whole.
const lecturers = ref<Lecturer[]>([]);
const assignments = ref<TeachingAssignment[]>([]);
const loadError = ref<string | null>(null);
const flash = ref<string | null>(null);
const failure = ref<string | null>(null);
const saving = ref<string | null>(null);
type ClassSettings = { sks: number; class_capacity: number };
const classSettings = reactive<Record<string, ClassSettings>>({});
const editingClass = ref<{ course: Course; classNumber: number } | null>(null);
const classSettingsDraft = reactive<ClassSettings>({ sks: 3, class_capacity: 40 });

const fetchAssignments = () => fetchAll<TeachingAssignment>('teaching-assignments', { study_program_id: studyProgramId.value });
const classKey = (courseId: number, classNumber: number) => `${courseId}-${classNumber}`;

function classLabel(classNumber: number): string {
    let label = '';

    for (let number = classNumber; number > 0; number = Math.floor((number - 1) / 26)) {
        label = String.fromCharCode(65 + ((number - 1) % 26)) + label;
    }

    return label;
}

function classSettingsFor(course: Course, classNumber: number): ClassSettings {
    const key = classKey(course.id, classNumber);

    return (classSettings[key] ??= {
        sks: course.sks,
        class_capacity: course.class_capacity,
    });
}

watch(
    courses,
    (items) => {
        items.forEach((course) => {
            for (let classNumber = 1; classNumber <= course.parallel_class_count; classNumber++) {
                classSettingsFor(course, classNumber);
            }
        });
    },
    { immediate: true },
);

function openClassSettings(course: Course, classNumber: number): void {
    const settings = classSettingsFor(course, classNumber);
    classSettingsDraft.sks = settings.sks;
    classSettingsDraft.class_capacity = settings.class_capacity;
    editingClass.value = { course, classNumber };
}

function saveClassSettings(): void {
    const target = editingClass.value;

    if (!target) return;

    classSettings[classKey(target.course.id, target.classNumber)] = {
        sks: classSettingsDraft.sks,
        class_capacity: classSettingsDraft.class_capacity,
    };
    flash.value = `Pengaturan Paralel ${classLabel(target.classNumber)} ${target.course.code} disimpan sementara di tampilan ini.`;
    editingClass.value = null;
}

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

        flash.value = `Paralel ${classLabel(classNumber)} ${course.code} diperbarui.`;
    } catch (e) {
        failure.value = toApiError(e).message;
    } finally {
        saving.value = null;
        await reloadAssignments();
        void reloadSubmission();
    }
}

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
const fieldClass = 'w-full rounded-xl border-gray-200 bg-gray-50 text-gray-900 text-sm focus:border-primary focus:ring-primary';
</script>

<template>
    <Head title="Mapping Dosen ke Mata Kuliah" />

    <AppLayout>
        <div class="mx-auto w-full max-w-[1600px] space-y-6 p-6 md:p-8">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Mapping Dosen ke Mata Kuliah</h1>
                    <p class="mt-1 text-sm text-gray-600">Atur dosen pengampu, SKS, dan kapasitas untuk setiap paralel mata kuliah.</p>
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <ProgramLockBanner v-if="locked" :submission="submission" />
                    <Link
                        :href="route('prodi.courses.import')"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground shadow-md shadow-primary/20 transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    >Import Mata Kuliah</Link>
                </div>
            </header>
            <ProgramLockBanner v-if="!locked" :submission="submission" />
            <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />
            <Alert v-if="failure" :title="failure" dismissible @dismiss="failure = null" />
            <Alert v-if="loadError || error" :title="loadError ?? error?.message ?? ''" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <StatCard label="Kelas Terpetakan" :value="mappedClasses" />
                <StatCard label="Mata Kuliah Belum Lengkap" :value="submission?.incomplete_courses_count ?? '-'" />
            </div>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
                <div class="space-y-4">
                    <div class="flex justify-start">
                        <input v-model="filters.q" type="search" placeholder="Cari kode atau nama mata kuliah..." aria-label="Cari mata kuliah" class="w-full rounded-xl border-gray-200 bg-white text-sm placeholder-gray-400 focus:border-primary focus:ring-primary md:w-80" />
                    </div>

                    <p v-if="!loading && courses.length === 0" class="rounded-2xl border border-gray-100 bg-white p-8 text-center text-sm text-gray-500">Tidak ada mata kuliah.</p>

                    <Card v-for="course in courses" :key="course.id" class="!p-0 border border-gray-100 shadow-sm" :class="{ 'opacity-60': loading }">
                        <div class="flex flex-col gap-3 border-b border-gray-100 bg-gray-50/70 p-5">
                            <div>
                                <h3 class="text-base font-bold text-gray-900">
                                    <span class="mr-2 rounded border border-blue-100 bg-blue-50 px-2 py-0.5 font-mono text-xs text-blue-700">{{ course.code }}</span>{{ course.name }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-500">Semester {{ course.semester }} · {{ course.parallel_class_count }} paralel</p>
                            </div>
                        </div>
                        <ul class="divide-y divide-gray-100">
                            <li v-for="n in course.parallel_class_count" :key="n" class="grid gap-3 px-5 py-4 sm:grid-cols-[minmax(100px,.7fr)_minmax(90px,.55fr)_minmax(130px,.8fr)_minmax(180px,1.4fr)_auto] sm:items-center">
                                <span class="text-base font-semibold text-gray-800">Paralel {{ classLabel(n) }}</span>
                                <span class="text-sm text-gray-700">
                                    <span class="block text-xs font-medium uppercase tracking-wide text-gray-400">SKS</span>
                                    {{ classSettingsFor(course, n).sks }}
                                </span>
                                <span class="text-sm text-gray-700">
                                    <span class="block text-xs font-medium uppercase tracking-wide text-gray-400">Kapasitas</span>
                                    {{ classSettingsFor(course, n).class_capacity }} mahasiswa
                                </span>
                                <label class="text-sm font-medium text-gray-600">
                                    Dosen pengampu
                                    <select
                                        :value="assignmentOf(course, n)?.lecturer_id ?? ''"
                                        :disabled="locked || saving === classKey(course.id, n)"
                                        :class="[fieldClass, assignmentOf(course, n) ? '' : '!border-amber-400']"
                                        :aria-label="`Dosen ${course.code} paralel ${classLabel(n)}`"
                                        @change="assign(course, n, ($event.target as HTMLSelectElement).value)"
                                    >
                                        <option value="">Belum ada dosen</option>
                                        <option v-for="lecturer in lecturers" :key="lecturer.id" :value="lecturer.id">{{ lecturer.name }}</option>
                                    </select>
                                </label>
                                <Button variant="secondary" size="md" :disabled="locked" @click="openClassSettings(course, n)">Edit</Button>
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

        <Modal :show="editingClass !== null" max-width="md" close-button @close="editingClass = null">
            <div class="border-b border-gray-100 bg-gray-50 px-6 py-5">
                <h2 class="text-lg font-bold text-gray-900">
                    Ubah Paralel {{ editingClass ? classLabel(editingClass.classNumber) : '' }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">{{ editingClass?.course.code }} · {{ editingClass?.course.name }}</p>
            </div>
            <form class="space-y-5 p-6" @submit.prevent="saveClassSettings">
                <label class="block text-sm font-semibold text-gray-900">
                    SKS
                    <select v-model.number="classSettingsDraft.sks" :class="[fieldClass, 'mt-1.5']">
                        <option :value="2">2 SKS</option>
                        <option :value="3">3 SKS</option>
                        <option :value="4">4 SKS</option>
                    </select>
                </label>
                <label class="block text-sm font-semibold text-gray-900">
                    Kapasitas
                    <input v-model.number="classSettingsDraft.class_capacity" type="number" min="1" max="1000" :class="[fieldClass, 'mt-1.5']" required />
                </label>
                <p class="text-sm text-gray-500" role="note">Belum tersimpan permanen.</p>
                <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                    <Button type="button" variant="secondary" @click="editingClass = null">Batal</Button>
                    <Button type="submit">Simpan</Button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Panel from '@/Components/ui/Panel.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import { fetchAll, usePaginatedList } from '@/composables/useApiList';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import { useApiForm } from '@/composables/useApiForm';
import { api, destroy, toApiError, type ApiError } from '@/lib/api';
import { formatTime } from '@/lib/labels';
import { queryParam } from '@/lib/query';
import type {
    Course,
    Lecturer,
    Room,
    StudyProgramSummary,
    TeachingAssignment,
    TimeSlot,
    TpbGroup,
} from '@/types/models';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';

type Tab = 'courses' | 'assignments' | 'groups';
type AssignmentDraft = { lecturer_id: string; tpb_group_id: string; room_id: string };
type GroupFilters = { course_id: number | ''; study_program_id: number | ''; time_slot_id: number | '' };
type CourseFilters = { q: string; semester: number | ''; study_program_id: null; is_tpb: boolean };

const fieldClass = 'w-full rounded-xl border-gray-200 bg-white text-sm text-gray-900 focus:border-primary focus:ring-primary';
const controlClass = 'rounded-lg border-gray-200 bg-white text-sm text-gray-700 focus:border-primary focus:ring-primary';
const semesters = [1, 2, 3, 4, 5, 6, 7, 8];
const days: Record<number, string> = { 1: 'Senin', 2: 'Selasa', 3: 'Rabu', 4: 'Kamis', 5: 'Jumat', 6: 'Sabtu', 7: 'Minggu' };
const activeTab = ref<Tab>('courses');
const flash = ref<string | null>(null);
const referencesLoading = ref(false);
const referencesFailure = ref<ApiError | null>(null);
const lecturers = ref<Lecturer[]>([]);
const rooms = ref<Room[]>([]);
const timeSlots = ref<TimeSlot[]>([]);
const studyPrograms = ref<StudyProgramSummary[]>([]);
const allTpbCourses = ref<Course[]>([]);
const selectedCourseId = ref<number | ''>('');
const assignments = ref<TeachingAssignment[]>([]);
const groupsForAssignment = ref<TpbGroup[]>([]);
const assignmentDrafts = reactive<Record<string, AssignmentDraft>>({});
const assignmentLoading = ref(false);
const assignmentFailure = ref<ApiError | null>(null);
const assignmentSaving = ref<string | null>(null);

const {
    filters: courseFilters,
    items: courses,
    pagination: coursePagination,
    page: coursePage,
    loading: coursesLoading,
    error: coursesError,
    reload: reloadCourses,
} = usePaginatedList<Course, CourseFilters>(
    'courses',
    { q: queryParam('q'), semester: '', study_program_id: null, is_tpb: true },
    20,
    ['course_lecturers'],
);

const {
    filters: groupFilters,
    items: groups,
    pagination: groupPagination,
    page: groupPage,
    loading: groupsLoading,
    error: groupsError,
    reload: reloadGroups,
} = usePaginatedList<TpbGroup, GroupFilters>(
    'tpb-groups',
    { course_id: '', study_program_id: '', time_slot_id: '' },
    20,
    ['courses', 'course_lecturers', 'study_programs', 'time_slots'],
);

const courseForm = useApiForm(() => ({
    code: '',
    name: '',
    sks: 3 as number,
    semester: 1 as number,
    parallel_class_count: 1 as number,
    class_capacity: 50 as number,
}));
const groupForm = useApiForm(() => ({
    course_id: 0,
    code: '',
    time_slot_id: null as number | null,
    study_program_ids: [] as number[],
}));

const courseModalOpen = ref(false);
const editingCourse = ref<Course | null>(null);
const groupModalOpen = ref(false);
const editingGroup = ref<TpbGroup | null>(null);
const deleteCourseTarget = ref<Course | null>(null);
const deleteGroupTarget = ref<TpbGroup | null>(null);
const deleting = ref(false);
const deleteFailure = ref<string | null>(null);
const hasCourseActions = computed(() => courses.value.some((course) => course.can_update || course.can_delete));
const classAssignmentCount = computed(() => assignments.value.length);
const assignedLecturerCount = computed(() => new Set(assignments.value.map((item) => item.lecturer_id)).size);
const selectedCourse = computed(() => allTpbCourses.value.find((course) => course.id === selectedCourseId.value));
const filteredAssignmentGroups = computed(() => groupsForAssignment.value.filter((group) => group.course_id === selectedCourseId.value));

function groupTimeLabel(group: TpbGroup): string {
    const slot = group.time_slot;
    if (!slot) return 'Belum ditempatkan';

    return `${days[slot.day] ?? slot.day}, sesi ${slot.session} (${formatTime(slot.start_time)}–${formatTime(slot.end_time)})`;
}

function slotLabel(slot: TimeSlot): string {
    return `${slot.day_label}, sesi ${slot.session} · ${formatTime(slot.start_time)}–${formatTime(slot.end_time)}`;
}

function assignmentKey(courseId: number, classNumber: number): string {
    return `${courseId}:${classNumber}`;
}

function assignmentFor(courseId: number, classNumber: number): TeachingAssignment | undefined {
    return assignments.value.find((item) => item.course_id === courseId && item.class_number === classNumber);
}

function draftFor(courseId: number, classNumber: number): AssignmentDraft {
    const key = assignmentKey(courseId, classNumber);
    if (!assignmentDrafts[key]) {
        const current = assignmentFor(courseId, classNumber);
        assignmentDrafts[key] = {
            lecturer_id: current ? String(current.lecturer_id) : '',
            tpb_group_id: current?.tpb_group_id ? String(current.tpb_group_id) : '',
            room_id: current?.room_id ? String(current.room_id) : '',
        };
    }

    return assignmentDrafts[key];
}

function assignmentIsDirty(courseId: number, classNumber: number): boolean {
    const current = assignmentFor(courseId, classNumber);
    const draft = draftFor(courseId, classNumber);

    return draft.lecturer_id !== (current ? String(current.lecturer_id) : '')
        || draft.tpb_group_id !== (current?.tpb_group_id ? String(current.tpb_group_id) : '')
        || draft.room_id !== (current?.room_id ? String(current.room_id) : '');
}

async function loadReferences(): Promise<void> {
    referencesLoading.value = true;
    referencesFailure.value = null;

    try {
        const [courseRows, lecturerRows, roomRows, slotRows, programRows] = await Promise.all([
            fetchAll<Course>('courses', { is_tpb: true }),
            fetchAll<Lecturer>('lecturers'),
            fetchAll<Room>('rooms'),
            fetchAll<TimeSlot>('time-slots'),
            fetchAll<StudyProgramSummary>('study-programs'),
        ]);
        allTpbCourses.value = courseRows;
        lecturers.value = lecturerRows;
        rooms.value = roomRows;
        timeSlots.value = slotRows;
        studyPrograms.value = programRows;
        if (selectedCourseId.value === '' && courseRows.length > 0) selectedCourseId.value = courseRows[0].id;
    } catch (error) {
        referencesFailure.value = toApiError(error);
    } finally {
        referencesLoading.value = false;
    }
}

async function loadAssignments(courseId: number | ''): Promise<void> {
    assignments.value = [];
    groupsForAssignment.value = [];
    assignmentFailure.value = null;
    Object.keys(assignmentDrafts).forEach((key) => delete assignmentDrafts[key]);

    if (courseId === '') return;

    assignmentLoading.value = true;
    try {
        const [assignmentRows, groupRows] = await Promise.all([
            fetchAll<TeachingAssignment>('teaching-assignments', { course_id: courseId }),
            fetchAll<TpbGroup>('tpb-groups', { course_id: courseId }),
        ]);
        assignments.value = assignmentRows;
        groupsForAssignment.value = groupRows;
    } catch (error) {
        assignmentFailure.value = toApiError(error);
    } finally {
        assignmentLoading.value = false;
    }
}

watch(selectedCourseId, (courseId) => void loadAssignments(courseId));

onMounted(() => {
    void loadReferences();
});

onResourcesChanged(['courses', 'course_lecturers', 'tpb_groups', 'study_programs', 'time_slots', 'rooms', 'lecturers'], () => {
    void loadReferences();
    void reloadCourses();
    void reloadGroups();
    if (activeTab.value === 'assignments') void loadAssignments(selectedCourseId.value);
});

function openCreateCourse(): void {
    editingCourse.value = null;
    courseForm.reset();
    courseModalOpen.value = true;
}

function openEditCourse(course: Course): void {
    editingCourse.value = course;
    courseForm.reset({
        code: course.code,
        name: course.name,
        sks: course.sks,
        semester: course.semester,
        parallel_class_count: course.parallel_class_count,
        class_capacity: course.class_capacity,
    });
    courseModalOpen.value = true;
}

async function saveCourse(): Promise<void> {
    const payload = { ...courseForm.data, is_tpb: true };
    const savedCourse = editingCourse.value
        ? await courseForm.submit<Course>('patch', `courses/${editingCourse.value.id}`, payload)
        : await courseForm.submit<Course>('post', 'courses', payload);

    if (!savedCourse) return;

    courseModalOpen.value = false;
    flash.value = `Mata kuliah TPB ${savedCourse.code} ${editingCourse.value ? 'diperbarui' : 'ditambahkan'}.`;
    await Promise.all([reloadCourses(), loadReferences()]);
}

function openCreateGroup(): void {
    editingGroup.value = null;
    groupForm.reset({ course_id: Number(groupFilters.course_id) || allTpbCourses.value[0]?.id || 0 });
    groupModalOpen.value = true;
}

function openEditGroup(group: TpbGroup): void {
    editingGroup.value = group;
    groupForm.reset({
        course_id: group.course_id,
        code: group.code,
        time_slot_id: group.time_slot_id,
        study_program_ids: group.study_programs.map((program) => program.id),
    });
    groupModalOpen.value = true;
}

function toggleStudyProgram(programId: number, checked: boolean): void {
    const selected = new Set(groupForm.data.study_program_ids);
    if (checked) selected.add(programId);
    else selected.delete(programId);
    groupForm.data.study_program_ids = [...selected];
}

async function saveGroup(): Promise<void> {
    const payload = { ...groupForm.data };
    const savedGroup = editingGroup.value
        ? await groupForm.submit<TpbGroup>('patch', `tpb-groups/${editingGroup.value.id}`, payload)
        : await groupForm.submit<TpbGroup>('post', 'tpb-groups', payload);

    if (!savedGroup) return;

    groupModalOpen.value = false;
    flash.value = `Grup ${savedGroup.course.code} ${savedGroup.code} ${editingGroup.value ? 'diperbarui' : 'ditambahkan'}.`;
    await Promise.all([reloadGroups(), loadReferences()]);
    if (activeTab.value === 'assignments') await loadAssignments(selectedCourseId.value);
}

async function saveAssignment(course: Course, classNumber: number): Promise<void> {
    const key = assignmentKey(course.id, classNumber);
    const draft = draftFor(course.id, classNumber);
    const current = assignmentFor(course.id, classNumber);
    if (!draft.lecturer_id) {
        assignmentFailure.value = { status: 422, message: 'Pilih dosen pengampu sebelum menyimpan kelas.', code: 'VALIDATION_FAILED', errors: {} };
        return;
    }

    assignmentSaving.value = key;
    assignmentFailure.value = null;
    try {
        const payload = {
            lecturer_id: Number(draft.lecturer_id),
            tpb_group_id: draft.tpb_group_id ? Number(draft.tpb_group_id) : null,
            room_id: draft.room_id ? Number(draft.room_id) : null,
        };
        if (current) await api.patch(`teaching-assignments/${current.id}`, payload);
        else await api.post('teaching-assignments', { course_id: course.id, class_number: classNumber, ...payload });

        flash.value = `Pengampu kelas ${course.code} ${String.fromCharCode(64 + classNumber)} disimpan.`;
        await loadAssignments(course.id);
    } catch (error) {
        assignmentFailure.value = toApiError(error);
    } finally {
        assignmentSaving.value = null;
    }
}

async function removeAssignment(course: Course, classNumber: number): Promise<void> {
    const current = assignmentFor(course.id, classNumber);
    if (!current) return;

    const key = assignmentKey(course.id, classNumber);
    assignmentSaving.value = key;
    assignmentFailure.value = null;
    try {
        await api.delete(`teaching-assignments/${current.id}`);
        flash.value = `Pengampu kelas ${course.code} dilepas.`;
        await loadAssignments(course.id);
    } catch (error) {
        assignmentFailure.value = toApiError(error);
    } finally {
        assignmentSaving.value = null;
    }
}

function askDeleteCourse(course: Course): void {
    deleteCourseTarget.value = course;
    deleteGroupTarget.value = null;
    deleteFailure.value = null;
}

function askDeleteGroup(group: TpbGroup): void {
    deleteGroupTarget.value = group;
    deleteCourseTarget.value = null;
    deleteFailure.value = null;
}

async function confirmDelete(): Promise<void> {
    const course = deleteCourseTarget.value;
    const group = deleteGroupTarget.value;
    if (!course && !group) return;

    deleting.value = true;
    const failure = await destroy(course ? `courses/${course.id}` : `tpb-groups/${group?.id}`);
    deleting.value = false;

    if (failure) {
        deleteFailure.value = failure.message;
        return;
    }

    if (course) {
        flash.value = `Mata kuliah TPB ${course.code} dihapus.`;
        await Promise.all([reloadCourses(), loadReferences()]);
    } else if (group) {
        flash.value = `Grup ${group.course.code} ${group.code} dihapus.`;
        await Promise.all([reloadGroups(), loadReferences()]);
        if (activeTab.value === 'assignments') await loadAssignments(selectedCourseId.value);
    }

    deleteCourseTarget.value = null;
    deleteGroupTarget.value = null;
}
</script>

<template>
    <Head title="Kelola Data TPB" />

    <AppLayout>
        <div class="mx-auto w-full max-w-7xl space-y-6 p-6 md:p-8">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Kelola Data TPB</h1>
                    <p class="mt-1 text-sm text-gray-500">Kelola mata kuliah bersama, kelas pengampu, dan grup peserta TPB tingkat institut.</p>
                </div>
                <Button v-if="activeTab === 'courses'" type="button" @click="openCreateCourse">Tambah Mata Kuliah TPB</Button>
                <Button v-else-if="activeTab === 'groups'" type="button" :disabled="referencesLoading || allTpbCourses.length === 0" @click="openCreateGroup">Tambah Grup TPB</Button>
            </header>

            <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />
            <Alert v-if="referencesFailure" :title="referencesFailure.message" />

            <section class="grid gap-4 sm:grid-cols-3" aria-label="Ringkasan data TPB">
                <StatCard label="Mata Kuliah TPB" :value="coursePagination.total" />
                <StatCard label="Grup TPB" :value="groupPagination.total" />
                <StatCard label="Kelas dengan pengampu" :value="activeTab === 'assignments' ? classAssignmentCount : 'Pilih tab pengampu'" />
            </section>

            <div class="flex flex-wrap gap-2 border-b border-gray-200" role="tablist" aria-label="Kelola data TPB">
                <button
                    v-for="tab in [
                        { id: 'courses', label: 'Mata Kuliah TPB' },
                        { id: 'assignments', label: 'Kelas & Pengampu' },
                        { id: 'groups', label: 'Grup TPB' },
                    ] as const"
                    :key="tab.id"
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === tab.id"
                    class="border-b-2 px-4 py-3 text-sm font-semibold transition-colors"
                    :class="activeTab === tab.id ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-800'"
                    @click="activeTab = tab.id"
                >
                    {{ tab.label }}
                </button>
            </div>

            <Panel v-if="activeTab === 'courses'">
                <div class="flex flex-col gap-3 border-b border-gray-200 p-4 md:flex-row md:items-center">
                    <input v-model="courseFilters.q" type="search" placeholder="Cari kode atau nama mata kuliah..." aria-label="Cari mata kuliah TPB" :class="[controlClass, 'w-full md:w-72']" />
                    <select v-model="courseFilters.semester" :class="[controlClass, 'md:w-44']" aria-label="Filter semester">
                        <option value="">Semua Semester</option>
                        <option v-for="semester in semesters" :key="semester" :value="semester">Semester {{ semester }}</option>
                    </select>
                    <p class="text-sm text-gray-500 md:ml-auto"><span class="font-semibold tabular-nums text-gray-900">{{ coursePagination.total }}</span> mata kuliah TPB</p>
                </div>

                <Alert v-if="coursesError" :title="coursesError.message" class="m-4" />
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="whitespace-nowrap px-6 py-3">Kode</th>
                                <th class="px-6 py-3">Mata Kuliah</th>
                                <th class="px-6 py-3 text-right">SKS</th>
                                <th class="px-6 py-3 text-right">Semester</th>
                                <th class="px-6 py-3 text-right">Kelas Paralel</th>
                                <th class="px-6 py-3 text-right">Kapasitas</th>
                                <th v-if="hasCourseActions" class="px-6 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" :class="{ 'opacity-60': coursesLoading }">
                            <tr v-if="!coursesLoading && courses.length === 0">
                                <td :colspan="hasCourseActions ? 7 : 6" class="px-6 py-10 text-center text-gray-500">Belum ada mata kuliah TPB atau tidak cocok dengan filter.</td>
                            </tr>
                            <tr v-for="course in courses" :key="course.id" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-3.5 font-semibold text-gray-900">{{ course.code }}</td>
                                <td class="px-6 py-3.5 text-gray-900">{{ course.name }}</td>
                                <td class="px-6 py-3.5 text-right tabular-nums text-gray-700">{{ course.sks }}</td>
                                <td class="px-6 py-3.5 text-right tabular-nums text-gray-700">{{ course.semester }}</td>
                                <td class="px-6 py-3.5 text-right tabular-nums text-gray-700">{{ course.parallel_class_count }}</td>
                                <td class="px-6 py-3.5 text-right tabular-nums text-gray-700">{{ course.class_capacity }}</td>
                                <td v-if="hasCourseActions" class="whitespace-nowrap px-6 py-3.5 text-right">
                                    <div class="flex justify-end gap-4">
                                        <button v-if="course.can_update" type="button" class="font-medium text-gray-700 hover:text-primary hover:underline" @click="openEditCourse(course)">Edit</button>
                                        <button v-if="course.can_delete" type="button" class="font-medium text-red-600 hover:underline" @click="askDeleteCourse(course)">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Pagination v-model:page="coursePage" :pagination="coursePagination" label="mata kuliah TPB" />
            </Panel>

            <Panel v-else-if="activeTab === 'assignments'">
                <div class="border-b border-gray-200 p-4">
                    <label class="block max-w-xl space-y-1 text-xs font-semibold text-gray-600">
                        Mata kuliah TPB
                        <select v-model="selectedCourseId" class="block" :class="fieldClass" :disabled="referencesLoading || allTpbCourses.length === 0">
                            <option v-if="allTpbCourses.length === 0" :value="''">Belum ada mata kuliah TPB</option>
                            <option v-for="course in allTpbCourses" :key="course.id" :value="course.id">{{ course.code }} · {{ course.name }}</option>
                        </select>
                    </label>
                    <p v-if="selectedCourseId !== ''" class="mt-2 text-xs text-gray-500">
                        {{ assignedLecturerCount }} dosen mengampu · {{ classAssignmentCount }} kelas sudah memiliki pengampu.
                    </p>
                </div>

                <Alert v-if="assignmentFailure" :title="assignmentFailure.message" class="m-4" />
                <div v-if="assignmentLoading || referencesLoading" class="p-8 text-center text-sm text-gray-500">Memuat kelas dan pengampu…</div>
                <div v-else-if="selectedCourseId === ''" class="p-8 text-center text-sm text-gray-500">Tambahkan mata kuliah TPB terlebih dahulu.</div>
                <div v-else-if="selectedCourse" class="divide-y divide-gray-100">
                    <div v-for="classNumber in selectedCourse.parallel_class_count" :key="classNumber" class="grid gap-4 p-5 lg:grid-cols-[100px_1fr_1fr_1fr_auto] lg:items-end">
                        <div>
                            <p class="text-xs font-semibold text-gray-500">Kelas</p>
                            <p class="mt-1 font-bold text-gray-900">{{ String.fromCharCode(64 + classNumber) }}</p>
                        </div>
                        <label class="space-y-1 text-xs font-semibold text-gray-600">
                            Dosen pengampu
                            <select v-model="draftFor(selectedCourse.id, classNumber).lecturer_id" :class="fieldClass" :disabled="Boolean(assignmentSaving)">
                                <option value="">Pilih dosen…</option>
                                <option v-for="lecturer in lecturers" :key="lecturer.id" :value="String(lecturer.id)">{{ lecturer.name }}</option>
                            </select>
                        </label>
                        <label class="space-y-1 text-xs font-semibold text-gray-600">
                            Grup TPB
                            <select v-model="draftFor(selectedCourse.id, classNumber).tpb_group_id" :class="fieldClass" :disabled="Boolean(assignmentSaving)">
                                <option value="">Belum ditempatkan</option>
                                <option v-for="group in filteredAssignmentGroups" :key="group.id" :value="String(group.id)">{{ group.code }} · {{ group.study_programs.length }} prodi</option>
                            </select>
                        </label>
                        <label class="space-y-1 text-xs font-semibold text-gray-600">
                            Ruang
                            <select v-model="draftFor(selectedCourse.id, classNumber).room_id" :class="fieldClass" :disabled="Boolean(assignmentSaving)">
                                <option value="">Tanpa ruang</option>
                                <option v-for="room in rooms" :key="room.id" :value="String(room.id)">{{ room.code }} · {{ room.capacity }} kursi</option>
                            </select>
                        </label>
                        <div class="flex gap-2">
                            <Button type="button" class="whitespace-nowrap" :loading="assignmentSaving === assignmentKey(selectedCourse.id, classNumber)" :disabled="!assignmentIsDirty(selectedCourse.id, classNumber) || !draftFor(selectedCourse.id, classNumber).lecturer_id" @click="saveAssignment(selectedCourse, classNumber)">Simpan</Button>
                            <Button v-if="assignmentFor(selectedCourse.id, classNumber)" type="button" variant="ghost" class="whitespace-nowrap border border-gray-200" :disabled="Boolean(assignmentSaving)" @click="removeAssignment(selectedCourse, classNumber)">Lepas</Button>
                        </div>
                    </div>
                </div>
                <div v-if="selectedCourse && lecturers.length === 0" class="border-t border-gray-100 p-5">
                    <Alert title="Belum ada data dosen">Daftarkan dosen terlebih dahulu sebelum menetapkan pengampu kelas TPB.</Alert>
                </div>
            </Panel>

            <Panel v-else>
                <div class="flex flex-col gap-3 border-b border-gray-200 p-4 lg:flex-row lg:items-center">
                    <select v-model="groupFilters.course_id" :class="[controlClass, 'lg:w-64']" aria-label="Filter mata kuliah">
                        <option value="">Semua mata kuliah TPB</option>
                        <option v-for="course in allTpbCourses" :key="course.id" :value="course.id">{{ course.code }} · {{ course.name }}</option>
                    </select>
                    <select v-model="groupFilters.study_program_id" :class="[controlClass, 'lg:w-56']" aria-label="Filter program studi">
                        <option value="">Semua prodi peserta</option>
                        <option v-for="program in studyPrograms" :key="program.id" :value="program.id">{{ program.code }} · {{ program.name }}</option>
                    </select>
                    <select v-model="groupFilters.time_slot_id" :class="[controlClass, 'lg:w-64']" aria-label="Filter slot">
                        <option value="">Semua slot</option>
                        <option v-for="slot in timeSlots" :key="slot.id" :value="slot.id">{{ slotLabel(slot) }}</option>
                    </select>
                </div>
                <Alert v-if="groupsError" :title="groupsError.message" class="m-4" />
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-5 py-3">Mata Kuliah / Grup</th>
                                <th class="px-5 py-3">Program Studi Peserta</th>
                                <th class="px-5 py-3">Slot</th>
                                <th class="px-5 py-3 text-right">Kelas terpetakan</th>
                                <th class="px-5 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" :class="{ 'opacity-60': groupsLoading }">
                            <tr v-if="!groupsLoading && groups.length === 0">
                                <td colspan="5" class="px-5 py-10 text-center text-gray-500">Belum ada grup TPB atau tidak cocok dengan filter.</td>
                            </tr>
                            <tr v-for="group in groups" :key="group.id" class="align-top hover:bg-gray-50">
                                <td class="px-5 py-4">
                                    <span class="font-mono text-xs font-bold text-primary">{{ group.course.code }} · {{ group.code }}</span>
                                    <span class="mt-1 block font-medium text-gray-900">{{ group.course.name }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap gap-1.5">
                                        <span v-for="program in group.study_programs" :key="program.id" class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">{{ program.code }}</span>
                                    </div>
                                    <p v-if="group.study_programs.length === 0" class="text-xs text-gray-400">Belum ada prodi peserta</p>
                                </td>
                                <td class="px-5 py-4 text-gray-700">{{ groupTimeLabel(group) }}</td>
                                <td class="px-5 py-4 text-right tabular-nums text-gray-700">{{ group.course_lecturers_count }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <div class="flex justify-end gap-4">
                                        <button v-if="group.can_update" type="button" class="font-medium text-gray-700 hover:text-primary hover:underline" @click="openEditGroup(group)">Edit</button>
                                        <button v-if="group.can_delete" type="button" class="font-medium text-red-600 hover:underline" @click="askDeleteGroup(group)">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Pagination v-model:page="groupPage" :pagination="groupPagination" label="grup TPB" />
                <p class="px-5 pb-4 text-xs text-gray-500">Grup yang belum memiliki slot belum memblokir waktu belajar prodi peserta.</p>
            </Panel>
        </div>

        <Modal :show="courseModalOpen" max-width="2xl" @close="courseModalOpen = false">
            <div class="flex items-center justify-between rounded-t-lg border-b border-gray-100 bg-gray-50 p-6">
                <h2 class="text-lg font-bold text-gray-900">{{ editingCourse ? `Ubah Mata Kuliah ${editingCourse.code}` : 'Tambah Mata Kuliah TPB' }}</h2>
                <button type="button" class="text-gray-400 transition hover:text-gray-900" @click="courseModalOpen = false">Tutup</button>
            </div>
            <form class="space-y-6 p-6" @submit.prevent="saveCourse">
                <Alert v-if="courseForm.failure.value" :title="courseForm.failure.value.message" />
                <div class="grid gap-5 md:grid-cols-3">
                    <label class="space-y-1 text-sm font-semibold text-gray-700">Kode
                        <input v-model="courseForm.data.code" type="text" maxlength="20" :class="fieldClass" required />
                        <span v-if="courseForm.errors.value.code" class="block text-xs font-normal text-red-600">{{ courseForm.errors.value.code }}</span>
                    </label>
                    <label class="space-y-1 text-sm font-semibold text-gray-700 md:col-span-2">Nama mata kuliah
                        <input v-model="courseForm.data.name" type="text" maxlength="150" :class="fieldClass" required />
                        <span v-if="courseForm.errors.value.name" class="block text-xs font-normal text-red-600">{{ courseForm.errors.value.name }}</span>
                    </label>
                </div>
                <div class="grid grid-cols-2 gap-5 md:grid-cols-4">
                    <label class="space-y-1 text-sm font-semibold text-gray-700">SKS
                        <select v-model.number="courseForm.data.sks" :class="fieldClass"><option :value="2">2</option><option :value="3">3</option><option :value="4">4</option></select>
                    </label>
                    <label class="space-y-1 text-sm font-semibold text-gray-700">Semester
                        <select v-model.number="courseForm.data.semester" :class="fieldClass"><option v-for="semester in semesters" :key="semester" :value="semester">{{ semester }}</option></select>
                    </label>
                    <label class="space-y-1 text-sm font-semibold text-gray-700">Kelas paralel
                        <input v-model.number="courseForm.data.parallel_class_count" type="number" min="1" max="99" :class="fieldClass" required />
                    </label>
                    <label class="space-y-1 text-sm font-semibold text-gray-700">Kapasitas
                        <input v-model.number="courseForm.data.class_capacity" type="number" min="1" max="1000" :class="fieldClass" required />
                    </label>
                </div>
                <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                    <Button type="button" variant="ghost" class="bg-gray-100 hover:bg-gray-200" @click="courseModalOpen = false">Batal</Button>
                    <Button type="submit" :loading="courseForm.processing.value">Simpan Mata Kuliah</Button>
                </div>
            </form>
        </Modal>

        <Modal :show="groupModalOpen" max-width="2xl" @close="groupModalOpen = false">
            <div class="flex items-center justify-between rounded-t-lg border-b border-gray-100 bg-gray-50 p-6">
                <h2 class="text-lg font-bold text-gray-900">{{ editingGroup ? `Ubah Grup ${editingGroup.code}` : 'Tambah Grup TPB' }}</h2>
                <button type="button" class="text-gray-400 transition hover:text-gray-900" @click="groupModalOpen = false">Tutup</button>
            </div>
            <form class="space-y-5 p-6" @submit.prevent="saveGroup">
                <Alert v-if="groupForm.failure.value" :title="groupForm.failure.value.message" />
                <label class="block space-y-1 text-sm font-semibold text-gray-700">Mata kuliah TPB
                    <select v-model.number="groupForm.data.course_id" :class="fieldClass" required :disabled="Boolean(editingGroup)">
                        <option :value="0" disabled>Pilih mata kuliah</option>
                        <option v-for="course in allTpbCourses" :key="course.id" :value="course.id">{{ course.code }} · {{ course.name }}</option>
                    </select>
                    <span v-if="groupForm.errors.value.course_id" class="block text-xs font-normal text-red-600">{{ groupForm.errors.value.course_id }}</span>
                </label>
                <label class="block space-y-1 text-sm font-semibold text-gray-700">Kode grup
                    <input v-model="groupForm.data.code" type="text" maxlength="20" :class="fieldClass" required />
                    <span v-if="groupForm.errors.value.code" class="block text-xs font-normal text-red-600">{{ groupForm.errors.value.code }}</span>
                </label>
                <label class="block space-y-1 text-sm font-semibold text-gray-700">Slot waktu
                    <select v-model="groupForm.data.time_slot_id" :class="fieldClass">
                        <option :value="null">Belum ditempatkan</option>
                        <option v-for="slot in timeSlots" :key="slot.id" :value="slot.id">{{ slotLabel(slot) }}</option>
                    </select>
                    <span v-if="groupForm.errors.value.time_slot_id" class="block text-xs font-normal text-red-600">{{ groupForm.errors.value.time_slot_id }}</span>
                </label>
                <fieldset class="space-y-2">
                    <legend class="text-sm font-semibold text-gray-700">Program studi peserta <span class="font-normal text-gray-500">(pilih minimal satu)</span></legend>
                    <div class="max-h-52 space-y-2 overflow-y-auto rounded-xl border border-gray-200 p-3">
                        <label v-for="program in studyPrograms" :key="program.id" class="flex items-center gap-2 text-sm text-gray-700">
                            <input
                                type="checkbox"
                                :checked="groupForm.data.study_program_ids.includes(program.id)"
                                class="rounded border-gray-300 text-primary focus:ring-primary"
                                @change="toggleStudyProgram(program.id, ($event.target as HTMLInputElement).checked)"
                            />
                            <span>{{ program.code }} · {{ program.name }}</span>
                        </label>
                        <p v-if="studyPrograms.length === 0" class="text-sm text-gray-500">Belum ada program studi.</p>
                    </div>
                    <span v-if="groupForm.errors.value.study_program_ids" class="block text-xs font-normal text-red-600">{{ groupForm.errors.value.study_program_ids }}</span>
                </fieldset>
                <Alert v-if="groupForm.failure.value" :title="groupForm.failure.value.message">
                    <template v-if="groupForm.failure.value.status === 422">Periksa jadwal bentrok pada slot atau program studi peserta.</template>
                </Alert>
                <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                    <Button type="button" variant="ghost" class="bg-gray-100 hover:bg-gray-200" @click="groupModalOpen = false">Batal</Button>
                    <Button type="submit" :loading="groupForm.processing.value" :disabled="studyPrograms.length === 0">Simpan Grup</Button>
                </div>
            </form>
        </Modal>

        <ConfirmDialog
            :show="deleteCourseTarget !== null || deleteGroupTarget !== null"
            :title="deleteCourseTarget ? 'Hapus mata kuliah TPB?' : 'Hapus grup TPB?'"
            :processing="deleting"
            :error="deleteFailure"
            @confirm="confirmDelete"
            @close="deleteCourseTarget = null; deleteGroupTarget = null"
        >
            <template v-if="deleteCourseTarget">Mata kuliah <strong>{{ deleteCourseTarget.code }}</strong> akan dihapus. Penghapusan dapat ditolak jika masih memiliki kelas atau jadwal.</template>
            <template v-else>Grup <strong>{{ deleteGroupTarget?.code }}</strong> akan dihapus. Penghapusan ditolak selama masih dipakai oleh kelas.</template>
        </ConfirmDialog>
    </AppLayout>
</template>

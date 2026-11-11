<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Panel from '@/Components/ui/Panel.vue';
import SearchSelect from '@/Components/shared/SearchSelect.vue';
import { fetchAll, useCollection } from '@/composables/useApiList';
import { useApiForm } from '@/composables/useApiForm';
import { usePeriodPicker } from '@/composables/usePeriodPicker';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import { api, destroy, toApiError, type ApiError } from '@/lib/api';
import { classLabel, COHORT_LABELS, REGULAR_COHORTS } from '@/lib/labels';
import { queryParam } from '@/lib/query';
import type { Course, Lecturer, StudyProgramSummary, TeachingAssignment, TpbCohort, TpbGroup, TpbOffering } from '@/types/models';
import { Head, Link } from '@inertiajs/vue3';
import { Check, Copy } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';

type Tab = 'offering' | 'groups' | 'classes';

const TABS: { id: Tab; label: string; hint: string }[] = [
    { id: 'offering', label: 'Jumlah kelas', hint: 'Banyak kelas dan kapasitasnya.' },
    { id: 'groups', label: 'Grup peserta', hint: 'Kelas yang kuliah di waktu sama.' },
    { id: 'classes', label: 'Dosen pengampu', hint: 'Satu dosen untuk tiap kelas.' },
];
const fieldClass = 'h-10 w-full rounded-lg border-gray-200 bg-white text-sm text-gray-900 focus:border-primary focus:ring-primary';
const controlClass = 'h-10 rounded-lg border-gray-200 bg-white text-sm text-gray-700 focus:border-primary focus:ring-primary';
const COHORT_TONE: Record<TpbCohort, string> = {
    maba: 'bg-blue-50 text-blue-700',
    non_maba: 'bg-indigo-50 text-indigo-700',
    campuran: 'bg-teal-50 text-teal-700',
    mengulang: 'bg-amber-50 text-amber-800',
    pilihan: 'bg-gray-100 text-gray-700',
};

const { periods, period, error: periodsError } = usePeriodPicker();
const initialTab = queryParam('tab');
const tab = ref<Tab>(TABS.some((t) => t.id === initialTab) ? (initialTab as Tab) : 'offering');
const selectedCourseId = ref<number | null>(Number(queryParam('course')) || null);
type StatusFilter = 'all' | 'open' | 'closed';
const STATUS_FILTERS: { id: StatusFilter; label: string }[] = [
    { id: 'all', label: 'Semua' },
    { id: 'open', label: 'Dibuka' },
    { id: 'closed', label: 'Belum dibuka' },
];
const statusFilter = ref<StatusFilter>('all');
const search = ref('');
const flash = ref<string | null>(null);
const failure = ref<ApiError | null>(null);

const offerings = ref<TpbOffering[]>([]);
const tpbCourses = ref<Course[]>([]);
const { items: programs } = useCollection<StudyProgramSummary>('study-programs');
const lecturers = ref<Lecturer[]>([]);
const groups = ref<TpbGroup[]>([]);
const classes = ref<TeachingAssignment[]>([]);
const periodClasses = ref<TeachingAssignment[]>([]);
const loadingList = ref(false);
const loadingDetail = ref(false);

const totals = computed(() => offerings.value.reduce(
    (sum, o) => ({ classes: sum.classes + o.parallel_class_count, grouped: sum.grouped + o.course_lecturers_count, assigned: sum.assigned + o.assigned_classes_count }),
    { classes: 0, grouped: 0, assigned: 0 },
));
const selected = computed(() => offerings.value.find((o) => o.course_id === selectedCourseId.value) ?? null);
const selectedId = computed(() => selected.value?.id ?? null);
const selectedCourse = computed(() => tpbCourses.value.find((c) => c.id === selectedCourseId.value) ?? null);

/** Every active TPB course, or one that already has an offering in this period, with its offering if any. */
type Entry = { course: Pick<Course, 'id' | 'code' | 'name' | 'sks'>; offering: TpbOffering | null };
const entries = computed<Entry[]>(() => {
    const byCourse = new Map(offerings.value.map((o) => [o.course_id, o]));
    const rows: Entry[] = tpbCourses.value
        .filter((c) => c.is_active || byCourse.has(c.id))
        .map((c) => ({ course: c, offering: byCourse.get(c.id) ?? null }));
    const known = new Set(rows.map((r) => r.course.id));
    for (const o of offerings.value) if (!known.has(o.course_id)) rows.push({ course: o.course, offering: o });
    return rows.sort((a, b) => a.course.name.localeCompare(b.course.name) || a.course.code.localeCompare(b.course.code));
});
const openCount = computed(() => entries.value.filter((e) => e.offering).length);
const closedCount = computed(() => entries.value.length - openCount.value);
const visibleEntries = computed(() => {
    const q = search.value.trim().toLowerCase();
    return entries.value.filter((e) => {
        if (statusFilter.value === 'open' && !e.offering) return false;
        if (statusFilter.value === 'closed' && e.offering) return false;
        return !q || `${e.course.code} ${e.course.name}`.toLowerCase().includes(q);
    });
});
const groupOfClass = computed(() => {
    const map = new Map<number, TpbGroup>();
    for (const group of groups.value) for (const n of group.class_numbers) map.set(n, group);
    return map;
});
const ungroupedClasses = computed(() => {
    if (!selected.value) return [];
    return Array.from({ length: selected.value.parallel_class_count }, (_, i) => i + 1).filter((n) => !groupOfClass.value.has(n));
});
const lecturerLoad = computed(() => {
    const map = new Map<number, number>();
    for (const row of periodClasses.value) {
        if (row.lecturer_id !== null) map.set(row.lecturer_id, (map.get(row.lecturer_id) ?? 0) + row.course.sks);
    }
    return map;
});
const sortedPrograms = computed(() => [...programs.value].sort((a, b) => a.name.localeCompare(b.name)));
const lecturerOptions = computed(() => lecturers.value.map((l) => ({
    value: l.id,
    label: l.title ? `${l.name}, ${l.title}` : l.name,
    hint: l.study_program.name,
})));
const stepIndex = computed(() => TABS.findIndex((t) => t.id === tab.value));
const stepDone = computed<Record<Tab, boolean>>(() => {
    const o = selected.value;
    return {
        offering: o !== null,
        groups: o !== null && groups.value.length > 0 && ungroupedClasses.value.length === 0,
        classes: o !== null && o.assigned_classes_count === o.parallel_class_count,
    };
});
const stepSummary = computed<Record<Tab, string>>(() => {
    const o = selected.value;
    return {
        offering: o ? `${o.parallel_class_count} kelas, ${o.class_capacity} kursi` : '',
        groups: o ? `${o.groups_count} grup` : '',
        classes: o ? `${o.assigned_classes_count} dari ${o.parallel_class_count} kelas` : '',
    };
});

/** Semester shared by every participant of the group, or null when they differ or none is set. */
function commonSemester(group: TpbGroup): number | null {
    const semesters = new Set(group.participants.map((p) => p.target_semester));
    return semesters.size === 1 ? ([...semesters][0] ?? null) : null;
}

function goStep(delta: number): void {
    tab.value = TABS[Math.min(Math.max(stepIndex.value + delta, 0), TABS.length - 1)].id;
}

function offeringStatus(o: TpbOffering): { grouped: boolean; assigned: boolean } {
    return { grouped: o.course_lecturers_count === o.parallel_class_count, assigned: o.assigned_classes_count === o.parallel_class_count };
}

function syncUrl(): void {
    const url = new URL(window.location.href);
    url.searchParams.set('period', period.value);
    url.searchParams.set('tab', tab.value);
    url.searchParams.delete('offering');
    if (selectedCourseId.value) url.searchParams.set('course', String(selectedCourseId.value));
    else url.searchParams.delete('course');
    window.history.replaceState(window.history.state, '', url);
}

async function loadReferences(): Promise<void> {
    try {
        [lecturers.value, tpbCourses.value] = await Promise.all([
            fetchAll<Lecturer>('lecturers'),
            fetchAll<Course>('courses', { is_tpb: true }),
        ]);
    } catch (e) {
        failure.value = toApiError(e);
    }
}

async function loadOfferings(): Promise<void> {
    if (!period.value) return;
    failure.value = null;
    loadingList.value = true;
    try {
        [offerings.value, periodClasses.value] = await Promise.all([
            fetchAll<TpbOffering>('tpb-offerings', { period: period.value }),
            fetchAll<TeachingAssignment>('teaching-assignments', { tpb_period: period.value }),
        ]);
        if (!entries.value.some((e) => e.course.id === selectedCourseId.value)) selectedCourseId.value = (entries.value.find((e) => e.offering) ?? entries.value[0])?.course.id ?? null;
    } catch (e) {
        failure.value = toApiError(e);
    } finally {
        loadingList.value = false;
    }
}

async function loadDetail(): Promise<void> {
    const id = selectedId.value;
    groups.value = [];
    classes.value = [];
    Object.keys(drafts).forEach((k) => delete drafts[Number(k)]);
    if (!id) return;
    loadingDetail.value = true;
    try {
        const [groupRows, classRows] = await Promise.all([
            fetchAll<TpbGroup>('tpb-groups', { tpb_offering_id: id }),
            fetchAll<TeachingAssignment>('teaching-assignments', { tpb_offering_id: id }),
        ]);
        if (selectedId.value !== id) return;
        groups.value = groupRows;
        classes.value = classRows;
    } catch (e) {
        failure.value = toApiError(e);
    } finally {
        loadingDetail.value = false;
    }
}

onMounted(loadReferences);
watch(period, () => {
    void loadOfferings();
    syncUrl();
}, { immediate: true });
watch(selectedCourseId, syncUrl);
watch(selectedId, () => {
    void loadDetail();
    offeringForm.reset({ parallel_class_count: selected.value?.parallel_class_count ?? 1, class_capacity: selected.value?.class_capacity ?? 40 });
});
watch(selected, (o) => {
    if (o) offeringForm.reset({ parallel_class_count: o.parallel_class_count, class_capacity: o.class_capacity });
});
watch(tab, syncUrl);
onResourcesChanged(['tpb_offerings', 'tpb_groups', 'course_lecturers', 'courses'], () => {
    void loadReferences();
    void loadOfferings();
    void loadDetail();
});

const offeringForm = useApiForm(() => ({ parallel_class_count: 1 as number, class_capacity: 40 as number }));
async function saveOffering(): Promise<void> {
    if (!selected.value) return;
    const saved = await offeringForm.submit<TpbOffering>('patch', `tpb-offerings/${selected.value.id}`, { ...offeringForm.data });
    if (!saved) return;
    flash.value = `Jumlah kelas ${saved.course.code} disimpan.`;
    await loadOfferings();
}

async function openOffering(): Promise<void> {
    const course = selectedCourse.value;
    if (!course) return;
    const saved = await offeringForm.submit<TpbOffering>('post', 'tpb-offerings', { ...offeringForm.data, course_id: course.id, period: period.value });
    if (!saved) return;
    flash.value = `${course.code} dibuka di periode ini.`;
    tab.value = 'offering';
    await loadOfferings();
}

const copyOpen = ref(false);
const copySource = ref('');
const copyRows = ref<TpbOffering[]>([]);
const copyLoading = ref(false);
const copying = ref(false);
const copyError = ref<string | null>(null);
const copySourceOptions = computed(() => periods.value.filter((p) => p.code !== period.value));
const copyCandidates = computed(() => {
    const open = new Set(offerings.value.map((o) => o.course_id));
    const active = new Set(tpbCourses.value.filter((c) => c.is_active).map((c) => c.id));
    return copyRows.value.filter((o) => !open.has(o.course_id) && active.has(o.course_id));
});

async function openCopy(): Promise<void> {
    copyError.value = null;
    copySource.value = copySourceOptions.value[0]?.code ?? '';
    copyOpen.value = true;
}

watch([copySource, copyOpen], async () => {
    if (!copyOpen.value || !copySource.value) {
        copyRows.value = [];
        return;
    }
    copyLoading.value = true;
    try {
        copyRows.value = await fetchAll<TpbOffering>('tpb-offerings', { period: copySource.value });
    } catch (e) {
        copyError.value = toApiError(e).message;
    } finally {
        copyLoading.value = false;
    }
});

async function confirmCopy(): Promise<void> {
    copying.value = true;
    copyError.value = null;
    let done = 0;
    try {
        for (const o of copyCandidates.value) {
            await api.post('tpb-offerings', { course_id: o.course_id, period: period.value, parallel_class_count: o.parallel_class_count, class_capacity: o.class_capacity });
            done++;
        }
        copyOpen.value = false;
        flash.value = `${done} mata kuliah disalin ke periode ini. Atur grup dan dosennya per mata kuliah.`;
    } catch (e) {
        copyError.value = `${done} berhasil disalin, lalu gagal: ${toApiError(e).message}`;
    } finally {
        copying.value = false;
        await loadOfferings();
    }
}

type ParticipantRow = { id: number; target_semester: number | null };
const groupModalOpen = ref(false);
const editingGroup = ref<TpbGroup | null>(null);
const sameSemester = ref(true);
const sharedSemester = ref<number>(1);
const groupForm = useApiForm(() => ({
    code: '',
    cohort: 'maba' as TpbCohort,
    participants: [] as ParticipantRow[],
    class_numbers: [] as number[],
}));
const isRegular = computed(() => REGULAR_COHORTS.includes(groupForm.data.cohort));
const firstSemester = computed(() => (period.value.endsWith('GENAP') ? 2 : 1));
const semesterChoices = computed(() => [1, 2, 3, 4, 5, 6, 7, 8].filter((s) => s % 2 === firstSemester.value % 2));
const takenByOtherGroup = computed(() => {
    const map = new Map<number, string>();
    for (const group of groups.value) {
        if (group.id === editingGroup.value?.id) continue;
        for (const n of group.class_numbers) map.set(n, group.code);
    }
    return map;
});

function defaultSemester(cohort: TpbCohort): number {
    return cohort === 'non_maba' ? firstSemester.value + 2 : firstSemester.value;
}

function openCreateGroup(): void {
    editingGroup.value = null;
    groupForm.reset({ code: `G${groups.value.length + 1}`, class_numbers: [...ungroupedClasses.value] });
    sameSemester.value = true;
    sharedSemester.value = firstSemester.value;
    groupModalOpen.value = true;
}

function openEditGroup(group: TpbGroup): void {
    editingGroup.value = group;
    const semesters = new Set(group.participants.map((p) => p.target_semester));
    groupForm.reset({
        code: group.code,
        cohort: group.cohort,
        participants: group.participants.map((p) => ({ id: p.study_program_id, target_semester: p.target_semester })),
        class_numbers: [...group.class_numbers],
    });
    sameSemester.value = semesters.size <= 1;
    sharedSemester.value = group.participants[0]?.target_semester ?? defaultSemester(group.cohort);
    groupModalOpen.value = true;
}

function hasProgram(id: number): boolean {
    return groupForm.data.participants.some((p) => p.id === id);
}

function toggleProgram(id: number, checked: boolean): void {
    if (checked && !hasProgram(id)) groupForm.data.participants.push({ id, target_semester: sharedSemester.value });
    if (!checked) groupForm.data.participants = groupForm.data.participants.filter((p) => p.id !== id);
}

function selectAllPrograms(): void {
    groupForm.data.participants = sortedPrograms.value.map((p) => ({ id: p.id, target_semester: sharedSemester.value }));
}

function toggleClass(n: number, checked: boolean): void {
    const set = new Set(groupForm.data.class_numbers);
    if (checked) set.add(n);
    else set.delete(n);
    groupForm.data.class_numbers = [...set].sort((a, b) => a - b);
}

watch(() => groupForm.data.cohort, (cohort, previous) => {
    if (!groupModalOpen.value || cohort === previous) return;
    if (REGULAR_COHORTS.includes(cohort) && !REGULAR_COHORTS.includes(previous)) sharedSemester.value = defaultSemester(cohort);
    if (cohort === 'non_maba' && previous !== 'non_maba') sharedSemester.value = defaultSemester(cohort);
});

async function saveGroup(): Promise<void> {
    if (!selected.value) return;
    const cohort = groupForm.data.cohort;
    const payload: Record<string, unknown> = {
        code: groupForm.data.code,
        cohort,
        class_numbers: groupForm.data.class_numbers,
    };
    if (cohort !== 'pilihan') {
        payload.study_programs = groupForm.data.participants.map((p) => (REGULAR_COHORTS.includes(cohort)
            ? { id: p.id, target_semester: sameSemester.value ? sharedSemester.value : p.target_semester ?? sharedSemester.value }
            : { id: p.id }));
    }
    const saved = editingGroup.value
        ? await groupForm.submit<TpbGroup>('patch', `tpb-groups/${editingGroup.value.id}`, payload)
        : await groupForm.submit<TpbGroup>('post', 'tpb-groups', { ...payload, tpb_offering_id: selected.value.id });
    if (!saved) return;
    groupModalOpen.value = false;
    flash.value = `Grup ${saved.code} disimpan.`;
    await Promise.all([loadDetail(), loadOfferings()]);
}

const drafts = reactive<Record<number, string>>({});
const savingClass = ref<number | null>(null);

function draftValue(row: TeachingAssignment): number | '' {
    const draft = draftOf(row);
    return draft ? Number(draft) : '';
}

function draftOf(row: TeachingAssignment): string {
    if (drafts[row.id] === undefined) drafts[row.id] = row.lecturer_id ? String(row.lecturer_id) : '';
    return drafts[row.id];
}

async function saveLecturer(row: TeachingAssignment): Promise<void> {
    savingClass.value = row.id;
    failure.value = null;
    try {
        await api.patch(`teaching-assignments/${row.id}`, { lecturer_id: drafts[row.id] ? Number(drafts[row.id]) : null });
        flash.value = `Pengampu kelas ${row.class_label} disimpan.`;
        await Promise.all([loadDetail(), loadOfferings()]);
    } catch (e) {
        failure.value = toApiError(e);
    } finally {
        savingClass.value = null;
    }
}

const deleteTarget = ref<{ kind: 'offering' | 'group'; id: number; label: string } | null>(null);
const deleting = ref(false);
const deleteError = ref<string | null>(null);

async function confirmDelete(): Promise<void> {
    const target = deleteTarget.value;
    if (!target) return;
    deleting.value = true;
    const err = await destroy(target.kind === 'offering' ? `tpb-offerings/${target.id}` : `tpb-groups/${target.id}`);
    deleting.value = false;
    if (err) {
        deleteError.value = err.message;
        return;
    }
    flash.value = `${target.label} dihapus.`;
    deleteTarget.value = null;
    await Promise.all([loadOfferings(), loadDetail()]);
}
</script>

<template>
    <Head title="Mapping Dosen Pengampu" />
    <AppLayout>
        <div class="space-y-6">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Mapping Dosen Pengampu</h1>
                    <p class="mt-1 text-sm text-gray-500">Tentukan jumlah kelas, grup peserta, dan dosen pengampu tiap mata kuliah TPB.</p>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <label class="text-sm font-medium text-gray-700">Periode
                        <select v-model="period" class="mt-1 block sm:w-56" :class="controlClass">
                            <option v-for="item in periods" :key="item.id" :value="item.code">{{ item.label }}</option>
                        </select>
                    </label>
                    <Button variant="secondary" class="h-10" :disabled="copySourceOptions.length === 0" @click="openCopy"><Copy class="mr-1.5 size-4" aria-hidden="true" />Salin dari periode lain</Button>
                    <Link :href="`/tpb/mappings/import?period=${encodeURIComponent(period)}`" class="inline-flex h-10 items-center rounded-lg bg-primary px-4 text-sm font-semibold text-white shadow-md shadow-primary/20 hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">Import data</Link>
                </div>
            </header>

            <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />
            <Alert v-if="failure" :title="failure.message" dismissible @dismiss="failure = null" />
            <Alert v-if="periodsError" :title="periodsError.message" />

            <dl v-if="entries.length" class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Ringkasan mapping">
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <dt class="text-xs font-medium text-gray-500">Mata kuliah dibuka</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-gray-900">{{ openCount }}<span v-if="closedCount" class="text-sm font-medium text-gray-500"> · {{ closedCount }} belum dibuka</span></dd>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <dt class="text-xs font-medium text-gray-500">Total kelas</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-gray-900">{{ totals.classes }}</dd>
                </div>
                <div class="rounded-lg border bg-white p-4" :class="totals.grouped === totals.classes ? 'border-gray-200' : 'border-amber-300'">
                    <dt class="text-xs font-medium text-gray-500">Kelas sudah masuk grup</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums" :class="totals.grouped === totals.classes ? 'text-gray-900' : 'text-amber-700'">{{ totals.grouped }}<span class="text-sm font-medium text-gray-500"> dari {{ totals.classes }}</span></dd>
                </div>
                <div class="rounded-lg border bg-white p-4" :class="totals.assigned === totals.classes ? 'border-gray-200' : 'border-amber-300'">
                    <dt class="text-xs font-medium text-gray-500">Kelas sudah punya dosen</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums" :class="totals.assigned === totals.classes ? 'text-gray-900' : 'text-amber-700'">{{ totals.assigned }}<span class="text-sm font-medium text-gray-500"> dari {{ totals.classes }}</span></dd>
                </div>
            </dl>

            <div class="grid gap-6 lg:grid-cols-[320px_1fr]">
                <!-- Master: every TPB course with its status in the chosen period -->
                <Panel class="self-start">
                    <div class="space-y-3 border-b border-gray-200 p-4">
                        <input v-model="search" type="search" placeholder="Cari mata kuliah" aria-label="Cari mata kuliah" :class="[controlClass, 'w-full']" />
                        <div class="flex gap-1.5" role="group" aria-label="Filter status">
                            <button
                                v-for="f in STATUS_FILTERS"
                                :key="f.id"
                                type="button"
                                class="rounded-full px-3 py-1 text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                :class="statusFilter === f.id ? 'bg-primary text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                :aria-pressed="statusFilter === f.id"
                                @click="statusFilter = f.id"
                            >{{ f.label }}</button>
                        </div>
                        <p class="text-xs text-gray-500"><span class="font-semibold tabular-nums text-gray-900">{{ visibleEntries.length }}</span> mata kuliah {{ search || statusFilter !== 'all' ? 'ditemukan' : 'terdaftar' }}</p>
                    </div>
                    <p v-if="loadingList" class="p-4 text-sm text-gray-500">Memuat…</p>
                    <p v-else-if="entries.length === 0" class="p-4 text-sm text-gray-500">Belum ada mata kuliah TPB. Tambahkan di menu Kelola Mata Kuliah, atau pilih Import data.</p>
                    <p v-else-if="visibleEntries.length === 0" class="p-4 text-sm text-gray-500">Tidak ada mata kuliah yang cocok.</p>
                    <ul v-else class="max-h-[70vh] divide-y divide-gray-100 overflow-y-auto">
                        <li v-for="e in visibleEntries" :key="e.course.id">
                            <button
                                type="button"
                                class="block w-full px-4 py-3 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"
                                :class="e.course.id === selectedCourseId ? 'bg-primary/5' : 'hover:bg-gray-50'"
                                :aria-current="e.course.id === selectedCourseId ? 'true' : undefined"
                                @click="selectedCourseId = e.course.id"
                            >
                                <span class="block text-sm font-semibold" :class="e.course.id === selectedCourseId ? 'text-primary' : e.offering ? 'text-gray-900' : 'text-gray-500'">{{ e.course.name }}</span>
                                <span class="mt-0.5 block text-xs tabular-nums text-gray-500">{{ e.course.code }} · {{ e.course.sks }} SKS</span>
                                <span class="mt-2 flex flex-wrap gap-1.5">
                                    <template v-if="e.offering">
                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="offeringStatus(e.offering).grouped ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-800'">Grup {{ e.offering.course_lecturers_count }}/{{ e.offering.parallel_class_count }}</span>
                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="offeringStatus(e.offering).assigned ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-800'">Dosen {{ e.offering.assigned_classes_count }}/{{ e.offering.parallel_class_count }}</span>
                                    </template>
                                    <span v-else class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">Belum dibuka</span>
                                </span>
                            </button>
                        </li>
                    </ul>
                </Panel>

                <!-- Detail: the chosen offering, as a vertical wizard -->
                <Panel v-if="selected">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <p class="text-xs tabular-nums text-gray-500">{{ selected.course.code }} · {{ selected.course.sks }} SKS<template v-if="selected.course.sks === 4"> · 2 pertemuan</template></p>
                        <h2 class="text-base font-bold text-gray-900">{{ selected.course.name }}</h2>
                    </div>

                    <div class="grid md:grid-cols-[230px_1fr]">
                        <ol class="space-y-1 border-b border-gray-200 p-4 md:border-b-0 md:border-r" role="tablist" aria-orientation="vertical" aria-label="Langkah mapping">
                            <li v-for="(t, i) in TABS" :key="t.id">
                                <button
                                    type="button"
                                    role="tab"
                                    :aria-selected="tab === t.id"
                                    class="flex w-full items-start gap-3 rounded-lg px-3 py-2.5 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                    :class="tab === t.id ? 'bg-primary/5' : 'hover:bg-gray-50'"
                                    @click="tab = t.id"
                                >
                                    <span
                                        class="mt-0.5 inline-flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                        :class="stepDone[t.id] ? 'bg-green-100 text-green-700' : tab === t.id ? 'bg-primary text-white' : 'bg-gray-100 text-gray-500'"
                                    >
                                        <Check v-if="stepDone[t.id]" class="size-3.5" aria-hidden="true" />
                                        <template v-else>{{ i + 1 }}</template>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold" :class="tab === t.id ? 'text-primary' : 'text-gray-900'">{{ t.label }}</span>
                                        <span class="block text-xs text-gray-500">{{ stepSummary[t.id] || t.hint }}</span>
                                    </span>
                                </button>
                            </li>
                        </ol>

                        <div class="min-w-0">
                            <p v-if="loadingDetail" class="p-6 text-sm text-gray-500">Memuat…</p>

                            <!-- 1. Classes and capacity -->
                            <form v-else-if="tab === 'offering'" class="space-y-5 p-6" @submit.prevent="saveOffering">
                                <div>
                                    <h3 class="text-sm font-bold text-gray-900">Jumlah kelas</h3>
                                    <p class="mt-1 text-xs text-gray-500">Isi berapa kelas yang dibuka dan berapa mahasiswa per kelas.</p>
                                </div>
                                <Alert v-if="offeringForm.failure.value" :title="offeringForm.failure.value.message" />
                                <div class="grid max-w-lg gap-5 sm:grid-cols-2">
                                    <label class="space-y-1 text-sm font-semibold text-gray-700">Jumlah kelas
                                        <input v-model.number="offeringForm.data.parallel_class_count" type="number" min="1" max="99" :class="fieldClass" required />
                                        <span class="block text-xs font-normal text-gray-500">{{ (offeringForm.data.parallel_class_count || 1) > 1 ? `Kelas A sampai ${classLabel(offeringForm.data.parallel_class_count)}.` : 'Hanya kelas A.' }}</span>
                                        <span v-if="offeringForm.errors.value.parallel_class_count" class="block text-xs font-normal text-red-600">{{ offeringForm.errors.value.parallel_class_count }}</span>
                                    </label>
                                    <label class="space-y-1 text-sm font-semibold text-gray-700">Kapasitas per kelas
                                        <input v-model.number="offeringForm.data.class_capacity" type="number" min="1" max="1000" :class="fieldClass" required />
                                        <span class="block text-xs font-normal text-gray-500">Kelas hanya ditempatkan di ruang yang muat.</span>
                                    </label>
                                </div>
                                <p class="text-sm text-gray-600">
                                    Total {{ selected.parallel_class_count * selected.class_capacity }} kursi. Sudah ada {{ selected.groups_count }} grup, berisi {{ selected.course_lecturers_count }} dari {{ selected.parallel_class_count }} kelas.
                                </p>
                                <div class="flex flex-wrap gap-3 border-t border-gray-100 pt-4">
                                    <Button type="submit" :loading="offeringForm.processing.value" :disabled="!selected.can_update">Simpan</Button>
                                    <Button v-if="selected.can_delete" type="button" @click="deleteTarget = { kind: 'offering', id: selected.id, label: `${selected.course.name} dari periode ini` }; deleteError = null">Hapus dari periode ini</Button>
                                    <Button type="button" variant="secondary" class="ml-auto" @click="goStep(1)">Lanjut ke grup peserta</Button>
                                </div>
                            </form>

                            <!-- 2. Groups -->
                            <div v-else-if="tab === 'groups'" class="space-y-5 p-6">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-sm font-bold text-gray-900">Grup peserta</h3>
                                        <p class="mt-1 text-xs text-gray-500">Kelas dalam satu grup selalu kuliah di waktu yang sama.</p>
                                    </div>
                                    <Button size="sm" @click="openCreateGroup">Tambah grup</Button>
                                </div>
                                <p v-if="ungroupedClasses.length" class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">Kelas yang belum masuk grup: {{ ungroupedClasses.map(classLabel).join(', ') }}.</p>
                                <p v-if="groups.length === 0" class="rounded-lg bg-gray-50 p-6 text-center text-sm text-gray-500">Belum ada grup. Pilih Tambah grup, lalu tentukan kelas dan program studi pesertanya.</p>
                                <ul v-else class="divide-y divide-gray-100 rounded-lg border border-gray-200">
                                    <li v-for="group in groups" :key="group.id" class="grid gap-4 p-4 md:grid-cols-[140px_1fr_auto]">
                                        <div>
                                            <p class="text-sm font-bold text-primary">{{ group.code }}</p>
                                            <span class="mt-1 inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="COHORT_TONE[group.cohort]">{{ COHORT_LABELS[group.cohort] }}</span>
                                        </div>
                                        <div class="min-w-0 space-y-3">
                                            <p class="text-sm text-gray-900">Kelas <span class="font-semibold">{{ group.class_numbers.map(classLabel).join(', ') }}</span> <span class="text-gray-500">({{ group.class_numbers.length }} kelas)</span></p>
                                            <p v-if="group.cohort === 'pilihan'" class="text-sm text-gray-500">Terbuka untuk semua program studi.</p>
                                            <div v-else>
                                                <p class="mb-1.5 text-xs font-semibold text-gray-500">
                                                    {{ group.participants.length }} program studi<template v-if="commonSemester(group)"> · Semester {{ commonSemester(group) }}</template>
                                                </p>
                                                <ul class="grid gap-1.5 sm:grid-cols-2 xl:grid-cols-3">
                                                    <li v-for="p in group.participants" :key="p.study_program_id" class="truncate rounded-md bg-gray-100 px-2.5 py-1 text-xs text-gray-700" :title="p.name">
                                                        {{ p.name }}<template v-if="p.target_semester && !commonSemester(group)"> · Smt {{ p.target_semester }}</template>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-2">
                                            <Button v-if="group.can_update" size="sm" variant="secondary" @click="openEditGroup(group)">Edit</Button>
                                            <Button v-if="group.can_delete" size="sm" @click="deleteTarget = { kind: 'group', id: group.id, label: `Grup ${group.code}` }; deleteError = null">Hapus</Button>
                                        </div>
                                    </li>
                                </ul>
                                <div class="flex flex-wrap gap-3 border-t border-gray-100 pt-4">
                                    <Button type="button" variant="secondary" @click="goStep(-1)">Kembali</Button>
                                    <Button type="button" variant="secondary" class="ml-auto" @click="goStep(1)">Lanjut ke dosen pengampu</Button>
                                </div>
                            </div>

                            <!-- 3. Lecturer per class -->
                            <div v-else class="space-y-5 p-6">
                                <div>
                                    <h3 class="text-sm font-bold text-gray-900">Dosen pengampu</h3>
                                    <p class="mt-1 text-xs text-gray-500">Cari nama dosen, pilih, lalu simpan per kelas.</p>
                                </div>
                                <p v-if="classes.length === 0" class="rounded-lg bg-gray-50 p-6 text-center text-sm text-gray-500">Belum ada kelas. Kelas baru muncul setelah dimasukkan ke sebuah grup.</p>
                                <div v-else class="overflow-x-auto">
                                    <table class="w-full border-collapse text-left text-sm">
                                        <thead>
                                            <tr class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                <th class="py-2 pr-4">Kelas</th>
                                                <th class="py-2 pr-4">Grup</th>
                                                <th class="py-2 pr-4">Dosen pengampu</th>
                                                <th class="py-2 pr-4 text-right">Beban TPB</th>
                                                <th class="py-2" />
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr v-for="row in classes" :key="row.id">
                                                <td class="py-3 pr-4 font-bold text-gray-900">{{ row.class_label }}</td>
                                                <td class="py-3 pr-4 text-gray-700">
                                                    {{ groupOfClass.get(row.class_number)?.code }}
                                                    <span v-if="groupOfClass.get(row.class_number)?.cohort === 'mengulang'" class="ml-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Mengulang</span>
                                                </td>
                                                <td class="min-w-72 py-3 pr-4">
                                                    <SearchSelect :model-value="draftValue(row)" :options="lecturerOptions" :label="`dosen kelas ${row.class_label}`" placeholder="Cari nama dosen" @update:model-value="drafts[row.id] = $event === '' ? '' : String($event)" />
                                                </td>
                                                <td class="whitespace-nowrap py-3 pr-4 text-right tabular-nums text-gray-600">{{ drafts[row.id] ? `${lecturerLoad.get(Number(drafts[row.id])) ?? 0} SKS` : '-' }}</td>
                                                <td class="py-3 text-right">
                                                    <Button size="sm" :loading="savingClass === row.id" :disabled="draftOf(row) === (row.lecturer_id ? String(row.lecturer_id) : '')" @click="saveLecturer(row)">Simpan</Button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="border-t border-gray-100 pt-4">
                                    <Button type="button" variant="secondary" @click="goStep(-1)">Kembali</Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </Panel>

                <!-- Detail: a course that is not opened in this period yet -->
                <Panel v-else-if="selectedCourse">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <p class="text-xs tabular-nums text-gray-500">{{ selectedCourse.code }} · {{ selectedCourse.sks }} SKS</p>
                        <h2 class="text-base font-bold text-gray-900">{{ selectedCourse.name }}</h2>
                    </div>
                    <form class="space-y-5 p-6" @submit.prevent="openOffering">
                        <div>
                            <h3 class="text-sm font-bold text-gray-900">Belum dibuka di {{ periods.find((p) => p.code === period)?.label ?? period }}</h3>
                            <p class="mt-1 text-xs text-gray-500">Isi jumlah kelas dan kapasitas untuk membuka mata kuliah ini di periode ini. Grup dan dosen diatur setelahnya.</p>
                        </div>
                        <Alert v-if="offeringForm.failure.value" :title="offeringForm.failure.value.message" />
                        <div class="grid max-w-lg gap-5 sm:grid-cols-2">
                            <label class="space-y-1 text-sm font-semibold text-gray-700">Jumlah kelas
                                <input v-model.number="offeringForm.data.parallel_class_count" type="number" min="1" max="99" :class="fieldClass" required />
                                <span v-if="offeringForm.errors.value.parallel_class_count" class="block text-xs font-normal text-red-600">{{ offeringForm.errors.value.parallel_class_count }}</span>
                            </label>
                            <label class="space-y-1 text-sm font-semibold text-gray-700">Kapasitas per kelas
                                <input v-model.number="offeringForm.data.class_capacity" type="number" min="1" max="1000" :class="fieldClass" required />
                            </label>
                        </div>
                        <div class="border-t border-gray-100 pt-4">
                            <Button type="submit" :loading="offeringForm.processing.value">Buka di periode ini</Button>
                        </div>
                    </form>
                </Panel>
                <Panel v-else class="p-10 text-center text-sm text-gray-500">Pilih mata kuliah di daftar sebelah kiri, atau gunakan Import Data.</Panel>
            </div>
        </div>

        <Modal :show="groupModalOpen" max-width="3xl" close-button @close="groupModalOpen = false">
            <div class="rounded-t-lg border-b border-gray-100 bg-gray-50 py-5 pl-6 pr-14">
                <h2 class="text-lg font-bold text-gray-900">{{ editingGroup ? `Ubah grup ${editingGroup.code}` : 'Tambah grup' }}</h2>
                <p v-if="selected" class="mt-0.5 text-sm text-gray-500">{{ selected.course.code }} · {{ selected.course.name }}</p>
            </div>
            <form class="space-y-6 p-6" @submit.prevent="saveGroup">
                <Alert v-if="groupForm.failure.value" :title="groupForm.failure.value.message">
                    <ul v-if="Object.keys(groupForm.errors.value).length" class="mt-1 list-disc pl-5 text-sm">
                        <li v-for="(message, key) in groupForm.errors.value" :key="key">{{ message }}</li>
                    </ul>
                </Alert>

                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="space-y-1 text-sm font-semibold text-gray-700">Kode grup
                        <input v-model="groupForm.data.code" type="text" maxlength="20" :class="fieldClass" required />
                    </label>
                    <label class="space-y-1 text-sm font-semibold text-gray-700">Kategori peserta
                        <select v-model="groupForm.data.cohort" :class="fieldClass">
                            <option v-for="(label, value) in COHORT_LABELS" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </label>
                    <p class="rounded-lg bg-gray-50 px-4 py-3 text-xs text-gray-600 sm:col-span-2">
                        <template v-if="isRegular">Program studi peserta tidak boleh punya jadwal lain di waktu kuliah grup ini.</template>
                        <template v-else-if="groupForm.data.cohort === 'mengulang'">Fakultas diminta menyisakan minimal satu kelas paralel di luar waktu kuliah grup ini.</template>
                        <template v-else>Terbuka untuk semua program studi dan tidak mengunci jadwal program studi mana pun.</template>
                    </p>
                </div>

                <fieldset v-if="selected">
                    <legend class="text-sm font-semibold text-gray-700">Kelas dalam grup</legend>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <label
                            v-for="n in selected.parallel_class_count"
                            :key="n"
                            class="flex h-10 items-center gap-2 rounded-lg border px-3 text-sm"
                            :class="takenByOtherGroup.has(n) ? 'border-gray-100 bg-gray-50 text-gray-400' : 'border-gray-200 text-gray-800'"
                            :title="takenByOtherGroup.has(n) ? `Sudah di grup ${takenByOtherGroup.get(n)}` : undefined"
                        >
                            <input type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary" :checked="groupForm.data.class_numbers.includes(n)" :disabled="takenByOtherGroup.has(n)" @change="toggleClass(n, ($event.target as HTMLInputElement).checked)" />
                            {{ classLabel(n) }}
                        </label>
                    </div>
                </fieldset>

                <fieldset v-if="groupForm.data.cohort !== 'pilihan'" class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <legend class="text-sm font-semibold text-gray-700">Program studi peserta <span class="font-normal text-gray-500">({{ groupForm.data.participants.length }} dipilih)</span></legend>
                        <div class="flex items-center gap-3 text-xs font-semibold">
                            <button type="button" class="text-primary hover:underline" @click="selectAllPrograms">Pilih semua</button>
                            <button type="button" class="text-primary hover:underline" @click="groupForm.data.participants = []">Kosongkan</button>
                        </div>
                    </div>
                    <div v-if="isRegular" class="flex flex-wrap items-center gap-3 rounded-lg bg-gray-50 px-4 py-2.5 text-sm">
                        <label class="flex items-center gap-2 text-gray-700">
                            <input v-model="sameSemester" type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary" />
                            Semester sama untuk semua program studi
                        </label>
                        <select v-if="sameSemester" v-model.number="sharedSemester" :class="[controlClass, 'w-36']" aria-label="Semester sasaran">
                            <option v-for="s in semesterChoices" :key="s" :value="s">Semester {{ s }}</option>
                        </select>
                    </div>
                    <div class="grid max-h-72 gap-x-4 gap-y-1 overflow-y-auto rounded-lg border border-gray-200 p-2 sm:grid-cols-2">
                        <div v-for="p in sortedPrograms" :key="p.id" class="flex min-h-10 items-center justify-between gap-2 rounded-md px-2 hover:bg-gray-50">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary" :checked="hasProgram(p.id)" @change="toggleProgram(p.id, ($event.target as HTMLInputElement).checked)" />
                                {{ p.name }}
                            </label>
                            <select
                                v-if="isRegular && !sameSemester && hasProgram(p.id)"
                                class="h-8 w-32 rounded-md border-gray-200 py-0 text-xs"
                                :aria-label="`Semester sasaran ${p.name}`"
                                :value="groupForm.data.participants.find((row) => row.id === p.id)?.target_semester ?? sharedSemester"
                                @change="groupForm.data.participants.find((row) => row.id === p.id)!.target_semester = Number(($event.target as HTMLSelectElement).value)"
                            >
                                <option v-for="s in semesterChoices" :key="s" :value="s">Semester {{ s }}</option>
                            </select>
                        </div>
                    </div>
                </fieldset>

                <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                    <Button type="button" variant="secondary" @click="groupModalOpen = false">Batal</Button>
                    <Button type="submit" :loading="groupForm.processing.value">Simpan grup</Button>
                </div>
            </form>
        </Modal>

        <Modal :show="copyOpen" max-width="lg" close-button @close="copyOpen = false">
            <div class="rounded-t-lg border-b border-gray-100 bg-gray-50 py-5 pl-6 pr-14">
                <h2 class="text-lg font-bold text-gray-900">Salin dari periode lain</h2>
                <p class="mt-0.5 text-sm text-gray-500">Jumlah kelas dan kapasitas disalin ke {{ periods.find((p) => p.code === period)?.label ?? period }}.</p>
            </div>
            <div class="space-y-4 p-6">
                <Alert v-if="copyError" :title="copyError" />
                <label class="block space-y-1 text-sm font-semibold text-gray-700">Salin dari
                    <select v-model="copySource" :class="[fieldClass]">
                        <option v-for="p in copySourceOptions" :key="p.id" :value="p.code">{{ p.label }}</option>
                    </select>
                </label>
                <p v-if="copyLoading" class="text-sm text-gray-500">Memuat…</p>
                <p v-else-if="copyCandidates.length === 0" class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600">Tidak ada mata kuliah yang bisa disalin. Semua yang dibuka di periode itu sudah dibuka di periode ini, atau periode itu kosong.</p>
                <template v-else>
                    <p class="text-sm text-gray-700"><span class="font-semibold tabular-nums">{{ copyCandidates.length }}</span> mata kuliah akan dibuka di periode ini:</p>
                    <ul class="max-h-56 divide-y divide-gray-100 overflow-y-auto rounded-lg border border-gray-200 text-sm">
                        <li v-for="o in copyCandidates" :key="o.id" class="flex items-center justify-between gap-3 px-3 py-2">
                            <span class="min-w-0 truncate text-gray-900">{{ o.course.name }} <span class="text-xs tabular-nums text-gray-500">{{ o.course.code }}</span></span>
                            <span class="shrink-0 text-xs tabular-nums text-gray-500">{{ o.parallel_class_count }} kelas · {{ o.class_capacity }} kursi</span>
                        </li>
                    </ul>
                    <p class="text-xs text-gray-500">Grup dan dosen tidak ikut disalin. Setelah ini, hapus atau ubah mata kuliah yang berbeda di periode ini.</p>
                </template>
                <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                    <Button type="button" variant="secondary" @click="copyOpen = false">Batal</Button>
                    <Button type="button" :loading="copying" :disabled="copyCandidates.length === 0 || copyLoading" @click="confirmCopy">Salin {{ copyCandidates.length || '' }} mata kuliah</Button>
                </div>
            </div>
        </Modal>

        <ConfirmDialog :show="deleteTarget !== null" :title="`Hapus ${deleteTarget?.label ?? ''}?`" :danger="false" :processing="deleting" :error="deleteError" @confirm="confirmDelete" @close="deleteTarget = null">
            <template v-if="deleteTarget?.kind === 'group'">Semua kelas di grup ini ikut terhapus, termasuk dosen pengampunya.</template>
            <template v-else>Mata kuliah kembali berstatus belum dibuka di periode ini. Ini hanya bisa dilakukan setelah semua grupnya dihapus.</template>
        </ConfirmDialog>
    </AppLayout>
</template>

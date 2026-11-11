<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import AppLogo from '@/Components/shared/AppLogo.vue';
import campusImage from '../../../images/kampus-itk.png';
import { toApiError, type ApiError } from '@/lib/api';
import { publicApi } from '@/lib/publicApi';
import type { Collection, Paginated } from '@/types/models';
import { Search, RotateCcw, Check, CheckCircle2, ArrowDown, X, Trash2, LayoutDashboard, Mail } from 'lucide-vue-next';

interface CatalogProgram {
    id: number;
    code: string;
    name: string;
}

interface CatalogFaculty {
    id: number;
    code: string;
    name: string;
    study_programs: CatalogProgram[];
}

interface CatalogClass {
    class_number: number;
    class_label: string;
    lecturer: { id: number; name: string; title: string | null } | null;
}

interface CatalogCourse {
    id: number;
    code: string;
    name: string;
    sks: number;
    semester: number;
    is_tpb: boolean;
    study_program: CatalogProgram | null;
    faculty: { id: number; code: string; name: string } | null;
    classes: CatalogClass[];
}

interface SelectedCourse {
    course_id: number;
    code: string;
    name: string;
    sks: number;
    semester: number;
    class_number: number;
    class_name: string;
    study_program: string;
}

type ScheduleFormat = 'pdf' | 'xlsx' | 'ics';

const FORMAT_OPTIONS: { value: ScheduleFormat; label: string }[] = [
    { value: 'pdf', label: 'PDF' },
    { value: 'xlsx', label: 'Excel (.xlsx)' },
    { value: 'ics', label: 'Kalender (.ics)' },
];
const SELECTION_STORAGE_KEY = 'simjadwal:landing:selection:v1';

const props = defineProps<{
    auth?: {
        user: {
            name: string;
            identity_number: string | null;
            role: string | null;
            study_program: { id: number; code: string; name: string } | null;
        } | null;
    };
}>();

const selectedFacultyId = ref<number | ''>('');
const selectedStudyProgramId = ref<number | ''>('');
const selectedSemester = ref<number | ''>('');
const searchQuery = ref('');
const appliedFilters = ref({ facultyId: '', programId: '', semester: '', search: '' });
const pageSize = 12;

const faculties = ref<CatalogFaculty[]>([]);
const courses = ref<CatalogCourse[]>([]);
const totalCourses = ref(0);
const currentPage = ref(1);
const lastPage = ref(1);
const loading = ref(false);
const catalogError = ref<ApiError | null>(null);
let courseRequest = 0;

const hasMore = computed(() => currentPage.value < lastPage.value);

async function loadFaculties(): Promise<void> {
    try {
        const response = await publicApi.get<Collection<CatalogFaculty>>('faculties');
        faculties.value = response.data.data;
    } catch (error) {
        catalogError.value = toApiError(error);
    }
}

async function loadCourses(page = 1): Promise<void> {
    const request = ++courseRequest;
    loading.value = true;
    catalogError.value = null;
    const filter = appliedFilters.value;

    try {
        const response = await publicApi.get<Paginated<CatalogCourse>>('courses', {
            params: {
                faculty_id: filter.facultyId,
                study_program_id: filter.programId,
                semester: filter.semester,
                q: filter.search,
                page,
                per_page: pageSize,
            },
        });
        if (request !== courseRequest) return;

        courses.value = page === 1 ? response.data.data : [...courses.value, ...response.data.data];
        totalCourses.value = response.data.meta.pagination.total;
        currentPage.value = response.data.meta.pagination.page;
        lastPage.value = response.data.meta.pagination.last_page;
    } catch (error) {
        if (request === courseRequest) catalogError.value = toApiError(error);
    } finally {
        if (request === courseRequest) loading.value = false;
    }
}

const availableStudyPrograms = computed(() => {
    if (!selectedFacultyId.value) {
        return faculties.value.flatMap((f) => f.study_programs);
    }
    const found = faculties.value.find((f) => f.id === Number(selectedFacultyId.value));
    return found ? found.study_programs : [];
});

watch(selectedFacultyId, () => {
    if (selectedFacultyId.value && selectedStudyProgramId.value) {
        const match = availableStudyPrograms.value.some((p) => p.id === Number(selectedStudyProgramId.value));
        if (!match) {
            selectedStudyProgramId.value = '';
        }
    }
});

function applyFilter() {
    appliedFilters.value = {
        facultyId: String(selectedFacultyId.value),
        programId: String(selectedStudyProgramId.value),
        semester: String(selectedSemester.value),
        search: searchQuery.value.trim(),
    };
    void loadCourses(1);
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch([selectedFacultyId, selectedStudyProgramId, selectedSemester], applyFilter);
watch(searchQuery, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilter, 300);
});

function resetFilter() {
    selectedFacultyId.value = '';
    selectedStudyProgramId.value = '';
    selectedSemester.value = '';
    searchQuery.value = '';
}

const hasActiveFilter = computed(() => Boolean(selectedFacultyId.value || selectedStudyProgramId.value || selectedSemester.value || searchQuery.value));

function loadMore() {
    if (hasMore.value && !loading.value) void loadCourses(currentPage.value + 1);
}

function scrollToSection(id: string) {
    const el = document.getElementById(id);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth' });
    }
}

const selectedCourses = ref<SelectedCourse[]>([]);

function restoreSelection(): void {
    try {
        const stored = window.localStorage.getItem(SELECTION_STORAGE_KEY);
        const parsed: unknown = stored ? JSON.parse(stored) : [];

        if (Array.isArray(parsed)) {
            selectedCourses.value = parsed.filter((item): item is SelectedCourse => typeof item?.course_id === 'number' && typeof item?.class_number === 'number');
        }
    } catch {
        selectedCourses.value = [];
    }
}

watch(
    selectedCourses,
    (items) => {
        try {
            window.localStorage.setItem(SELECTION_STORAGE_KEY, JSON.stringify(items));
        } catch {
        }
    },
    { deep: true },
);

function isCourseSelected(courseId: number): boolean {
    return selectedCourses.value.some((sc) => sc.course_id === courseId);
}

function selectedClassNumber(courseId: number): number | null {
    return selectedCourses.value.find((sc) => sc.course_id === courseId)?.class_number ?? null;
}

function lecturerLabel(cl: CatalogClass): string {
    if (!cl.lecturer) return 'Dosen belum ditentukan';

    return cl.lecturer.title ? `${cl.lecturer.name}, ${cl.lecturer.title}` : cl.lecturer.name;
}

const classPickerCourse = ref<CatalogCourse | null>(null);

function openClassPicker(course: CatalogCourse) {
    classPickerCourse.value = course;
}

function chooseClass(course: CatalogCourse, cl: CatalogClass) {
    const entry: SelectedCourse = {
        course_id: course.id,
        code: course.code,
        name: course.name,
        sks: course.sks,
        semester: course.semester,
        class_number: cl.class_number,
        class_name: `Kelas ${cl.class_label}`,
        study_program: course.study_program?.name ?? 'Mata Kuliah Bersama',
    };
    const index = selectedCourses.value.findIndex((sc) => sc.course_id === course.id);

    if (index > -1) selectedCourses.value.splice(index, 1, entry);
    else selectedCourses.value.push(entry);

    classPickerCourse.value = null;
}

function unpickCourse(course: CatalogCourse) {
    removeSelectedCourse(course.id);
    classPickerCourse.value = null;
}

function removeSelectedCourse(courseId: number) {
    const idx = selectedCourses.value.findIndex((sc) => sc.course_id === courseId);
    if (idx > -1) {
        selectedCourses.value.splice(idx, 1);
    }
}

function clearAllSelections() {
    selectedCourses.value = [];
}

const totalSks = computed(() => {
    return selectedCourses.value.reduce((acc, curr) => acc + curr.sks, 0);
});

const isDrawerOpen = ref(false);
const isVerifyModalOpen = ref(false);
const emailAddress = ref('');
const emailFormats = ref<ScheduleFormat[]>(['pdf']);
const emailConfirmed = ref(false);
const emailSending = ref(false);
const emailError = ref<string | null>(null);

function openVerificationModal() {
    if (selectedCourses.value.length === 0) return;
    emailAddress.value = '';
    emailConfirmed.value = false;
    emailError.value = null;
    isVerifyModalOpen.value = true;
}

async function confirmEmail() {
    emailAddress.value = emailAddress.value.trim();

    if (emailFormats.value.length === 0) {
        emailError.value = 'Pilih minimal satu format file.';
        return;
    }

    emailSending.value = true;
    emailError.value = null;

    try {
        await publicApi.post('schedule-email', {
            email: emailAddress.value,
            classes: selectedCourses.value.map((item) => `${item.course_id}:${item.class_number}`),
            formats: emailFormats.value,
        });
        emailConfirmed.value = true;
    } catch (error) {
        const failure = toApiError(error);
        const fieldMessage = Object.values(failure.errors).flat().map(String)[0];
        emailError.value = failure.status === 429
            ? 'Terlalu banyak permintaan. Coba lagi dalam beberapa saat.'
            : fieldMessage ?? failure.message;
    } finally {
        emailSending.value = false;
    }
}

onMounted(() => {
    restoreSelection();
    void loadFaculties();
    void loadCourses(1);
});
</script>

<template>
    <Head title="SIMJADWAL - Sistem Informasi Penjadwalan Kuliah ITK" />

    <div class="flex min-h-screen flex-col bg-white font-sans text-slate-800 selection:bg-primary selection:text-white">
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <Link href="/" class="flex items-center gap-3" aria-label="SIMJADWAL, beranda">
                    <AppLogo variant="mark" class="h-9" />
                    <span class="flex flex-col leading-tight">
                        <span class="font-display text-lg font-bold tracking-tight text-primary">SIMJADWAL</span>
                        <span class="hidden text-xs text-slate-500 sm:block">Institut Teknologi Kalimantan</span>
                    </span>
                </Link>

                <div class="flex items-center gap-4">
                    <template v-if="props.auth?.user">
                        <span class="hidden text-sm text-slate-600 sm:block">{{ props.auth.user.name }}</span>
                        <Link href="/dashboard" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                            <LayoutDashboard class="size-4" aria-hidden="true" />
                            Dashboard
                        </Link>
                    </template>
                    <Link v-else href="/login" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-800 transition hover:border-primary hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                        Masuk
                    </Link>
                </div>
            </div>
        </header>

        <main class="flex-1" :class="selectedCourses.length > 0 ? 'pb-28' : ''">
            <section id="beranda" class="relative isolate flex min-h-[min(640px,calc(100dvh-4rem))] items-center overflow-hidden bg-[#0c1a3b]">
                <img :src="campusImage" alt="Kampus Institut Teknologi Kalimantan" class="absolute inset-0 -z-20 h-full w-full object-cover object-center" />
                <div class="absolute inset-0 -z-10 bg-gradient-to-r from-[#0c1a3b] via-[#0c1a3b]/85 to-[#0c1a3b]/35 max-md:from-[#0c1a3b]/90 max-md:via-[#0c1a3b]/85 max-md:to-[#0c1a3b]/80"></div>

                <div class="mx-auto w-full max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                    <div class="max-w-3xl">
                        <p class="text-sm font-semibold text-blue-200">Institut Teknologi Kalimantan</p>
                        <h1 class="mt-4 font-display text-4xl font-bold leading-[1.08] tracking-tight text-white sm:text-5xl lg:text-6xl">
                            Pilih kelas kuliah, jadwalnya dikirim ke email.
                        </h1>
                        <p class="mt-6 max-w-[52ch] text-lg leading-relaxed text-blue-100">
                            Pilih mata kuliah dan kelas yang Anda ambil. Jadwal dari fakultas dikirim ke email dalam PDF, Excel, atau kalender.
                        </p>
                        <button
                            type="button"
                            @click="scrollToSection('pilih-matkul')"
                            class="mt-10 inline-flex items-center gap-2 rounded-lg bg-white px-7 py-3.5 text-base font-semibold text-primary transition hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white active:translate-y-px"
                        >
                            Pilih mata kuliah
                            <ArrowDown class="size-5" aria-hidden="true" />
                        </button>
                    </div>
                </div>
            </section>

            <div class="relative z-10 mx-auto -mt-10 max-w-7xl px-4 sm:-mt-12 sm:px-6 lg:px-8">
                <ol class="grid divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-900/10 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                    <li class="group relative flex flex-col items-center p-6 text-center transition hover:bg-blue-50/60 sm:p-8">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary text-white shadow-md shadow-primary/30 transition group-hover:scale-105">
                            <Search class="size-5" aria-hidden="true" />
                        </span>
                        <div class="mt-4 min-w-0">
                            <p class="font-display text-base font-bold text-slate-900">Cari mata kuliah</p>
                            <p class="mt-1 text-sm leading-relaxed text-slate-600">Cari berdasarkan kode atau nama.</p>
                        </div>
                    </li>
                    <li class="group relative flex flex-col items-center p-6 text-center transition hover:bg-blue-50/60 sm:p-8">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary text-white shadow-md shadow-primary/30 transition group-hover:scale-105">
                            <Check class="size-5" aria-hidden="true" />
                        </span>
                        <div class="mt-4 min-w-0">
                            <p class="font-display text-base font-bold text-slate-900">Pilih kelas</p>
                            <p class="mt-1 text-sm leading-relaxed text-slate-600">Tandai kelas yang Anda ambil.</p>
                        </div>
                    </li>
                    <li class="group relative flex flex-col items-center p-6 text-center transition hover:bg-blue-50/60 sm:p-8">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary text-white shadow-md shadow-primary/30 transition group-hover:scale-105">
                            <Mail class="size-5" aria-hidden="true" />
                        </span>
                        <div class="mt-4 min-w-0">
                            <p class="font-display text-base font-bold text-slate-900">Terima jadwal</p>
                            <p class="mt-1 text-sm leading-relaxed text-slate-600">Jadwal masuk email dalam PDF, Excel, atau kalender.</p>
                        </div>
                    </li>
                </ol>
            </div>

            <section id="pilih-matkul" class="scroll-mt-20 py-12 sm:py-16">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mb-8 flex flex-wrap items-end justify-between gap-3">
                        <h2 class="font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Mata kuliah</h2>
                        <p class="text-sm text-slate-600" aria-live="polite">
                            <span class="font-semibold tabular-nums text-slate-900">{{ totalCourses }}</span> mata kuliah ditemukan
                        </p>
                    </div>

                    <div class="grid gap-8 lg:grid-cols-[17rem_1fr] lg:gap-10">
                        <form class="space-y-5 lg:sticky lg:top-24 lg:self-start" @submit.prevent="applyFilter" role="search" aria-label="Saring mata kuliah">
                            <div>
                                <label for="filter-search" class="mb-2 block text-sm font-medium text-slate-900">Cari</label>
                                <div class="relative">
                                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                    <input id="filter-search" v-model="searchQuery" type="search" placeholder="Kode atau nama" class="w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-900 placeholder-slate-500 focus:border-primary focus:outline-hidden focus:ring-1 focus:ring-primary" />
                                </div>
                            </div>
                            <div>
                                <label for="filter-faculty" class="mb-2 block text-sm font-medium text-slate-900">Fakultas</label>
                                <select id="filter-faculty" v-model="selectedFacultyId" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-primary focus:outline-hidden focus:ring-1 focus:ring-primary">
                                    <option value="">Semua fakultas</option>
                                    <option v-for="fac in faculties" :key="fac.id" :value="fac.id">{{ fac.code }} - {{ fac.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="filter-prodi" class="mb-2 block text-sm font-medium text-slate-900">Program studi</label>
                                <select id="filter-prodi" v-model="selectedStudyProgramId" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-primary focus:outline-hidden focus:ring-1 focus:ring-primary">
                                    <option value="">Semua program studi</option>
                                    <option v-for="prog in availableStudyPrograms" :key="prog.id" :value="prog.id">{{ prog.code ? prog.code + ' - ' : '' }}{{ prog.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="filter-semester" class="mb-2 block text-sm font-medium text-slate-900">Semester</label>
                                <select id="filter-semester" v-model="selectedSemester" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-primary focus:outline-hidden focus:ring-1 focus:ring-primary">
                                    <option value="">Semua semester</option>
                                    <option v-for="sem in [1, 2, 3, 4, 5, 6, 7, 8]" :key="sem" :value="sem">Semester {{ sem }}</option>
                                </select>
                            </div>
                            <button v-if="hasActiveFilter" type="button" @click="resetFilter" class="inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                                <RotateCcw class="size-4" aria-hidden="true" />
                                Atur ulang
                            </button>
                        </form>

                        <div class="min-w-0">
                            <div v-if="catalogError" role="alert" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                                {{ catalogError.message }}
                            </div>

                            <ul v-if="loading && courses.length === 0" class="divide-y divide-slate-200 rounded-lg border border-slate-200" role="status" aria-label="Memuat mata kuliah">
                                <li v-for="n in 6" :key="n" class="flex animate-pulse items-center justify-between gap-6 px-5 py-5 motion-reduce:animate-none">
                                    <div class="w-full max-w-md space-y-2.5">
                                        <div class="h-3 w-24 rounded bg-slate-200"></div>
                                        <div class="h-4 w-3/4 rounded bg-slate-200"></div>
                                        <div class="h-3 w-1/2 rounded bg-slate-100"></div>
                                    </div>
                                    <div class="h-9 w-40 rounded-lg bg-slate-100"></div>
                                </li>
                            </ul>

                            <ul v-else-if="courses.length > 0" class="divide-y divide-slate-200 rounded-lg border border-slate-200 transition-opacity" :class="loading ? 'opacity-60' : ''">
                                <li
                                    v-for="course in courses"
                                    :key="course.id"
                                    class="flex flex-col gap-4 px-5 py-5 transition-colors sm:flex-row sm:items-center sm:justify-between sm:gap-6"
                                    :class="isCourseSelected(course.id) ? 'bg-primary/5' : 'bg-white hover:bg-slate-50'"
                                >
                                    <div class="min-w-0">
                                        <p class="font-mono text-xs text-slate-500">{{ course.code }}</p>
                                        <h3 class="mt-0.5 text-base font-semibold leading-snug text-slate-900">{{ course.name }}</h3>
                                        <p class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600">
                                            <span>{{ course.study_program?.name ?? 'Mata kuliah bersama' }}</span>
                                            <span>Semester {{ course.semester }}</span>
                                            <span class="tabular-nums">{{ course.sks }} SKS</span>
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        @click="openClassPicker(course)"
                                        class="inline-flex h-9 w-28 shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary active:translate-y-px"
                                        :class="isCourseSelected(course.id) ? 'border border-slate-300 bg-white text-slate-700 hover:border-primary hover:text-primary' : 'bg-primary text-white hover:bg-primary-hover'"
                                    >
                                        <Check v-if="isCourseSelected(course.id)" class="size-4" aria-hidden="true" />
                                        {{ isCourseSelected(course.id) ? 'Dipilih' : 'Pilih' }}
                                    </button>
                                </li>
                            </ul>

                            <div v-else-if="!catalogError" class="rounded-lg border border-dashed border-slate-300 px-6 py-14 text-center">
                                <template v-if="!faculties.length">
                                    <h3 class="text-base font-semibold text-slate-900">Jadwal belum dipublikasikan</h3>
                                    <p class="mx-auto mt-1.5 max-w-sm text-sm text-slate-600">Mata kuliah tampil di sini setelah fakultasnya mempublikasikan jadwal. Coba lagi nanti.</p>
                                </template>
                                <template v-else>
                                    <h3 class="text-base font-semibold text-slate-900">Tidak ada mata kuliah yang cocok</h3>
                                    <p class="mx-auto mt-1.5 max-w-sm text-sm text-slate-600">Ubah fakultas, program studi, semester, atau kata kunci, lalu coba lagi.</p>
                                </template>
                                <button v-if="faculties.length" type="button" @click="resetFilter" class="mt-5 inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-800 transition hover:border-primary hover:text-primary">
                                    <RotateCcw class="size-4" aria-hidden="true" />
                                    Atur ulang filter
                                </button>
                            </div>

                            <div v-if="hasMore" class="mt-6 flex justify-center">
                                <button type="button" @click="loadMore" :disabled="loading" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-6 py-2.5 text-sm font-semibold text-slate-800 transition hover:border-primary hover:text-primary disabled:opacity-60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                                    Tampilkan lebih banyak
                                    <ArrowDown class="size-4" aria-hidden="true" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200 bg-slate-50">
            <div class="mx-auto max-w-7xl px-4 py-8 text-sm text-slate-600 sm:px-6 lg:px-8">
                <p class="font-semibold text-slate-900">SIMJADWAL</p>
                <p class="mt-1">Capstone Project - Group 11</p>
            </div>
        </footer>

        <div v-if="selectedCourses.length > 0" class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white shadow-[0_-8px_24px_-12px_rgba(15,23,42,0.18)]">
            <div v-if="isDrawerOpen" class="max-h-64 overflow-y-auto border-b border-slate-200">
                <ul class="mx-auto grid max-w-7xl gap-x-8 divide-y divide-slate-100 px-4 sm:px-6 md:grid-cols-2 md:divide-y-0 lg:px-8">
                    <li v-for="item in selectedCourses" :key="item.course_id" class="flex items-center justify-between gap-3 py-3 md:border-b md:border-slate-100">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ item.name }}</p>
                            <p class="mt-0.5 flex gap-x-3 text-xs text-slate-500">
                                <span class="font-mono">{{ item.code }}</span>
                                <span>{{ item.class_name }}</span>
                                <span class="tabular-nums">{{ item.sks }} SKS</span>
                            </p>
                        </div>
                        <button type="button" @click="removeSelectedCourse(item.course_id)" :aria-label="`Hapus ${item.name}`" class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-red-700">
                            <X class="size-4" aria-hidden="true" />
                        </button>
                    </li>
                </ul>
            </div>

            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
                <p class="text-sm text-slate-700">
                    <span class="font-semibold tabular-nums text-slate-900">{{ selectedCourses.length }}</span> mata kuliah,
                    <span class="font-semibold tabular-nums text-slate-900">{{ totalSks }}</span> SKS
                </p>
                <div class="flex items-center gap-2">
                    <button type="button" @click="isDrawerOpen = !isDrawerOpen" :aria-expanded="isDrawerOpen" class="rounded-lg px-3 py-2 text-sm font-semibold text-primary transition hover:bg-slate-100">
                        {{ isDrawerOpen ? 'Sembunyikan' : 'Lihat pilihan' }}
                    </button>
                    <button type="button" @click="clearAllSelections" aria-label="Kosongkan pilihan" class="rounded-lg p-2.5 text-slate-500 transition hover:bg-slate-100 hover:text-red-700">
                        <Trash2 class="size-4" aria-hidden="true" />
                    </button>
                    <button type="button" @click="openVerificationModal" class="inline-flex items-center gap-2 whitespace-nowrap rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary active:translate-y-px">
                        <Mail class="size-4" aria-hidden="true" />
                        Kirim ke email
                    </button>
                </div>
            </div>
        </div>

        <div v-if="classPickerCourse" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4" role="dialog" aria-modal="true" aria-labelledby="class-modal-title" @click.self="classPickerCourse = null" @keydown.esc="classPickerCourse = null">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="font-mono text-xs text-slate-500">{{ classPickerCourse.code }}</p>
                        <h3 id="class-modal-title" class="mt-0.5 font-display text-lg font-bold leading-snug text-slate-900">{{ classPickerCourse.name }}</h3>
                        <p class="mt-1 text-sm text-slate-600">Pilih kelas yang Anda ambil.</p>
                    </div>
                    <button type="button" aria-label="Tutup" @click="classPickerCourse = null" class="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100">
                        <X class="size-5" aria-hidden="true" />
                    </button>
                </div>

                <ul class="mt-5 space-y-2" role="radiogroup" aria-label="Kelas">
                    <li v-for="cl in classPickerCourse.classes" :key="cl.class_number">
                        <button
                            type="button"
                            role="radio"
                            :aria-checked="selectedClassNumber(classPickerCourse.id) === cl.class_number"
                            @click="chooseClass(classPickerCourse, cl)"
                            class="flex w-full items-center justify-between gap-4 rounded-lg border px-4 py-3 text-left transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                            :class="selectedClassNumber(classPickerCourse.id) === cl.class_number ? 'border-primary bg-primary/5' : 'border-slate-300 hover:border-primary'"
                        >
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-slate-900">Kelas {{ cl.class_label }}</span>
                                <span class="mt-0.5 block truncate text-sm text-slate-600">{{ lecturerLabel(cl) }}</span>
                            </span>
                            <Check v-if="selectedClassNumber(classPickerCourse.id) === cl.class_number" class="size-5 shrink-0 text-primary" aria-hidden="true" />
                        </button>
                    </li>
                </ul>

                <div v-if="isCourseSelected(classPickerCourse.id)" class="mt-5 border-t border-slate-200 pt-4">
                    <button type="button" @click="unpickCourse(classPickerCourse)" class="text-sm font-semibold text-red-700 hover:underline">Batalkan pilihan mata kuliah ini</button>
                </div>
            </div>
        </div>

        <div v-if="isVerifyModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4" role="dialog" aria-modal="true" aria-labelledby="email-modal-title">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <div v-if="!emailConfirmed">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 id="email-modal-title" class="font-display text-lg font-bold text-slate-900">Kirim jadwal ke email</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ selectedCourses.length }} mata kuliah, {{ totalSks }} SKS.</p>
                        </div>
                        <button type="button" aria-label="Tutup" @click="isVerifyModalOpen = false" class="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100">
                            <X class="size-5" aria-hidden="true" />
                        </button>
                    </div>
                    <form class="mt-6 space-y-5" @submit.prevent="confirmEmail">
                        <div>
                            <label for="schedule-email" class="mb-2 block text-sm font-medium text-slate-900">Alamat email</label>
                            <input id="schedule-email" v-model.trim="emailAddress" type="email" autocomplete="email" placeholder="nama@student.itk.ac.id" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder-slate-500 focus:border-primary focus:outline-hidden focus:ring-1 focus:ring-primary" />
                        </div>
                        <fieldset>
                            <legend class="mb-2 block text-sm font-medium text-slate-900">Format file</legend>
                            <div class="flex flex-wrap gap-x-5 gap-y-2">
                                <label v-for="option in FORMAT_OPTIONS" :key="option.value" class="inline-flex items-center gap-2 text-sm text-slate-700">
                                    <input v-model="emailFormats" type="checkbox" :value="option.value" class="rounded border-slate-300 text-primary focus:ring-primary" />
                                    {{ option.label }}
                                </label>
                            </div>
                        </fieldset>
                        <p v-if="emailError" role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ emailError }}</p>
                        <div class="flex justify-end gap-2 border-t border-slate-200 pt-5">
                            <button type="button" @click="isVerifyModalOpen = false" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Batal</button>
                            <button type="submit" :disabled="emailSending" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-hover disabled:opacity-60">
                                {{ emailSending ? 'Mengirim…' : 'Kirim jadwal' }}
                            </button>
                        </div>
                    </form>
                </div>
                <div v-else role="status">
                    <CheckCircle2 class="size-10 text-emerald-600" aria-hidden="true" />
                    <h3 id="email-modal-title" class="mt-4 font-display text-lg font-bold text-slate-900">Jadwal sedang dikirim</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                        Jadwal dikirim ke <strong class="text-slate-900">{{ emailAddress }}</strong>. Cek kotak masuk atau folder spam dalam beberapa menit.
                    </p>
                    <div class="mt-6 flex justify-end">
                        <button type="button" @click="isVerifyModalOpen = false" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-hover">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

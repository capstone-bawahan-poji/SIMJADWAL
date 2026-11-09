<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLogo from '@/Components/shared/AppLogo.vue';
import campusImage from '../../../images/kampus-itk.png';
import { courses as mockCourses, faculties, type CourseItem } from '@/mocks/publicCatalog';
import {
    Search,
    RotateCcw,
    Check,
    CheckCircle2,
    BookOpen,
    ArrowRight,
    ArrowDown,
    X,
    Trash2,
    Users,
    LayoutDashboard,
} from 'lucide-vue-next';

interface SelectedCourse {
    course_id: number;
    code: string;
    name: string;
    sks: number;
    semester: number;
    class_id: number;
    class_name: string;
    study_program: string;
}

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

// Filter States
const selectedFacultyId = ref<number | ''>('');
const selectedStudyProgramId = ref<number | ''>('');
const selectedSemester = ref<number | ''>('');
const searchQuery = ref('');
const appliedFilters = ref({ facultyId: '', programId: '', semester: '', search: '' });
const pageSize = 9;
const visibleCount = ref(pageSize);
const courses = computed(() => mockCourses.filter((course) => {
    const filter = appliedFilters.value;
    return (!filter.facultyId || course.faculty_id === Number(filter.facultyId))
        && (!filter.programId || course.study_program_id === Number(filter.programId))
        && (!filter.semester || course.semester === Number(filter.semester))
        && (!filter.search || `${course.code} ${course.name}`.toLowerCase().includes(filter.search));
}));
const visibleCourses = computed(() => courses.value.slice(0, visibleCount.value));

// Available study programs based on chosen faculty
const availableStudyPrograms = computed(() => {
    if (!selectedFacultyId.value) {
        return faculties.flatMap((f) => f.study_programs);
    }
    const found = faculties.find((f) => f.id === Number(selectedFacultyId.value));
    return found ? found.study_programs : [];
});

// Reset study program selection if faculty changes
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
        search: searchQuery.value.trim().toLowerCase(),
    };
    visibleCount.value = pageSize;
}

function resetFilter() {
    selectedFacultyId.value = '';
    selectedStudyProgramId.value = '';
    selectedSemester.value = '';
    searchQuery.value = '';
    applyFilter();
}

function scrollToSection(id: string) {
    const el = document.getElementById(id);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth' });
    }
}

// Selection (Cart) State
const selectedCourses = ref<SelectedCourse[]>([]);
const courseClassSelection = ref<Record<number, number>>({}); // course_id -> class_id

// Init default class for all courses
mockCourses.forEach((c) => {
    if (c.classes && c.classes.length > 0) {
        courseClassSelection.value[c.id] = c.classes[0].id;
    }
});

function isCourseSelected(courseId: number): boolean {
    return selectedCourses.value.some((sc) => sc.course_id === courseId);
}

function getSelectedClassForCourse(courseId: number): string | null {
    const item = selectedCourses.value.find((sc) => sc.course_id === courseId);
    return item ? item.class_name : null;
}

function toggleCourseSelection(course: CourseItem) {
    const existingIndex = selectedCourses.value.findIndex((sc) => sc.course_id === course.id);
    if (existingIndex > -1) {
        selectedCourses.value.splice(existingIndex, 1);
    } else {
        const chosenClassId = courseClassSelection.value[course.id] || (course.classes[0]?.id ?? 1);
        const chosenClass = course.classes.find((cl) => cl.id === chosenClassId) || course.classes[0];
        selectedCourses.value.push({
            course_id: course.id,
            code: course.code,
            name: course.name,
            sks: course.sks,
            semester: course.semester,
            class_id: chosenClass ? chosenClass.id : 1,
            class_name: chosenClass ? chosenClass.name : 'Kelas A',
            study_program: course.study_program_name || 'Program Studi',
        });
    }
}

function selectClassForCourse(course: CourseItem, classId: number) {
    courseClassSelection.value[course.id] = classId;
    const existing = selectedCourses.value.find((sc) => sc.course_id === course.id);
    if (existing) {
        const chosenClass = course.classes.find((cl) => cl.id === classId);
        if (chosenClass) {
            existing.class_id = chosenClass.id;
            existing.class_name = chosenClass.name;
        }
    }
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

// Drawer & Modal States
const isDrawerOpen = ref(false);
const isVerifyModalOpen = ref(false);
const emailAddress = ref('');
const emailConfirmed = ref(false);

function openVerificationModal() {
    if (selectedCourses.value.length === 0) return;
    emailAddress.value = '';
    emailConfirmed.value = false;
    isVerifyModalOpen.value = true;
}

function confirmEmail() {
    emailAddress.value = emailAddress.value.trim();
    emailConfirmed.value = true;
}
</script>

<template>
    <Head title="SIMJADWAL - Sistem Informasi Penjadwalan Kuliah ITK" />

    <div class="min-h-screen bg-slate-50 font-sans text-slate-800 flex flex-col selection:bg-blue-600 selection:text-white">
        <!-- Navigation Bar -->
        <header class="sticky top-0 z-40 w-full border-b border-slate-200/80 bg-white/95 backdrop-blur-md shadow-xs transition-all">
            <div class="mx-auto flex min-h-20 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <!-- Brand Logo & Name -->
                <div class="flex items-center gap-3">
                    <Link href="/" class="flex items-center gap-3 group">
                        <AppLogo variant="mark" class="h-10 w-auto transition-transform group-hover:scale-105" />
                        <div class="flex flex-col">
                            <span class="font-display text-xl font-bold tracking-tight text-primary">
                                SIMJADWAL
                            </span>
                            <span class="text-[11px] font-medium text-slate-500">Institut Teknologi Kalimantan</span>
                        </div>
                    </Link>
                </div>

                <!-- Auth Action: Dedicated Login for Staff/Admin -->
                <div class="flex items-center gap-3">
                    <template v-if="props.auth?.user">
                        <div class="hidden sm:flex flex-col text-right">
                            <span class="text-xs font-semibold text-slate-900 leading-tight">{{ props.auth.user.name }}</span>
                            <span class="text-[11px] text-slate-500 capitalize">{{ props.auth.user.role || 'Civitas ITK' }}</span>
                        </div>
                        <Link
                            href="/dashboard"
                            class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-primary-hover focus:outline-hidden transition-all"
                        >
                            <LayoutDashboard class="size-4" />
                            <span>Dashboard</span>
                        </Link>
                    </template>
                    <template v-else>
                        <!-- Specific button for Lecturers to Superadmins -->
                        <Link
                            href="/login"
                            class="group relative inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs sm:text-sm font-semibold text-white shadow-sm hover:bg-primary-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary transition-all active:scale-95"
                            title="Portal Login Khusus Dosen, Tim Kurikulum, Admin Fakultas/Prodi, dan Superadmin"
                        >
                            <span>Masuk</span>
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <main class="flex-1">
            <!-- Hero Section -->
            <section
                id="beranda"
                class="relative overflow-hidden py-16 text-white sm:py-20"
                style="background-color: #122b68"
            >
                <img :src="campusImage" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full scale-105 object-cover object-center blur-sm" />
                <div class="absolute inset-0" style="background: linear-gradient(120deg, rgba(12, 26, 59, 0.94), rgba(18, 43, 104, 0.87), rgba(30, 58, 138, 0.8))"></div>
                <div class="relative mx-auto max-w-5xl px-4 text-center sm:px-6 lg:px-8">
                    <p class="text-sm font-semibold tracking-wide text-blue-200">SIMJADWAL · Institut Teknologi Kalimantan</p>
                    <h1 class="mx-auto mt-5 max-w-4xl font-display text-4xl font-bold leading-tight tracking-tight sm:text-5xl">
                        Susun rencana kuliahmu
                        <span class="block text-blue-300">lebih mudah</span>
                    </h1>
                    <button
                        type="button"
                        @click="scrollToSection('pilih-matkul')"
                        class="mt-8 inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-semibold text-primary shadow-lg transition hover:bg-blue-50"
                    >
                        <BookOpen class="size-4" />
                        <span>Lihat mata kuliah</span>
                        <ArrowDown class="size-4" />
                    </button>
                </div>
            </section>

            <!-- Main Course Catalog & Class Picker Section -->
            <section id="pilih-matkul" class="scroll-mt-24 py-14 sm:py-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <!-- Section Title -->
                    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-primary">Katalog & Pemilihan</span>
                            <h2 class="mt-1 font-display text-2xl sm:text-3xl font-bold text-slate-900">
                                Pilih Mata Kuliah & Kelas
                            </h2>
                        </div>

                        <!-- Selection Summary Pill if items chosen -->
                        <div v-if="selectedCourses.length > 0" class="flex items-center gap-3">
                            <button
                                type="button"
                                @click="isDrawerOpen = true"
                                class="inline-flex items-center gap-2 rounded-xl bg-blue-50 px-3.5 py-2 text-xs font-bold text-primary border border-blue-200 hover:bg-blue-100 transition-all cursor-pointer"
                            >
                                <span>{{ selectedCourses.length }} Matkul Terpilih ({{ totalSks }} SKS)</span>
                                <ArrowRight class="size-3.5" />
                            </button>
                        </div>
                    </div>

                    <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                            <!-- Filter Fakultas -->
                            <div>
                                <label for="filter-faculty" class="mb-1.5 block text-sm font-medium text-slate-700">
                                    Fakultas
                                </label>
                                <select
                                    id="filter-faculty"
                                    v-model="selectedFacultyId"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-sm text-slate-800 focus:border-primary focus:bg-white focus:ring-1 focus:ring-primary focus:outline-hidden transition-all"
                                >
                                    <option value="">Semua Fakultas</option>
                                    <option v-for="fac in faculties" :key="fac.id" :value="fac.id">
                                        {{ fac.code }} - {{ fac.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Filter Program Studi -->
                            <div>
                                <label for="filter-prodi" class="mb-1.5 block text-sm font-medium text-slate-700">
                                    Program Studi
                                </label>
                                <select
                                    id="filter-prodi"
                                    v-model="selectedStudyProgramId"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-sm text-slate-800 focus:border-primary focus:bg-white focus:ring-1 focus:ring-primary focus:outline-hidden transition-all"
                                >
                                    <option value="">Semua Program Studi</option>
                                    <option v-for="prog in availableStudyPrograms" :key="prog.id" :value="prog.id">
                                        {{ prog.code ? prog.code + ' - ' : '' }}{{ prog.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Filter Semester -->
                            <div>
                                <label for="filter-semester" class="mb-1.5 block text-sm font-medium text-slate-700">
                                    Semester
                                </label>
                                <select
                                    id="filter-semester"
                                    v-model="selectedSemester"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-sm text-slate-800 focus:border-primary focus:bg-white focus:ring-1 focus:ring-primary focus:outline-hidden transition-all"
                                >
                                    <option value="">Semua Semester</option>
                                    <option v-for="sem in [1, 2, 3, 4, 5, 6, 7, 8]" :key="sem" :value="sem">
                                        Semester {{ sem }}
                                    </option>
                                </select>
                            </div>

                            <!-- Search Keyword & Buttons -->
                            <div>
                                <label for="filter-search" class="mb-1.5 block text-sm font-medium text-slate-700">
                                    Cari Mata Kuliah
                                </label>
                                <div class="relative">
                                    <input
                                        id="filter-search"
                                        v-model="searchQuery"
                                        type="text"
                                        placeholder="Kode / nama matkul..."
                                        @keyup.enter="applyFilter"
                                        class="w-full rounded-xl border border-slate-200 bg-slate-50/50 pl-9 pr-3 py-2.5 text-sm text-slate-800 placeholder-slate-400 focus:border-primary focus:bg-white focus:ring-1 focus:ring-primary focus:outline-hidden transition-all"
                                    />
                                    <Search class="absolute left-3 top-3 size-4 text-slate-400" />
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons row -->
                        <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between flex-wrap gap-3">
                            <span class="text-sm text-slate-600">
                                Menampilkan <b class="text-slate-800">{{ visibleCourses.length }}</b> dari <b class="text-slate-800">{{ courses.length }}</b> mata kuliah
                            </span>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="applyFilter"
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition-all hover:bg-primary-hover active:scale-95 cursor-pointer"
                                >
                                    <Search class="size-3.5" />
                                    <span>Terapkan Filter</span>
                                </button>
                                <button
                                    type="button"
                                    @click="resetFilter"
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3.5 py-2.5 text-sm font-medium text-slate-700 transition-all hover:bg-slate-200 cursor-pointer"
                                >
                                    <RotateCcw class="size-3.5 text-slate-500" />
                                    <span>Reset</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Course Cards Grid -->
                    <div v-if="courses.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div
                            v-for="course in visibleCourses"
                            :key="course.id"
                            :class="[
                                'relative flex flex-col rounded-2xl bg-white border transition-all duration-200 shadow-xs hover:shadow-md',
                                isCourseSelected(course.id)
                                    ? 'border-primary ring-2 ring-primary/20 bg-blue-50/20'
                                    : 'border-slate-200/90 hover:border-slate-300'
                            ]"
                        >
                            <!-- Card Header (SIMJADWAL Navy Badge Style) -->
                            <div class="p-5 pb-3 border-b border-slate-100 flex items-start justify-between gap-3">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-primary text-white">
                                        {{ course.faculty_code || 'FSTI' }}
                                    </span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                        {{ course.study_program_name || 'Prodi ITK' }}
                                    </span>
                                </div>
                                <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-bold text-primary">
                                    Sem. {{ course.semester }}
                                </span>
                            </div>

                            <!-- Card Body -->
                            <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
                                <div>
                                    <div class="text-xs font-mono font-semibold tracking-wide text-slate-500">
                                        {{ course.code }}
                                    </div>
                                    <h3 class="mt-1 font-display text-lg font-semibold leading-snug text-slate-900 line-clamp-2">
                                        {{ course.name }}
                                    </h3>

                                    <div class="mt-3 flex items-center gap-3 text-sm text-slate-600">
                                        <span class="inline-flex items-center gap-1 font-semibold text-slate-800">
                                            <BookOpen class="size-3.5 text-primary" />
                                            {{ course.sks }} SKS
                                        </span>
                                        <span class="text-slate-300">•</span>
                                        <span class="inline-flex items-center gap-1 text-slate-500">
                                            <Users class="size-3.5 text-slate-400" />
                                            Kapasitas {{ course.classes[0]?.capacity || 45 }} Mhs
                                        </span>
                                    </div>
                                </div>

                                <!-- Class Selection Pills (Kelas A, Kelas B, etc.) -->
                                <div class="pt-3 border-t border-slate-100">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-600">
                                            Pilihan Kelas
                                        </span>
                                        <span v-if="isCourseSelected(course.id)" class="flex items-center gap-1 text-xs font-bold text-primary">
                                            <Check class="size-3" />
                                            {{ getSelectedClassForCourse(course.id) }} Terpilih
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-3 gap-2">
                                        <button
                                            v-for="cl in course.classes"
                                            :key="cl.id"
                                            type="button"
                                            @click="selectClassForCourse(course, cl.id)"
                                            :class="[
                                                'rounded-lg border px-2.5 py-2 text-sm font-medium text-center transition-all cursor-pointer',
                                                courseClassSelection[course.id] === cl.id
                                                    ? 'bg-primary text-white border-primary shadow-xs'
                                                    : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'
                                            ]"
                                        >
                                            {{ cl.name }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Footer Action -->
                            <div class="p-4 pt-0">
                                <button
                                    type="button"
                                    @click="toggleCourseSelection(course)"
                                    :class="[
                                        'flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold transition-all cursor-pointer',
                                        isCourseSelected(course.id)
                                            ? 'bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100'
                                            : 'bg-primary text-white hover:bg-primary-hover shadow-xs active:scale-98'
                                    ]"
                                >
                                    <template v-if="isCourseSelected(course.id)">
                                        <span>Batalkan Pilihan</span>
                                    </template>
                                    <template v-else>
                                        <span>Pilih Mata Kuliah Ini</span>
                                    </template>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="rounded-2xl bg-white p-12 text-center border border-slate-200/80 shadow-xs">
                        <BookOpen class="mx-auto size-12 text-slate-300" />
                        <h3 class="mt-4 font-display text-base font-bold text-slate-900">Tidak ada mata kuliah ditemukan</h3>
                        <p class="mt-1 text-xs text-slate-500 max-w-sm mx-auto">
                            Coba ubah kombinasi filter fakultas, program studi, semester, atau kata kunci pencarian Anda.
                        </p>
                        <button
                            type="button"
                            @click="resetFilter"
                            class="mt-4 inline-flex items-center gap-2 rounded-xl bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-200 transition-all cursor-pointer"
                        >
                            <RotateCcw class="size-3.5" />
                            <span>Reset Semua Filter</span>
                        </button>
                    </div>
                    <div v-if="visibleCourses.length < courses.length" class="mt-8 flex justify-center">
                        <button
                            type="button"
                            @click="visibleCount += pageSize"
                            class="inline-flex items-center gap-2 rounded-xl border border-blue-200 bg-white px-6 py-3 text-sm font-semibold text-primary shadow-sm transition hover:border-primary hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                        >
                            Lihat lebih banyak
                            <ArrowDown class="size-4" />
                        </button>
                    </div>
                </div>
            </section>
        </main>

        <!-- Floating Selection Bar (Cart) -->
        <div
            v-if="selectedCourses.length > 0"
            class="fixed bottom-0 inset-x-0 z-30 bg-white/95 backdrop-blur-md border-t border-slate-200/90 shadow-2xl p-4 transition-transform duration-300"
        >
            <div class="mx-auto max-w-7xl flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-4 w-full sm:w-auto justify-between sm:justify-start">
                    <div class="flex items-center gap-3">
                        <span class="flex size-10 items-center justify-center rounded-xl bg-blue-100 font-bold text-primary text-sm">
                            {{ selectedCourses.length }}
                        </span>
                        <div>
                            <div class="text-xs font-semibold text-slate-800">
                                {{ selectedCourses.length }} Mata Kuliah Terpilih
                            </div>
                            <div class="text-[11px] text-slate-500">
                                Total Beban: <b class="text-primary font-bold">{{ totalSks }} SKS</b>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="isDrawerOpen = !isDrawerOpen"
                        class="text-xs font-semibold text-primary hover:underline cursor-pointer sm:hidden"
                    >
                        {{ isDrawerOpen ? 'Tutup Daftar' : 'Lihat Daftar' }}
                    </button>
                </div>

                <div class="flex items-center gap-2.5 w-full sm:w-auto">
                    <button
                        type="button"
                        @click="isDrawerOpen = !isDrawerOpen"
                        class="hidden sm:inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-200 transition-all cursor-pointer"
                    >
                        <span>{{ isDrawerOpen ? 'Sembunyikan Daftar' : 'Tinjau Rencana' }}</span>
                    </button>
                    <button
                        type="button"
                        @click="clearAllSelections"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3.5 py-2.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition-all cursor-pointer"
                        title="Kosongkan Pilihan"
                    >
                        <Trash2 class="size-4" />
                    </button>
                    <button
                        type="button"
                        @click="openVerificationModal"
                        class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-xs sm:text-sm font-bold text-white shadow-md hover:bg-primary-hover active:scale-95 transition-all cursor-pointer"
                    >
                        <span>Konfirmasi Email</span>
                    </button>
                </div>
            </div>

            <!-- Drawer View of Chosen Courses -->
            <div v-if="isDrawerOpen" class="mt-4 pt-4 border-t border-slate-200 max-h-60 overflow-y-auto space-y-2">
                <div class="mx-auto max-w-7xl">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                        <div
                            v-for="item in selectedCourses"
                            :key="item.course_id"
                            class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs"
                        >
                            <div class="flex-1 pr-2">
                                <div class="font-bold text-slate-900 truncate">{{ item.name }}</div>
                                <div class="text-[11px] text-slate-500 flex items-center gap-2">
                                    <span>{{ item.code }}</span>
                                    <span>•</span>
                                    <span class="font-semibold text-primary">{{ item.class_name }}</span>
                                    <span>•</span>
                                    <span>{{ item.sks }} SKS</span>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="removeSelectedCourse(item.course_id)"
                                class="text-slate-400 hover:text-rose-600 p-1 transition-colors cursor-pointer"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="isVerifyModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="email-modal-title"
        >
            <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div v-if="!emailConfirmed">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 id="email-modal-title" class="font-display text-lg font-bold text-slate-900">Konfirmasi Email</h3>
                            <p class="mt-1 text-sm text-slate-600">Masukkan email untuk menerima jadwal kuliah.</p>
                        </div>
                        <button type="button" aria-label="Tutup" @click="isVerifyModalOpen = false" class="rounded-lg p-1 text-slate-500 hover:bg-slate-100">
                            <X class="size-5" />
                        </button>
                    </div>
                    <form class="mt-5 space-y-4" @submit.prevent="confirmEmail">
                        <div>
                            <label for="schedule-email" class="mb-1.5 block text-sm font-medium text-slate-700">Alamat email</label>
                            <input
                                id="schedule-email"
                                v-model.trim="emailAddress"
                                type="email"
                                autocomplete="email"
                                placeholder="nama@gmail.com"
                                required
                                class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary"
                            />
                        </div>
                        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                            <button type="button" @click="isVerifyModalOpen = false" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Batal</button>
                            <button type="submit" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover">Konfirmasi</button>
                        </div>
                    </form>
                </div>
                <div v-else class="text-center" role="status">
                    <CheckCircle2 class="mx-auto size-12 text-emerald-600" />
                    <h3 id="email-modal-title" class="mt-4 font-display text-lg font-bold text-slate-900">Konfirmasi berhasil</h3>
                    <p class="mt-2 text-sm text-slate-600">
                        Dalam alur sebenarnya, jadwal akan dikirim ke <strong>{{ emailAddress }}</strong>. Silakan cek email Anda.
                    </p>
                    <p class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-800">Ini hanya simulasi. Belum ada email yang dikirim.</p>
                    <button type="button" @click="isVerifyModalOpen = false" class="mt-5 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover">Tutup</button>
                </div>
            </div>
        </div>

    </div>
</template>

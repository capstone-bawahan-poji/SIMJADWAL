<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Alert from '@/Components/ui/Alert.vue';
import Card from '@/Components/ui/Card.vue';
import ScheduleOverrideModal from '@/Components/scheduling/ScheduleOverrideModal.vue';
import LecturerPreferences from '@/Pages/Dosen/Preferences.vue';
import { fetchAll } from '@/composables/useApiList';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import { api, toApiError, type ApiError } from '@/lib/api';
import { formatTime } from '@/lib/labels';
import type { AcademicPeriod, Collection, FacultySummary, Lecturer, Room, Schedule, ScheduleDetailBase, ScheduleExportFormat, StudyProgramSummary, TimeSlot } from '@/types/models';
import { AxiosError } from 'axios';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';

type ScheduleRow = Schedule['schedule_details'][number];
type Filters = {
    period: string;
    faculty_id: string;
    study_program_id: string;
    semester: string;
    class_number: string;
    lecturer_id: string;
    room_id: string;
    day: string;
};

const DAYS: Record<number, string> = {
    1: 'Senin',
    2: 'Selasa',
    3: 'Rabu',
    4: 'Kamis',
    5: 'Jumat',
    6: 'Sabtu',
    7: 'Minggu',
};
const fieldClass = 'w-full rounded-xl border-gray-200 bg-white text-sm text-gray-900 focus:border-primary focus:ring-primary';
const page = usePage();
const user = computed(() => page.props.auth.user);
const canChooseFaculty = computed(() => ['superadmin', 'admin_tpb', 'dosen'].includes(user.value.role ?? ''));
const canOverride = computed(() => ['superadmin', 'admin_fakultas'].includes(user.value.role ?? ''));
const isLecturer = computed(() => user.value.role === 'dosen');
const isStudent = computed(() => user.value.role === 'mahasiswa');
const canReadLecturerAndRoomReferences = computed(() => !isLecturer.value && !isStudent.value);
const isPreferencesPage = computed(
    () => isLecturer.value && new URLSearchParams(page.url.split('?')[1] ?? '').get('view') === 'preferences',
);

function currentQuery(): URLSearchParams {
    return new URL(window.location.href).searchParams;
}

const query = currentQuery();
const filters = reactive<Filters>({
    period: query.get('period') ?? '',
    faculty_id: query.get('faculty_id') ?? '',
    study_program_id: user.value.role === 'mahasiswa'
        ? String(user.value.study_program_id ?? '')
        : query.get('study_program_id') ?? (user.value.role === 'admin_prodi' ? String(user.value.study_program_id ?? '') : ''),
    semester: query.get('semester') ?? '',
    class_number: user.value.role === 'mahasiswa' ? '' : query.get('class_number') ?? '',
    lecturer_id: user.value.role === 'dosen'
        ? String(user.value.lecturer_id ?? '')
        : query.get('lecturer_id') ?? '',
    room_id: query.get('room_id') ?? '',
    day: query.get('day') ?? '',
});

const periods = ref<AcademicPeriod[]>([]);
const faculties = ref<FacultySummary[]>([]);
const programs = ref<StudyProgramSummary[]>([]);
const lecturers = ref<Lecturer[]>([]);
const rooms = ref<Room[]>([]);
const timeSlots = ref<TimeSlot[]>([]);
const schedule = ref<Schedule | null>(null);
const studentBaseSchedule = ref<Schedule | null>(null);
const studentClasses = ref<string[]>([]);
const studentClassOptions = ref<{ key: string; courseId: number; courseCode: string; courseName: string; semester: number; classNumber: number }[]>([]);
const studentStorageKey = ref('');
const studentSelectionSyncing = ref(false);
const studentDownloadError = ref<string | null>(null);
const downloadingFormat = ref<ScheduleExportFormat | null>(null);
const downloadMenuOpen = ref(false);
const loading = ref(true);
const referencesLoading = ref(true);
const failure = ref<ApiError | null>(null);
const referencesFailure = ref<ApiError | null>(null);
const overrideTarget = ref<ScheduleDetailBase | null>(null);
let scheduleRequest = 0;

const visiblePrograms = computed(() => programs.value.filter((program) => !filters.faculty_id || program.faculty_id === Number(filters.faculty_id)));
const visibleLecturers = computed(() =>
    lecturers.value.filter((lecturer) => {
        const program = programs.value.find((item) => item.id === lecturer.study_program_id);

        return !filters.study_program_id || lecturer.study_program_id === Number(filters.study_program_id)
            ? !filters.faculty_id || program?.faculty_id === Number(filters.faculty_id)
            : false;
    }),
);
const days = computed(() => [...new Set(timeSlots.value.map((slot) => slot.day))].sort((a, b) => a - b));
const sessions = computed(() => [...new Set(timeSlots.value.map((slot) => slot.session))].sort((a, b) => a - b));
const rowsByCell = computed(() => {
    const result = new Map<string, ScheduleRow[]>();

    for (const row of schedule.value?.schedule_details ?? []) {
        const key = `${row.time_slot.day}-${row.time_slot.session}`;
        result.set(key, [...(result.get(key) ?? []), row]);
    }

    for (const rows of result.values()) {
        rows.sort((a, b) => a.course.code.localeCompare(b.course.code) || a.class_number - b.class_number || a.meeting_number - b.meeting_number);
    }

    return result;
});
const hasUnpublishedRun = computed(() => schedule.value?.runs.some((run) => run.published_at === null) ?? false);
const studentCourseOptions = computed(() => {
    const coursesById = new Map<number, { id: number; code: string; name: string; semester: number; classes: typeof studentClassOptions.value }>();

    for (const option of studentClassOptions.value) {
        const course = coursesById.get(option.courseId) ?? {
            id: option.courseId,
            code: option.courseCode,
            name: option.courseName,
            semester: option.semester,
            classes: [],
        };
        course.classes.push(option);
        coursesById.set(option.courseId, course);
    }

    return [...coursesById.values()].sort((a, b) => a.code.localeCompare(b.code));
});

function sessionTime(session: number): string {
    const slot = timeSlots.value.find((item) => item.session === session);

    return slot ? `${formatTime(slot.start_time)}–${formatTime(slot.end_time)}` : '';
}

function dayLabel(day: number): string {
    return timeSlots.value.find((slot) => slot.day === day)?.day_label ?? DAYS[day] ?? String(day);
}

function classLabel(number: number): string {
    return number > 0 && number <= 26 ? String.fromCharCode(64 + number) : String(number);
}

function updateAddress(): void {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(filters)) {
        if (value) params.set(key, value);
    }

    const search = params.toString();
    window.history.replaceState(window.history.state, '', `${window.location.pathname}${search ? `?${search}` : ''}`);
}

function exportUrl(format: ScheduleExportFormat): string {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(filters)) {
        if (value) params.set(key, value);
    }

    if (isLecturer.value && user.value.lecturer_id) {
        params.set('lecturer_id', String(user.value.lecturer_id));
    }
    params.set('format', format);

    return `/api/internal/schedules/export?${params.toString()}`;
}

function studentClassesStorageKey(): string {
    return `simjadwal:student-classes:${user.value.id}:${user.value.study_program_id ?? 'none'}:${filters.period || 'current'}:${filters.semester || 'all'}`;
}

function classSelectionKey(courseId: number, classNumber: number): string {
    return `${courseId}:${classNumber}`;
}

function syncStudentClassOptions(base: Schedule): void {
    const options = new Map<string, (typeof studentClassOptions.value)[number]>();

    for (const row of base.schedule_details) {
        const course = row.course;
        if (filters.semester && String(course.semester) !== filters.semester) continue;

        const key = classSelectionKey(course.id, row.class_number);
        options.set(key, {
            key,
            courseId: course.id,
            courseCode: course.code,
            courseName: course.name,
            semester: course.semester,
            classNumber: row.class_number,
        });
    }

    studentClassOptions.value = [...options.values()].sort((a, b) =>
        a.courseCode.localeCompare(b.courseCode) || a.classNumber - b.classNumber,
    );
}

function selectedStudentClassesFromStorage(): string[] {
    const stored = localStorage.getItem(studentStorageKey.value);
    const available = new Set(studentClassOptions.value.map((option) => option.key));

    if (stored === null) {
        return [];
    }

    try {
        const selected: unknown = JSON.parse(stored);
        if (!Array.isArray(selected) || selected.some((item) => typeof item !== 'string')) {
            throw new Error('Format pilihan kelas tersimpan tidak valid.');
        }

        const selectedByCourse = new Map<number, string>();
        for (const key of selected) {
            if (!available.has(key)) continue;
            const courseId = Number(key.split(':')[0]);
            if (!selectedByCourse.has(courseId)) selectedByCourse.set(courseId, key);
        }

        return [...selectedByCourse.values()];
    } catch (cause) {
        throw new Error(cause instanceof Error ? cause.message : 'Pilihan kelas tersimpan tidak dapat dibaca.');
    }
}

async function applyStudentClassSelection(request: number, base: Schedule, classes: string[]): Promise<void> {
    if (request !== scheduleRequest) return;

    if (classes.length === 0) {
        schedule.value = { ...base, schedule_details: [] };
        loading.value = false;
        return;
    }

    try {
        const params = {
            ...Object.fromEntries(Object.entries(filters).filter(([key, value]) => value !== '' && key !== 'class_number')),
            classes,
        };
        const response = await api.get<{ data: Schedule }>('schedules', { params });
        if (request === scheduleRequest) schedule.value = response.data.data;
    } catch (cause) {
        if (request === scheduleRequest) failure.value = toApiError(cause);
    } finally {
        if (request === scheduleRequest) loading.value = false;
    }
}

async function loadStudentSchedule(): Promise<void> {
    const request = ++scheduleRequest;
    loading.value = true;
    failure.value = null;
    const params = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '' && value !== 'class_number'));

    try {
        const response = await api.get<{ data: Schedule }>('schedules', { params });
        if (request !== scheduleRequest) return;

        const base = response.data.data;
        studentBaseSchedule.value = base;
        syncStudentClassOptions(base);
        studentStorageKey.value = studentClassesStorageKey();
        studentSelectionSyncing.value = true;
        studentClasses.value = selectedStudentClassesFromStorage();

        localStorage.setItem(studentStorageKey.value, JSON.stringify(studentClasses.value));

        studentSelectionSyncing.value = false;
        await applyStudentClassSelection(request, base, studentClasses.value);
    } catch (cause) {
        studentSelectionSyncing.value = false;
        if (request === scheduleRequest) {
            failure.value = cause instanceof Error && !(cause instanceof AxiosError)
                ? { status: 0, message: cause.message, code: 'LOCAL_STORAGE_ERROR', errors: {} }
                : toApiError(cause);
            loading.value = false;
        }
    }
}

async function loadSchedule(): Promise<void> {
    if (isStudent.value) {
        await loadStudentSchedule();
        return;
    }

    const request = ++scheduleRequest;
    loading.value = true;
    failure.value = null;
    const params = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));
    if (isLecturer.value) {
        if (!user.value.lecturer_id) {
            schedule.value = null;
            loading.value = false;
            return;
        }

        params.lecturer_id = String(user.value.lecturer_id);
    }

    try {
        const response = await api.get<{ data: Schedule }>('schedules', { params });
        if (request === scheduleRequest) schedule.value = response.data.data;
    } catch (error) {
        if (request === scheduleRequest) failure.value = toApiError(error);
    } finally {
        if (request === scheduleRequest) loading.value = false;
    }
}

function selectStudentClass(courseId: number, key: string | null): void {
    studentClasses.value = [
        ...studentClasses.value.filter((selected) => Number(selected.split(':')[0]) !== courseId),
        ...(key ? [key] : []),
    ];
}

async function downloadStudentSchedule(format: ScheduleExportFormat): Promise<void> {
    if (studentClasses.value.length === 0) {
        studentDownloadError.value = 'Pilih setidaknya satu mata kuliah atau kelas untuk diunduh.';
        return;
    }

    downloadingFormat.value = format;
    downloadMenuOpen.value = false;
    studentDownloadError.value = null;

    try {
        const params = {
            ...Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '' && value !== 'class_number')),
            classes: studentClasses.value,
            format,
        };
        const response = await api.get<Blob>('schedules/export', { params, responseType: 'blob' });
        const disposition = response.headers['content-disposition'] as string | undefined;
        const filename = disposition?.match(/filename="?([^";]+)"?/i)?.[1] ?? `jadwal-pribadi.${format}`;
        const objectUrl = URL.createObjectURL(response.data);
        const link = document.createElement('a');
        link.href = objectUrl;
        link.download = filename;
        document.body.append(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
    } catch (cause) {
        if (cause instanceof AxiosError && cause.response?.data instanceof Blob) {
            const body = await cause.response.data.text();
            try {
                const parsed = JSON.parse(body) as { message?: string };
                studentDownloadError.value = parsed.message ?? 'Jadwal gagal diunduh.';
            } catch {
                studentDownloadError.value = 'Jadwal gagal diunduh. Silakan coba lagi.';
            }
        } else {
            studentDownloadError.value = toApiError(cause).message;
        }
    } finally {
        downloadingFormat.value = null;
    }
}

async function loadReferences(): Promise<void> {
    referencesLoading.value = true;
    referencesFailure.value = null;

    try {
        const [periodResponse, facultyResponse, programResponse, lecturerResponse, roomResponse, slotResponse] = await Promise.all([
            api.get<Collection<AcademicPeriod>>('academic-periods'),
            api.get<Collection<FacultySummary>>('faculties'),
            api.get<Collection<StudyProgramSummary>>('study-programs'),
            canReadLecturerAndRoomReferences.value ? fetchAll<Lecturer>('lecturers') : Promise.resolve([] as Lecturer[]),
            canReadLecturerAndRoomReferences.value ? fetchAll<Room>('rooms') : Promise.resolve([] as Room[]),
            api.get<Collection<TimeSlot>>('time-slots'),
        ]);
        periods.value = periodResponse.data.data;
        faculties.value = facultyResponse.data.data;
        programs.value = programResponse.data.data;
        lecturers.value = lecturerResponse;
        rooms.value = roomResponse;
        timeSlots.value = slotResponse.data.data;

        if (!filters.period) {
            filters.period = periods.value.find((period) => period.is_current)?.code ?? '';
        }
    } catch (error) {
        referencesFailure.value = toApiError(error);
    } finally {
        referencesLoading.value = false;
    }
}

watch(
    filters,
    () => {
        updateAddress();
        void loadSchedule();
    },
    { deep: true },
);

watch(
    studentClasses,
    async (classes) => {
        if (!isStudent.value || studentSelectionSyncing.value || !studentStorageKey.value || !studentBaseSchedule.value) return;

        try {
            localStorage.setItem(studentStorageKey.value, JSON.stringify(classes));
            const request = ++scheduleRequest;
            loading.value = true;
            failure.value = null;
            await applyStudentClassSelection(request, studentBaseSchedule.value, classes);
        } catch (cause) {
            failure.value = {
                status: 0,
                message: cause instanceof Error ? cause.message : 'Pilihan kelas tidak dapat disimpan.',
                code: 'LOCAL_STORAGE_ERROR',
                errors: {},
            };
        }
    },
    { deep: true },
);

watch(
    () => filters.faculty_id,
    () => {
        if (filters.study_program_id && !visiblePrograms.value.some((program) => String(program.id) === filters.study_program_id)) {
            filters.study_program_id = '';
        }
        filters.lecturer_id = user.value.role === 'dosen' ? String(user.value.lecturer_id ?? '') : '';
    },
);

watch(
    () => filters.study_program_id,
    () => {
        if (user.value.role !== 'dosen' && filters.lecturer_id && !visibleLecturers.value.some((lecturer) => String(lecturer.id) === filters.lecturer_id)) {
            filters.lecturer_id = '';
        }
    },
);

function replaceUpdatedRow(updated: ScheduleDetailBase): void {
    if (!schedule.value) return;
    const index = schedule.value.schedule_details.findIndex((row) => row.id === updated.id);
    if (index >= 0) schedule.value.schedule_details[index] = { ...schedule.value.schedule_details[index], ...updated };
    overrideTarget.value = null;
}

onMounted(() => {
    if (isPreferencesPage.value) return;

    void loadReferences();
    void loadSchedule();
});
onResourcesChanged(['scheduling_runs', 'schedule_details'], () => void loadSchedule());
</script>

<template>
    <Head :title="isPreferencesPage ? 'Preferensi Jadwal' : isStudent ? 'Jadwal Pribadi' : isLecturer ? 'Jadwal Mengajar' : 'Jadwal Kuliah'" />

    <AppLayout>
        <LecturerPreferences v-if="isPreferencesPage" />
        <div v-else class="mx-auto w-full max-w-[1600px] space-y-6 p-6 md:p-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ isStudent ? 'Jadwal Pribadi' : isLecturer ? 'Jadwal Mengajar' : 'Jadwal Kuliah' }}</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ isStudent ? 'Pilih mata kuliah dan kelas yang Anda ikuti untuk melihat jadwal pribadi.' : isLecturer ? 'Jadwal mengajar Anda sesuai periode dan filter yang dipilih.' : 'Jadwal aktif sesuai periode dan filter yang dipilih.' }}
                    </p>
                </div>
                <div v-if="isStudent && schedule?.schedule_details.length" class="relative">
                    <button
                        type="button"
                        class="cursor-pointer list-none rounded-lg bg-primary px-4 py-2 text-center text-sm font-semibold text-white hover:bg-primary-hover"
                        aria-haspopup="menu"
                        :aria-expanded="downloadMenuOpen"
                        :disabled="downloadingFormat !== null"
                        @click="downloadMenuOpen = !downloadMenuOpen"
                    >
                        {{ downloadingFormat ? 'Menyiapkan unduhan...' : 'Unduh jadwal' }}
                    </button>
                    <div v-if="downloadMenuOpen" class="absolute right-0 z-30 mt-2 w-52 rounded-xl border border-gray-200 bg-white p-2 shadow-lg">
                        <button
                            v-for="format in (['xlsx', 'pdf', 'ics'] as ScheduleExportFormat[])"
                            :key="format"
                            type="button"
                            class="block w-full rounded-lg px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                            :disabled="downloadingFormat !== null"
                            @click="downloadStudentSchedule(format)"
                        >
                            {{ format === 'xlsx' ? 'Excel (.xlsx)' : format === 'pdf' ? 'PDF' : 'Kalender (.ics)' }}
                        </button>
                        <p class="px-3 pb-2 pt-1 text-[11px] leading-relaxed text-gray-500">
                            Impor .ics ke Google Calendar atau Outlook. Impor ulang memperbarui jadwal, bukan menggandakannya.
                        </p>
                    </div>
                </div>
                <div
                    v-if="isLecturer && user.lecturer_id && schedule?.schedule_details.length"
                    class="flex flex-wrap gap-2"
                    aria-label="Unduh jadwal mengajar"
                >
                    <a
                        v-for="format in (['xlsx', 'pdf', 'ics'] as ScheduleExportFormat[])"
                        :key="format"
                        :href="exportUrl(format)"
                        class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:border-primary hover:text-primary"
                    >
                        {{ format === 'xlsx' ? 'Unduh Excel' : format === 'pdf' ? 'Unduh PDF' : 'Tambah ke Kalender' }}
                    </a>
                </div>
            </div>

            <Alert v-if="isLecturer && !user.lecturer_id" title="Akun dosen belum terhubung">
                Data dosen untuk akun ini belum tersedia. Hubungi administrator untuk menghubungkan akun.
            </Alert>
            <Alert v-if="referencesFailure" :title="referencesFailure.message" />
            <Alert v-if="failure" :title="failure.message" />
            <Alert v-if="studentDownloadError" :title="studentDownloadError" />
            <Alert v-if="hasUnpublishedRun" title="Jadwal belum dipublikasikan">
                Jadwal aktif ini belum dipublikasikan. Hanya admin yang berwenang yang dapat melihatnya.
            </Alert>

            <Card class="border border-gray-100 shadow-sm">
                <div class="grid gap-4 sm:grid-cols-2" :class="isStudent ? 'lg:grid-cols-2' : 'lg:grid-cols-4'">
                    <label class="space-y-1 text-xs font-semibold text-gray-600">
                        Periode
                        <select v-model="filters.period" class="block" :class="fieldClass" :disabled="referencesLoading">
                            <option value="">Periode berjalan</option>
                            <option v-for="period in periods" :key="period.id" :value="period.code">{{ period.label }}</option>
                        </select>
                    </label>
                    <label v-if="isStudent" class="space-y-1 text-xs font-semibold text-gray-600">
                        Semester
                        <select v-model="filters.semester" class="block" :class="fieldClass">
                            <option value="">Semua semester</option>
                            <option v-for="semester in 14" :key="semester" :value="String(semester)">{{ semester }}</option>
                        </select>
                    </label>
                    <label v-if="canChooseFaculty" class="space-y-1 text-xs font-semibold text-gray-600">
                        Fakultas
                        <select v-model="filters.faculty_id" class="block" :class="fieldClass">
                            <option value="">Semua fakultas</option>
                            <option v-for="faculty in faculties" :key="faculty.id" :value="String(faculty.id)">{{ faculty.code }} · {{ faculty.name }}</option>
                        </select>
                    </label>
                    <label v-if="!isStudent" class="space-y-1 text-xs font-semibold text-gray-600">
                        Program studi
                        <select v-model="filters.study_program_id" class="block" :class="fieldClass">
                            <option value="">Semua prodi</option>
                            <option v-for="program in visiblePrograms" :key="program.id" :value="String(program.id)">{{ program.code }} · {{ program.name }}</option>
                        </select>
                    </label>
                    <label v-if="!isStudent" class="space-y-1 text-xs font-semibold text-gray-600">
                        Semester
                        <select v-model="filters.semester" class="block" :class="fieldClass">
                            <option value="">Semua semester</option>
                            <option v-for="semester in 14" :key="semester" :value="String(semester)">{{ semester }}</option>
                        </select>
                    </label>
                    <label v-if="!isStudent" class="space-y-1 text-xs font-semibold text-gray-600">
                        Kelas
                        <select v-model="filters.class_number" class="block" :class="fieldClass">
                            <option value="">Semua kelas</option>
                            <option v-for="number in 26" :key="number" :value="String(number)">{{ classLabel(number) }}</option>
                        </select>
                    </label>
                    <label v-if="!isStudent && !isLecturer" class="space-y-1 text-xs font-semibold text-gray-600">
                        Dosen
                        <select v-model="filters.lecturer_id" class="block" :class="fieldClass" :disabled="user.role === 'dosen'">
                            <option value="">Semua dosen</option>
                            <option v-for="lecturer in visibleLecturers" :key="lecturer.id" :value="String(lecturer.id)">{{ lecturer.name }}</option>
                        </select>
                    </label>
                    <label v-if="!isStudent && !isLecturer" class="space-y-1 text-xs font-semibold text-gray-600">
                        Ruang
                        <select v-model="filters.room_id" class="block" :class="fieldClass">
                            <option value="">Semua ruang</option>
                            <option v-for="room in rooms" :key="room.id" :value="String(room.id)">{{ room.code }}{{ room.name ? ` · ${room.name}` : '' }}</option>
                        </select>
                    </label>
                    <label v-if="!isStudent" class="space-y-1 text-xs font-semibold text-gray-600">
                        Hari
                        <select v-model="filters.day" class="block" :class="fieldClass">
                            <option value="">Semua hari</option>
                            <option v-for="day in days" :key="day" :value="String(day)">{{ DAYS[day] }}</option>
                        </select>
                    </label>
                </div>
            </Card>

            <Card v-if="isStudent" class="border border-gray-100 shadow-sm">
                <div>
                    <div>
                        <h2 class="text-sm font-bold text-gray-900">Mata kuliah dan kelas yang diambil</h2>
                        <p class="mt-1 text-xs text-gray-500">Pilih satu kelas untuk setiap mata kuliah yang Anda ambil. Pilihan tersimpan di perangkat ini.</p>
                    </div>
                </div>

                <p v-if="!loading && studentClassOptions.length === 0" class="mt-4 rounded-lg bg-gray-50 p-4 text-sm text-gray-500">
                    Belum ada kelas terpublikasi untuk periode dan semester ini.
                </p>
                <div v-else class="mt-4 grid gap-3 md:grid-cols-2">
                    <fieldset v-for="course in studentCourseOptions" :key="course.id" class="rounded-xl border border-gray-100 p-4">
                        <legend class="px-1 text-xs font-bold text-gray-900">{{ course.code }} · {{ course.name }}</legend>
                        <p class="mb-3 text-[11px] text-gray-500">Semester {{ course.semester }}</p>
                        <label v-for="option in course.classes" :key="option.key" class="mr-4 inline-flex items-center gap-2 py-1.5 text-sm text-gray-700">
                            <input
                                type="radio"
                                :name="`student-course-${course.id}`"
                                class="border-gray-300 text-primary focus:ring-primary"
                                :checked="studentClasses.includes(option.key)"
                                @change="selectStudentClass(course.id, option.key)"
                            />
                            Kelas {{ classLabel(option.classNumber) }}
                        </label>
                        <label class="inline-flex items-center gap-2 py-1.5 text-sm text-gray-500">
                            <input
                                type="radio"
                                :name="`student-course-${course.id}`"
                                class="border-gray-300 text-primary focus:ring-primary"
                                :checked="!course.classes.some((option) => studentClasses.includes(option.key))"
                                @change="selectStudentClass(course.id, null)"
                            />
                            Tidak diambil
                        </label>
                    </fieldset>
                </div>
                <p class="mt-3 text-xs text-gray-500">{{ studentClasses.length }} mata kuliah dipilih</p>
            </Card>

            <div v-if="loading || referencesLoading" class="rounded-xl border border-gray-100 bg-white p-8 text-center text-sm text-gray-500">
                Memuat jadwal…
            </div>
            <Card v-else-if="isStudent && studentClasses.length === 0" class="border border-gray-100 text-center text-sm text-gray-500 shadow-sm">
                {{ studentClassOptions.length === 0 ? 'Belum ada kelas terpublikasi untuk periode dan semester ini.' : 'Pilih setidaknya satu kelas untuk menampilkan jadwal pribadi.' }}
            </Card>
            <Card v-else-if="schedule && schedule.schedule_details.length === 0" class="border border-gray-100 text-center text-sm text-gray-500 shadow-sm">
                Belum ada jadwal yang dipublikasikan untuk periode ini.
            </Card>
            <Card v-else-if="schedule" class="!p-0 overflow-hidden border border-gray-100 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1000px] border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                <th class="sticky left-0 z-10 bg-gray-50/95 px-4 py-3 text-left">Sesi</th>
                                <th v-for="day in days" :key="day" class="min-w-52 px-3 py-3 text-center">{{ dayLabel(day) }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="session in sessions" :key="session" class="align-top">
                                <th class="sticky left-0 z-10 whitespace-nowrap bg-white px-4 py-3 text-left">
                                    <span class="font-bold text-gray-900">Sesi {{ session }}</span>
                                    <span class="mt-1 block text-[10px] font-normal text-gray-500">{{ sessionTime(session) }}</span>
                                </th>
                                <td v-for="day in days" :key="day" class="min-w-52 border-l border-gray-50 p-2 align-top">
                                    <div v-if="!(rowsByCell.get(`${day}-${session}`)?.length)" class="flex min-h-24 items-center justify-center rounded-lg border border-dashed border-gray-200 text-[11px] text-gray-400">
                                        Kosong
                                    </div>
                                    <div v-else class="space-y-2">
                                        <article
                                            v-for="row in rowsByCell.get(`${day}-${session}`)"
                                            :key="row.id"
                                            class="rounded-lg border border-blue-100 bg-blue-50/60 p-3"
                                            :class="{ 'cursor-pointer hover:border-primary hover:shadow-sm': canOverride }"
                                            @click="canOverride && (overrideTarget = row)"
                                        >
                                            <div class="flex items-start justify-between gap-2">
                                                <span class="font-mono text-[10px] font-bold text-blue-800">{{ row.course.code }}</span>
                                                <span class="shrink-0 rounded bg-white px-1.5 py-0.5 text-[10px] font-semibold text-gray-600">Kelas {{ classLabel(row.class_number) }}</span>
                                            </div>
                                            <p class="mt-1 font-bold leading-snug text-gray-900">{{ row.course.name }}</p>
                                            <p class="mt-1 text-[11px] text-gray-600">{{ row.lecturer.name }}<span v-if="row.lecturer.title">, {{ row.lecturer.title }}</span></p>
                                            <p class="mt-1 text-[11px] text-gray-600">
                                                {{ row.room?.code ?? 'Belum mendapat ruang' }}<span v-if="row.room"> · {{ row.room.capacity }} kursi</span>
                                            </p>
                                            <span v-if="row.is_overridden" class="mt-2 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800">Diubah manual</span>
                                            <span v-if="canOverride" class="mt-2 block text-[10px] font-medium text-primary">Klik untuk ubah jadwal</span>
                                        </article>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>

        <ScheduleOverrideModal
            v-if="overrideTarget"
            :schedule-detail="overrideTarget"
            :show="true"
            @close="overrideTarget = null"
            @updated="replaceUpdatedRow"
        />
    </AppLayout>
</template>

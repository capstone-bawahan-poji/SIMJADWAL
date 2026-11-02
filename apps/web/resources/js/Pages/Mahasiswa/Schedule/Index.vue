<script setup lang="ts">
import Alert from '@/Components/ui/Alert.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import { api, toApiError, type ApiError } from '@/lib/api';
import { formatTime } from '@/lib/labels';
import type { AcademicPeriod, Collection, Schedule, ScheduleExportFormat, TimeSlot } from '@/types/models';
import { AxiosError } from 'axios';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';

type ScheduleRow = Schedule['schedule_details'][number];
type CourseOption = {
    id: number;
    code: string;
    name: string;
    semester: number;
    classes: number[];
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
const query = new URLSearchParams(window.location.search);
const filters = reactive({
    period: query.get('period') ?? '',
    semester: query.get('semester') ?? '',
});
const periods = ref<AcademicPeriod[]>([]);
const timeSlots = ref<TimeSlot[]>([]);
const baseSchedule = ref<Schedule | null>(null);
const selectedClasses = ref<string[]>([]);
const loading = ref(true);
const referencesLoading = ref(true);
const failure = ref<ApiError | null>(null);
const referencesFailure = ref<ApiError | null>(null);
const downloadFailure = ref<string | null>(null);
const downloadingFormat = ref<ScheduleExportFormat | null>(null);
const initialized = ref(false);
let scheduleRequest = 0;

const days = computed(() => [...new Set(timeSlots.value.map((slot) => slot.day))].sort((a, b) => a - b));
const sessions = computed(() => [...new Set(timeSlots.value.map((slot) => slot.session))].sort((a, b) => a - b));
const courseOptions = computed<CourseOption[]>(() => {
    const courses = new Map<number, CourseOption>();

    for (const row of baseSchedule.value?.schedule_details ?? []) {
        const course = courses.get(row.course.id) ?? {
            id: row.course.id,
            code: row.course.code,
            name: row.course.name,
            semester: row.course.semester,
            classes: [],
        };
        if (!course.classes.includes(row.class_number)) course.classes.push(row.class_number);
        courses.set(row.course.id, course);
    }

    return [...courses.values()]
        .map((course) => ({ ...course, classes: course.classes.sort((a, b) => a - b) }))
        .sort((a, b) => a.code.localeCompare(b.code));
});
const availableClassKeys = computed(() => new Set(courseOptions.value.flatMap((course) =>
    course.classes.map((classNumber) => `${course.id}:${classNumber}`),
)));
const selectedRows = computed(() => (baseSchedule.value?.schedule_details ?? []).filter((row) =>
    selectedClasses.value.includes(`${row.course.id}:${row.class_number}`),
));
const rowsByCell = computed(() => {
    const result = new Map<string, ScheduleRow[]>();
    for (const row of selectedRows.value) {
        const key = `${row.time_slot.day}-${row.time_slot.session}`;
        result.set(key, [...(result.get(key) ?? []), row]);
    }

    for (const rows of result.values()) {
        rows.sort((a, b) => a.course.code.localeCompare(b.course.code) || a.class_number - b.class_number);
    }

    return result;
});
const hasUnpublishedRun = computed(() => baseSchedule.value?.runs.some((run) => run.published_at === null) ?? false);
const courseSearch = ref('');
const filteredCourses = computed(() => {
    const term = courseSearch.value.trim().toLowerCase();
    if (!term) return courseOptions.value;

    return courseOptions.value.filter((course) =>
        course.name.toLowerCase().includes(term) || course.code.toLowerCase().includes(term),
    );
});
const agenda = computed(() =>
    days.value
        .map((day) => ({
            day,
            items: sessions.value.flatMap((session) =>
                (rowsByCell.value.get(`${day}-${session}`) ?? []).map((row) => ({ session, row })),
            ),
        }))
        .filter((group) => group.items.length > 0),
);

function classLabel(number: number): string {
    return number > 0 && number <= 26 ? String.fromCharCode(64 + number) : String(number);
}

function isCourseSelected(courseId: number): boolean {
    return selectedClasses.value.some((key) => Number(key.split(':')[0]) === courseId);
}

function dayLabel(day: number): string {
    return timeSlots.value.find((slot) => slot.day === day)?.day_label ?? DAYS[day] ?? String(day);
}

function sessionTime(session: number): string {
    const slot = timeSlots.value.find((item) => item.session === session);

    return slot ? `${formatTime(slot.start_time)}–${formatTime(slot.end_time)}` : '';
}

function storageKey(): string {
    return `simjadwal:student-classes:${user.value.id}:${user.value.study_program_id ?? 'none'}:${filters.period || 'current'}:${filters.semester || 'all'}`;
}

function updateAddress(): void {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(filters)) {
        if (value) params.set(key, value);
    }

    window.history.replaceState(window.history.state, '', `${window.location.pathname}${params.size ? `?${params}` : ''}`);
}

function saveSelection(selection: string[]): void {
    try {
        localStorage.setItem(storageKey(), JSON.stringify(selection));
        selectedClasses.value = selection;
        downloadFailure.value = null;
    } catch (error) {
        failure.value = {
            status: 0,
            message: error instanceof Error ? error.message : 'Pilihan kelas tidak dapat disimpan di perangkat ini.',
            code: 'LOCAL_STORAGE_ERROR',
            errors: {},
        };
    }
}

function selectClass(courseId: number, classNumber: number | null): void {
    const selection = selectedClasses.value.filter((key) => Number(key.split(':')[0]) !== courseId);
    if (classNumber !== null) selection.push(`${courseId}:${classNumber}`);
    saveSelection(selection);
}

async function loadReferences(): Promise<void> {
    referencesLoading.value = true;
    referencesFailure.value = null;

    try {
        const [periodResponse, slotResponse] = await Promise.all([
            api.get<Collection<AcademicPeriod>>('academic-periods'),
            api.get<Collection<TimeSlot>>('time-slots'),
        ]);
        periods.value = periodResponse.data.data;
        timeSlots.value = slotResponse.data.data;
        if (!filters.period) filters.period = periods.value.find((period) => period.is_current)?.code ?? '';
    } catch (error) {
        referencesFailure.value = toApiError(error);
    } finally {
        referencesLoading.value = false;
    }
}

async function loadSchedule(): Promise<void> {
    const request = ++scheduleRequest;
    loading.value = true;
    failure.value = null;
    baseSchedule.value = null;
    selectedClasses.value = [];

    if (!user.value.study_program_id) {
        loading.value = false;
        return;
    }

    try {
        const params = {
            study_program_id: user.value.study_program_id,
            ...(filters.period ? { period: filters.period } : {}),
            ...(filters.semester ? { semester: Number(filters.semester) } : {}),
        };
        const response = await api.get<{ data: Schedule }>('schedules', { params });
        if (request !== scheduleRequest) return;

        baseSchedule.value = response.data.data;
        const stored = localStorage.getItem(storageKey());
        if (stored !== null) {
            const parsed: unknown = JSON.parse(stored);
            if (!Array.isArray(parsed) || parsed.some((item) => typeof item !== 'string')) {
                throw new Error('Format pilihan kelas tersimpan tidak valid.');
            }
            selectedClasses.value = [...new Set(parsed)].filter((key: string) => availableClassKeys.value.has(key));
            localStorage.setItem(storageKey(), JSON.stringify(selectedClasses.value));
        }
    } catch (error) {
        if (request === scheduleRequest) {
            failure.value = error instanceof AxiosError
                ? toApiError(error)
                : { status: 0, message: error instanceof Error ? error.message : 'Jadwal gagal dimuat.', code: 'STUDENT_SCHEDULE_ERROR', errors: {} };
        }
    } finally {
        if (request === scheduleRequest) loading.value = false;
    }
}

async function downloadSchedule(format: ScheduleExportFormat): Promise<void> {
    if (selectedClasses.value.length === 0 || !user.value.study_program_id) return;

    downloadingFormat.value = format;
    downloadFailure.value = null;

    try {
        const response = await api.get<Blob>('schedules/export', {
            params: {
                study_program_id: user.value.study_program_id,
                ...(filters.period ? { period: filters.period } : {}),
                ...(filters.semester ? { semester: Number(filters.semester) } : {}),
                classes: selectedClasses.value,
                format,
            },
            responseType: 'blob',
        });
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
    } catch (error) {
        if (error instanceof AxiosError && error.response?.data instanceof Blob) {
            const body = await error.response.data.text();
            try {
                const parsed = JSON.parse(body) as { message?: string };
                downloadFailure.value = parsed.message ?? 'Jadwal gagal diunduh.';
            } catch {
                downloadFailure.value = 'Jadwal gagal diunduh. Silakan coba lagi.';
            }
        } else {
            downloadFailure.value = toApiError(error).message;
        }
    } finally {
        downloadingFormat.value = null;
    }
}

watch(
    filters,
    () => {
        if (!initialized.value) return;
        updateAddress();
        void loadSchedule();
    },
    { deep: true },
);

onMounted(async () => {
    await loadReferences();
    initialized.value = true;
    updateAddress();
    await loadSchedule();
});

onResourcesChanged(['scheduling_runs', 'schedule_details'], () => {
    if (initialized.value) void loadSchedule();
});
</script>

<template>
    <Head title="Jadwal Pribadi" />

    <AppLayout>
        <div class="mx-auto w-full max-w-5xl space-y-10 p-6 md:p-8">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Jadwal pribadi</h1>
                    <p class="mt-1 text-sm text-gray-600">Pilih satu kelas untuk setiap mata kuliah yang kamu ikuti, jadwalmu tersusun otomatis.</p>
                </div>
                <div v-if="selectedRows.length" class="flex flex-wrap gap-2" aria-label="Unduh jadwal pribadi">
                    <button
                        v-for="format in (['xlsx', 'pdf', 'ics'] as ScheduleExportFormat[])"
                        :key="format"
                        type="button"
                        class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:border-primary hover:text-primary disabled:opacity-50"
                        :disabled="downloadingFormat !== null"
                        @click="downloadSchedule(format)"
                    >
                        {{ downloadingFormat === format ? 'Menyiapkan…' : format === 'xlsx' ? 'Unduh Excel' : format === 'pdf' ? 'Unduh PDF' : 'Tambah ke Kalender' }}
                    </button>
                </div>
            </header>

            <div class="space-y-3">
                <Alert v-if="!user.study_program_id" title="Akun mahasiswa belum terhubung ke program studi">
                    Hubungi administrator untuk melengkapi data program studi akunmu.
                </Alert>
                <Alert v-if="referencesFailure" :title="referencesFailure.message" />
                <Alert v-if="failure" :title="failure.message" />
                <Alert v-if="downloadFailure" :title="downloadFailure" />
                <Alert v-if="hasUnpublishedRun" title="Jadwal belum dipublikasikan">
                    Jadwal aktif belum dipublikasikan dan belum dapat dilihat mahasiswa.
                </Alert>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="space-y-1 text-sm font-medium text-gray-700">
                    Periode akademik
                    <select v-model="filters.period" class="block" :class="fieldClass" :disabled="referencesLoading">
                        <option value="">Periode berjalan</option>
                        <option v-for="period in periods" :key="period.id" :value="period.code">{{ period.label }}</option>
                    </select>
                </label>
                <label class="space-y-1 text-sm font-medium text-gray-700">
                    Semester
                    <select v-model="filters.semester" class="block" :class="fieldClass">
                        <option value="">Semua semester</option>
                        <option v-for="semester in 14" :key="semester" :value="String(semester)">Semester {{ semester }}</option>
                    </select>
                </label>
            </div>

            <p v-if="loading || referencesLoading" class="py-10 text-center text-sm text-gray-500">Memuat mata kuliah dan jadwal…</p>

            <p v-else-if="baseSchedule && courseOptions.length === 0" class="py-10 text-center text-sm text-gray-500">
                Belum ada mata kuliah terjadwal untuk periode dan semester ini.
            </p>

            <section v-else-if="baseSchedule">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Mata kuliah yang kamu ikuti</h2>
                        <p class="mt-1 text-sm text-gray-600">
                            <span class="font-medium tabular-nums text-gray-900">{{ selectedClasses.length }}</span>
                            dari {{ courseOptions.length }} dipilih · tersimpan di perangkat ini
                        </p>
                    </div>
                    <input
                        v-model="courseSearch"
                        type="search"
                        placeholder="Cari kode atau nama mata kuliah"
                        aria-label="Cari mata kuliah"
                        class="w-full sm:w-72"
                        :class="fieldClass"
                    />
                </div>

                <ul class="mt-4 divide-y divide-gray-200 border-y border-gray-200">
                    <li
                        v-for="course in filteredCourses"
                        :key="course.id"
                        class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-6"
                    >
                        <div class="border-l-2 pl-3" :class="isCourseSelected(course.id) ? 'border-primary' : 'border-transparent'">
                            <p class="font-medium leading-snug text-gray-900">{{ course.name }}</p>
                            <p class="mt-0.5 text-xs text-gray-500">{{ course.code }} · Semester {{ course.semester }}</p>
                        </div>

                        <div class="flex flex-wrap gap-x-8 gap-y-3" role="radiogroup" :aria-label="`Kelas ${course.name}`">
                            <label
                                v-for="classNumber in course.classes"
                                :key="classNumber"
                                class="inline-flex min-w-24 cursor-pointer items-center gap-3 py-1 text-sm text-gray-800"
                            >
                                <input
                                    type="radio"
                                    :name="`course-${course.id}`"
                                    class="size-4 border-gray-300 text-primary focus:ring-primary"
                                    :checked="selectedClasses.includes(`${course.id}:${classNumber}`)"
                                    @change="selectClass(course.id, classNumber)"
                                />
                                Kelas {{ classLabel(classNumber) }}
                            </label>
                            <label class="inline-flex min-w-28 cursor-pointer items-center gap-3 py-1 text-sm text-gray-500">
                                <input
                                    type="radio"
                                    :name="`course-${course.id}`"
                                    class="size-4 border-gray-300 text-primary focus:ring-primary"
                                    :checked="!isCourseSelected(course.id)"
                                    @change="selectClass(course.id, null)"
                                />
                                Tidak diambil
                            </label>
                        </div>
                    </li>
                    <li v-if="filteredCourses.length === 0" class="py-8 text-center text-sm text-gray-500">
                        Tidak ada mata kuliah yang cocok dengan “{{ courseSearch }}”.
                    </li>
                </ul>
            </section>

            <p
                v-if="!loading && !referencesLoading && selectedClasses.length === 0 && courseOptions.length"
                class="rounded-xl border border-dashed border-gray-300 px-6 py-10 text-center text-sm text-gray-600"
            >
                <strong class="block text-base font-semibold text-gray-900">Belum ada kelas dipilih</strong>
                Jadwalmu akan muncul di sini setelah kamu memilih kelas.
            </p>

            <section v-else-if="selectedRows.length">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Jadwal saya</h2>
                        <p class="mt-1 text-sm text-gray-600">{{ baseSchedule?.period }} · {{ selectedRows.length }} pertemuan per minggu</p>
                    </div>
                    <span class="text-sm text-gray-500">{{ user.study_program?.name ?? 'Program studi' }}</span>
                </div>

                <div class="mt-5 space-y-8 md:hidden">
                    <section v-for="group in agenda" :key="group.day">
                        <h3 class="text-sm font-semibold text-gray-900">{{ dayLabel(group.day) }}</h3>
                        <ul class="mt-2 divide-y divide-gray-200 border-y border-gray-200">
                            <li v-for="item in group.items" :key="item.row.id" class="flex gap-4 py-4">
                                <div class="w-24 shrink-0 text-xs text-gray-500">
                                    <p class="font-medium text-gray-900">Sesi {{ item.session }}</p>
                                    <p class="mt-0.5">{{ sessionTime(item.session) }}</p>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium leading-snug text-gray-900">{{ item.row.course.name }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ item.row.course.code }} · Kelas {{ classLabel(item.row.class_number) }}</p>
                                    <p class="mt-2 text-xs leading-relaxed text-gray-600">
                                        {{ item.row.lecturer.name }}<span v-if="item.row.lecturer.title">, {{ item.row.lecturer.title }}</span>
                                        <span class="text-gray-300"> / </span>
                                        {{ item.row.room?.code ?? 'belum mendapat ruang' }}
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </section>
                </div>

                <div class="mt-5 hidden overflow-x-auto rounded-xl border border-gray-200 bg-white md:block">
                    <table class="w-full min-w-[900px] border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-xs text-gray-500">
                                <th class="sticky left-0 z-10 w-36 bg-gray-50 px-5 py-4 text-left font-medium">Sesi</th>
                                <th v-for="day in days" :key="day" class="min-w-52 border-l border-gray-200 px-5 py-4 text-left font-medium">
                                    {{ dayLabel(day) }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y-2 divide-gray-200">
                            <tr v-for="session in sessions" :key="session" class="align-top">
                                <th class="sticky left-0 z-10 whitespace-nowrap bg-white px-5 py-5 text-left font-medium text-gray-900">
                                    Sesi {{ session }}
                                    <span class="mt-1 block text-xs font-normal text-gray-500">{{ sessionTime(session) }}</span>
                                </th>
                                <td v-for="day in days" :key="day" class="min-w-52 border-l border-gray-200 px-5 py-5">
                                    <p v-if="!rowsByCell.get(`${day}-${session}`)?.length" class="text-gray-300" aria-label="Kosong">—</p>
                                    <ul v-else class="divide-y divide-gray-200">
                                        <li v-for="row in rowsByCell.get(`${day}-${session}`)" :key="row.id" class="py-4 first:pt-0 last:pb-0">
                                            <div class="border-l-2 border-primary/40 pl-3">
                                                <p class="font-semibold leading-snug text-gray-900">{{ row.course.name }}</p>
                                                <p class="mt-1 text-xs text-gray-500">{{ row.course.code }} · Kelas {{ classLabel(row.class_number) }}</p>
                                                <p class="mt-2 text-xs leading-relaxed text-gray-600">
                                                    {{ row.lecturer.name }}<span v-if="row.lecturer.title">, {{ row.lecturer.title }}</span>
                                                </p>
                                                <p class="mt-1 text-xs text-gray-600">{{ row.room?.code ?? 'Belum mendapat ruang' }}</p>
                                            </div>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

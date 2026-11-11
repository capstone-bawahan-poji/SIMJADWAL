<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Alert from '@/Components/ui/Alert.vue';
import Card from '@/Components/ui/Card.vue';
import PublicationAdminPanel from '@/Components/scheduling/PublicationAdminPanel.vue';
import ScheduleOverrideModal from '@/Components/scheduling/ScheduleOverrideModal.vue';
import SubmissionStatusBadge from '@/Components/scheduling/SubmissionStatusBadge.vue';
import { fetchAll } from '@/composables/useApiList';
import { useProgramSubmission } from '@/composables/useProgramSubmission';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import { useSemesterOptions } from '@/composables/useSemesterOptions';
import { api, toApiError, type ApiError } from '@/lib/api';
import { formatTime } from '@/lib/labels';
import type { AcademicPeriod, Collection, FacultySummary, Lecturer, Room, Schedule, ScheduleDetailBase, ScheduleExportFormat, StudyProgramSummary, TimeSlot } from '@/types/models';
import { Head, usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, toRef, watch } from 'vue';

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
const isLecturer = computed(() => user.value.role === 'dosen');
const canReadLecturerAndRoomReferences = computed(() => !isLecturer.value);

function currentQuery(): URLSearchParams {
    return new URL(window.location.href).searchParams;
}

const query = currentQuery();
const filters = reactive<Filters>({
    period: query.get('period') ?? '',
    faculty_id: query.get('faculty_id') ?? '',
    study_program_id: query.get('study_program_id') ?? (user.value.role === 'admin_prodi' ? String(user.value.study_program_id ?? '') : ''),
    semester: query.get('semester') ?? '',
    class_number: query.get('class_number') ?? '',
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
const loading = ref(true);
const referencesLoading = ref(true);
const failure = ref<ApiError | null>(null);
const referencesFailure = ref<ApiError | null>(null);
const overrideTarget = ref<ScheduleDetailBase | null>(null);
const exportOpen = ref(false);
let scheduleRequest = 0;

// Only the program moves its own meetings, and only while its schedule is a draft or returned.
const isProgramAdmin = computed(() => user.value.role === 'admin_prodi');
const ownProgramId = computed(() => (isProgramAdmin.value ? user.value.study_program_id : null));
const resolvedPeriod = computed(() => schedule.value?.period ?? '');
const { submission, editable } = useProgramSubmission(ownProgramId, resolvedPeriod);
const canOverride = computed(() => isProgramAdmin.value && editable.value);

const semesterOptions = useSemesterOptions(
    () => filters.period || periods.value.find((period) => period.is_current)?.code || '',
    toRef(filters, 'semester'),
);

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
const EXPORT_FORMATS: { format: ScheduleExportFormat; label: string; hint: string }[] = [
    { format: 'xlsx', label: 'Excel (.xlsx)', hint: 'Matriks dan daftar jadwal' },
    { format: 'pdf', label: 'PDF', hint: 'Siap cetak, kertas A4 landscape' },
    { format: 'ics', label: 'Kalender (.ics)', hint: 'Tambah jadwal ke kalender' },
];

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

function onExportFocusOut(event: FocusEvent): void {
    if (!(event.currentTarget as HTMLElement).contains(event.relatedTarget as Node | null)) exportOpen.value = false;
}

async function loadSchedule(): Promise<void> {
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

// A swap moves two meetings, so reload instead of patching one row.
function overrideSaved(): void {
    overrideTarget.value = null;
    void loadSchedule();
}

onMounted(() => {
    void loadReferences();
    void loadSchedule();
});
onResourcesChanged(['scheduling_runs', 'schedule_details'], () => void loadSchedule());
</script>

<template>
    <Head :title="isLecturer ? 'Jadwal Mengajar' : 'Jadwal Kuliah'" />

    <AppLayout>
        <div class="mx-auto w-full max-w-[1600px] space-y-6 p-6 md:p-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ isLecturer ? 'Jadwal Mengajar' : 'Jadwal Kuliah' }}</h1>
                    <p v-if="isLecturer" class="mt-1 text-sm text-gray-500">Jadwal mengajar Anda sesuai periode dan filter yang dipilih.</p>
                    <p v-if="isProgramAdmin && submission && submission.status !== 'accepted'" class="mt-2 flex flex-wrap items-center gap-2 text-sm text-gray-600">
                        Status jadwal program studi <SubmissionStatusBadge :status="submission.status" />
                        <span v-if="canOverride">Klik pertemuan untuk memindahkan atau menukarnya.</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <div v-if="isLecturer && user.lecturer_id && schedule?.schedule_details.length" class="flex flex-wrap gap-2" aria-label="Unduh jadwal mengajar">
                        <a
                            v-for="format in (['xlsx', 'pdf', 'ics'] as ScheduleExportFormat[])"
                            :key="format"
                            :href="exportUrl(format)"
                            class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:border-primary hover:text-primary"
                        >
                            {{ format === 'xlsx' ? 'Unduh Excel' : format === 'pdf' ? 'Unduh PDF' : 'Tambah ke Kalender' }}
                        </a>
                    </div>
                    <div
                        v-if="user.role === 'admin_prodi' && schedule?.schedule_details.length"
                        class="relative"
                        @focusout="onExportFocusOut"
                        @keydown.esc="exportOpen = false"
                    >
                        <button
                            type="button"
                            class="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                            aria-haspopup="menu"
                            :aria-expanded="exportOpen"
                            @click="exportOpen = !exportOpen"
                        >
                            Unduh Jadwal
                            <ChevronDown class="size-4 transition-transform" :class="exportOpen ? 'rotate-180' : ''" aria-hidden="true" />
                        </button>
                        <div v-if="exportOpen" role="menu" class="absolute right-0 z-30 mt-1 w-64 rounded-lg border border-gray-200 bg-white p-1 shadow-lg">
                            <a
                                v-for="item in EXPORT_FORMATS"
                                :key="item.format"
                                :href="exportUrl(item.format)"
                                role="menuitem"
                                class="block rounded-md px-3 py-2 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                @click="exportOpen = false"
                            >
                                <span class="block text-sm font-semibold text-gray-900">{{ item.label }}</span>
                                <span class="block text-xs text-gray-500">{{ item.hint }}</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <Alert v-if="isLecturer && !user.lecturer_id" title="Akun dosen belum terhubung">
                Data dosen untuk akun ini belum tersedia. Hubungi administrator untuk menghubungkan akun.
            </Alert>
            <Alert v-if="referencesFailure" :title="referencesFailure.message" />
            <Alert v-if="failure" :title="failure.message" />
            <PublicationAdminPanel
                v-if="user.role === 'superadmin' && filters.faculty_id && resolvedPeriod"
                :faculty-id="Number(filters.faculty_id)"
                :period="resolvedPeriod"
                @withdrawn="loadSchedule"
            />
            <Alert v-if="hasUnpublishedRun" title="Jadwal belum dipublikasikan">
                Jadwal aktif ini belum dipublikasikan. Hanya admin yang berwenang yang dapat melihatnya.
            </Alert>

            <Card class="border border-gray-100 shadow-sm">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="space-y-1 text-xs font-semibold text-gray-600">
                        Periode
                        <select v-model="filters.period" class="block" :class="fieldClass" :disabled="referencesLoading">
                            <option value="">Periode berjalan</option>
                            <option v-for="period in periods" :key="period.id" :value="period.code">{{ period.label }}</option>
                        </select>
                    </label>
                    <label v-if="canChooseFaculty" class="space-y-1 text-xs font-semibold text-gray-600">
                        Fakultas
                        <select v-model="filters.faculty_id" class="block" :class="fieldClass">
                            <option value="">Semua fakultas</option>
                            <option v-for="faculty in faculties" :key="faculty.id" :value="String(faculty.id)">{{ faculty.code }} · {{ faculty.name }}</option>
                        </select>
                    </label>
                    <label class="space-y-1 text-xs font-semibold text-gray-600">
                        Program studi
                        <select v-model="filters.study_program_id" class="block" :class="fieldClass">
                            <option value="">Semua prodi</option>
                            <option v-for="program in visiblePrograms" :key="program.id" :value="String(program.id)">{{ program.code }} · {{ program.name }}</option>
                        </select>
                    </label>
                    <label class="space-y-1 text-xs font-semibold text-gray-600">
                        Semester
                        <select v-model="filters.semester" class="block" :class="fieldClass">
                            <option value="">Semua semester</option>
                            <option v-for="semester in semesterOptions" :key="semester" :value="String(semester)">{{ semester }}</option>
                        </select>
                    </label>
                    <label class="space-y-1 text-xs font-semibold text-gray-600">
                        Kelas
                        <select v-model="filters.class_number" class="block" :class="fieldClass">
                            <option value="">Semua kelas</option>
                            <option v-for="number in 26" :key="number" :value="String(number)">{{ classLabel(number) }}</option>
                        </select>
                    </label>
                    <label v-if="!isLecturer" class="space-y-1 text-xs font-semibold text-gray-600">
                        Dosen
                        <select v-model="filters.lecturer_id" class="block" :class="fieldClass" :disabled="user.role === 'dosen'">
                            <option value="">Semua dosen</option>
                            <option v-for="lecturer in visibleLecturers" :key="lecturer.id" :value="String(lecturer.id)">{{ lecturer.name }}</option>
                        </select>
                    </label>
                    <label v-if="!isLecturer" class="space-y-1 text-xs font-semibold text-gray-600">
                        Ruang
                        <select v-model="filters.room_id" class="block" :class="fieldClass">
                            <option value="">Semua ruang</option>
                            <option v-for="room in rooms" :key="room.id" :value="String(room.id)">{{ room.code }}{{ room.name ? ` · ${room.name}` : '' }}</option>
                        </select>
                    </label>
                    <label class="space-y-1 text-xs font-semibold text-gray-600">
                        Hari
                        <select v-model="filters.day" class="block" :class="fieldClass">
                            <option value="">Semua hari</option>
                            <option v-for="day in days" :key="day" :value="String(day)">{{ DAYS[day] }}</option>
                        </select>
                    </label>
                </div>
            </Card>

            <div v-if="loading || referencesLoading" class="rounded-xl border border-gray-100 bg-white p-8 text-center text-sm text-gray-500">
                Memuat jadwal…
            </div>
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
            @updated="overrideSaved"
        />
    </AppLayout>
</template>

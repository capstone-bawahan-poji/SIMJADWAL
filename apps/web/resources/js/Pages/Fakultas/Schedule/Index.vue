<script setup lang="ts">
import Alert from '@/Components/ui/Alert.vue';
import Card from '@/Components/ui/Card.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import { useSemesterOptions } from '@/composables/useSemesterOptions';
import { api, toApiError, type ApiError } from '@/lib/api';
import { formatTime } from '@/lib/labels';
import type { AcademicPeriod, Collection, Schedule, StudyProgramSummary, TimeSlot } from '@/types/models';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, toRef, watch } from 'vue';

type ScheduleRow = Schedule['schedule_details'][number];

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
const query = new URLSearchParams(window.location.search);
const filters = reactive({
    period: query.get('period') ?? '',
    study_program_id: query.get('study_program_id') ?? '',
    semester: query.get('semester') ?? '',
    day: query.get('day') ?? '',
});
const periods = ref<AcademicPeriod[]>([]);
const programs = ref<StudyProgramSummary[]>([]);
const timeSlots = ref<TimeSlot[]>([]);
const schedule = ref<Schedule | null>(null);
const referencesLoading = ref(true);
const loading = ref(true);
const referencesFailure = ref<ApiError | null>(null);
const failure = ref<ApiError | null>(null);
const initialized = ref(false);
let scheduleRequest = 0;

const page = usePage();
const user = computed(() => page.props.auth.user);
// "Periode berjalan" ('') resolves to the current period so its parity still applies.
const semesterOptions = useSemesterOptions(
    () => filters.period || periods.value.find((period) => period.is_current)?.code || '',
    toRef(filters, 'semester'),
);

const days = computed(() => [...new Set(timeSlots.value.map((slot) => slot.day))].sort((a, b) => a - b));
const visibleDays = computed(() => filters.day ? [Number(filters.day)] : days.value);
const sessions = computed(() => [...new Set(timeSlots.value.map((slot) => slot.session))].sort((a, b) => a - b));
const rowsByCell = computed(() => {
    const result = new Map<string, ScheduleRow[]>();
    for (const row of schedule.value?.schedule_details ?? []) {
        const key = `${row.time_slot.day}-${row.time_slot.session}`;
        result.set(key, [...(result.get(key) ?? []), row]);
    }

    for (const rows of result.values()) {
        rows.sort((a, b) => a.course.code.localeCompare(b.course.code) || a.class_number - b.class_number);
    }

    return result;
});
const hasUnpublishedRun = computed(() => schedule.value?.runs.some((run) => run.published_at === null) ?? false);

function classLabel(number: number): string {
    return number > 0 && number <= 26 ? String.fromCharCode(64 + number) : String(number);
}

function dayLabel(day: number): string {
    return timeSlots.value.find((slot) => slot.day === day)?.day_label ?? DAYS[day] ?? String(day);
}

function sessionTime(session: number): string {
    const slot = timeSlots.value.find((item) => item.session === session);

    return slot ? `${formatTime(slot.start_time)}–${formatTime(slot.end_time)}` : '';
}

function updateAddress(): void {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(filters)) {
        if (value) params.set(key, value);
    }

    window.history.replaceState(window.history.state, '', `${window.location.pathname}${params.size ? `?${params}` : ''}`);
}

async function loadReferences(): Promise<void> {
    referencesLoading.value = true;
    referencesFailure.value = null;

    try {
        const [periodResponse, programResponse, slotResponse] = await Promise.all([
            api.get<Collection<AcademicPeriod>>('academic-periods'),
            // Faculty admins only pick from their own faculty; superadmin and TPB admins see every program.
            api.get<Collection<StudyProgramSummary>>('study-programs', {
                params: user.value.role === 'admin_fakultas' && user.value.faculty_id ? { faculty_id: user.value.faculty_id } : {},
            }),
            api.get<Collection<TimeSlot>>('time-slots'),
        ]);
        periods.value = periodResponse.data.data;
        programs.value = programResponse.data.data;
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

async function loadSchedule(): Promise<void> {
    const request = ++scheduleRequest;
    loading.value = true;
    failure.value = null;
    const params = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));

    try {
        const response = await api.get<{ data: Schedule }>('schedules', { params });
        if (request === scheduleRequest) schedule.value = response.data.data;
    } catch (error) {
        if (request === scheduleRequest) failure.value = toApiError(error);
    } finally {
        if (request === scheduleRequest) loading.value = false;
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
    <Head title="Jadwal Fakultas" />

    <AppLayout>
        <div class="mx-auto w-full max-w-[1600px] space-y-6 p-6 md:p-8">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">Jadwal Fakultas</h1>
                <p class="mt-1 text-sm text-gray-500">Lihat jadwal aktif fakultas berdasarkan periode, program studi, semester, dan hari.</p>
            </div>

            <Alert v-if="referencesFailure" :title="referencesFailure.message" />
            <Alert v-if="failure" :title="failure.message" />
            <Alert v-if="hasUnpublishedRun" title="Jadwal belum dipublikasikan">
                Jadwal aktif ini belum dipublikasikan dan hanya terlihat oleh admin fakultas.
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
                    <label class="space-y-1 text-xs font-semibold text-gray-600">
                        Program studi
                        <select v-model="filters.study_program_id" class="block" :class="fieldClass" :disabled="referencesLoading">
                            <option value="">Semua prodi</option>
                            <option v-for="program in programs" :key="program.id" :value="String(program.id)">{{ program.code }} · {{ program.name }}</option>
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
                        Hari
                        <select v-model="filters.day" class="block" :class="fieldClass">
                            <option value="">Semua hari</option>
                            <option v-for="day in days" :key="day" :value="String(day)">{{ dayLabel(day) }}</option>
                        </select>
                    </label>
                </div>
            </Card>

            <div v-if="loading || referencesLoading" class="rounded-xl border border-gray-100 bg-white p-8 text-center text-sm text-gray-500">
                Memuat jadwal…
            </div>
            <Card v-else-if="schedule && schedule.schedule_details.length === 0" class="border border-gray-100 text-center text-sm text-gray-500 shadow-sm">
                Belum ada jadwal aktif untuk filter yang dipilih.
            </Card>
            <Card v-else-if="schedule" class="!p-0 overflow-hidden border border-gray-100 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-4">
                    <p class="text-sm font-semibold text-gray-900">
                        {{ schedule.runs[0]?.faculty.name ?? 'Fakultas' }}
                        <span class="font-normal text-gray-500">· {{ schedule.period }}</span>
                    </p>
                    <p class="text-xs text-gray-500">{{ schedule.schedule_details.length }} jadwal</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1000px] border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50/60 text-xs text-gray-500">
                                <th class="sticky left-0 z-10 w-36 bg-gray-50 px-5 py-4 text-left font-medium">Sesi</th>
                                <th v-for="day in visibleDays" :key="day" class="min-w-60 border-l border-gray-200 px-5 py-4 text-left font-medium">
                                    {{ dayLabel(day) }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y-2 divide-gray-300">
                            <tr v-for="session in sessions" :key="session" class="align-top">
                                <th class="sticky left-0 z-10 whitespace-nowrap bg-white px-5 py-5 text-left font-medium text-gray-900">
                                    Sesi {{ session }}
                                    <span class="mt-1 block text-xs font-normal text-gray-500">{{ sessionTime(session) }}</span>
                                </th>
                                <td v-for="day in visibleDays" :key="day" class="min-w-60 border-l border-gray-200 px-5 py-5">
                                    <p v-if="!rowsByCell.get(`${day}-${session}`)?.length" class="text-gray-300" aria-label="Kosong">—</p>
                                    <ul v-else class="divide-y divide-gray-300">
                                        <li
                                            v-for="row in rowsByCell.get(`${day}-${session}`)"
                                            :key="row.id"
                                            class="py-5 first:pt-0 last:pb-0"
                                        >
                                            <div class="border-l-2 border-transparent pl-3">
                                                <p class="font-medium leading-snug text-gray-900">{{ row.course.name }}</p>
                                                <p class="mt-1 text-xs text-gray-500">
                                                    {{ row.course.code }} · Kelas {{ classLabel(row.class_number) }} · Semester {{ row.course.semester }}
                                                </p>
                                                <p class="mt-2 text-xs leading-relaxed text-gray-600">
                                                    {{ row.lecturer.name }}<span v-if="row.lecturer.title">, {{ row.lecturer.title }}</span>
                                                    <span class="text-gray-300"> / </span>
                                                    {{ row.room?.code ?? 'Belum mendapat ruang' }}
                                                </p>
                                                <p v-if="row.is_overridden" class="mt-1 text-xs text-amber-700">Diubah manual</p>
                                            </div>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>

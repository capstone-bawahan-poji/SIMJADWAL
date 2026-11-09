<script setup lang="ts">
import Alert from '@/Components/ui/Alert.vue';
import Card from '@/Components/ui/Card.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DashboardChart from '@/Components/dashboard/DashboardChart.vue';
import DashboardSummary from '@/Components/dashboard/DashboardSummary.vue';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import { api, toApiError, type ApiError } from '@/lib/api';
import { formatTime } from '@/lib/labels';
import type { AcademicPeriod, Collection, Schedule, ScheduleFilters } from '@/types/models';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

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

const page = usePage();
const user = computed(() => page.props.auth.user);
const schedule = ref<Schedule | null>(null);
const loading = ref(true);
const failure = ref<ApiError | null>(null);
const missingLecturer = computed(() => !user.value.lecturer_id);
let scheduleRequest = 0;

const rows = computed(() => schedule.value?.schedule_details ?? []);
const today = computed(() => {
    const weekday = new Date().getDay();

    return weekday === 0 ? 7 : weekday;
});
const todayCount = computed(() => rows.value.filter((row) => row.time_slot.day === today.value).length);
const courseCount = computed(() => new Set(rows.value.map((row) => row.course.id)).size);
const classCount = computed(() => new Set(rows.value.map((row) => `${row.course.id}:${row.class_number}`)).size);
const meetingsByDay = computed(() => Object.entries(DAYS).map(([day, label]) => ({
    label,
    value: rows.value.filter((row) => row.time_slot.day === Number(day)).length,
})));
const upcomingRows = computed(() => {
    const now = new Date();
    const nowMinutes = now.getHours() * 60 + now.getMinutes();

    return [...rows.value]
        .sort((a, b) => {
            const offset = (row: ScheduleRow) => {
                let daysUntil = (row.time_slot.day - today.value + 7) % 7;
                const [hour, minute] = row.time_slot.start_time.split(':').map(Number);
                if (daysUntil === 0 && hour * 60 + minute < nowMinutes) daysUntil = 7;

                return daysUntil;
            };

            return offset(a) - offset(b)
                || a.time_slot.start_time.localeCompare(b.time_slot.start_time)
                || a.course.code.localeCompare(b.course.code);
        })
        .slice(0, 5);
});

function classLabel(number: number): string {
    return number > 0 && number <= 26 ? String.fromCharCode(64 + number) : String(number);
}

function timeRange(row: ScheduleRow): string {
    return `${formatTime(row.time_slot.start_time)}–${formatTime(row.time_slot.end_time)}`;
}

async function loadSchedule(): Promise<void> {
    const request = ++scheduleRequest;
    loading.value = true;
    failure.value = null;

    if (!user.value.lecturer_id) {
        loading.value = false;
        return;
    }

    try {
        const periodResponse = await api.get<Collection<AcademicPeriod>>('academic-periods');
        if (request !== scheduleRequest) return;

        const currentPeriod = periodResponse.data.data.find((period) => period.is_current)?.code;
        const params = {
            lecturer_id: user.value.lecturer_id,
            ...(currentPeriod ? { period: currentPeriod } : {}),
        } satisfies ScheduleFilters;
        const response = await api.get<{ data: Schedule }>('schedules', { params });
        if (request === scheduleRequest) schedule.value = response.data.data;
    } catch (error) {
        if (request === scheduleRequest) failure.value = toApiError(error);
    } finally {
        if (request === scheduleRequest) loading.value = false;
    }
}

onMounted(() => void loadSchedule());
onResourcesChanged(['scheduling_runs', 'schedule_details'], () => void loadSchedule());
</script>

<template>
    <Head title="Dashboard Dosen" />

    <AppLayout>
        <div class="dashboard-page w-full space-y-6 p-6 md:p-8">
            <header>
                <h1 class="text-3xl font-bold tracking-tight text-[#112D4E]">Dashboard</h1>
                <p class="mt-1 text-sm text-[#112D4E]/70">
                    Dosen · Ringkasan jadwal mengajar{{ schedule ? ` periode ${schedule.period}` : '' }}
                </p>
            </header>

            <Alert v-if="failure" :title="failure.message" />
            <Alert v-if="missingLecturer" title="Akun dosen belum terhubung">
                Data dosen untuk akun ini belum tersedia. Hubungi administrator untuk menghubungkan akun.
            </Alert>

            <div v-if="loading" class="rounded-xl border border-gray-100 bg-white p-8 text-center text-sm text-gray-500">
                Memuat jadwal mengajar…
            </div>

            <template v-else-if="schedule && !missingLecturer && !failure">
                <DashboardSummary label="Ringkasan jadwal mengajar" :figures="[
                    { label: 'Pertemuan per minggu', value: rows.length },
                    { label: 'Mata kuliah diampu', value: courseCount },
                    { label: 'Kelas diampu', value: classCount },
                    { label: 'Pertemuan hari ini', value: todayCount, detail: DAYS[today] },
                ]" />

                <DashboardChart
                    title="Jadwal mengajar per hari"
                    description="Jumlah pertemuan dalam satu minggu"
                    unit="pertemuan"
                    :items="meetingsByDay"
                />

                <Card class="!p-0 overflow-hidden border border-gray-100 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 p-5">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900">Jadwal Mengajar Terdekat</h2>
                            <p class="mt-1 text-xs text-gray-500">Lima pertemuan berikutnya pada jadwal mingguan.</p>
                        </div>
                        <Link :href="route('schedule.index', { period: schedule.period })" class="text-xs font-semibold text-primary hover:underline">
                            Lihat matriks jadwal
                        </Link>
                    </div>

                    <div v-if="upcomingRows.length === 0" class="p-8 text-center text-sm text-gray-500">
                        Belum ada jadwal mengajar untuk periode ini.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[640px] border-collapse text-left text-sm">
                            <thead>
                                <tr class="bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                    <th class="px-5 py-3">Hari / Waktu</th>
                                    <th class="px-5 py-3">Mata Kuliah</th>
                                    <th class="px-5 py-3">Kelas</th>
                                    <th class="px-5 py-3">Ruang</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700">
                                <tr v-for="row in upcomingRows" :key="row.id">
                                    <td class="whitespace-nowrap px-5 py-4">
                                        <span class="font-semibold text-gray-900">{{ DAYS[row.time_slot.day] ?? row.time_slot.day }}</span>
                                        <span class="mt-1 block text-xs text-gray-500">{{ timeRange(row) }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="font-mono text-xs font-bold text-primary">{{ row.course.code }}</span>
                                        <span class="mt-1 block font-medium text-gray-900">{{ row.course.name }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4">Kelas {{ classLabel(row.class_number) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4">{{ row.room?.code ?? 'Belum ditentukan' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>
            </template>

        </div>
    </AppLayout>
</template>

<style scoped>
.dashboard-page { background: #F9F7F7; min-height: calc(100vh - 4rem); }
.dashboard-page :deep(.shadow-sm) { border-color: #DBE2EF; border-radius: 20px; box-shadow: none; }
</style>

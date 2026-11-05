<script setup lang="ts">
import Alert from '@/Components/ui/Alert.vue';
import Card from '@/Components/ui/Card.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import { api, toApiError, type ApiError } from '@/lib/api';
import { formatTime } from '@/lib/labels';
import type { Schedule, ScheduleFilters } from '@/types/models';
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
const missingStudyProgram = computed(() => !user.value.study_program_id);
let scheduleRequest = 0;

const rows = computed(() => schedule.value?.schedule_details ?? []);
const today = computed(() => new Date().getDay() || 7);
const todayCount = computed(() => rows.value.filter((row) => row.time_slot.day === today.value).length);
const courseCount = computed(() => new Set(rows.value.map((row) => row.course.id)).size);
const classCount = computed(() => new Set(rows.value.map((row) => `${row.course.id}:${row.class_number}`)).size);

const upcomingRows = computed(() => {
    const now = new Date();
    const nowMinutes = now.getHours() * 60 + now.getMinutes();

    return [...rows.value]
        .sort((a, b) => {
            const daysUntil = (row: ScheduleRow) => {
                let offset = (row.time_slot.day - today.value + 7) % 7;
                const [hour, minute] = row.time_slot.start_time.split(':').map(Number);
                if (offset === 0 && hour * 60 + minute < nowMinutes) offset = 7;

                return offset;
            };

            return daysUntil(a) - daysUntil(b)
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

    if (!user.value.study_program_id) {
        loading.value = false;
        return;
    }

    try {
        const params = { study_program_id: user.value.study_program_id } satisfies ScheduleFilters;
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
    <Head title="Dashboard Mahasiswa" />

    <AppLayout>
        <div class="mx-auto w-full max-w-7xl space-y-6 p-6 md:p-8">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Dashboard Mahasiswa</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Ringkasan jadwal terpublikasi{{ schedule ? ` periode ${schedule.period}` : '' }} untuk {{ user.study_program?.name ?? 'program studi Anda' }}.
                    </p>
                </div>
                <Link
                    :href="route('mahasiswa.schedule.index')"
                    class="inline-flex items-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover"
                >
                    Pilih kelas &amp; lihat jadwal pribadi
                </Link>
            </header>

            <Alert v-if="failure" :title="failure.message" />
            <Alert v-if="missingStudyProgram" title="Akun mahasiswa belum terhubung ke program studi">
                Hubungi administrator untuk melengkapi data program studi akun Anda.
            </Alert>

            <div v-if="loading" class="rounded-xl border border-gray-100 bg-white p-8 text-center text-sm text-gray-500">
                Memuat ringkasan jadwal…
            </div>

            <template v-else-if="schedule && !missingStudyProgram">
                <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Ringkasan jadwal program studi">
                    <Card class="border border-gray-100 shadow-sm">
                        <p class="text-xs font-semibold text-gray-500">Pertemuan per minggu</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900">{{ rows.length }}</p>
                    </Card>
                    <Card class="border border-gray-100 shadow-sm">
                        <p class="text-xs font-semibold text-gray-500">Mata kuliah tersedia</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900">{{ courseCount }}</p>
                    </Card>
                    <Card class="border border-gray-100 shadow-sm">
                        <p class="text-xs font-semibold text-gray-500">Kelas terjadwal</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900">{{ classCount }}</p>
                    </Card>
                    <Card class="border border-gray-100 shadow-sm">
                        <p class="text-xs font-semibold text-gray-500">Pertemuan hari ini</p>
                        <p class="mt-2 text-3xl font-bold" :class="todayCount ? 'text-primary' : 'text-gray-900'">{{ todayCount }}</p>
                        <p class="mt-2 text-xs text-gray-500">{{ DAYS[today] }}</p>
                    </Card>
                </section>

                <Card class="!p-0 overflow-hidden border border-gray-100 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 p-5">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900">Jadwal Terdekat di Program Studi</h2>
                            <p class="mt-1 text-xs text-gray-500">Jadwal terpublikasi untuk prodi Anda; pilih kelas yang diambil di halaman Jadwal Pribadi.</p>
                        </div>
                        <Link :href="route('mahasiswa.schedule.index')" class="text-xs font-semibold text-primary hover:underline">
                            Buka jadwal pribadi
                        </Link>
                    </div>

                    <div v-if="upcomingRows.length === 0" class="p-8 text-center text-sm text-gray-500">
                        Belum ada jadwal yang dipublikasikan untuk program studi ini.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[640px] border-collapse text-left text-sm">
                            <thead>
                                <tr class="bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                    <th class="px-5 py-3">Hari / Waktu</th>
                                    <th class="px-5 py-3">Mata Kuliah</th>
                                    <th class="px-5 py-3">Kelas</th>
                                    <th class="px-5 py-3">Dosen</th>
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
                                    <td class="px-5 py-4">{{ row.lecturer.name }}</td>
                                    <td class="whitespace-nowrap px-5 py-4">{{ row.room?.code ?? 'Belum mendapat ruang' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>
            </template>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import RoomMatrix from '@/Components/scheduling/RoomMatrix.vue';
import RoomGridExportButton from '@/Components/scheduling/RoomGridExportButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Alert from '@/Components/ui/Alert.vue';
import Panel from '@/Components/ui/Panel.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { usePaginatedList } from '@/composables/useApiList';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import { api, toApiError, type ApiError } from '@/lib/api';
import { queryParam } from '@/lib/query';
import type { Schedule, ScheduleFilters, ScheduleScope, Room } from '@/types/models';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type RoomIssue = Schedule['schedule_details'][number];

const props = withDefaults(defineProps<{ scope?: ScheduleScope }>(), { scope: 'faculty' });
const readonly = computed(() => props.scope === 'tpb');

const { filters: roomFilters, items: rooms, pagination, page, loading: roomsLoading, error: roomError } = usePaginatedList<Room, { q: string; shared: string }>(
    'rooms',
    { q: queryParam('q'), shared: '' },
);
const DAYS: Record<number, string> = { 1: 'Senin', 2: 'Selasa', 3: 'Rabu', 4: 'Kamis', 5: 'Jumat', 6: 'Sabtu', 7: 'Minggu' };
const overCapacity = ref<RoomIssue[]>([]);
const unassigned = ref<RoomIssue[]>([]);
const loadingIssues = ref(false);
const issuesError = ref<ApiError | null>(null);
const currentPeriod = ref('');
let issueRequestId = 0;

const controlClass = 'rounded-lg border-gray-200 bg-white text-sm text-gray-700 focus:border-primary focus:ring-primary';
const issueCount = computed(() => overCapacity.value.length + unassigned.value.length);
const issuesLoaded = computed(() => currentPeriod.value !== '');

async function loadIssues(): Promise<void> {
    const requestId = ++issueRequestId;
    loadingIssues.value = true;
    issuesError.value = null;

    try {
        const [capacityResponse, unassignedResponse] = await Promise.all([
            api.get<{ data: Schedule }>('schedules', { params: { period: currentPeriod.value, over_capacity: 1, scope: props.scope } satisfies ScheduleFilters }),
            api.get<{ data: Schedule }>('schedules', { params: { period: currentPeriod.value, unassigned: 1, scope: props.scope } satisfies ScheduleFilters }),
        ]);
        if (requestId !== issueRequestId) return;

        overCapacity.value = capacityResponse.data.data.schedule_details;
        unassigned.value = unassignedResponse.data.data.schedule_details;
        currentPeriod.value = capacityResponse.data.data.period;
    } catch (error) {
        if (requestId === issueRequestId) issuesError.value = toApiError(error);
    } finally {
        if (requestId === issueRequestId) loadingIssues.value = false;
    }
}

function classLabel(number: number): string {
    return number > 0 && number <= 26 ? String.fromCharCode(64 + number) : String(number);
}

function timeLabel(detail: RoomIssue): string {
    return `${detail.time_slot.start_time.slice(0, 5)}–${detail.time_slot.end_time.slice(0, 5)}`;
}

function dayLabel(detail: RoomIssue): string {
    return DAYS[detail.time_slot.day] ?? `Hari ${detail.time_slot.day}`;
}

function locationOf(room: Room): string {
    return [room.building, room.floor != null ? `Lt ${room.floor}` : null].filter(Boolean).join(' · ') || '-';
}

/** Rooms are moved by the program; the faculty returns the schedule with a note. */
function reviewUrl(detail: RoomIssue): string {
    return `/fakultas/review${detail.course.study_program ? `?program=${detail.course.study_program.id}` : ''}`;
}

onResourcesChanged(['scheduling_runs', 'schedule_details'], () => void loadIssues());
void loadIssues();
const VIEWS = [
    { id: 'summary', label: 'Ringkasan ruangan' },
    { id: 'matrix', label: 'Seluruh jadwal' },
] as const;
const view = ref<(typeof VIEWS)[number]['id']>(queryParam('view') === 'matrix' ? 'matrix' : 'summary');
watch(view, (value) => {
    const url = new URL(window.location.href);
    if (value === 'matrix') url.searchParams.set('view', 'matrix');
    else url.searchParams.delete('view');
    window.history.replaceState(window.history.state, '', url);
});
</script>

<template>
    <Head title="Alokasi Ruangan" />
    <AppLayout>
        <div class="space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Alokasi Ruangan</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ readonly ? 'Jadwal TPB aktif' : 'Jadwal program studi yang sudah dikirim ke fakultas,' }} periode {{ currentPeriod || 'berjalan' }}.
                        <template v-if="readonly">Ruang kelas TPB ditentukan GA; jalankan ulang GA untuk mengubahnya.</template>
                    </p>
                </div>
                <RoomGridExportButton :period="currentPeriod || undefined" :scope="props.scope" />
            </div>


            <div class="inline-flex rounded-lg bg-gray-100 p-1" role="tablist" aria-label="Tampilan halaman">
                <button
                    v-for="item in VIEWS"
                    :key="item.id"
                    type="button"
                    role="tab"
                    :aria-selected="view === item.id"
                    class="rounded-md px-4 py-1.5 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                    :class="view === item.id ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                    @click="view = item.id"
                >{{ item.label }}</button>
            </div>

            <RoomMatrix v-if="view === 'matrix'" :period="currentPeriod || undefined" :scope="props.scope" />
            <template v-else>
            <Alert v-if="roomError" :title="roomError.message" />
            <Alert v-if="issuesError" :title="issuesError.message" />

            <section
                v-if="issuesLoaded && !issuesError"
                class="flex items-center gap-3 rounded-lg border px-5 py-4"
                :style="issueCount === 0 ? { backgroundColor: '#f0fdf4', borderColor: '#bbf7d0' } : { backgroundColor: '#fffbeb', borderColor: '#fde68a' }"
                role="status"
            >
                <span
                    class="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
                    :style="{ backgroundColor: issueCount === 0 ? '#16a34a' : '#f59e0b' }"
                ></span>
                <p v-if="issueCount === 0" class="text-sm font-medium" style="color: #15803d">Semua kelas sudah mendapat ruang yang cukup.</p>
                <p v-else class="text-sm font-medium" style="color: #b45309">
                    {{ issueCount }} kelas perlu tindakan:
                    <span v-if="unassigned.length">{{ unassigned.length }} tanpa ruang</span><span v-if="unassigned.length && overCapacity.length">, </span><span v-if="overCapacity.length">{{ overCapacity.length }} melebihi kapasitas ruang</span>.
                </p>
            </section>

            <Panel v-if="unassigned.length > 0">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">Kelas tanpa ruang <span class="ml-1 font-normal text-gray-400">{{ unassigned.length }}</span></h2>
                    <p class="mt-0.5 text-sm text-gray-500">{{ readonly ? 'GA tidak menemukan ruang bersama yang kosong untuk kelas berikut.' : 'Ruang diatur oleh program studi. Kembalikan jadwalnya dengan catatan bila perlu diperbaiki.' }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="whitespace-nowrap px-6 py-3">Slot waktu</th>
                                <th class="px-6 py-3">Mata kuliah</th>
                                <th class="px-6 py-3">Dosen</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">Butuh</th>
                                <th v-if="!readonly" class="whitespace-nowrap px-6 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" :class="{ 'opacity-60': loadingIssues }">
                            <tr v-for="detail in unassigned" :key="detail.id" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-3.5">
                                    <p class="font-medium text-gray-900">{{ dayLabel(detail) }} · Sesi {{ detail.time_slot.session }}</p>
                                    <p class="text-xs tabular-nums text-gray-500">{{ timeLabel(detail) }}</p>
                                </td>
                                <td class="px-6 py-3.5">
                                    <p class="font-medium text-gray-900">{{ detail.course.name }}</p>
                                    <p class="text-xs text-gray-500">{{ detail.course.code }} · Kelas {{ classLabel(detail.class_number) }}</p>
                                </td>
                                <td class="px-6 py-3.5 text-gray-700">{{ detail.lecturer.name }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-right tabular-nums text-gray-700">{{ detail.course.class_capacity }} kursi</td>
                                <td v-if="!readonly" class="whitespace-nowrap px-6 py-3.5 text-right">
                                    <Link :href="reviewUrl(detail)" class="text-xs font-semibold text-primary hover:underline">Lihat</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Panel>

            <Panel v-if="overCapacity.length > 0">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">Melebihi kapasitas ruang <span class="ml-1 font-normal text-gray-400">{{ overCapacity.length }}</span></h2>
                    <p class="mt-0.5 text-sm text-gray-500">Ruangnya lebih kecil daripada jumlah mahasiswa di kelas.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="whitespace-nowrap px-6 py-3">Slot waktu</th>
                                <th class="px-6 py-3">Mata kuliah</th>
                                <th class="px-6 py-3">Dosen</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">Ruang / kelas</th>
                                <th v-if="!readonly" class="whitespace-nowrap px-6 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" :class="{ 'opacity-60': loadingIssues }">
                            <tr v-for="detail in overCapacity" :key="detail.id" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-3.5">
                                    <p class="font-medium text-gray-900">{{ dayLabel(detail) }} · Sesi {{ detail.time_slot.session }}</p>
                                    <p class="text-xs tabular-nums text-gray-500">{{ timeLabel(detail) }}</p>
                                </td>
                                <td class="px-6 py-3.5">
                                    <p class="font-medium text-gray-900">{{ detail.course.name }}</p>
                                    <p class="text-xs text-gray-500">{{ detail.course.code }} · Kelas {{ classLabel(detail.class_number) }}</p>
                                </td>
                                <td class="px-6 py-3.5 text-gray-700">{{ detail.lecturer.name }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-right tabular-nums">
                                    <span class="font-semibold text-red-600">{{ detail.room?.capacity ?? '–' }}</span>
                                    <span class="text-gray-500"> / {{ detail.course.class_capacity }} kursi</span>
                                    <p v-if="detail.room" class="text-xs text-gray-500">{{ detail.room.code }}</p>
                                </td>
                                <td v-if="!readonly" class="whitespace-nowrap px-6 py-3.5 text-right">
                                    <Link :href="reviewUrl(detail)" class="text-xs font-semibold text-primary hover:underline">Lihat</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Panel>

            <Panel>
                <div class="flex flex-col gap-3 border-b border-gray-200 p-4 md:flex-row md:items-center">
                    <select v-if="readonly" v-model="roomFilters.shared" :class="[controlClass, 'md:w-56']" aria-label="Filter jenis ruangan">
                        <option value="">Semua ruangan</option>
                        <option value="1">Ruang bersama (dipakai TPB)</option>
                        <option value="0">Ruang milik fakultas</option>
                    </select>
                    <input
                        v-model="roomFilters.q"
                        type="search"
                        placeholder="Cari kode, nama, atau gedung..."
                        aria-label="Cari ruangan"
                        :class="[controlClass, 'w-full placeholder-gray-400 md:w-72']"
                    />
                    <p class="text-sm text-gray-500 md:ml-auto">
                        <span class="font-semibold tabular-nums text-gray-900">{{ pagination.total }}</span> {{ readonly ? 'ruangan ITK' : 'ruangan fakultas' }}
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="whitespace-nowrap px-6 py-3">Ruangan</th>
                                <th class="whitespace-nowrap px-6 py-3">Lokasi</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">Kapasitas</th>
                                <th class="whitespace-nowrap px-6 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" :class="{ 'opacity-60': roomsLoading }">
                            <tr v-if="!roomsLoading && rooms.length === 0">
                                <td colspan="4" class="px-6 py-10 text-center text-gray-500">Tidak ada ruangan yang cocok dengan pencarian.</td>
                            </tr>
                            <tr v-for="room in rooms" :key="room.id" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-3.5">
                                    <span class="font-semibold text-gray-900">{{ room.code }}</span>
                                    <span v-if="room.name" class="ml-2 text-gray-500">{{ room.name }}</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-gray-700">{{ locationOf(room) }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-right tabular-nums text-gray-900">{{ room.capacity }} kursi</td>
                                <td class="whitespace-nowrap px-6 py-3.5 font-medium">
                                    <span v-if="room.is_in_use" class="inline-flex items-center gap-2 text-gray-800">
                                        <span class="h-2 w-2 rounded-full bg-primary"></span>Digunakan
                                    </span>
                                    <span v-else class="inline-flex items-center gap-2 text-green-700">
                                        <span class="h-2 w-2 rounded-full bg-green-600"></span>Tersedia
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Pagination v-model:page="page" :pagination="pagination" label="ruangan" />
            </Panel>
            </template>
        </div>

    </AppLayout>
</template>

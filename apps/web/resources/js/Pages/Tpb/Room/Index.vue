<script setup lang="ts">
import RoomMatrix from '@/Components/scheduling/RoomMatrix.vue';
import RoomGridExportButton from '@/Components/scheduling/RoomGridExportButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Alert from '@/Components/ui/Alert.vue';
import Panel from '@/Components/ui/Panel.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { fetchAll, useCollection } from '@/composables/useApiList';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import { api, toApiError, type ApiError } from '@/lib/api';
import { classLabel, DAY_LABELS, formatTime } from '@/lib/labels';
import { queryParam } from '@/lib/query';
import type { AcademicPeriod, Pagination as PaginationMeta, Room, Schedule, ScheduleFilters } from '@/types/models';
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type RoomIssue = Schedule['schedule_details'][number];

const PER_PAGE = 15;
const controlClass = 'h-10 w-full rounded-lg border-gray-200 bg-white text-sm text-gray-700 focus:border-primary focus:ring-primary';

const { items: periods } = useCollection<AcademicPeriod>('academic-periods');
const rooms = ref<Room[]>([]);
const roomsLoading = ref(false);
const roomError = ref<ApiError | null>(null);
const overCapacity = ref<RoomIssue[]>([]);
const unassigned = ref<RoomIssue[]>([]);
const loadingIssues = ref(false);
const issuesError = ref<ApiError | null>(null);
const currentPeriod = ref('');
const search = ref(queryParam('q'));
const building = ref('');
const kind = ref('');
const usage = ref('');
const page = ref(1);
let issueRequestId = 0;

const periodLabel = computed(() => periods.value.find((p) => p.code === currentPeriod.value)?.label ?? currentPeriod.value);
const buildings = computed(() => [...new Set(rooms.value.map((r) => r.building).filter((b): b is string => !!b))].sort());
const inUseCount = computed(() => rooms.value.filter((r) => r.is_in_use).length);
const visibleRooms = computed(() => {
    const q = search.value.trim().toLowerCase();
    return rooms.value
        .filter((r) =>
            (!q || `${r.code} ${r.name ?? ''} ${r.building ?? ''}`.toLowerCase().includes(q))
            && (!building.value || r.building === building.value)
            && (!kind.value || (kind.value === 'shared') === r.is_shared)
            && (!usage.value || (usage.value === 'used') === r.is_in_use))
        .sort((a, b) => a.code.localeCompare(b.code, 'id', { numeric: true }));
});
const pagination = computed<PaginationMeta>(() => ({ page: page.value, per_page: PER_PAGE, total: visibleRooms.value.length, last_page: Math.max(1, Math.ceil(visibleRooms.value.length / PER_PAGE)) }));
const pageRooms = computed(() => visibleRooms.value.slice((page.value - 1) * PER_PAGE, page.value * PER_PAGE));
const hasFilter = computed(() => !!(search.value || building.value || kind.value || usage.value));

function timeLabel(detail: RoomIssue): string {
    return `${formatTime(detail.time_slot.start_time)}–${formatTime(detail.time_slot.end_time)}`;
}

function slotLabel(detail: RoomIssue): string {
    return `${DAY_LABELS[detail.time_slot.day] ?? `Hari ${detail.time_slot.day}`}, sesi ${detail.time_slot.session}`;
}

async function loadRooms(): Promise<void> {
    roomsLoading.value = true;
    roomError.value = null;
    try {
        rooms.value = await fetchAll<Room>('rooms');
    } catch (error) {
        roomError.value = toApiError(error);
    } finally {
        roomsLoading.value = false;
    }
}

async function loadIssues(): Promise<void> {
    const requestId = ++issueRequestId;
    loadingIssues.value = true;
    issuesError.value = null;
    try {
        const [capacityResponse, unassignedResponse] = await Promise.all([
            api.get<{ data: Schedule }>('schedules', { params: { period: currentPeriod.value, over_capacity: 1, scope: 'tpb' } satisfies ScheduleFilters }),
            api.get<{ data: Schedule }>('schedules', { params: { period: currentPeriod.value, unassigned: 1, scope: 'tpb' } satisfies ScheduleFilters }),
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

function resetFilters(): void {
    search.value = '';
    building.value = '';
    kind.value = '';
    usage.value = '';
}

watch([search, building, kind, usage], () => { page.value = 1; });
onResourcesChanged(['scheduling_runs', 'schedule_details'], () => {
    void loadIssues();
    void loadRooms();
});
void loadRooms();
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
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Alokasi Ruangan</h1>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">
                        Ruang untuk jadwal TPB {{ periodLabel || 'periode berjalan' }} ditentukan oleh GA. Jalankan GA lagi kalau ruangnya perlu diubah.
                    </p>
                </div>
                <RoomGridExportButton :period="currentPeriod || undefined" />
            </header>


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

            <RoomMatrix v-if="view === 'matrix'" :period="currentPeriod || undefined" />
            <template v-else>
            <Alert v-if="roomError" :title="roomError.message" />
            <Alert v-if="issuesError" :title="issuesError.message" />

            <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Ringkasan ruangan">
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <dt class="text-xs font-medium text-gray-500">Total ruangan</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-gray-900">{{ rooms.length }}</dd>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <dt class="text-xs font-medium text-gray-500">Dipakai jadwal TPB</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-gray-900">{{ inUseCount }}<span class="text-sm font-medium text-gray-500"> dari {{ rooms.length }}</span></dd>
                </div>
                <div class="rounded-lg border bg-white p-4" :class="unassigned.length ? 'border-amber-300' : 'border-gray-200'">
                    <dt class="text-xs font-medium text-gray-500">Kelas tanpa ruang</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums" :class="unassigned.length ? 'text-amber-700' : 'text-gray-900'">{{ unassigned.length }}</dd>
                </div>
                <div class="rounded-lg border bg-white p-4" :class="overCapacity.length ? 'border-red-300' : 'border-gray-200'">
                    <dt class="text-xs font-medium text-gray-500">Melebihi kapasitas</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums" :class="overCapacity.length ? 'text-red-700' : 'text-gray-900'">{{ overCapacity.length }}</dd>
                </div>
            </dl>

            <Panel v-if="unassigned.length > 0">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-sm font-bold text-gray-900">Kelas tanpa ruang ({{ unassigned.length }})</h2>
                    <p class="mt-0.5 text-xs text-gray-500">GA tidak menemukan ruang bersama yang kosong untuk kelas berikut.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-xs font-semibold text-gray-500">
                                <th class="whitespace-nowrap px-6 py-3">Waktu kuliah</th>
                                <th class="px-6 py-3">Mata kuliah</th>
                                <th class="px-6 py-3">Dosen</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">Kebutuhan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" :class="{ 'opacity-60': loadingIssues }">
                            <tr v-for="detail in unassigned" :key="detail.id">
                                <td class="whitespace-nowrap px-6 py-3.5">
                                    <p class="font-medium text-gray-900">{{ slotLabel(detail) }}</p>
                                    <p class="text-xs tabular-nums text-gray-500">{{ timeLabel(detail) }}</p>
                                </td>
                                <td class="px-6 py-3.5">
                                    <p class="font-medium text-gray-900">{{ detail.course.name }}</p>
                                    <p class="text-xs text-gray-500">{{ detail.course.code }}, kelas {{ classLabel(detail.class_number) }}</p>
                                </td>
                                <td class="px-6 py-3.5 text-gray-700">{{ detail.lecturer.name }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-right tabular-nums text-gray-700">{{ detail.course.class_capacity }} kursi</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Panel>

            <Panel v-if="overCapacity.length > 0">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-sm font-bold text-gray-900">Melebihi kapasitas ruang ({{ overCapacity.length }})</h2>
                    <p class="mt-0.5 text-xs text-gray-500">Ruangnya lebih kecil daripada jumlah mahasiswa di kelas.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-xs font-semibold text-gray-500">
                                <th class="whitespace-nowrap px-6 py-3">Waktu kuliah</th>
                                <th class="px-6 py-3">Mata kuliah</th>
                                <th class="px-6 py-3">Dosen</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">Ruang</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">Kapasitas / kebutuhan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" :class="{ 'opacity-60': loadingIssues }">
                            <tr v-for="detail in overCapacity" :key="detail.id">
                                <td class="whitespace-nowrap px-6 py-3.5">
                                    <p class="font-medium text-gray-900">{{ slotLabel(detail) }}</p>
                                    <p class="text-xs tabular-nums text-gray-500">{{ timeLabel(detail) }}</p>
                                </td>
                                <td class="px-6 py-3.5">
                                    <p class="font-medium text-gray-900">{{ detail.course.name }}</p>
                                    <p class="text-xs text-gray-500">{{ detail.course.code }}, kelas {{ classLabel(detail.class_number) }}</p>
                                </td>
                                <td class="px-6 py-3.5 text-gray-700">{{ detail.lecturer.name }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-right text-gray-700">{{ detail.room?.code ?? '-' }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-right tabular-nums"><span class="font-semibold text-red-700">{{ detail.room?.capacity ?? '-' }}</span><span class="text-gray-500"> / {{ detail.course.class_capacity }} kursi</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Panel>

            <Panel>
                <div class="space-y-3 border-b border-gray-200 p-4">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <input v-model="search" type="search" placeholder="Cari kode atau nama ruangan" aria-label="Cari ruangan" :class="[controlClass, 'placeholder-gray-400']" />
                        <select v-model="building" :class="controlClass" aria-label="Filter gedung">
                            <option value="">Semua gedung</option>
                            <option v-for="name in buildings" :key="name" :value="name">{{ name }}</option>
                        </select>
                        <select v-model="kind" :class="controlClass" aria-label="Filter jenis ruangan">
                            <option value="">Semua jenis ruangan</option>
                            <option value="shared">Ruang bersama (dipakai TPB)</option>
                            <option value="faculty">Ruang milik fakultas</option>
                        </select>
                        <select v-model="usage" :class="controlClass" aria-label="Filter pemakaian">
                            <option value="">Semua pemakaian</option>
                            <option value="used">Dipakai jadwal TPB</option>
                            <option value="free">Belum dipakai</option>
                        </select>
                    </div>
                    <p class="text-sm text-gray-500">
                        <span class="font-semibold tabular-nums text-gray-900">{{ visibleRooms.length }}</span> ruangan {{ hasFilter ? 'ditemukan' : 'terdaftar' }}
                        <button v-if="hasFilter" type="button" class="ml-3 font-semibold text-primary hover:underline" @click="resetFilters">Hapus filter</button>
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="whitespace-nowrap px-6 py-3">Ruangan</th>
                                <th class="whitespace-nowrap px-6 py-3">Gedung</th>
                                <th class="whitespace-nowrap px-6 py-3">Lantai</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">Kapasitas (kursi)</th>
                                <th class="whitespace-nowrap px-6 py-3">Jenis</th>
                                <th class="whitespace-nowrap px-6 py-3">Pemakaian</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" :class="{ 'opacity-60': roomsLoading }">
                            <tr v-if="!roomsLoading && pageRooms.length === 0">
                                <td colspan="6" class="px-6 py-10 text-center text-gray-500">Tidak ada ruangan yang cocok dengan pencarian atau filter ini.</td>
                            </tr>
                            <tr v-for="room in pageRooms" :key="room.id" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-3.5">
                                    <span class="font-semibold text-gray-900">{{ room.code }}</span>
                                    <span v-if="room.name" class="ml-2 text-gray-500">{{ room.name }}</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-gray-700">{{ room.building ?? '-' }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5 tabular-nums text-gray-700">{{ room.floor ?? '-' }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-right tabular-nums font-medium text-gray-900">{{ room.capacity }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-gray-700">{{ room.is_shared ? 'Ruang bersama' : (room.faculty?.name ?? 'Fakultas') }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="room.is_in_use ? 'bg-blue-50 text-blue-800' : 'bg-gray-100 text-gray-600'">{{ room.is_in_use ? 'Dipakai' : 'Belum dipakai' }}</span>
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

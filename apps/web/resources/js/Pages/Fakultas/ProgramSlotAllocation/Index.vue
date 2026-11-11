<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import CellGrid from '@/Components/scheduling/CellGrid.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { usePeriodPicker } from '@/composables/usePeriodPicker';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import AppLayout from '@/Layouts/AppLayout.vue';
import { api, fieldErrors, toApiError, type ApiError } from '@/lib/api';
import { DAY_LABELS, formatDateTime } from '@/lib/labels';
import { programTone as toneOf } from '@/lib/programTone';
import type { CellChange, CellView, GridRoom, Paginated, RoomSlotGrid, SlotRequest, TimeSlot } from '@/types/models';
import { Head, Link } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type GridCell = RoomSlotGrid['cells'][number];
const EMPTY_TONE = 'border border-dashed border-gray-300 bg-white text-gray-400 hover:border-primary hover:text-primary';

/** demoTools: set by the server outside production only. */
defineProps<{ demoTools?: boolean }>();

const { periods, period, error: periodError } = usePeriodPicker();
const grid = ref<RoomSlotGrid | null>(null);
const loading = ref(false);
const loadError = ref<ApiError | null>(null);
const programId = ref<number | null>(null);
const building = ref('');
const day = ref(1);
const pending = ref(new Map<string, number | null>());
const cellErrors = ref(new Map<string, string>());
const saving = ref(false);
const saveError = ref<ApiError | null>(null);
const flash = ref<string | null>(null);

const requests = ref<SlotRequest[]>([]);
const requestsError = ref<ApiError | null>(null);
const activeRequest = ref<SlotRequest | null>(null);
const requestCells = ref(new Set<string>());
const responseNote = ref('');
const answering = ref<'fulfill' | 'reject' | null>(null);
const rejectOpen = ref(false);
const requestError = ref<ApiError | null>(null);

const fieldClass = 'mt-1 block w-full rounded-lg border-gray-200 bg-white text-sm text-gray-700 focus:border-primary focus:ring-primary';
const key = (slotId: number, roomId: number) => `${slotId}:${roomId}`;

const programs = computed(() => [...(grid.value?.study_programs ?? [])].sort((a, b) => a.code.localeCompare(b.code)));
const programById = computed(() => new Map(programs.value.map((program) => [program.id, program])));
const selectedProgram = computed(() => (programId.value ? programById.value.get(programId.value) ?? null : null));
const cells = computed(() => new Map((grid.value?.cells ?? []).map((cell) => [key(cell.time_slot_id, cell.room_id), cell])));
const buildings = computed(() => [...new Set((grid.value?.rooms ?? []).map((room) => room.building ?? ''))].sort());
const rooms = computed(() => (grid.value?.rooms ?? []).filter((room) => !building.value || (room.building ?? '') === building.value));
const days = computed(() => [...new Set((grid.value?.time_slots ?? []).map((slot) => slot.day))].sort((a, b) => a - b));
const slots = computed(() => (grid.value?.time_slots ?? []).filter((slot) => slot.day === day.value).sort((a, b) => a.session - b.session));
const locked = computed(() => !grid.value || grid.value.tpb_source.type === null || grid.value.published);
const pendingCount = computed(() => pending.value.size);

function freeCellsFitting(minCapacity: number): number {
    let free = 0;
    for (const slot of grid.value?.time_slots ?? []) {
        for (const room of grid.value?.rooms ?? []) {
            if (room.capacity < minCapacity) continue;
            const cellKey = key(slot.id, room.id);
            const status = cells.value.get(cellKey)?.status;
            if (status === 'tpb' || status === 'other_faculty') continue;
            if (holderOf(cellKey) === null) free++;
        }
    }
    return free;
}

const requestViews = computed(() => requests.value.map((request) => ({
    request,
    meetings: request.needs.reduce((sum, need) => sum + need.meetings, 0),
    needs: request.needs.map((need) => ({ ...need, fits: freeCellsFitting(need.min_capacity) })),
})));

/** Cells per program after the unsaved changes. */
const effectiveCounts = computed(() => {
    const counts = new Map(programs.value.map((program) => [program.id, program.cells_count]));
    for (const [cellKey, holder] of pending.value) {
        const original = originalHolder(cells.value.get(cellKey));
        if (original !== null) counts.set(original, (counts.get(original) ?? 0) - 1);
        if (holder !== null) counts.set(holder, (counts.get(holder) ?? 0) + 1);
    }
    return counts;
});

const SLOT_TYPES = [3, 2] as const;
const MINUTES_PER_SKS = 50;

const effectiveTypeCounts = computed(() => {
    const typeOfSlot = new Map((grid.value?.time_slots ?? []).map((slot) => [slot.id, slot.type]));
    const counts = new Map<number, Record<2 | 3, number>>();
    for (const cellKey of new Set([...cells.value.keys(), ...pending.value.keys()])) {
        const holder = holderOf(cellKey);
        const type = typeOfSlot.get(Number(cellKey.split(':')[0]));
        if (holder === null || type === undefined) continue;
        const row = counts.get(holder) ?? { 2: 0, 3: 0 };
        row[type]++;
        counts.set(holder, row);
    }
    return counts;
});

function typeLines(program: (typeof programs.value)[number]): { type: 2 | 3; label: string; have: number; need: number }[] {
    return SLOT_TYPES.map((type) => ({
        type,
        label: `${type} SKS`,
        have: effectiveTypeCounts.value.get(program.id)?.[type] ?? 0,
        need: program.required_by_type?.[type] ?? 0,
    })).filter((line) => line.have > 0 || line.need > 0);
}

function originalHolder(cell: GridCell | undefined): number | null {
    return cell && (cell.status === 'allocated' || cell.status === 'conflict') ? cell.study_program_id : null;
}

function holderOf(cellKey: string): number | null {
    return pending.value.has(cellKey) ? pending.value.get(cellKey)! : originalHolder(cells.value.get(cellKey));
}

function programTone(id: number): string {
    return toneOf(programById.value.get(id)?.code).cell;
}

function cellView(slot: TimeSlot, room: GridRoom): CellView {
    const cellKey = key(slot.id, room.id);
    const cell = cells.value.get(cellKey);
    const error = cellErrors.value.get(cellKey);

    if (cell?.status === 'tpb') return { label: 'TPB', tone: 'bg-gray-100 text-gray-500', title: 'Dipakai TPB', disabled: true };
    if (cell?.status === 'other_faculty') return { label: cell.faculty?.code ?? 'Fakultas lain', tone: 'bg-gray-100 text-gray-500', title: 'Dipakai fakultas lain', disabled: true };

    if (activeRequest.value) {
        const picked = requestCells.value.has(cellKey);
        const holder = holderOf(cellKey);
        if (picked) return { label: `+ ${activeRequest.value.study_program.code}`, tone: `${toneOf(activeRequest.value.study_program.code).cell} ring-2 ring-primary hover:brightness-95`, title: 'Dipilih untuk permintaan ini' };
        if (holder === null) return { label: 'Kosong', tone: EMPTY_TONE, title: 'Klik untuk memberikan sel ini' };
        if (cell?.locked) return { label: programById.value.get(holder)?.code ?? '-', tone: `${programTone(holder)} opacity-60`, title: 'Jadwal pemegang sudah dikirim ke fakultas', disabled: true };
        return { label: programById.value.get(holder)?.code ?? '-', tone: `${programTone(holder)} hover:brightness-95`, title: 'Klik untuk mengambil sel ini dari program studi lain', disabled: holder === activeRequest.value.study_program.id };
    }

    const holder = holderOf(cellKey);
    const isPending = pending.value.has(cellKey);
    if (cell?.status === 'conflict' && !isPending) {
        return { label: `${programById.value.get(cell.study_program_id!)?.code ?? '-'} bentrok TPB`, tone: 'bg-red-100 text-red-800 hover:bg-red-200', title: 'TPB memakai sel ini setelah dialokasikan. Klik untuk mencabut.', disabled: locked.value };
    }
    if (holder === null) {
        return { label: error ? 'Gagal' : 'Kosong', tone: error ? 'bg-red-50 text-red-700' : EMPTY_TONE, title: error ?? 'Klik untuk memberikan sel ini ke program studi terpilih', disabled: locked.value || !programId.value, pending: isPending };
    }
    return {
        label: programById.value.get(holder)?.code ?? '-',
        tone: `${programTone(holder)} hover:brightness-95 ${error ? 'ring-2 ring-red-500' : holder === programId.value ? 'ring-2 ring-primary' : ''}`,
        title: error ?? (cell?.locked ? 'Jadwal pemegang sudah dikirim ke fakultas' : holder === programId.value ? 'Klik untuk mencabut' : 'Klik untuk memindahkan ke program studi terpilih'),
        disabled: locked.value || !!cell?.locked || !programId.value,
        pending: isPending,
    };
}

function setHolder(cellKey: string, holder: number | null): void {
    const next = new Map(pending.value);
    if (holder === originalHolder(cells.value.get(cellKey))) next.delete(cellKey);
    else next.set(cellKey, holder);
    pending.value = next;
    if (cellErrors.value.has(cellKey)) {
        const errors = new Map(cellErrors.value);
        errors.delete(cellKey);
        cellErrors.value = errors;
    }
}

function select(slot: TimeSlot, room: GridRoom): void {
    const cellKey = key(slot.id, room.id);
    if (activeRequest.value) {
        const picked = new Set(requestCells.value);
        if (picked.has(cellKey)) picked.delete(cellKey);
        else picked.add(cellKey);
        requestCells.value = picked;
        return;
    }
    if (!programId.value) return;
    setHolder(cellKey, holderOf(cellKey) === programId.value ? null : programId.value);
}

async function load(): Promise<void> {
    if (!period.value) return;
    loading.value = true;
    try {
        const response = await api.get<{ data: RoomSlotGrid }>('room-slot-allocations', { params: { period: period.value } });
        grid.value = response.data.data;
        loadError.value = null;
        if (!programId.value || !programById.value.has(programId.value)) programId.value = programs.value[0]?.id ?? null;
        if (!days.value.includes(day.value)) day.value = days.value[0] ?? 1;
    } catch (error) {
        loadError.value = toApiError(error);
    } finally {
        loading.value = false;
    }
}

async function loadRequests(): Promise<void> {
    if (!period.value) return;
    try {
        const response = await api.get<Paginated<SlotRequest>>('slot-requests', { params: { period: period.value, status: 'pending', per_page: 100 } });
        requests.value = response.data.data;
        requestsError.value = null;
        if (activeRequest.value && !requests.value.some((request) => request.id === activeRequest.value?.id)) closeRequest();
    } catch (error) {
        requestsError.value = toApiError(error);
    }
}

async function save(): Promise<void> {
    const changes: CellChange[] = [...pending.value].map(([cellKey, holder]) => {
        const [slotId, roomId] = cellKey.split(':').map(Number);
        return { time_slot_id: slotId, room_id: roomId, study_program_id: holder };
    });
    saving.value = true;
    saveError.value = null;
    try {
        const response = await api.put<{ data: { allocated: number; released: number; grid: RoomSlotGrid } }>('room-slot-allocations', { period: period.value, cells: changes });
        grid.value = response.data.data.grid;
        pending.value = new Map();
        cellErrors.value = new Map();
        flash.value = `${response.data.data.allocated} sel diberikan, ${response.data.data.released} sel dicabut.`;
    } catch (error) {
        const apiError = toApiError(error);
        // cells.{i}... points at the changed cell i.
        const errors = new Map<string, string>();
        for (const [field, message] of Object.entries(fieldErrors(apiError))) {
            const index = Number(field.split('.')[1]);
            const change = changes[index];
            if (change) errors.set(key(change.time_slot_id, change.room_id), message);
        }
        cellErrors.value = errors;
        saveError.value = apiError;
    } finally {
        saving.value = false;
    }
}

const fillOpen = ref(false);
const filling = ref(false);
const fillError = ref<string | null>(null);

/** Demo only: gives every program the cells it is short of. */
async function demoFill(): Promise<void> {
    filling.value = true;
    fillError.value = null;
    try {
        const response = await api.post<{ data: { programs: { code: string; added: number; sufficient: boolean }[]; grid: RoomSlotGrid } }>('room-slot-allocations/auto-fill', {}, { params: { period: period.value } });
        grid.value = response.data.data.grid;
        const rows = response.data.data.programs;
        const short = rows.filter((row) => !row.sufficient).map((row) => row.code);
        flash.value = `${rows.reduce((sum, row) => sum + row.added, 0)} sel diberikan ke ${rows.filter((row) => row.added).length} program studi.${short.length ? ` Masih kurang: ${short.join(', ')}.` : ''}`;
        fillOpen.value = false;
    } catch (error) {
        fillError.value = toApiError(error).message;
    } finally {
        filling.value = false;
    }
}

function discard(): void {
    pending.value = new Map();
    cellErrors.value = new Map();
    saveError.value = null;
}

function openRequest(request: SlotRequest): void {
    if (pendingCount.value) return;
    activeRequest.value = request;
    programId.value = request.study_program.id;
    requestCells.value = new Set();
    responseNote.value = '';
    requestError.value = null;
}

function closeRequest(): void {
    activeRequest.value = null;
    requestCells.value = new Set();
    rejectOpen.value = false;
    requestError.value = null;
}

async function fulfill(): Promise<void> {
    if (!activeRequest.value) return;
    answering.value = 'fulfill';
    requestError.value = null;
    try {
        const cellsPicked = [...requestCells.value].map((cellKey) => {
            const [slotId, roomId] = cellKey.split(':').map(Number);
            return { time_slot_id: slotId, room_id: roomId };
        });
        await api.post(`slot-requests/${activeRequest.value.id}/fulfill`, { cells: cellsPicked, response_note: responseNote.value.trim() || null });
        flash.value = `Permintaan ${activeRequest.value.study_program.code} dipenuhi dengan ${cellsPicked.length} sel.`;
        closeRequest();
        await Promise.all([load(), loadRequests()]);
    } catch (error) {
        requestError.value = toApiError(error);
    } finally {
        answering.value = null;
    }
}

async function reject(): Promise<void> {
    if (!activeRequest.value) return;
    answering.value = 'reject';
    requestError.value = null;
    try {
        await api.post(`slot-requests/${activeRequest.value.id}/reject`, { note: responseNote.value.trim() });
        flash.value = `Permintaan ${activeRequest.value.study_program.code} ditolak.`;
        closeRequest();
        await loadRequests();
    } catch (error) {
        requestError.value = toApiError(error);
    } finally {
        answering.value = null;
    }
}

function warnUnsaved(event: BeforeUnloadEvent): void {
    if (pendingCount.value) event.preventDefault();
}

watch(period, () => {
    discard();
    closeRequest();
    void load();
    void loadRequests();
}, { immediate: true });
onResourcesChanged(['room_slot_allocations', 'program_schedule_submissions', 'schedule_publications', 'tpb_occupancy_imports', 'scheduling_runs'], () => {
    // Keep the local edits: a reload only refreshes what others changed.
    void load();
});
onResourcesChanged(['slot_requests'], () => void loadRequests());
onMounted(() => window.addEventListener('beforeunload', warnUnsaved));
onBeforeUnmount(() => window.removeEventListener('beforeunload', warnUnsaved));
</script>

<template>
    <Head title="Slot Ruangan Program Studi" />
    <AppLayout>
        <div class="space-y-6">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Slot Ruangan Program Studi</h1>
                    <p class="mt-1 max-w-3xl text-sm text-gray-600">
                        Bagikan slot ruangan (ruang, hari, sesi) kepada setiap program studi. Program studi menjalankan GA hanya di dalam slot yang diberikan.
                    </p>
                </div>
                <label class="text-sm font-medium text-gray-700">
                    Periode
                    <select v-model="period" class="mt-1 block min-w-56 rounded-lg border-gray-200 bg-white text-sm focus:border-primary focus:ring-primary">
                        <option v-for="item in periods" :key="item.id" :value="item.code">{{ item.label }}</option>
                    </select>
                </label>
            </header>

            <div class="space-y-3">
                <Alert v-if="periodError" :title="periodError.message" />
                <Alert v-if="loadError" :title="loadError.message" />
                <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />
                <Alert v-if="grid && grid.tpb_source.type === null" variant="warning" title="Data jadwal TPB periode ini belum tersedia">
                    Slot ruangan baru dapat dibagikan setelah jadwal TPB diketahui, supaya tidak bentrok.
                    <Link href="/fakultas/tpb-import" class="font-semibold text-primary hover:underline">Import jadwal TPB</Link>
                </Alert>
                <Alert v-if="grid?.published" variant="success" title="Jadwal fakultas periode ini sudah dipublikasikan">Slot ruangan tidak dapat diubah lagi.</Alert>
                <Alert v-if="saveError" :title="saveError.message">
                    <span v-if="cellErrors.size">{{ cellErrors.size }} sel ditandai merah. Arahkan kursor ke sel untuk melihat alasannya.</span>
                </Alert>
            </div>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="space-y-4">
                    <section v-if="grid" aria-label="Program studi">
                        <p class="mb-2 text-xs text-gray-500">Sel dialokasikan / kebutuhan pertemuan per minggu. Kecukupan juga bergantung pada kapasitas ruang.</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="program in programs"
                                :key="program.id"
                                type="button"
                                class="min-w-0 flex-1 basis-56 rounded-lg border px-3 py-2.5 text-left text-xs transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                                :class="programId === program.id ? 'border-primary bg-white ring-1 ring-primary' : 'border-gray-200 bg-white hover:border-gray-300'"
                                :title="`${effectiveCounts.get(program.id) ?? 0} sel dialokasikan, butuh ${program.required_meetings} pertemuan`"
                                :disabled="!!activeRequest"
                                @click="programId = program.id"
                            >
                                <span class="flex items-baseline justify-between gap-3">
                                    <span class="flex min-w-0 items-center gap-2 text-sm">
                                        <span class="size-2.5 shrink-0 rounded-sm" :class="toneOf(program.code).dot" aria-hidden="true" />
                                        <span class="font-semibold text-gray-900">{{ program.name }} ({{ program.code }})</span>
                                    </span>
                                    <span class="whitespace-nowrap text-xs tabular-nums text-gray-500" aria-hidden="true">
                                        <span class="font-semibold text-gray-900">{{ effectiveCounts.get(program.id) ?? 0 }}</span> / {{ program.required_meetings }}
                                    </span>
                                </span>
                                <span class="mt-1.5 block space-y-0.5">
                                    <span v-for="line in typeLines(program)" :key="line.type" class="flex items-baseline justify-between gap-3 tabular-nums">
                                        <span class="text-gray-500">{{ line.label }}</span>
                                        <span :class="line.have < line.need ? 'text-amber-700' : 'text-gray-500'">
                                            <span class="font-semibold" :class="line.have < line.need ? 'text-amber-700' : 'text-gray-900'">{{ line.have }}</span> / {{ line.need }}
                                        </span>
                                    </span>
                                </span>
                                <span class="mt-2 block h-1 overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                                    <span
                                        class="block h-full rounded-full"
                                        :class="toneOf(program.code).dot"
                                        :style="{ width: `${Math.min(100, program.required_meetings ? ((effectiveCounts.get(program.id) ?? 0) / program.required_meetings) * 100 : 0)}%` }"
                                    />
                                </span>
                                <span class="sr-only">{{ effectiveCounts.get(program.id) ?? 0 }} sel dialokasikan dari {{ program.required_meetings }} pertemuan yang dibutuhkan</span>
                            </button>
                        </div>
                    </section>

                    <section class="flex flex-wrap items-end gap-4 rounded-lg border border-gray-200 bg-white p-4">
                        <label class="block min-w-40 text-sm font-medium text-gray-700">
                            Gedung
                            <select v-model="building" :class="fieldClass">
                                <option value="">Semua gedung</option>
                                <option v-for="item in buildings.filter((value) => value)" :key="item" :value="item">{{ item }}</option>
                            </select>
                        </label>
                        <div class="flex flex-wrap gap-1" role="tablist" aria-label="Hari">
                            <button
                                v-for="item in days"
                                :key="item"
                                type="button"
                                role="tab"
                                :aria-selected="day === item"
                                class="rounded-lg px-3 py-2 text-sm"
                                :class="day === item ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                @click="day = item"
                            >{{ DAY_LABELS[item] }}</button>
                        </div>
                        <div class="ml-auto flex items-center gap-2">
                            <span v-if="pendingCount" class="text-sm text-gray-600">{{ pendingCount }} perubahan belum disimpan</span>
                            <Button v-if="demoTools" variant="secondary" :disabled="locked || pendingCount > 0 || !!activeRequest" title="Khusus demo: beri tiap prodi slot yang kurang" @click="fillOpen = true; fillError = null">Isi otomatis (demo)</Button>
                            <Button variant="secondary" :disabled="!pendingCount || saving" @click="discard">Batalkan</Button>
                            <Button :disabled="!pendingCount || locked" :loading="saving" @click="save">Simpan</Button>
                        </div>
                    </section>

                    <p class="text-sm text-gray-600">
                        <template v-if="activeRequest">Pilih sel untuk permintaan {{ activeRequest.study_program.code }}, lalu klik Penuhi.</template>
                        <template v-else-if="selectedProgram">Klik sel kosong untuk memberikannya ke {{ selectedProgram.code }}. Klik sel milik {{ selectedProgram.code }} untuk mencabutnya, atau sel program studi lain untuk memindahkannya.</template>
                    </p>

                    <p v-if="loading && !grid" class="py-10 text-center text-sm text-gray-500">Memuat slot ruangan…</p>
                    <CellGrid v-else-if="grid" :rooms="rooms" :slots="slots" :view="cellView" :caption="`Slot ruangan hari ${DAY_LABELS[day]}`" @select="select" />

                    <div class="flex flex-wrap gap-x-5 gap-y-2 text-xs text-gray-600" aria-label="Keterangan">
                        <span class="inline-flex items-center gap-2"><i class="size-3 rounded-sm border border-dashed border-gray-300 bg-white" aria-hidden="true" />Kosong</span>
                        <span class="inline-flex items-center gap-2"><i class="size-3 rounded-sm bg-gray-100 ring-1 ring-gray-200" aria-hidden="true" />TPB atau fakultas lain</span>
                        <span class="inline-flex items-center gap-2"><i class="size-3 rounded-sm bg-red-100 ring-1 ring-red-200" aria-hidden="true" />Bentrok dengan TPB</span>
                        <span class="inline-flex items-center gap-2"><i class="size-3 rounded-sm outline-dashed outline-2 outline-gray-700" aria-hidden="true" />Belum disimpan</span>
                    </div>
                </div>

                <aside class="space-y-3">
                    <div class="flex items-baseline justify-between gap-3">
                        <h2 class="text-lg font-bold text-gray-900">Permintaan slot</h2>
                        <span class="text-sm tabular-nums text-gray-500">{{ requests.length }} menunggu</span>
                    </div>
                    <Alert v-if="requestsError" :title="requestsError.message" />
                    <p v-if="!requests.length" class="rounded-lg border border-gray-200 bg-white px-4 py-6 text-center text-sm text-gray-500">Tidak ada permintaan yang menunggu.</p>
                    <article
                        v-for="{ request, meetings, needs } in requestViews"
                        :key="request.id"
                        class="rounded-lg border border-gray-200 bg-white"
                        :class="activeRequest?.id === request.id ? 'ring-1 ring-primary' : ''"
                    >
                        <header class="px-4 pt-4">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                                <span class="size-2.5 shrink-0 rounded-sm" :class="toneOf(request.study_program.code).dot" aria-hidden="true" />
                                {{ request.study_program.name }} ({{ request.study_program.code }})
                            </h3>
                            <p class="mt-1 text-xs text-gray-500">{{ request.requested_by?.name ?? '-' }}, {{ formatDateTime(request.created_at) }}</p>
                        </header>

                        <p v-if="request.note" class="mx-4 mt-3 max-h-24 overflow-y-auto whitespace-pre-line text-sm text-gray-700">&ldquo;{{ request.note }}&rdquo;</p>

                        <ul class="mt-3 divide-y divide-gray-100 border-t border-gray-100 text-sm">
                            <li v-for="need in needs" :key="need.course_id" class="flex items-start justify-between gap-3 px-4 py-2.5">
                                <div class="min-w-0">
                                    <p class="font-medium leading-snug text-gray-900">{{ need.name ?? need.code ?? `MK #${need.course_id}` }}</p>
                                    <p class="text-xs text-gray-500">{{ need.code }}</p>
                                    <p class="mt-0.5 text-xs text-gray-700">
                                        <template v-if="need.sks">
                                            <span>
                                                <strong class="font-semibold">{{ need.sks }} SKS</strong>, <br>
                                            </span>
                                        </template>
                                        {{ need.meetings }} pertemuan dan min. {{ need.min_capacity }} kursi
                                    </p>
                                </div>
                            </li>
                        </ul>

                        <div v-if="activeRequest?.id === request.id" class="space-y-3 border-t border-gray-100 p-4">
                            <Alert v-if="requestError" :title="requestError.message" />
                            <p class="text-sm text-gray-700">
                                <strong class="font-semibold tabular-nums text-gray-900">{{ requestCells.size }}</strong> dari {{ meetings }} sel dipilih di grid
                            </p>
                            <label class="block text-sm font-medium text-gray-700">
                                Catatan untuk program studi
                                <textarea v-model="responseNote" rows="2" maxlength="1000" :class="fieldClass" placeholder="Opsional saat memenuhi, wajib saat menolak" />
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <Button variant="secondary" @click="rejectOpen = true">Tolak</Button>
                                <Button :disabled="!requestCells.size" :loading="answering === 'fulfill'" @click="fulfill">Penuhi</Button>
                            </div>
                            <Button variant="ghost" class="w-full" title="Sembunyikan tanggapan dan batalkan pilihan sel" @click="closeRequest">
                                <X class="size-4" aria-hidden="true" /> Sembunyikan
                            </Button>
                        </div>
                        <div v-else class="border-t border-gray-100 p-4">
                            <Button class="w-full" :disabled="locked || pendingCount > 0" :title="pendingCount ? 'Simpan atau batalkan perubahan dulu' : undefined" @click="openRequest(request)">Tanggapi permintaan</Button>
                        </div>
                    </article>
                </aside>
            </div>
        </div>

        <ConfirmDialog
            :show="fillOpen"
            title="Isi slot otomatis untuk demo?"
            confirm-label="Isi otomatis"
            :processing="filling"
            :error="fillError"
            @confirm="demoFill"
            @close="fillOpen = false"
        >
            Setiap program studi diberi sel yang kurang untuk pertemuannya: ruang yang muat, sesi sesuai SKS, dan di jam saat dosennya bebas dari TPB.
            Sel yang sudah dipegang prodi tidak diubah. Fitur ini hanya ada di lingkungan non-produksi.
        </ConfirmDialog>

        <Modal :show="rejectOpen" max-width="md" @close="rejectOpen = false">
            <div class="space-y-4 p-6">
                <h2 class="text-lg font-bold text-gray-900">Tolak permintaan {{ activeRequest?.study_program.code }}?</h2>
                <p class="text-sm text-gray-600">Tulis alasannya, misalnya tidak ada slot tersisa di fakultas dan permintaan diteruskan ke TPB Pusat di luar sistem.</p>
                <Alert v-if="requestError" :title="requestError.message" />
                <textarea v-model="responseNote" rows="3" maxlength="1000" :class="fieldClass" aria-label="Alasan penolakan" />
                <div class="flex justify-end gap-3">
                    <Button variant="secondary" @click="rejectOpen = false">Batal</Button>
                    <Button variant="danger" :disabled="!responseNote.trim()" :loading="answering === 'reject'" @click="reject">Tolak</Button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

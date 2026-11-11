<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import CellGrid from '@/Components/scheduling/CellGrid.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import { usePeriodPicker } from '@/composables/usePeriodPicker';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import AppLayout from '@/Layouts/AppLayout.vue';
import { api, fieldErrors, toApiError, type ApiError } from '@/lib/api';
import { DAY_LABELS, formatDateTime } from '@/lib/labels';
import { programTone as toneOf } from '@/lib/programTone';
import type { CellChange, CellView, GridRoom, Paginated, RoomSlotGrid, SlotRequest, TimeSlot } from '@/types/models';
import { Head, Link } from '@inertiajs/vue3';
import { ClipboardList, Eye, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * POS 040/IT10.1/OT.07/2026 steps 1, 5 and 6: the faculty hands room slots (room × day × session)
 * to its study programs, moves them between programs, and answers requests for more.
 * Changes stay local until saved; one save sends only the changed cells.
 */
type GridCell = RoomSlotGrid['cells'][number];
const EMPTY_TONE = 'border border-dashed border-gray-300 bg-white text-gray-400 hover:border-primary hover:text-primary';

const { periods, period, error: periodError } = usePeriodPicker();
const grid = ref<RoomSlotGrid | null>(null);
const loading = ref(false);
const loadError = ref<ApiError | null>(null);
const programId = ref<number | null>(null);
const building = ref('');
const day = ref(1);
/** "slot:room" => desired holder, only for cells that differ from the server. */
const pending = ref(new Map<string, number | null>());
const cellErrors = ref(new Map<string, string>());
const saving = ref(false);
const saveError = ref<ApiError | null>(null);
const flash = ref<string | null>(null);

const requests = ref<SlotRequest[]>([]);
const requestsError = ref<ApiError | null>(null);
const activeRequest = ref<SlotRequest | null>(null);
const detailRequest = ref<SlotRequest | null>(null);
/** Cells picked to answer activeRequest. */
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
const summarizedRequests = computed(() => requests.value.map((request) => {
    const capacities = request.needs.map((need) => need.min_capacity);
    const min = capacities.length ? Math.min(...capacities) : null;
    const max = capacities.length ? Math.max(...capacities) : null;

    return {
        request,
        courses: new Set(request.needs.map((need) => need.course_id)).size,
        meetings: request.needs.reduce((sum, need) => sum + need.meetings, 0),
        seats: min === null ? null : min === max ? `${min}` : `${min}–${max}`,
    };
}));

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
        if (detailRequest.value) detailRequest.value = requests.value.find((request) => request.id === detailRequest.value?.id) ?? null;
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
    detailRequest.value = null;
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
        <div class="mx-auto w-full max-w-[1400px] space-y-6 p-5 md:p-6">
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

                <aside class="space-y-4">
                    <div class="flex items-center gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-primary"><ClipboardList class="size-5" aria-hidden="true" /></span>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">Permintaan slot</h2>
                            <p class="text-sm text-gray-500">{{ requests.length }} menunggu</p>
                        </div>
                    </div>
                    <Alert v-if="requestsError" :title="requestsError.message" />
                    <p v-if="!requests.length" class="rounded-lg border border-gray-200 bg-white px-4 py-6 text-center text-sm text-gray-500">Tidak ada permintaan yang menunggu.</p>
                    <article
                        v-for="{ request, courses, meetings, seats } in summarizedRequests"
                        :key="request.id"
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
                    >
                        <div class="flex items-start gap-3">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-sm font-bold text-primary">{{ request.study_program.code }}</span>
                            <div class="min-w-0">
                                <h3 class="text-base font-bold leading-snug text-gray-900">{{ request.study_program.name }}</h3>
                                <p class="mt-1 text-sm text-gray-500">{{ formatDateTime(request.created_at) }}</p>
                                <p class="text-sm text-gray-500">oleh {{ request.requested_by?.name ?? '-' }}</p>
                            </div>
                        </div>
                        <dl class="mt-4 grid grid-cols-3 gap-2">
                            <div class="rounded-lg bg-gray-50 px-3 py-2.5">
                                <dt class="text-xs text-gray-600">Mata kuliah</dt>
                                <dd class="mt-1 text-lg font-bold tabular-nums text-gray-900">{{ courses }}</dd>
                            </div>
                            <div class="rounded-lg bg-gray-50 px-3 py-2.5">
                                <dt class="text-xs text-gray-600">Pertemuan</dt>
                                <dd class="mt-1 text-lg font-bold tabular-nums text-gray-900">{{ meetings }}</dd>
                            </div>
                            <div class="rounded-lg bg-gray-50 px-3 py-2.5">
                                <dt class="text-xs text-gray-600">Min. kursi</dt>
                                <dd class="mt-1 text-lg font-bold tabular-nums text-gray-900">{{ seats ?? '—' }}</dd>
                            </div>
                        </dl>
                        <Button variant="secondary" class="mt-4 w-full" @click="detailRequest = request">
                            <Eye class="size-4" aria-hidden="true" /> Lihat rincian
                        </Button>
                        <p v-if="request.note" class="mt-4 max-h-24 overflow-y-auto whitespace-pre-line rounded-lg bg-gray-50 p-3 text-sm text-gray-700">{{ request.note }}</p>

                        <div v-if="activeRequest?.id === request.id" class="mt-4 space-y-3 border-t border-gray-100 pt-4">
                            <Alert v-if="requestError" :title="requestError.message" />
                            <p class="rounded-lg bg-blue-50 px-3 py-2 text-sm text-primary"><strong class="font-bold tabular-nums">{{ requestCells.size }}</strong> sel dipilih di grid</p>
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
                        <div v-else class="mt-2">
                            <Button class="w-full" :disabled="locked || pendingCount > 0" :title="pendingCount ? 'Simpan atau batalkan perubahan dulu' : undefined" @click="openRequest(request)">Tanggapi permintaan</Button>
                        </div>
                    </article>
                </aside>
            </div>
        </div>

        <Modal :show="detailRequest !== null" max-width="lg" close-button @close="detailRequest = null">
            <div class="border-b border-gray-100 px-6 py-5">
                <h2 class="text-lg font-semibold text-gray-900">Rincian permintaan</h2>
                <p class="mt-1 text-sm text-gray-600">
                    {{ detailRequest?.study_program.code }} {{ detailRequest?.study_program.name }} · {{ formatDateTime(detailRequest?.created_at ?? null) }}
                </p>
            </div>
            <div class="max-h-[60vh] overflow-auto px-6 py-2">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-white text-xs text-gray-500">
                        <tr>
                            <th scope="col" class="py-3 text-left font-medium">Mata kuliah</th>
                            <th scope="col" class="py-3 text-right font-medium">Pertemuan</th>
                            <th scope="col" class="py-3 text-right font-medium">Ruang min.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!detailRequest?.needs.length">
                            <td colspan="3" class="py-4 text-center text-gray-500">Tidak ada rincian mata kuliah.</td>
                        </tr>
                        <tr v-for="need in detailRequest?.needs ?? []" :key="need.course_id">
                            <td class="py-2.5 pr-4">
                                <span class="font-medium text-gray-900">{{ need.name ?? need.code ?? `MK #${need.course_id}` }}</span>
                                <span v-if="need.name && need.code" class="block text-xs text-gray-500">{{ need.code }}</span>
                            </td>
                            <td class="py-2.5 text-right tabular-nums text-gray-700">{{ need.meetings }}</td>
                            <td class="py-2.5 text-right tabular-nums text-gray-700">{{ need.min_capacity }} kursi</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end border-t border-gray-100 px-6 py-4">
                <Button variant="secondary" @click="detailRequest = null">Tutup</Button>
            </div>
        </Modal>

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

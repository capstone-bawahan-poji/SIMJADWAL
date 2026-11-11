<script setup lang="ts">
import CellGrid from '@/Components/scheduling/CellGrid.vue';
import TpbScheduleImport from '@/Components/scheduling/TpbScheduleImport.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { usePeriodPicker } from '@/composables/usePeriodPicker';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import AppLayout from '@/Layouts/AppLayout.vue';
import { api, toApiError, type ApiError } from '@/lib/api';
import { DAY_LABELS, formatDateTime } from '@/lib/labels';
import type { CellView, GridRoom, RoomSlotGrid, TimeSlot, TpbImportResult, TpbOccupancy } from '@/types/models';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

/**
 * POS 1.2: the faculty must know which room slots TPB holds before handing slots to its programs.
 * A published TPB schedule in the system wins; otherwise the faculty imports the file it got from TPB.
 */
const { periods, period, error: periodError } = usePeriodPicker();
const occupancy = ref<TpbOccupancy | null>(null);
const grid = ref<RoomSlotGrid | null>(null);
const loadError = ref<ApiError | null>(null);
const flash = ref<string | null>(null);
const day = ref(1);
const building = ref('');
const confirmDelete = ref(false);
const deleting = ref(false);
const deleteError = ref<string | null>(null);

const tpbCells = computed(() => new Set((occupancy.value?.cells ?? []).map((cell) => `${cell.time_slot_id}:${cell.room_id}`)));
const buildings = computed(() => [...new Set((grid.value?.rooms ?? []).map((room) => room.building ?? ''))].filter(Boolean).sort());
const rooms = computed(() => (grid.value?.rooms ?? []).filter((room) => !building.value || room.building === building.value));
const days = computed(() => [...new Set((grid.value?.time_slots ?? []).map((slot) => slot.day))].sort((a, b) => a - b));
const slots = computed(() => (grid.value?.time_slots ?? []).filter((slot) => slot.day === day.value).sort((a, b) => a.session - b.session));
const sourceType = computed(() => occupancy.value?.source.type ?? null);

function cellView(slot: TimeSlot, room: GridRoom): CellView {
    return tpbCells.value.has(`${slot.id}:${room.id}`)
        ? { label: 'TPB', tone: 'bg-gray-200 text-gray-700', disabled: true, title: 'Dipakai TPB' }
        : { label: 'Kosong', tone: 'bg-blue-50 text-blue-700', disabled: true, title: 'Dapat dibagikan ke program studi' };
}

async function load(): Promise<void> {
    if (!period.value) return;
    try {
        const [occupancyResponse, gridResponse] = await Promise.all([
            api.get<{ data: TpbOccupancy }>('tpb-occupancy', { params: { period: period.value } }),
            api.get<{ data: RoomSlotGrid }>('room-slot-allocations', { params: { period: period.value } }),
        ]);
        occupancy.value = occupancyResponse.data.data;
        grid.value = gridResponse.data.data;
        if (!days.value.includes(day.value)) day.value = days.value[0] ?? 1;
        loadError.value = null;
    } catch (error) {
        loadError.value = toApiError(error);
    }
}

function imported(result: TpbImportResult): void {
    flash.value = `${result.rows ?? 0} baris jadwal TPB tersimpan.`;
    void load();
}

async function remove(): Promise<void> {
    deleting.value = true;
    deleteError.value = null;
    try {
        await api.delete('tpb-occupancy', { params: { period: period.value } });
        confirmDelete.value = false;
        flash.value = 'Import jadwal TPB dihapus.';
        await load();
    } catch (error) {
        deleteError.value = toApiError(error).message;
    } finally {
        deleting.value = false;
    }
}

watch(period, () => void load(), { immediate: true });
onResourcesChanged(['tpb_occupancy_imports', 'scheduling_runs', 'schedule_publications'], () => void load());
</script>

<template>
    <Head title="Import Jadwal TPB" />
    <AppLayout>
        <div class="mx-auto w-full max-w-7xl space-y-6 p-6 md:p-8">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Import Jadwal TPB</h1>
                    <p class="mt-1 max-w-3xl text-sm text-gray-600">
                        Slot ruangan yang dipakai TPB tidak dapat dibagikan ke program studi. Data ini diperlukan sebelum membagi slot ruangan.
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
            </div>

            <section v-if="occupancy" class="rounded-xl border border-gray-200 bg-white p-5">
                <h2 class="text-base font-semibold text-gray-900">Sumber data TPB</h2>
                <p v-if="sourceType === 'run'" class="mt-1 text-sm text-gray-600">
                    Jadwal TPB sudah dipublikasikan oleh admin TPB (run #{{ occupancy.source.run_id }}). Data ini yang dipakai, sehingga import fakultas tidak diperlukan.
                </p>
                <div v-else-if="sourceType === 'import' && occupancy.import" class="mt-1 flex flex-wrap items-center justify-between gap-3 text-sm text-gray-600">
                    <p>
                        Dari file {{ occupancy.import.file_name ?? 'tanpa nama' }}, diimpor {{ formatDateTime(occupancy.import.imported_at) }}
                        oleh {{ occupancy.import.imported_by?.name ?? '-' }}. {{ occupancy.cells.length }} slot ruangan dipakai TPB.
                    </p>
                    <Button variant="secondary" size="sm" :disabled="grid?.published" @click="confirmDelete = true">Hapus import</Button>
                </div>
                <p v-else class="mt-1 text-sm text-amber-700">Belum ada data jadwal TPB untuk periode ini. Unggah file jadwal TPB yang diterima dari TPB.</p>
                <p v-if="sourceType" class="mt-2 text-sm">
                    <Link href="/fakultas/program-slot-allocation" class="font-medium text-primary hover:underline">Lanjut ke Slot Prodi</Link>
                </p>
            </section>

            <TpbScheduleImport v-if="period && sourceType !== 'run'" target="faculty" :period="period" :disabled="grid?.published" @imported="imported" />

            <section v-if="grid && sourceType" class="space-y-3">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <h2 class="text-base font-semibold text-gray-900">Slot ruangan yang dipakai TPB</h2>
                    <div class="flex flex-wrap items-end gap-3">
                        <select v-model="building" class="rounded-lg border-gray-200 text-sm" aria-label="Gedung">
                            <option value="">Semua gedung</option>
                            <option v-for="item in buildings" :key="item" :value="item">{{ item }}</option>
                        </select>
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
                    </div>
                </div>
                <CellGrid :rooms="rooms" :slots="slots" :view="cellView" :caption="`Slot ruangan TPB hari ${DAY_LABELS[day]}`" />
            </section>
        </div>

        <ConfirmDialog :show="confirmDelete" title="Hapus import jadwal TPB?" confirm-label="Hapus" :processing="deleting" :error="deleteError" @confirm="remove" @close="confirmDelete = false">
            Slot ruangan yang sudah dibagikan tetap ada. Tanpa data TPB, slot ruangan baru tidak dapat dibagikan.
        </ConfirmDialog>
    </AppLayout>
</template>

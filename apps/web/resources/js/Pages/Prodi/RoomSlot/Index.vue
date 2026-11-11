<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import CellGrid from '@/Components/scheduling/CellGrid.vue';
import SlotRequestList from '@/Components/scheduling/SlotRequestList.vue';
import SubmissionStatusBadge from '@/Components/scheduling/SubmissionStatusBadge.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import { usePeriodPicker } from '@/composables/usePeriodPicker';
import { useProgramSubmission } from '@/composables/useProgramSubmission';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import AppLayout from '@/Layouts/AppLayout.vue';
import { api, fieldErrors, toApiError, type ApiError } from '@/lib/api';
import { DAY_LABELS } from '@/lib/labels';
import type { CellView, Collection, GridRoom, Paginated, ProgramRoomSlots, SlotRequest, TimeSlot } from '@/types/models';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

/**
 * POS 040/IT10.1/OT.07/2026 steps 3 and 4: the room slots the faculty gave the program, whether
 * they hold all its class meetings, and requests for more when they do not.
 */
interface NeedRow { course_id: number; label: string; meetings: number; min_capacity: number }

const user = computed(() => usePage().props.auth.user);
const programId = computed(() => user.value.study_program_id);
const { periods, period, error: periodError } = usePeriodPicker();
const { submission, editable } = useProgramSubmission(programId, period);
const data = ref<ProgramRoomSlots | null>(null);
const timeSlots = ref<TimeSlot[]>([]);
const loadError = ref<ApiError | null>(null);
const loading = ref(false);
const day = ref(1);
const requests = ref<SlotRequest[]>([]);
const requestOpen = ref(false);
const needs = ref<NeedRow[]>([]);
const note = ref('');
const sending = ref(false);
const sendError = ref<ApiError | null>(null);
const sendErrors = ref<Record<string, string>>({});
const cancelling = ref<number | null>(null);
const flash = ref<string | null>(null);

const fieldClass = 'block w-full rounded-lg border-gray-200 bg-white text-sm text-gray-700 focus:border-primary focus:ring-primary';
const sufficiency = computed(() => data.value?.sufficiency ?? null);
const hasPending = computed(() => requests.value.some((request) => request.status === 'pending'));
const days = computed(() => [...new Set(timeSlots.value.map((slot) => slot.day))].sort((a, b) => a - b));
const slots = computed(() => timeSlots.value.filter((slot) => slot.day === day.value).sort((a, b) => a.session - b.session));
const cellKeys = computed(() => new Set((data.value?.cells ?? []).map((cell) => `${cell.time_slot_id}:${cell.room_id}`)));
/** Only rooms the program holds at least once, so the grid stays short. */
const rooms = computed<GridRoom[]>(() => {
    const unique = new Map<number, GridRoom>();
    for (const cell of data.value?.cells ?? []) {
        unique.set(cell.room_id, { id: cell.room_id, code: cell.room_code, name: null, building: null, capacity: cell.capacity, faculty_id: 0 });
    }
    return [...unique.values()].sort((a, b) => a.code.localeCompare(b.code));
});

function cellView(slot: TimeSlot, room: GridRoom): CellView {
    return cellKeys.value.has(`${slot.id}:${room.id}`)
        ? { label: 'Milik prodi', tone: 'bg-primary/10 text-primary', disabled: true }
        : { label: '', tone: 'bg-white', disabled: true };
}

async function load(): Promise<void> {
    if (!programId.value || !period.value) return;
    loading.value = true;
    try {
        const [slotsResponse, requestResponse] = await Promise.all([
            api.get<{ data: ProgramRoomSlots }>(`study-programs/${programId.value}/room-slots`, { params: { period: period.value } }),
            api.get<Paginated<SlotRequest>>('slot-requests', { params: { period: period.value, per_page: 50 } }),
        ]);
        data.value = slotsResponse.data.data;
        requests.value = requestResponse.data.data;
        loadError.value = null;
        if (!timeSlots.value.length) {
            const response = await api.get<Collection<TimeSlot>>('time-slots');
            timeSlots.value = response.data.data;
            day.value = days.value[0] ?? 1;
        }
    } catch (error) {
        loadError.value = toApiError(error);
    } finally {
        loading.value = false;
    }
}

function openRequest(): void {
    needs.value = (sufficiency.value?.shortages ?? []).map((short) => ({
        course_id: short.course_id,
        label: `${short.code} ${short.name}`,
        meetings: short.meetings,
        min_capacity: short.min_capacity,
    }));
    note.value = '';
    sendError.value = null;
    sendErrors.value = {};
    requestOpen.value = true;
}

async function sendRequest(): Promise<void> {
    sending.value = true;
    sendError.value = null;
    sendErrors.value = {};
    try {
        await api.post('slot-requests', {
            period: period.value,
            needs: needs.value.map(({ course_id, meetings, min_capacity }) => ({ course_id, meetings, min_capacity })),
            note: note.value.trim() || null,
        });
        requestOpen.value = false;
        flash.value = 'Permintaan tambahan slot dikirim ke fakultas.';
        await load();
    } catch (error) {
        const apiError = toApiError(error);
        sendErrors.value = fieldErrors(apiError);
        sendError.value = apiError;
    } finally {
        sending.value = false;
    }
}

async function cancel(request: SlotRequest): Promise<void> {
    cancelling.value = request.id;
    try {
        await api.delete(`slot-requests/${request.id}`);
        flash.value = 'Permintaan dibatalkan.';
        await load();
    } catch (error) {
        loadError.value = toApiError(error);
    } finally {
        cancelling.value = null;
    }
}

watch([programId, period], () => void load(), { immediate: true });
onResourcesChanged(['room_slot_allocations', 'slot_requests', 'courses', 'course_lecturers'], () => void load());
</script>

<template>
    <Head title="Slot Ruangan" />
    <AppLayout>
        <div class="space-y-6">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Slot Ruangan</h1>
                    <p class="mt-1 max-w-3xl text-sm text-gray-600">
                        Slot ruangan (ruang, hari, sesi) yang diberikan fakultas kepada program studi. GA hanya menempatkan kelas di slot ini.
                    </p>
                </div>
                <div class="flex items-end gap-3">
                    <SubmissionStatusBadge v-if="submission" :status="submission.status" />
                    <label class="text-sm font-medium text-gray-700">
                        Periode
                        <select v-model="period" class="mt-1 block min-w-56 rounded-lg border-gray-200 bg-white text-sm focus:border-primary focus:ring-primary">
                            <option v-for="item in periods" :key="item.id" :value="item.code">{{ item.label }}</option>
                        </select>
                    </label>
                </div>
            </header>

            <div class="space-y-3">
                <Alert v-if="periodError" :title="periodError.message" />
                <Alert v-if="loadError" :title="loadError.message" />
                <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />
            </div>

            <section v-if="sufficiency" class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-5">
                    <p class="text-sm text-gray-600">Slot ruangan dimiliki</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-gray-900">{{ sufficiency.allocated_cells }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-5">
                    <p class="text-sm text-gray-600">Pertemuan yang perlu dijadwalkan</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-gray-900">{{ sufficiency.required_meetings }}</p>
                </div>
                <div class="rounded-xl border p-5" :class="sufficiency.sufficient ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'">
                    <p class="text-sm" :class="sufficiency.sufficient ? 'text-emerald-800' : 'text-amber-800'">Kecukupan slot</p>
                    <p class="mt-1 text-2xl font-bold" :class="sufficiency.sufficient ? 'text-emerald-800' : 'text-amber-800'">{{ sufficiency.sufficient ? 'Cukup' : 'Kurang' }}</p>
                    <p v-if="sufficiency.unusable_cells" class="mt-1 text-xs text-gray-600">{{ sufficiency.unusable_cells }} slot ruangan tidak terpakai: dosen prodi sibuk mengajar TPB atau prodi lain di jam itu.</p>
                    <p v-if="sufficiency.type_mismatches" class="mt-1 text-xs text-gray-600">{{ sufficiency.type_mismatches }} pertemuan memakai slot dengan panjang sesi berbeda dari SKS-nya.</p>
                </div>
            </section>

            <section v-if="sufficiency && !sufficiency.sufficient" class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Kekurangan slot</h2>
                        <p class="text-sm text-gray-600">Kapasitas kelas dibandingkan dengan kapasitas ruang pada slot yang dimiliki.</p>
                    </div>
                    <Button :disabled="hasPending || !editable" :title="hasPending ? 'Masih ada permintaan yang menunggu' : undefined" @click="openRequest">Ajukan tambahan slot</Button>
                </div>
                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                    <table class="w-full min-w-[560px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-xs text-gray-500">
                                <th class="px-4 py-3 font-medium">Mata kuliah</th>
                                <th class="px-4 py-3 text-right font-medium">Pertemuan tanpa slot</th>
                                <th class="px-4 py-3 text-right font-medium">Ruang minimal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="short in sufficiency.shortages" :key="short.course_id">
                                <td class="px-4 py-3"><span class="font-medium text-gray-900">{{ short.name }}</span> <span class="text-xs text-gray-500">{{ short.code }}</span></td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ short.meetings }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ short.min_capacity }} kursi</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-gray-900">Slot per hari</h2>
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
                <p v-if="loading && !data" class="py-8 text-center text-sm text-gray-500">Memuat slot ruangan…</p>
                <p v-else-if="data && !data.cells.length" class="rounded-xl border border-gray-200 bg-white px-4 py-8 text-center text-sm text-gray-500">
                    Fakultas belum memberikan slot ruangan untuk periode ini.
                </p>
                <CellGrid v-else-if="data" :rooms="rooms" :slots="slots" :view="cellView" :caption="`Slot ruangan program studi hari ${DAY_LABELS[day]}`" />
                <p class="text-sm text-gray-600">Setelah slot cukup, jalankan GA dari <Link href="/prodi/ga" class="font-medium text-primary hover:underline">Proses GA</Link>.</p>
            </section>

            <section class="space-y-3">
                <h2 class="text-base font-semibold text-gray-900">Riwayat permintaan tambahan slot</h2>
                <SlotRequestList :requests="requests" cancellable :cancelling="cancelling" @cancel="cancel" />
            </section>
        </div>

        <Modal :show="requestOpen" max-width="2xl" @close="requestOpen = false">
            <div class="space-y-4 p-6">
                <h2 class="text-lg font-bold text-gray-900">Ajukan tambahan slot ruangan</h2>
                <p class="text-sm text-gray-600">Kebutuhan diisi dari kekurangan slot. Ubah bila perlu, lalu kirim ke fakultas.</p>
                <Alert v-if="sendError && !Object.keys(sendErrors).length" :title="sendError.message" />
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-xs text-gray-500">
                            <th class="py-2 font-medium">Mata kuliah</th>
                            <th class="w-32 py-2 font-medium">Pertemuan</th>
                            <th class="w-36 py-2 font-medium">Ruang minimal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="(need, index) in needs" :key="need.course_id">
                            <td class="py-2 pr-3 text-gray-900">{{ need.label }}</td>
                            <td class="py-2 pr-3"><input v-model.number="need.meetings" type="number" min="1" :class="fieldClass" :aria-label="`Pertemuan ${need.label}`" /></td>
                            <td class="py-2">
                                <input v-model.number="need.min_capacity" type="number" min="1" :class="fieldClass" :aria-label="`Ruang minimal ${need.label}`" />
                                <span v-if="sendErrors[`needs.${index}.min_capacity`]" class="text-xs text-red-600">{{ sendErrors[`needs.${index}.min_capacity`] }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <label class="block space-y-1 text-sm font-medium text-gray-700">
                    Catatan untuk fakultas
                    <textarea v-model="note" rows="3" maxlength="1000" :class="fieldClass" placeholder="Opsional, misalnya kelas yang butuh ruang laboratorium" />
                </label>
                <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                    <Button variant="secondary" :disabled="sending" @click="requestOpen = false">Batal</Button>
                    <Button :disabled="!needs.length" :loading="sending" @click="sendRequest">Kirim permintaan</Button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

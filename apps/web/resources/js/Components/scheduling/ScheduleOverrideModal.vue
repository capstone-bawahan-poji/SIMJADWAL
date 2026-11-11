<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import { api, fieldErrors, toApiError, type ApiError } from '@/lib/api';
import { classLabel, DAY_LABELS } from '@/lib/labels';
import type { ScheduleDetailBase, ScheduleOptions } from '@/types/models';
import { computed, onMounted, ref, watch } from 'vue';

/**
 * Manual change to one meeting of the active run of a study program, while its schedule is a
 * draft: move it to a free cell of the program, or swap cells with another meeting of the run.
 */
const props = defineProps<{
    scheduleDetail: ScheduleDetailBase;
    show: boolean;
}>();
const emit = defineEmits<{
    close: [];
    updated: [];
}>();
const fieldClass = 'w-full rounded-lg border-gray-200 bg-white text-sm text-gray-900 focus:border-primary focus:ring-primary';
const mode = ref<'move' | 'swap'>('move');
const options = ref<ScheduleOptions | null>(null);
const selectedSlotId = ref<number | null>(null);
const selectedRoomId = ref<number | null>(null);
const swapWith = ref<number | null>(null);
const reason = ref('');
const loading = ref(false);
const saving = ref(false);
const failure = ref<ApiError | null>(null);
const errors = ref<Record<string, string>>({});

const selectedSlot = computed(() => options.value?.time_slots.find((slot) => slot.id === selectedSlotId.value) ?? null);
const slotDays = computed(() => [...new Set(options.value?.time_slots.map((slot) => slot.day) ?? [])].sort((a, b) => a - b));
const slotSessions = computed(() => [...new Set(options.value?.time_slots.map((slot) => slot.session) ?? [])].sort((a, b) => a - b));
const slotById = computed(() => new Map((options.value?.time_slots ?? []).map((slot) => [slot.id, slot])));
const isCurrent = computed(() => selectedSlotId.value === options.value?.current.time_slot_id && selectedRoomId.value === options.value?.current.room_id);
const canSave = computed(() => (mode.value === 'move'
    ? selectedSlot.value?.available === true && selectedRoomId.value !== null && !isCurrent.value
    : swapWith.value !== null));

function slotLabel(slotId: number): string {
    const slot = slotById.value.get(slotId);

    return slot ? `${DAY_LABELS[slot.day]}, sesi ${slot.session}` : '';
}

async function loadOptions(): Promise<void> {
    loading.value = true;
    failure.value = null;
    errors.value = {};
    try {
        const response = await api.get<{ data: ScheduleOptions }>(`schedule-details/${props.scheduleDetail.id}/options`);
        options.value = response.data.data;
        selectedSlotId.value = response.data.data.current.time_slot_id;
        selectedRoomId.value = response.data.data.current.room_id;
    } catch (error) {
        failure.value = toApiError(error);
    } finally {
        loading.value = false;
    }
}

function chooseSlot(slotId: number): void {
    selectedSlotId.value = slotId;
    const rooms = options.value?.time_slots.find((item) => item.id === slotId)?.rooms ?? [];
    selectedRoomId.value = rooms.some((room) => room.id === selectedRoomId.value) ? selectedRoomId.value : (rooms[0]?.id ?? null);
    errors.value = {};
}

async function save(): Promise<void> {
    saving.value = true;
    failure.value = null;
    errors.value = {};
    try {
        const note = reason.value.trim() || null;
        if (mode.value === 'move') {
            await api.patch(`schedule-details/${props.scheduleDetail.id}`, { time_slot_id: selectedSlotId.value, room_id: selectedRoomId.value, reason: note });
        } else {
            await api.post('schedule-details/swap', { a: props.scheduleDetail.id, b: swapWith.value, reason: note });
        }
        emit('updated');
    } catch (error) {
        const apiError = toApiError(error);
        const fieldMessages = fieldErrors(apiError);
        if (apiError.status === 422 && Object.keys(fieldMessages).length) {
            errors.value = fieldMessages;
        } else {
            failure.value = apiError;
        }
    } finally {
        saving.value = false;
    }
}

watch(() => props.show, (visible) => {
    if (visible && !options.value && !loading.value) void loadOptions();
});
watch(mode, () => {
    errors.value = {};
});
onMounted(() => {
    if (props.show) void loadOptions();
});
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div class="space-y-5 p-6">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Ubah Jadwal Manual</h2>
                <p class="mt-1 text-sm text-gray-600">{{ scheduleDetail.course.code }} {{ scheduleDetail.course.name }}, kelas {{ classLabel(scheduleDetail.class_number) }}</p>
            </div>

            <div class="flex gap-1 border-b border-gray-100" role="tablist" aria-label="Cara mengubah">
                <button
                    v-for="tab in ([['move', 'Pindah ke slot kosong'], ['swap', 'Tukar dengan jadwal lain']] as const)"
                    :key="tab[0]"
                    type="button"
                    role="tab"
                    :aria-selected="mode === tab[0]"
                    class="-mb-px border-b-2 px-3 py-2 text-sm"
                    :class="mode === tab[0] ? 'border-primary font-semibold text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                    @click="mode = tab[0]"
                >{{ tab[1] }}</button>
            </div>

            <Alert v-if="failure" :title="failure.message" />
            <div v-if="loading" class="py-8 text-center text-sm text-gray-500">Memeriksa slot ruangan program studi…</div>

            <template v-else-if="options && mode === 'move'">
                <section class="space-y-2">
                    <h3 class="text-sm font-bold text-gray-900">Pilih hari dan sesi</h3>
                    <p v-if="errors.time_slot_id" class="text-xs text-red-600">{{ errors.time_slot_id }}</p>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[560px] border-collapse text-xs">
                            <thead>
                                <tr>
                                    <th class="p-2 text-left text-gray-500">Sesi</th>
                                    <th v-for="day in slotDays" :key="day" class="p-2 text-center text-gray-500">{{ DAY_LABELS[day] }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="session in slotSessions" :key="session">
                                    <th class="whitespace-nowrap p-2 text-left text-gray-700">Sesi {{ session }}</th>
                                    <td v-for="day in slotDays" :key="day" class="p-1">
                                        <template v-for="slot in options.time_slots.filter((item) => item.day === day && item.session === session)" :key="slot.id">
                                            <button
                                                type="button"
                                                class="w-full rounded-lg border px-2 py-2 text-left transition-colors"
                                                :class="[
                                                    selectedSlotId === slot.id ? 'border-primary bg-primary/10 text-primary' : 'border-gray-200 bg-white text-gray-700',
                                                    slot.available && slot.rooms.length ? 'hover:border-primary' : 'cursor-not-allowed bg-gray-50 text-gray-400',
                                                ]"
                                                :disabled="!slot.available || (!slot.rooms.length && slot.id !== options.current.time_slot_id)"
                                                :title="slot.available ? (slot.rooms.length ? `${slot.rooms.length} ruang kosong` : 'Tidak ada slot ruangan kosong') : slot.reasons.join('\n')"
                                                @click="chooseSlot(slot.id)"
                                            >
                                                <span class="block font-semibold">{{ slot.start_time }}–{{ slot.end_time }}</span>
                                                <span v-if="slot.id === options.current.time_slot_id" class="mt-0.5 block text-[10px]">Saat ini</span>
                                                <span v-else-if="!slot.available" class="mt-0.5 block text-[10px]">Bentrok</span>
                                                <span v-else-if="!slot.rooms.length" class="mt-0.5 block text-[10px]">Tidak ada ruang</span>
                                                <span v-else class="mt-0.5 block text-[10px]">{{ slot.rooms.length }} ruang</span>
                                            </button>
                                        </template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <ul v-if="selectedSlot && !selectedSlot.available" class="list-disc pl-5 text-xs text-red-600">
                        <li v-for="item in selectedSlot.reasons" :key="item">{{ item }}</li>
                    </ul>
                </section>

                <label class="block space-y-1 text-sm font-semibold text-gray-700">
                    Ruangan
                    <select v-model="selectedRoomId" class="block" :class="fieldClass" :disabled="!selectedSlot?.available">
                        <option v-if="selectedSlotId === options.current.time_slot_id && options.current.room_id !== null" :value="options.current.room_id">
                            {{ scheduleDetail.room?.code ?? 'Ruang saat ini' }} (saat ini)
                        </option>
                        <option v-for="room in selectedSlot?.rooms ?? []" :key="room.id" :value="room.id">{{ room.code }}, {{ room.capacity }} kursi</option>
                    </select>
                    <span v-if="errors.room_id" class="block text-xs font-normal text-red-600">{{ errors.room_id }}</span>
                    <span class="block text-xs font-normal text-gray-500">Hanya slot ruangan yang diberikan fakultas kepada program studi dan masih kosong.</span>
                </label>
            </template>

            <template v-else-if="options && mode === 'swap'">
                <p class="text-sm text-gray-600">Saat tidak ada slot kosong, tukar slot dan ruang pertemuan ini dengan pertemuan lain. Daftar hanya berisi pertukaran yang tidak menimbulkan bentrok.</p>
                <p v-if="errors.a || errors.b" class="text-xs text-red-600">{{ errors.a ?? errors.b }}</p>
                <p v-if="!options.swap_candidates.length" class="rounded-lg bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">Tidak ada pertemuan yang bisa ditukar tanpa bentrok.</p>
                <ul v-else class="max-h-72 divide-y divide-gray-100 overflow-y-auto rounded-lg border border-gray-200">
                    <li v-for="candidate in options.swap_candidates" :key="candidate.schedule_detail_id">
                        <label class="flex cursor-pointer items-center gap-3 px-4 py-3 text-sm hover:bg-gray-50">
                            <input v-model="swapWith" type="radio" :value="candidate.schedule_detail_id" class="text-primary focus:ring-primary" />
                            <span class="flex-1">
                                <span class="block font-medium text-gray-900">{{ candidate.course.name }}, kelas {{ classLabel(candidate.class_number) }}</span>
                                <span class="block text-xs text-gray-500">{{ slotLabel(candidate.time_slot_id) }}, ruang {{ candidate.room_code ?? '-' }}</span>
                            </span>
                        </label>
                    </li>
                </ul>
            </template>

            <label v-if="options" class="block space-y-1 text-sm font-semibold text-gray-700">
                Alasan perubahan
                <textarea v-model="reason" rows="2" maxlength="500" class="block" :class="fieldClass" placeholder="Opsional, untuk catatan riwayat" />
                <span v-if="errors.reason" class="block text-xs font-normal text-red-600">{{ errors.reason }}</span>
            </label>

            <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                <Button variant="ghost" :disabled="saving" @click="emit('close')">Batal</Button>
                <Button :disabled="!canSave" :loading="saving" @click="save">{{ mode === 'move' ? 'Pindahkan' : 'Tukar' }}</Button>
            </div>
        </div>
    </Modal>
</template>

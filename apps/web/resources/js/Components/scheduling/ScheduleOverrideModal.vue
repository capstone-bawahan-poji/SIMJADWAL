<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import { api, fieldErrors, toApiError, type ApiError } from '@/lib/api';
import type { ScheduleDetailBase, ScheduleOptions } from '@/types/models';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    scheduleDetail: ScheduleDetailBase;
    show: boolean;
}>();
const emit = defineEmits<{
    close: [];
    updated: [detail: ScheduleDetailBase];
}>();
const DAYS: Record<number, string> = { 1: 'Senin', 2: 'Selasa', 3: 'Rabu', 4: 'Kamis', 5: 'Jumat', 6: 'Sabtu', 7: 'Minggu' };
const fieldClass = 'w-full rounded-xl border-gray-200 bg-white text-sm text-gray-900 focus:border-primary focus:ring-primary';
const options = ref<ScheduleOptions | null>(null);
const selectedSlotId = ref<number | null>(null);
const selectedRoomId = ref<number | null>(null);
const loading = ref(false);
const saving = ref(false);
const failure = ref<ApiError | null>(null);
const errors = ref<Record<string, string>>({});

const selectedSlot = computed(() => options.value?.time_slots.find((slot) => slot.id === selectedSlotId.value) ?? null);
const slotDays = computed(() => [...new Set(options.value?.time_slots.map((slot) => slot.day) ?? [])].sort((a, b) => a - b));
const slotSessions = computed(() => [...new Set(options.value?.time_slots.map((slot) => slot.session) ?? [])].sort((a, b) => a - b));
const roomsForSelectedSlot = computed(() => {
    if (!selectedSlot.value) return [];
    const availableRooms = [...selectedSlot.value.rooms];
    const currentRoom = props.scheduleDetail.room;

    if (selectedSlot.value.id === options.value?.current.time_slot_id && currentRoom && !availableRooms.some((room) => room.id === currentRoom.id)) {
        availableRooms.unshift(currentRoom);
    }

    return availableRooms;
});

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
    const slot = options.value?.time_slots.find((item) => item.id === slotId);
    if (selectedRoomId.value !== null && !slot?.rooms.some((room) => room.id === selectedRoomId.value)) {
        selectedRoomId.value = null;
    }
    errors.value = {};
}

async function save(): Promise<void> {
    if (selectedSlotId.value === null) return;
    saving.value = true;
    failure.value = null;
    errors.value = {};

    try {
        const response = await api.patch<{ data: ScheduleDetailBase }>(`schedule-details/${props.scheduleDetail.id}`, {
            time_slot_id: selectedSlotId.value,
            room_id: selectedRoomId.value,
        });
        emit('updated', response.data.data);
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

watch(
    () => props.show,
    (visible) => {
        if (visible && !options.value && !loading.value) void loadOptions();
    },
);
onMounted(() => {
    if (props.show) void loadOptions();
});
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div class="space-y-5 p-6">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Ubah Jadwal Manual</h2>
                <p class="mt-1 text-sm text-gray-600">{{ scheduleDetail.course.code }} · {{ scheduleDetail.course.name }} · Kelas {{ String.fromCharCode(64 + scheduleDetail.class_number) }}</p>
            </div>

            <Alert v-if="failure" :title="failure.message" />
            <div v-if="loading" class="py-8 text-center text-sm text-gray-500">Memeriksa slot dan ruang yang tersedia…</div>

            <template v-else-if="options">
                <section class="space-y-2">
                    <h3 class="text-sm font-bold text-gray-900">Pilih hari dan sesi</h3>
                    <p v-if="errors.time_slot_id" class="text-xs text-red-600">{{ errors.time_slot_id }}</p>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[560px] border-collapse text-xs">
                            <thead>
                                <tr>
                                    <th class="p-2 text-left text-gray-500">Sesi</th>
                                    <th v-for="day in slotDays" :key="day" class="p-2 text-center text-gray-500">{{ DAYS[day] }}</th>
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
                                                    slot.available ? 'hover:border-primary' : 'cursor-not-allowed bg-gray-50 text-gray-400',
                                                ]"
                                                :disabled="!slot.available"
                                                :title="slot.available ? `${slot.start_time}–${slot.end_time}` : slot.reasons.join('\n')"
                                                @click="chooseSlot(slot.id)"
                                            >
                                                <span class="block font-semibold">{{ slot.start_time }}–{{ slot.end_time }}</span>
                                                <span v-if="slot.id === options.current.time_slot_id" class="mt-0.5 block text-[10px]">Saat ini</span>
                                                <span v-else-if="!slot.available" class="mt-0.5 block text-[10px]">Tidak tersedia</span>
                                            </button>
                                        </template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <ul v-if="selectedSlot && !selectedSlot.available" class="list-disc pl-5 text-xs text-red-600">
                        <li v-for="reason in selectedSlot.reasons" :key="reason">{{ reason }}</li>
                    </ul>
                </section>

                <label class="block space-y-1 text-sm font-semibold text-gray-700">
                    Ruangan
                    <select v-model="selectedRoomId" class="block" :class="fieldClass" :disabled="!selectedSlot || !selectedSlot.available">
                        <option :value="null">Tanpa ruang</option>
                        <option v-for="room in roomsForSelectedSlot" :key="room.id" :value="room.id">{{ room.code }}{{ room.name ? ` · ${room.name}` : '' }} · {{ room.capacity }} kursi</option>
                    </select>
                    <span v-if="errors.room_id" class="block text-xs font-normal text-red-600">{{ errors.room_id }}</span>
                    <span v-if="selectedSlot && selectedSlot.available && selectedSlot.rooms.length === 0" class="block text-xs font-normal text-amber-700">Tidak ada ruang kosong di jam ini, pilih slot lain atau lanjutkan tanpa ruang.</span>
                </label>
            </template>

            <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                <Button variant="ghost" :disabled="saving" @click="emit('close')">Batal</Button>
                <Button :disabled="!options || !selectedSlot?.available" :loading="saving" @click="save">Simpan perubahan</Button>
            </div>
        </div>
    </Modal>
</template>

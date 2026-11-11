<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import { api, fieldErrors, toApiError, type ApiError } from '@/lib/api';
import { classLabel, DAY_LABELS } from '@/lib/labels';
import type { TpbBlockOptions } from '@/types/models';
import { computed, onMounted, ref, watch } from 'vue';

/**
 * Manual change to one meeting of a TPB group while the TPB run is active and not published:
 * move the whole block (all classes of the group) to another slot, or swap slots with the block
 * of another group. Rooms are picked by the server.
 */
const props = defineProps<{
    scheduleDetailId: number;
    show: boolean;
}>();
const emit = defineEmits<{
    close: [];
    updated: [];
}>();
const fieldClass = 'w-full rounded-lg border-gray-200 bg-white text-sm text-gray-900 focus:border-primary focus:ring-primary';
const mode = ref<'move' | 'swap'>('move');
const options = ref<TpbBlockOptions | null>(null);
const selectedSlotId = ref<number | null>(null);
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
const isCurrent = computed(() => selectedSlotId.value === options.value?.current.time_slot_id);
const canSave = computed(() => (mode.value === 'move'
    ? selectedSlot.value?.available === true && !isCurrent.value
    : swapWith.value !== null));

function slotLabel(slotId: number): string {
    const slot = slotById.value.get(slotId);

    return slot ? `${DAY_LABELS[slot.day]}, sesi ${slot.session}` : '';
}

function classesLabel(classes: { class_number: number }[]): string {
    return `Kelas ${classes.map((item) => classLabel(item.class_number)).join(', ')}`;
}

async function loadOptions(): Promise<void> {
    loading.value = true;
    failure.value = null;
    errors.value = {};
    try {
        const response = await api.get<{ data: TpbBlockOptions }>(`tpb-blocks/${props.scheduleDetailId}/options`);
        options.value = response.data.data;
        selectedSlotId.value = response.data.data.current.time_slot_id;
    } catch (error) {
        failure.value = toApiError(error);
    } finally {
        loading.value = false;
    }
}

function chooseSlot(slotId: number): void {
    selectedSlotId.value = slotId;
    errors.value = {};
}

async function save(): Promise<void> {
    saving.value = true;
    failure.value = null;
    errors.value = {};
    try {
        const note = reason.value.trim() || null;
        if (mode.value === 'move') {
            await api.patch(`tpb-blocks/${props.scheduleDetailId}`, { time_slot_id: selectedSlotId.value, reason: note });
        } else {
            await api.post('tpb-blocks/swap', { a: props.scheduleDetailId, b: swapWith.value, reason: note });
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
                <h2 class="text-lg font-bold text-gray-900">Ubah Jadwal Blok TPB</h2>
                <p v-if="options" class="mt-1 text-sm text-gray-600">
                    {{ options.block.course.code }} {{ options.block.course.name }}<template v-if="options.block.group_code"> · Grup {{ options.block.group_code }}</template><template v-if="options.block.meeting_number > 1"> · Pertemuan {{ options.block.meeting_number }}</template>
                    · {{ classesLabel(options.block.classes) }}
                </p>
                <p class="mt-1 text-xs text-gray-500">Semua kelas satu grup berpindah bersama. Ruang dipilih otomatis dari ruang bersama yang kosong.</p>
            </div>

            <div class="flex gap-1 border-b border-gray-100" role="tablist" aria-label="Cara mengubah">
                <button
                    v-for="tab in ([['move', 'Pindah ke slot kosong'], ['swap', 'Tukar dengan blok lain']] as const)"
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
            <div v-if="loading" class="py-8 text-center text-sm text-gray-500">Memeriksa slot dan ruang bersama…</div>

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
                                                    slot.available ? 'hover:border-primary' : 'cursor-not-allowed bg-gray-50 text-gray-400',
                                                ]"
                                                :disabled="!slot.available && slot.id !== options.current.time_slot_id"
                                                :title="slot.available ? slot.warnings.join('\n') : slot.reasons.join('\n')"
                                                @click="chooseSlot(slot.id)"
                                            >
                                                <span class="block font-semibold">{{ slot.start_time }}–{{ slot.end_time }}</span>
                                                <span v-if="slot.id === options.current.time_slot_id" class="mt-0.5 block text-[10px]">Saat ini</span>
                                                <span v-else-if="!slot.available" class="mt-0.5 block text-[10px]">Bentrok</span>
                                                <span v-else-if="slot.warnings.length" class="mt-0.5 block text-[10px] text-amber-700">Panjang beda</span>
                                                <span v-else class="mt-0.5 block text-[10px]">Kosong</span>
                                            </button>
                                        </template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <ul v-if="selectedSlot && !selectedSlot.available && !isCurrent" class="list-disc pl-5 text-xs text-red-600">
                        <li v-for="item in selectedSlot.reasons" :key="item">{{ item }}</li>
                    </ul>
                    <ul v-if="selectedSlot?.available && selectedSlot.warnings.length" class="list-disc pl-5 text-xs text-amber-700">
                        <li v-for="item in selectedSlot.warnings" :key="item">{{ item }}</li>
                    </ul>
                </section>

                <section v-if="selectedSlot?.available && !isCurrent" class="space-y-2">
                    <h3 class="text-sm font-bold text-gray-900">Ruang yang dipakai</h3>
                    <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200 text-sm">
                        <li v-for="room in selectedSlot.rooms" :key="room.schedule_detail_id" class="flex items-center justify-between px-4 py-2">
                            <span class="font-medium text-gray-900">Kelas {{ classLabel(room.class_number) }}</span>
                            <span class="text-gray-600">{{ room.room_code }}, {{ room.capacity }} kursi</span>
                        </li>
                    </ul>
                </section>
            </template>

            <template v-else-if="options && mode === 'swap'">
                <p class="text-sm text-gray-600">Saat tidak ada slot kosong, tukar slot blok ini dengan blok grup lain. Daftar hanya berisi pertukaran yang tidak menimbulkan bentrok.</p>
                <p v-if="errors.a || errors.b" class="text-xs text-red-600">{{ errors.a ?? errors.b }}</p>
                <p v-if="!options.swap_candidates.length" class="rounded-lg bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">Tidak ada blok yang bisa ditukar tanpa bentrok.</p>
                <ul v-else class="max-h-72 divide-y divide-gray-100 overflow-y-auto rounded-lg border border-gray-200">
                    <li v-for="candidate in options.swap_candidates" :key="candidate.schedule_detail_id">
                        <label class="flex cursor-pointer items-center gap-3 px-4 py-3 text-sm hover:bg-gray-50">
                            <input v-model="swapWith" type="radio" :value="candidate.schedule_detail_id" class="text-primary focus:ring-primary" />
                            <span class="flex-1">
                                <span class="block font-medium text-gray-900">{{ candidate.course.name }}<template v-if="candidate.group_code"> · Grup {{ candidate.group_code }}</template><template v-if="candidate.meeting_number > 1"> · Pertemuan {{ candidate.meeting_number }}</template></span>
                                <span class="block text-xs text-gray-500">{{ classesLabel(candidate.classes) }}, {{ slotLabel(candidate.time_slot_id) }}</span>
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

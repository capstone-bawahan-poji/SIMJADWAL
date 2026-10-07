<script setup lang="ts">
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Pencil, Trash2 } from 'lucide-vue-next';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Modal from '@/Components/Modal.vue';
import Alert from '@/Components/ui/Alert.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { destroy } from '@/lib/api';
import { formatTime, SKS_MINUTES, slotCode } from '@/lib/labels';
import { useCollection } from '@/composables/useApiList';
import { useApiForm } from '@/composables/useApiForm';
import type { TimeSlot } from '@/types/models';

const DAYS = [
    { value: 1, label: 'Senin' },
    { value: 2, label: 'Selasa' },
    { value: 3, label: 'Rabu' },
    { value: 4, label: 'Kamis' },
    { value: 5, label: 'Jumat' },
];

const fieldClass = 'w-full rounded-xl border-gray-200 bg-gray-50 text-gray-900 text-sm focus:border-primary focus:ring-primary';

const viewMode = ref<'table' | 'grid'>('table');
const { items: slots, loading, error: listError, reload } = useCollection<TimeSlot>('time-slots');

const slotAt = computed(() => new Map(slots.value.map((slot) => [`${slot.day}-${slot.session}`, slot])));
const sessions = computed(() => [...new Set(slots.value.map((slot) => slot.session))].sort((a, b) => a - b));
const activeDays = computed(() => new Set(slots.value.map((slot) => slot.day)).size);

function sessionSummary(session: number): { time: string; sks: number; days: number } | null {
    const inSession = slots.value.filter((slot) => slot.session === session);

    if (inSession.length === 0) return null;

    const counts = new Map<string, { slot: TimeSlot; count: number }>();

    for (const slot of inSession) {
        const key = `${slot.start_time}-${slot.end_time}-${slot.type}`;
        counts.set(key, { slot, count: (counts.get(key)?.count ?? 0) + 1 });
    }

    const common = [...counts.values()].sort((a, b) => b.count - a.count)[0].slot;

    return { time: timeRange(common), sks: common.type, days: inSession.length };
}

function timeRange(slot: TimeSlot): string {
    return `${formatTime(slot.start_time)} - ${formatTime(slot.end_time)} WITA`;
}

function sksLabel(sks: number): string {
    return `${sks} SKS (${sks * SKS_MINUTES} Menit)`;
}

const flash = ref<string | null>(null);

function refresh(message: string): void {
    flash.value = message;
    void reload();
}

// Create / edit modal.
const isModalOpen = ref(false);
const editing = ref<TimeSlot | null>(null);
const form = useApiForm(() => ({ day: 1, session: 1, start_time: '07:30', end_time: '10:00', type: 3 as 2 | 3 }));

function openCreate(day?: number, session?: number): void {
    editing.value = null;
    form.reset({ day: day ?? 1, session: session ?? (sessions.value.at(-1) ?? 0) + 1 });
    isModalOpen.value = true;
}

function openEdit(slot: TimeSlot): void {
    editing.value = slot;
    form.reset({ day: slot.day, session: slot.session, start_time: slot.start_time.slice(0, 5), end_time: slot.end_time.slice(0, 5), type: slot.type });
    isModalOpen.value = true;
}

async function save(): Promise<void> {
    const saved = editing.value
        ? await form.submit<TimeSlot>('patch', `time-slots/${editing.value.id}`)
        : await form.submit<TimeSlot>('post', 'time-slots');

    if (saved) {
        isModalOpen.value = false;
        refresh(`Slot ${slotCode(saved)} ${editing.value ? 'diperbarui' : 'ditambahkan'}.`);
    }
}

// Delete; a slot already used by preferences, TPB groups or schedules is refused (409).
const deleteTarget = ref<TimeSlot | null>(null);
const deleteProcessing = ref(false);
const deleteError = ref<string | null>(null);

function askDelete(slot: TimeSlot): void {
    deleteTarget.value = slot;
    deleteError.value = null;
}

async function confirmDelete(): Promise<void> {
    const slot = deleteTarget.value;

    if (!slot) return;

    deleteProcessing.value = true;
    const error = await destroy(`time-slots/${slot.id}`);
    deleteProcessing.value = false;

    if (error) {
        deleteError.value = error.message;

        return;
    }

    deleteTarget.value = null;
    refresh(`Slot ${slotCode(slot)} dihapus.`);
}
</script>

<template>
    <Head title="Data Jadwal" />

    <AppLayout>
        <div class="p-6 md:p-8 space-y-7 max-w-7xl w-full mx-auto">
            <!-- Page Header -->
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Data Jadwal</h2>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <Button @click="openCreate()" class="bg-primary hover:bg-primary-hover text-white">+ Tambah Slot</Button>
                </div>
            </div>

            <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />
            <Alert v-if="listError" :title="listError.message" />

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-center">
                    <p class="text-[11px] font-bold tracking-wider text-gray-400 uppercase">Total Slot</p>
                    <h3 class="text-2xl font-extrabold text-gray-900 mt-1">{{ slots.length }} <span class="text-xs font-semibold text-gray-400">Slot</span></h3>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-center">
                    <p class="text-[11px] font-bold tracking-wider text-gray-400 uppercase">Hari Operasional</p>
                    <h3 class="text-2xl font-extrabold text-gray-900 mt-1">{{ activeDays }} <span class="text-xs font-semibold text-gray-400">Hari</span></h3>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-center">
                    <p class="text-[11px] font-bold tracking-wider text-gray-400 uppercase">Sesi per Hari</p>
                    <h3 class="text-2xl font-extrabold text-gray-900 mt-1">{{ sessions.length }} <span class="text-xs font-semibold text-gray-400">Sesi</span></h3>
                </div>
            </div>

            <!-- Matrix Grid Section -->
            <Card class="!p-0 border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-5 lg:p-6 pb-0 border-b border-gray-100 bg-white">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5">
                        <div>
                            <h3 class="font-bold text-base text-gray-900">Matriks Slot Waktu Mingguan</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Atur jam mulai, selesai, dan panjang sesi (1 SKS = {{ SKS_MINUTES }} menit) per kombinasi hari</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="flex items-center bg-gray-50 border border-gray-200 rounded-xl p-1 text-xs">
                                <button @click="viewMode = 'grid'" :class="['px-3 py-1.5 rounded-lg font-medium transition-colors', viewMode === 'grid' ? 'bg-white font-semibold text-primary shadow-sm' : 'text-gray-500 hover:text-gray-800']" type="button">Tampilan Grid</button>
                                <button @click="viewMode = 'table'" :class="['px-3 py-1.5 rounded-lg font-medium transition-colors', viewMode === 'table' ? 'bg-white font-semibold text-primary shadow-sm' : 'text-gray-500 hover:text-gray-800']" type="button">Tampilan Tabel</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="!loading && slots.length === 0" class="p-10 text-center text-sm text-gray-500 bg-white">
                    Belum ada slot waktu. Tambahkan slot pertama.
                </div>

                <div v-else-if="viewMode === 'table'" class="overflow-x-auto p-5 lg:p-6 pt-0 bg-white" :class="{ 'opacity-60': loading }">
                    <table class="w-full text-left border-separate border-spacing-2.5 min-w-[1100px] mt-6">
                        <thead>
                            <tr>
                                <th class="w-44 p-3 font-semibold text-xs text-gray-400 uppercase tracking-wider bg-gray-50 rounded-xl text-center">Sesi / Jam</th>
                                <th v-for="day in DAYS" :key="day.value" class="p-3 font-bold text-xs text-gray-700 uppercase tracking-wider bg-gray-50 rounded-xl text-center">{{ day.label }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="session in sessions" :key="session">
                                <td class="bg-gray-50 p-3.5 rounded-xl border border-gray-100 text-center align-middle">
                                    <span class="inline-block px-2.5 py-1 text-[11px] font-bold text-primary bg-primary/10 rounded-lg">Sesi {{ session }}</span>
                                    <template v-if="sessionSummary(session)">
                                        <p class="text-xs font-bold text-gray-800 mt-1.5">{{ sessionSummary(session)?.time }}</p>
                                        <p class="text-[10px] text-gray-400 mt-0.5 font-medium">{{ sksLabel(sessionSummary(session)?.sks ?? 0) }}</p>
                                    </template>
                                </td>
                                <td v-for="day in DAYS" :key="day.value">
                                    <div
                                        v-if="slotAt.get(`${day.value}-${session}`)"
                                        class="group relative p-3.5 bg-gray-50/50 hover:bg-white rounded-xl border border-gray-100 hover:border-primary/30 hover:shadow-sm transition-all"
                                    >
                                        <span class="text-xs font-bold text-gray-800">{{ slotCode(slotAt.get(`${day.value}-${session}`)!) }}</span>
                                        <p class="text-xs font-semibold text-gray-700 mt-2">{{ timeRange(slotAt.get(`${day.value}-${session}`)!) }}</p>
                                        <p class="text-[11px] text-gray-400 mt-0.5">{{ sksLabel(slotAt.get(`${day.value}-${session}`)!.type) }}</p>
                                        <div class="mt-2.5 pt-2 border-t border-gray-100 flex items-center justify-end gap-1 text-gray-400 group-hover:text-gray-600">
                                            <button
                                                v-if="slotAt.get(`${day.value}-${session}`)!.can_update"
                                                type="button"
                                                class="hover:text-primary p-0.5"
                                                title="Edit Slot"
                                                @click="openEdit(slotAt.get(`${day.value}-${session}`)!)"
                                            >
                                                <Pencil class="w-3.5 h-3.5" aria-hidden="true" />
                                            </button>
                                            <button
                                                v-if="slotAt.get(`${day.value}-${session}`)!.can_delete"
                                                type="button"
                                                class="hover:text-red-600 p-0.5"
                                                title="Hapus Slot"
                                                @click="askDelete(slotAt.get(`${day.value}-${session}`)!)"
                                            >
                                                <Trash2 class="w-3.5 h-3.5" aria-hidden="true" />
                                            </button>
                                        </div>
                                    </div>
                                    <button
                                        v-else
                                        type="button"
                                        class="w-full h-full min-h-[110px] rounded-xl border border-dashed border-gray-200 text-xs font-semibold text-gray-400 hover:border-primary/40 hover:text-primary transition-colors"
                                        @click="openCreate(day.value, session)"
                                    >
                                        + Tambah
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="p-5 lg:p-6 pt-0 bg-white grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-6">
                    <div v-for="session in sessions" :key="session" class="border border-gray-100 rounded-xl p-5 bg-gray-50/50 flex flex-col gap-3 hover:border-primary/30 transition-colors">
                        <div class="flex items-center justify-between">
                            <span class="inline-block px-2.5 py-1 text-[11px] font-bold text-primary bg-primary/10 rounded-lg">Sesi {{ session }}</span>
                            <span class="text-[10px] font-semibold text-gray-500 bg-white border border-gray-200 px-2 py-0.5 rounded-md">{{ sessionSummary(session)?.days }} Hari</span>
                        </div>
                        <div class="mt-1">
                            <p class="text-sm font-bold text-gray-900">{{ sessionSummary(session)?.time }}</p>
                            <p class="text-[11px] text-gray-500 mt-0.5">{{ sksLabel(sessionSummary(session)?.sks ?? 0) }}</p>
                        </div>
                    </div>
                </div>
            </Card>
        </div>

        <!-- Create / Edit Modal -->
        <Modal :show="isModalOpen" @close="isModalOpen = false" maxWidth="lg">
            <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50 rounded-t-lg">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">{{ editing ? `Ubah Slot ${slotCode(editing)}` : 'Tambah Slot Waktu' }}</h2>
                    <p class="text-sm text-gray-500">Slot yang sudah dipakai preferensi, grup TPB, atau jadwal tidak dapat diubah</p>
                </div>
                <button type="button" @click="isModalOpen = false" class="text-gray-400 hover:text-gray-900 transition">Tutup</button>
            </div>
            <form @submit.prevent="save" class="p-6 space-y-6">
                <Alert v-if="form.failure.value" :title="form.failure.value.message" />

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label for="slot-day" class="block text-sm font-semibold text-gray-900 mb-1.5">Hari</label>
                        <select id="slot-day" v-model.number="form.data.day" :class="fieldClass">
                            <option v-for="day in DAYS" :key="day.value" :value="day.value">{{ day.label }}</option>
                        </select>
                        <p v-if="form.errors.value.day" class="mt-1 text-xs text-red-600">{{ form.errors.value.day }}</p>
                    </div>
                    <div>
                        <label for="slot-session" class="block text-sm font-semibold text-gray-900 mb-1.5">Sesi ke-</label>
                        <input id="slot-session" v-model.number="form.data.session" type="number" min="1" max="10" :class="fieldClass" required />
                        <p v-if="form.errors.value.session" class="mt-1 text-xs text-red-600">{{ form.errors.value.session }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label for="slot-start" class="block text-sm font-semibold text-gray-900 mb-1.5">Jam Mulai</label>
                        <input id="slot-start" v-model="form.data.start_time" type="time" :class="fieldClass" required />
                        <p v-if="form.errors.value.start_time" class="mt-1 text-xs text-red-600">{{ form.errors.value.start_time }}</p>
                    </div>
                    <div>
                        <label for="slot-end" class="block text-sm font-semibold text-gray-900 mb-1.5">Jam Selesai</label>
                        <input id="slot-end" v-model="form.data.end_time" type="time" :class="fieldClass" required />
                        <p v-if="form.errors.value.end_time" class="mt-1 text-xs text-red-600">{{ form.errors.value.end_time }}</p>
                    </div>
                </div>
                <div>
                    <label for="slot-type" class="block text-sm font-semibold text-gray-900 mb-1.5">Panjang Sesi</label>
                    <select id="slot-type" v-model.number="form.data.type" :class="fieldClass">
                        <option :value="2">{{ sksLabel(2) }}</option>
                        <option :value="3">{{ sksLabel(3) }}</option>
                    </select>
                    <p v-if="form.errors.value.type" class="mt-1 text-xs text-red-600">{{ form.errors.value.type }}</p>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                    <Button type="button" @click="isModalOpen = false" variant="ghost" class="bg-gray-100 hover:bg-gray-200">Batal</Button>
                    <Button type="submit" :loading="form.processing.value">Simpan Slot</Button>
                </div>
            </form>
        </Modal>

        <ConfirmDialog
            :show="deleteTarget !== null"
            title="Hapus slot waktu?"
            :processing="deleteProcessing"
            :error="deleteError"
            @confirm="confirmDelete"
            @close="deleteTarget = null"
        >
            Slot <strong>{{ deleteTarget ? slotCode(deleteTarget) : '' }}</strong> akan dihapus. Penghapusan ditolak selama slot masih dipakai.
        </ConfirmDialog>
    </AppLayout>
</template>

<script setup lang="ts">
import SuperadminLayout from '@/Layouts/SuperadminLayout.vue';
import { Head } from '@inertiajs/vue3';
import { UserRound } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Modal from '@/Components/Modal.vue';
import Alert from '@/Components/ui/Alert.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { destroy } from '@/lib/api';
import { queryParam } from '@/lib/query';
import { countOf, useCollection, usePaginatedList } from '@/composables/useApiList';
import { useApiForm } from '@/composables/useApiForm';
import type { FacultySummary, Room } from '@/types/models';

/** Owner filter: a faculty id, or "shared" for rooms without a faculty. */
type Owner = number | 'shared' | '';

const fieldClass = 'w-full rounded-xl border-gray-200 bg-gray-50 text-gray-900 text-sm focus:border-primary focus:ring-primary';

const { items: faculties } = useCollection<FacultySummary>('faculties');

const owner = ref<Owner>('');
const { filters, items: rooms, pagination, page, loading, error: listError, reload } = usePaginatedList<Room, {
    q: string;
    faculty_id: number | '';
    shared: boolean | '';
    in_use: boolean | '';
}>('rooms', { q: queryParam('q'), faculty_id: '', shared: '', in_use: '' });

watch(owner, (value) => {
    filters.faculty_id = typeof value === 'number' ? value : '';
    filters.shared = value === 'shared' ? true : '';
});

const metrics = ref({ total: 0, available: 0, inUse: 0 });

async function loadMetrics(): Promise<void> {
    try {
        const [total, inUse] = await Promise.all([countOf('rooms'), countOf('rooms', { in_use: true })]);
        metrics.value = { total, available: total - inUse, inUse };
    } catch {
        // The table shows the error; the cards keep their last values.
    }
}

onMounted(loadMetrics);

const flash = ref<string | null>(null);

function refresh(message: string): void {
    flash.value = message;
    void reload();
    void loadMetrics();
}

// Create / edit modal.
const isModalOpen = ref(false);
const editing = ref<Room | null>(null);
const form = useApiForm(() => ({
    code: '',
    name: '',
    building: '',
    floor: '' as number | '',
    capacity: 40 as number | '',
    faculty_id: '' as number | '',
}));

const title = computed(() => (editing.value ? `Ubah Ruangan ${editing.value.code}` : 'Tambah Ruangan'));

function openCreate(): void {
    editing.value = null;
    form.reset({ faculty_id: typeof owner.value === 'number' ? owner.value : '' });
    isModalOpen.value = true;
}

function openEdit(room: Room): void {
    editing.value = room;
    form.reset({
        code: room.code,
        name: room.name ?? '',
        building: room.building ?? '',
        floor: room.floor ?? '',
        capacity: room.capacity,
        faculty_id: room.faculty_id ?? '',
    });
    isModalOpen.value = true;
}

async function save(): Promise<void> {
    const payload = {
        code: form.data.code,
        name: form.data.name || null,
        building: form.data.building || null,
        floor: form.data.floor === '' ? null : form.data.floor,
        capacity: form.data.capacity,
        // Empty = shared room.
        faculty_id: form.data.faculty_id || null,
    };
    const saved = editing.value
        ? await form.submit<Room>('patch', `rooms/${editing.value.id}`, payload)
        : await form.submit<Room>('post', 'rooms', payload);

    if (saved) {
        isModalOpen.value = false;
        refresh(`Ruangan ${saved.code} ${editing.value ? 'diperbarui' : 'ditambahkan'}.`);
    }
}

// Delete; refused with 409 while TPB classes or active schedules use the room.
const deleteTarget = ref<Room | null>(null);
const deleteProcessing = ref(false);
const deleteError = ref<string | null>(null);

function askDelete(room: Room): void {
    deleteTarget.value = room;
    deleteError.value = null;
}

async function confirmDelete(): Promise<void> {
    const room = deleteTarget.value;

    if (!room) return;

    deleteProcessing.value = true;
    const error = await destroy(`rooms/${room.id}`);
    deleteProcessing.value = false;

    if (error) {
        deleteError.value = error.message;

        return;
    }

    deleteTarget.value = null;
    refresh(`Ruangan ${room.code} dihapus.`);
}
</script>

<template>
    <Head title="Data Ruangan" />

    <SuperadminLayout>
        <div class="p-6 md:p-8 space-y-8">
            <!-- Header section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-2.5">
                    <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Data Ruangan</h2>
                </div>
                <div class="flex items-center gap-3">
                    <Button @click="openCreate" class="bg-primary hover:bg-primary-hover text-white">
                        + Tambah Ruangan
                    </Button>
                </div>
            </div>

            <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />

            <!-- Metrics grid -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Total Ruangan</p>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900">{{ metrics.total }} <span class="text-xs font-semibold text-gray-500">Ruangan</span></h3>
                </div>
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Ruang Tersedia</p>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900">{{ metrics.available }} <span class="text-xs font-semibold text-gray-500">Ruang</span></h3>
                </div>
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Digunakan</p>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900">{{ metrics.inUse }} <span class="text-xs font-semibold text-gray-500">Ruang</span></h3>
                    <p class="text-[11px] text-gray-400 mt-1">Terpakai di jadwal aktif</p>
                </div>
            </div>

            <!-- Table & Filters Section -->
            <Card class="!p-0 border border-gray-100 shadow-sm">
                <!-- Toolbar -->
                <div class="p-5 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white rounded-t-2xl">
                    <div class="flex flex-wrap gap-3">
                        <select v-model="owner" class="rounded-xl border-gray-200 text-xs font-semibold focus:border-primary focus:ring-primary text-gray-700 bg-gray-50 w-56" aria-label="Filter pemilik">
                            <option value="">Semua Pemilik</option>
                            <option value="shared">Ruang Bersama</option>
                            <option v-for="faculty in faculties" :key="faculty.id" :value="faculty.id">{{ faculty.name }}</option>
                        </select>
                        <select v-model="filters.in_use" class="rounded-xl border-gray-200 text-xs font-semibold focus:border-primary focus:ring-primary text-gray-700 bg-gray-50 w-40" aria-label="Filter status">
                            <option value="">Semua Status</option>
                            <option :value="false">Tersedia</option>
                            <option :value="true">Digunakan</option>
                        </select>
                    </div>
                    <div class="w-full md:w-64">
                        <input
                            v-model="filters.q"
                            type="search"
                            placeholder="Cari kode, nama, atau gedung..."
                            class="w-full rounded-xl border-gray-200 text-xs focus:border-primary focus:ring-primary placeholder-gray-400 bg-gray-50"
                        />
                    </div>
                </div>

                <Alert v-if="listError" :title="listError.message" class="m-5" />

                <!-- Table -->
                <div class="overflow-x-auto w-full">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50/70 text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-100">
                                <th class="py-4 px-6 min-w-[200px]">Kode & Nama Ruangan</th>
                                <th class="py-4 px-6">Gedung & Lantai</th>
                                <th class="py-4 px-6">Kapasitas</th>
                                <th class="py-4 px-6">Fakultas Pemilik</th>
                                <th class="py-4 px-6">Status</th>
                                <th class="py-4 px-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-xs text-gray-700" :class="{ 'opacity-60': loading }">
                            <tr v-if="!loading && rooms.length === 0">
                                <td colspan="6" class="py-10 px-6 text-center text-sm text-gray-500">Tidak ada ruangan yang cocok dengan filter.</td>
                            </tr>
                            <tr v-for="room in rooms" :key="room.id" class="hover:bg-gray-50/60 transition-colors">
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold text-[10px] shrink-0">{{ room.code }}</div>
                                        <div>
                                            <p class="font-bold text-gray-900">{{ room.name ?? `Ruang ${room.code}` }}</p>
                                            <p class="text-[11px] text-gray-400">KODE: {{ room.code }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <p class="font-semibold text-gray-800">{{ room.building ?? '-' }}</p>
                                    <p v-if="room.floor !== null" class="text-[11px] text-gray-400">Lantai {{ room.floor }}</p>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="inline-flex items-center gap-1.5 font-bold text-gray-800 bg-gray-100 px-2.5 py-1 rounded-md">
                                        <UserRound class="w-3.5 h-3.5 text-gray-400" aria-hidden="true" />
                                        {{ room.capacity }} Kursi
                                    </span>
                                </td>
                                <td class="py-4 px-6 font-medium text-gray-700">{{ room.faculty?.name ?? 'Ruang Bersama' }}</td>
                                <td class="py-4 px-6">
                                    <span
                                        v-if="room.is_in_use"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-100"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Digunakan
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-green-50 text-green-700 border border-green-100"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>Tersedia
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button v-if="room.can_update" type="button" class="p-1.5 rounded hover:bg-primary/10 text-gray-400 hover:text-primary transition-colors" @click="openEdit(room)">Edit</button>
                                        <button v-if="room.can_delete" type="button" class="p-1.5 rounded hover:bg-red-50 text-gray-400 hover:text-red-600 transition-colors" @click="askDelete(room)">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-model:page="page" :pagination="pagination" label="ruangan" />
            </Card>
        </div>

        <!-- Create / Edit Modal -->
        <Modal :show="isModalOpen" @close="isModalOpen = false" maxWidth="2xl">
            <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50 rounded-t-lg">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">{{ title }}</h2>
                    <p class="text-sm text-gray-500">Ruangan tanpa fakultas pemilik dipakai bersama semua fakultas</p>
                </div>
                <button type="button" @click="isModalOpen = false" class="text-gray-400 hover:text-gray-900 transition">Tutup</button>
            </div>
            <form @submit.prevent="save" class="p-6 space-y-6">
                <Alert v-if="form.failure.value" :title="form.failure.value.message" />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="room-code" class="block text-sm font-semibold text-gray-900 mb-1.5">Kode Ruangan</label>
                        <input id="room-code" v-model="form.data.code" type="text" placeholder="Contoh: E101" :class="fieldClass" required />
                        <p v-if="form.errors.value.code" class="mt-1 text-xs text-red-600">{{ form.errors.value.code }}</p>
                    </div>
                    <div>
                        <label for="room-name" class="block text-sm font-semibold text-gray-900 mb-1.5">Nama Ruangan (opsional)</label>
                        <input id="room-name" v-model="form.data.name" type="text" placeholder="Contoh: Lab Komputer" :class="fieldClass" />
                        <p v-if="form.errors.value.name" class="mt-1 text-xs text-red-600">{{ form.errors.value.name }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="room-building" class="block text-sm font-semibold text-gray-900 mb-1.5">Gedung</label>
                        <input id="room-building" v-model="form.data.building" type="text" placeholder="Contoh: Gedung E" :class="fieldClass" />
                        <p v-if="form.errors.value.building" class="mt-1 text-xs text-red-600">{{ form.errors.value.building }}</p>
                    </div>
                    <div>
                        <label for="room-floor" class="block text-sm font-semibold text-gray-900 mb-1.5">Lantai</label>
                        <input id="room-floor" v-model.number="form.data.floor" type="number" min="0" max="50" placeholder="1" :class="fieldClass" />
                        <p v-if="form.errors.value.floor" class="mt-1 text-xs text-red-600">{{ form.errors.value.floor }}</p>
                    </div>
                    <div>
                        <label for="room-capacity" class="block text-sm font-semibold text-gray-900 mb-1.5">Kapasitas (kursi)</label>
                        <input id="room-capacity" v-model.number="form.data.capacity" type="number" min="1" :class="fieldClass" required />
                        <p v-if="form.errors.value.capacity" class="mt-1 text-xs text-red-600">{{ form.errors.value.capacity }}</p>
                    </div>
                </div>
                <div>
                    <label for="room-faculty" class="block text-sm font-semibold text-gray-900 mb-1.5">Fakultas Pemilik</label>
                    <select id="room-faculty" v-model="form.data.faculty_id" :class="fieldClass">
                        <option value="">Ruang Bersama (semua fakultas)</option>
                        <option v-for="faculty in faculties" :key="faculty.id" :value="faculty.id">{{ faculty.name }}</option>
                    </select>
                    <p v-if="form.errors.value.faculty_id" class="mt-1 text-xs text-red-600">{{ form.errors.value.faculty_id }}</p>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                    <Button type="button" @click="isModalOpen = false" variant="ghost" class="bg-gray-100 hover:bg-gray-200">Batal</Button>
                    <Button type="submit" :loading="form.processing.value">Simpan Ruangan</Button>
                </div>
            </form>
        </Modal>

        <ConfirmDialog
            :show="deleteTarget !== null"
            title="Hapus ruangan?"
            :processing="deleteProcessing"
            :error="deleteError"
            @confirm="confirmDelete"
            @close="deleteTarget = null"
        >
            Ruangan <strong>{{ deleteTarget?.code }}</strong> akan dihapus. Penghapusan ditolak selama ruangan masih dipakai kelas TPB atau jadwal aktif.
        </ConfirmDialog>
    </SuperadminLayout>
</template>

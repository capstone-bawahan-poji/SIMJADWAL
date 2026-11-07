<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import Button from '@/Components/ui/Button.vue';
import MetricCard from '@/Components/ui/MetricCard.vue';
import Panel from '@/Components/ui/Panel.vue';
import Modal from '@/Components/Modal.vue';
import Alert from '@/Components/ui/Alert.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { destroy, toApiError, type ApiError } from '@/lib/api';
import { queryParam } from '@/lib/query';
import { countOf, useCollection, usePaginatedList } from '@/composables/useApiList';
import { useApiForm } from '@/composables/useApiForm';
import type { FacultySummary, Room } from '@/types/models';

/** Owner filter: a faculty id, or "shared" for rooms without a faculty. */
type Owner = number | 'shared' | '';

const fieldClass = 'w-full rounded-lg border-gray-200 bg-gray-50 text-gray-900 text-sm focus:border-primary focus:ring-primary';
const selectClass = 'rounded-lg border-gray-200 text-sm focus:border-primary focus:ring-primary text-gray-700';

const { items: faculties, error: facultyError } = useCollection<FacultySummary>('faculties');

const owner = ref<Owner>('');
const { filters, items: rooms, pagination, page, loading, error: listError, reload } = usePaginatedList<Room, {
    q: string;
    faculty_id: number | '';
    shared: boolean | '';
    in_use: boolean | '';
}>('rooms', { q: queryParam('q'), faculty_id: '', shared: '', in_use: '' }, 20, ['scheduling_runs']);

watch(owner, (value) => {
    filters.faculty_id = typeof value === 'number' ? value : '';
    filters.shared = value === 'shared' ? true : '';
});

const showOwner = computed(() => rooms.value.some((room) => room.faculty));

const metrics = ref({ total: 0, available: 0, inUse: 0 });
const metricsError = ref<ApiError | null>(null);

async function loadMetrics(): Promise<void> {
    metricsError.value = null;

    try {
        const [total, inUse] = await Promise.all([countOf('rooms'), countOf('rooms', { in_use: true })]);
        metrics.value = { total, available: total - inUse, inUse };
    } catch (error) {
        metricsError.value = toApiError(error);
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
    capacity: 50 as number | '',
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

    <AppLayout>
        <div class="space-y-8 p-6 md:p-8">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                <h2 class="text-2xl font-bold tracking-tight text-gray-900">Data Ruangan</h2>
                <Button @click="openCreate" class="bg-primary text-white hover:bg-primary-hover">
                    + Tambah Ruangan
                </Button>
            </div>

            <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />
            <Alert v-if="facultyError" :title="facultyError.message" />
            <Alert v-if="metricsError" :title="`Gagal memuat ringkasan ruangan: ${metricsError.message}`" />

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                <MetricCard title="Total Ruangan" :value="metrics.total.toLocaleString('id-ID')" unit="Ruang" />
                <MetricCard title="Ruang Tersedia" :value="metrics.available.toLocaleString('id-ID')" unit="Ruang" />
                <MetricCard title="Digunakan" :value="metrics.inUse.toLocaleString('id-ID')" unit="Ruang" description="Terpakai di jadwal aktif" />
            </div>

            <Panel>
                <div class="flex flex-col justify-between gap-4 border-b border-gray-100 bg-white p-5 md:flex-row">
                    <div class="flex flex-wrap gap-3">
                        <select v-model="owner" :class="[selectClass, 'w-56']" aria-label="Filter pemilik">
                            <option value="">Semua Pemilik</option>
                            <option value="shared">Ruang Bersama</option>
                            <option v-for="faculty in faculties" :key="faculty.id" :value="faculty.id">{{ faculty.name }}</option>
                        </select>
                        <select v-model="filters.in_use" :class="[selectClass, 'w-40']" aria-label="Filter status">
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
                            aria-label="Cari ruangan"
                            class="w-full rounded-lg border-gray-200 text-sm placeholder-gray-400 focus:border-primary focus:ring-primary"
                        />
                    </div>
                </div>

                <Alert v-if="listError" :title="listError.message" class="m-5" />

                <div class="w-full overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-500">
                                <th class="min-w-[200px] whitespace-nowrap px-6 py-4">Ruangan</th>
                                <th class="whitespace-nowrap px-6 py-4">Gedung &amp; Lantai</th>
                                <th class="whitespace-nowrap px-6 py-4">Kapasitas</th>
                                <th v-if="showOwner" class="whitespace-nowrap px-6 py-4">Fakultas Pemilik</th>
                                <th class="whitespace-nowrap px-6 py-4 text-center">Status</th>
                                <th class="whitespace-nowrap px-6 py-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm" :class="{ 'opacity-60': loading }">
                            <tr v-if="!loading && rooms.length === 0">
                                <td :colspan="showOwner ? 6 : 5" class="px-6 py-10 text-center text-gray-500">Tidak ada ruangan yang cocok dengan filter.</td>
                            </tr>
                            <tr v-for="room in rooms" :key="room.id" class="transition-colors hover:bg-gray-50/50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-xs font-bold text-gray-700">
                                            {{ room.code }}
                                        </div>
                                        <p class="font-bold text-gray-900">{{ room.name ?? `Ruang ${room.code}` }}</p>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <p class="font-semibold text-gray-900">{{ room.building ?? '-' }}</p>
                                    <p v-if="room.floor !== null" class="text-xs text-gray-500">Lantai {{ room.floor }}</p>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium text-gray-700">{{ room.capacity }} Kursi</td>
                                <td v-if="showOwner" class="whitespace-nowrap px-6 py-4 text-gray-700">{{ room.faculty?.name ?? 'Ruang Bersama' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-center">
                                    <span v-if="room.is_in_use" class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-primary"></span>Digunakan
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1.5 text-xs font-medium text-green-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-green-600"></span>Tersedia
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex justify-center gap-2">
                                        <Button v-if="room.can_update" variant="secondary" size="sm" @click="openEdit(room)">Edit</Button>
                                        <Button v-if="room.can_delete" variant="danger" size="sm" @click="askDelete(room)">Hapus</Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-model:page="page" :pagination="pagination" label="ruangan" />
            </Panel>
        </div>

        <!-- Create / Edit Modal -->
        <Modal :show="isModalOpen" @close="isModalOpen = false" maxWidth="2xl" close-button>
            <div class="border-b border-gray-200 py-4 pl-6 pr-14">
                <h2 class="text-lg font-bold text-gray-900">{{ title }}</h2>
                <p class="text-sm text-gray-500">Ruangan tanpa fakultas pemilik dipakai bersama semua fakultas</p>
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
                    <Button type="button" @click="isModalOpen = false" variant="secondary">Batal</Button>
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
    </AppLayout>
</template>

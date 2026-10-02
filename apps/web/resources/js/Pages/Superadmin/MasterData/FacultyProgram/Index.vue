<script setup lang="ts">
import SuperadminLayout from '@/Layouts/SuperadminLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import Button from '@/Components/ui/Button.vue';
import MetricCard from '@/Components/ui/MetricCard.vue';
import Card from '@/Components/ui/Card.vue';
import Modal from '@/Components/Modal.vue';
import Alert from '@/Components/ui/Alert.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { destroy } from '@/lib/api';
import { queryParam } from '@/lib/query';
import { countOf, useCollection } from '@/composables/useApiList';
import { useApiForm } from '@/composables/useApiForm';
import type { Department, Faculty, StudyProgram } from '@/types/models';

type Tab = 'faculties' | 'departments' | 'study-programs';
type Entity = { id: number; code: string; name: string };

const ENTITY_LABELS: Record<Tab, string> = {
    faculties: 'Fakultas',
    departments: 'Jurusan',
    'study-programs': 'Program Studi',
};

const selectClass = 'rounded-xl border-gray-200 text-sm focus:border-primary focus:ring-primary text-gray-700';
const fieldClass = 'w-full rounded-xl border-gray-200 bg-gray-50 text-gray-900 text-sm focus:border-primary focus:ring-primary';

// ?tab= and ?q= come from the topbar search.
const initialTab = queryParam('tab');
const tab = ref<Tab>(initialTab in ENTITY_LABELS ? (initialTab as Tab) : 'study-programs');
const search = ref(queryParam('q'));
const facultyFilter = ref<number | ''>('');
const departmentFilter = ref<number | ''>('');

const { items: faculties, loading: facultiesLoading, reload: reloadFaculties } = useCollection<Faculty>('faculties');
const { items: departments, loading: departmentsLoading, reload: reloadDepartments } = useCollection<Department>('departments');
const { items: programs, loading: programsLoading, reload: reloadPrograms } = useCollection<StudyProgram>('study-programs');

const coordinatorCount = ref(0);

async function loadCoordinatorCount(): Promise<void> {
    coordinatorCount.value = await countOf('users', { role: 'admin_prodi' }).catch(() => coordinatorCount.value);
}

onMounted(loadCoordinatorCount);

watch(facultyFilter, () => {
    departmentFilter.value = '';
});

function matches(entity: Entity): boolean {
    const q = search.value.trim().toLowerCase();

    return q === '' || entity.code.toLowerCase().includes(q) || entity.name.toLowerCase().includes(q);
}

// The lists are small and unpaginated, so filtering happens here.
const visibleFaculties = computed(() => faculties.value.filter((f) => matches(f) && (facultyFilter.value === '' || f.id === facultyFilter.value)));
const visibleDepartments = computed(() =>
    departments.value.filter((d) => matches(d) && (facultyFilter.value === '' || d.faculty_id === facultyFilter.value)),
);
const visiblePrograms = computed(() =>
    programs.value.filter(
        (p) =>
            matches(p) &&
            (facultyFilter.value === '' || p.faculty_id === facultyFilter.value) &&
            (departmentFilter.value === '' || p.department_id === departmentFilter.value),
    ),
);
const filterDepartments = computed(() =>
    facultyFilter.value === '' ? departments.value : departments.value.filter((d) => d.faculty_id === facultyFilter.value),
);
const loading = computed(() => facultiesLoading.value || departmentsLoading.value || programsLoading.value);

const flash = ref<string | null>(null);

function refresh(message: string): void {
    flash.value = message;
    void reloadFaculties();
    void reloadDepartments();
    void reloadPrograms();
    void loadCoordinatorCount();
}

// Create / edit modal for any of the three entities.
const isModalOpen = ref(false);
const entityType = ref<Tab>('study-programs');
const editingId = ref<number | null>(null);
const form = useApiForm(() => ({ faculty_id: '' as number | '', department_id: '' as number | '', code: '', name: '' }));

const formDepartments = computed(() => departments.value.filter((d) => d.faculty_id === form.data.faculty_id));

watch(
    () => form.data.faculty_id,
    () => {
        if (!formDepartments.value.some((d) => d.id === form.data.department_id)) {
            form.data.department_id = '';
        }
    },
);

function openCreate(type: Tab = tab.value): void {
    entityType.value = type;
    editingId.value = null;
    form.reset({ faculty_id: facultyFilter.value });
    isModalOpen.value = true;
}

function openEdit(type: Tab, entity: Faculty | Department | StudyProgram): void {
    entityType.value = type;
    editingId.value = entity.id;
    form.reset({
        code: entity.code,
        name: entity.name,
        faculty_id: 'faculty_id' in entity ? entity.faculty_id : '',
        department_id: 'department_id' in entity ? entity.department_id ?? '' : '',
    });
    isModalOpen.value = true;
}

function payload(): Record<string, unknown> {
    const data: Record<string, unknown> = { code: form.data.code, name: form.data.name };

    if (entityType.value !== 'faculties') data.faculty_id = form.data.faculty_id || null;
    if (entityType.value === 'study-programs') data.department_id = form.data.department_id || null;

    return data;
}

async function save(): Promise<void> {
    const url = editingId.value ? `${entityType.value}/${editingId.value}` : entityType.value;
    const saved = await form.submit<Entity>(editingId.value ? 'patch' : 'post', url, payload());

    if (saved) {
        isModalOpen.value = false;
        refresh(`${ENTITY_LABELS[entityType.value]} ${saved.name} ${editingId.value ? 'diperbarui' : 'ditambahkan'}.`);
    }
}

// Delete with the 409 RESOURCE_IN_USE message shown in the dialog.
const deleteTarget = ref<{ type: Tab; entity: Entity } | null>(null);
const deleteProcessing = ref(false);
const deleteError = ref<string | null>(null);

function askDelete(type: Tab, entity: Entity): void {
    deleteTarget.value = { type, entity };
    deleteError.value = null;
}

async function confirmDelete(): Promise<void> {
    const target = deleteTarget.value;

    if (!target) return;

    deleteProcessing.value = true;
    const error = await destroy(`${target.type}/${target.entity.id}`);
    deleteProcessing.value = false;

    if (error) {
        deleteError.value = error.message;

        return;
    }

    deleteTarget.value = null;
    refresh(`${ENTITY_LABELS[target.type]} ${target.entity.name} dihapus.`);
}
</script>

<template>
    <Head title="Master Data Fakultas & Prodi" />

    <SuperadminLayout>
        <div class="p-6 md:p-8 space-y-8">
            <!-- Header section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Master Data Fakultas & Prodi</h2>
                </div>
                <div class="flex items-center gap-3">
                    <Button @click="openCreate()" class="bg-primary hover:bg-primary-hover text-white">
                        + Tambah Entitas
                    </Button>
                </div>
            </div>

            <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />

            <!-- Metrics grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <MetricCard title="Total Fakultas" :value="faculties.length" unit="Fakultas" />
                <MetricCard title="Jurusan" :value="departments.length" unit="Jurusan" />
                <MetricCard title="Koor Prodi" :value="coordinatorCount" unit="Koor Prodi" />
            </div>

            <!-- Tabs -->
            <div class="flex items-center border-b border-gray-200 pb-2">
                <div class="flex bg-gray-100 p-1 rounded-xl" role="tablist">
                    <button
                        v-for="(label, key) in ENTITY_LABELS"
                        :key="key"
                        type="button"
                        role="tab"
                        :aria-selected="tab === key"
                        class="px-4 py-2 rounded-lg text-sm font-semibold"
                        :class="tab === key ? 'bg-white text-primary shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                        @click="tab = key"
                    >
                        {{ label }} ({{ key === 'faculties' ? faculties.length : key === 'departments' ? departments.length : programs.length }})
                    </button>
                </div>
            </div>

            <!-- Table & Filters Section -->
            <Card class="!p-0 border-none shadow-sm">
                <!-- Toolbar -->
                <div class="p-5 border-b border-gray-100 flex flex-col lg:flex-row justify-between gap-4 bg-white">
                    <div class="flex flex-wrap gap-3">
                        <select v-model="facultyFilter" :class="[selectClass, 'w-56']" aria-label="Filter fakultas">
                            <option value="">Semua Fakultas</option>
                            <option v-for="faculty in faculties" :key="faculty.id" :value="faculty.id">{{ faculty.name }} ({{ faculty.code }})</option>
                        </select>
                        <select v-if="tab === 'study-programs'" v-model="departmentFilter" :class="[selectClass, 'w-56']" aria-label="Filter jurusan">
                            <option value="">Semua Jurusan</option>
                            <option v-for="department in filterDepartments" :key="department.id" :value="department.id">{{ department.name }}</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <input
                            v-model="search"
                            type="search"
                            :placeholder="`Cari nama atau kode ${ENTITY_LABELS[tab].toLowerCase()}...`"
                            class="rounded-xl border-gray-200 text-sm focus:border-primary focus:ring-primary w-full lg:w-64 placeholder-gray-400"
                        />
                        <Button @click="openCreate()" variant="ghost" class="bg-gray-100 hover:bg-gray-200 text-primary font-semibold whitespace-nowrap">
                            + {{ ENTITY_LABELS[tab] }} Baru
                        </Button>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto w-full" :class="{ 'opacity-60': loading }">
                    <!-- Program Studi -->
                    <table v-if="tab === 'study-programs'" class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-500 border-b border-gray-100">
                                <th class="py-4 px-6 min-w-[250px]">Program Studi & Kode</th>
                                <th class="py-4 px-6">Fakultas & Jurusan</th>
                                <th class="py-4 px-6">Koor Prodi & Kontak</th>
                                <th class="py-4 px-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <tr v-if="!loading && visiblePrograms.length === 0">
                                <td colspan="4" class="py-10 px-6 text-center text-gray-500">Tidak ada program studi yang cocok.</td>
                            </tr>
                            <tr v-for="program in visiblePrograms" :key="program.id" class="hover:bg-gray-50/50 transition-colors">
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">{{ program.code }}</div>
                                        <div>
                                            <p class="font-bold text-gray-900">{{ program.name }}</p>
                                            <p class="text-xs text-gray-500">Kode: {{ program.code }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <p class="font-semibold text-gray-900">{{ program.faculty.code }}</p>
                                    <p class="text-xs text-gray-500">{{ program.department?.name ?? 'Tanpa jurusan' }}</p>
                                </td>
                                <td class="py-4 px-6">
                                    <template v-if="program.coordinator">
                                        <p class="font-semibold text-gray-900">{{ program.coordinator.name }}</p>
                                        <p class="text-xs text-gray-500">
                                            {{ program.coordinator.identity_number ? `NIP. ${program.coordinator.identity_number}` : program.coordinator.email }}
                                        </p>
                                    </template>
                                    <p v-else class="text-xs text-gray-400">Belum ada akun Koor Prodi</p>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button v-if="program.can_update" type="button" class="p-1.5 rounded hover:bg-gray-100 text-gray-400 hover:text-gray-900 transition-colors" @click="openEdit('study-programs', program)">Edit</button>
                                        <button v-if="program.can_delete" type="button" class="p-1.5 rounded hover:bg-red-50 text-gray-400 hover:text-red-600 transition-colors" @click="askDelete('study-programs', program)">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Jurusan -->
                    <table v-else-if="tab === 'departments'" class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-500 border-b border-gray-100">
                                <th class="py-4 px-6 min-w-[250px]">Jurusan & Kode</th>
                                <th class="py-4 px-6">Fakultas Induk</th>
                                <th class="py-4 px-6">Program Studi</th>
                                <th class="py-4 px-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <tr v-if="!loading && visibleDepartments.length === 0">
                                <td colspan="4" class="py-10 px-6 text-center text-gray-500">Tidak ada jurusan yang cocok.</td>
                            </tr>
                            <tr v-for="department in visibleDepartments" :key="department.id" class="hover:bg-gray-50/50 transition-colors">
                                <td class="py-4 px-6">
                                    <p class="font-bold text-gray-900">{{ department.name }}</p>
                                    <p class="text-xs text-gray-500">Kode: {{ department.code }}</p>
                                </td>
                                <td class="py-4 px-6">
                                    <p class="font-semibold text-gray-900">{{ department.faculty.code }}</p>
                                    <p class="text-xs text-gray-500">{{ department.faculty.name }}</p>
                                </td>
                                <td class="py-4 px-6 font-semibold text-gray-900">{{ department.study_programs_count }} Prodi</td>
                                <td class="py-4 px-6 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button v-if="department.can_update" type="button" class="p-1.5 rounded hover:bg-gray-100 text-gray-400 hover:text-gray-900 transition-colors" @click="openEdit('departments', department)">Edit</button>
                                        <button v-if="department.can_delete" type="button" class="p-1.5 rounded hover:bg-red-50 text-gray-400 hover:text-red-600 transition-colors" @click="askDelete('departments', department)">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Fakultas -->
                    <table v-else class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-500 border-b border-gray-100">
                                <th class="py-4 px-6 min-w-[250px]">Fakultas & Kode</th>
                                <th class="py-4 px-6">Jurusan</th>
                                <th class="py-4 px-6">Program Studi</th>
                                <th class="py-4 px-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <tr v-if="!loading && visibleFaculties.length === 0">
                                <td colspan="4" class="py-10 px-6 text-center text-gray-500">Tidak ada fakultas yang cocok.</td>
                            </tr>
                            <tr v-for="faculty in visibleFaculties" :key="faculty.id" class="hover:bg-gray-50/50 transition-colors">
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">{{ faculty.code }}</div>
                                        <p class="font-bold text-gray-900">{{ faculty.name }}</p>
                                    </div>
                                </td>
                                <td class="py-4 px-6 font-semibold text-gray-900">{{ faculty.departments_count }} Jurusan</td>
                                <td class="py-4 px-6 font-semibold text-gray-900">{{ faculty.study_programs_count }} Prodi</td>
                                <td class="py-4 px-6 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button v-if="faculty.can_update" type="button" class="p-1.5 rounded hover:bg-gray-100 text-gray-400 hover:text-gray-900 transition-colors" @click="openEdit('faculties', faculty)">Edit</button>
                                        <button v-if="faculty.can_delete" type="button" class="p-1.5 rounded hover:bg-red-50 text-gray-400 hover:text-red-600 transition-colors" @click="askDelete('faculties', faculty)">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>

        <!-- Create / Edit Modal -->
        <Modal :show="isModalOpen" @close="isModalOpen = false" maxWidth="2xl">
            <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50 rounded-t-lg">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">{{ editingId ? 'Ubah' : 'Tambah' }} {{ ENTITY_LABELS[entityType] }}</h2>
                    <p class="text-sm text-gray-500">Struktur akademik: Fakultas → Jurusan (opsional) → Program Studi</p>
                </div>
                <button type="button" @click="isModalOpen = false" class="text-gray-400 hover:text-gray-900 transition">Tutup</button>
            </div>
            <form @submit.prevent="save" class="p-6 space-y-6">
                <Alert v-if="form.failure.value" :title="form.failure.value.message" />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="entity-type" class="block text-sm font-semibold text-gray-900 mb-1.5">Tipe Entitas</label>
                        <select id="entity-type" v-model="entityType" :class="fieldClass" :disabled="editingId !== null">
                            <option value="study-programs">Program Studi (Prodi)</option>
                            <option value="departments">Jurusan</option>
                            <option value="faculties">Fakultas</option>
                        </select>
                    </div>
                    <div v-if="entityType !== 'faculties'">
                        <label for="entity-faculty" class="block text-sm font-semibold text-gray-900 mb-1.5">Fakultas Induk</label>
                        <select id="entity-faculty" v-model="form.data.faculty_id" :class="fieldClass" required>
                            <option value="" disabled>Pilih fakultas...</option>
                            <option v-for="faculty in faculties" :key="faculty.id" :value="faculty.id">{{ faculty.name }} ({{ faculty.code }})</option>
                        </select>
                        <p v-if="form.errors.value.faculty_id" class="mt-1 text-xs text-red-600">{{ form.errors.value.faculty_id }}</p>
                    </div>
                </div>
                <div v-if="entityType === 'study-programs'">
                    <label for="entity-department" class="block text-sm font-semibold text-gray-900 mb-1.5">Jurusan</label>
                    <select id="entity-department" v-model="form.data.department_id" :class="fieldClass" :disabled="form.data.faculty_id === ''">
                        <option value="">Tanpa jurusan</option>
                        <option v-for="department in formDepartments" :key="department.id" :value="department.id">{{ department.name }}</option>
                    </select>
                    <p v-if="form.errors.value.department_id" class="mt-1 text-xs text-red-600">{{ form.errors.value.department_id }}</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="entity-name" class="block text-sm font-semibold text-gray-900 mb-1.5">Nama {{ ENTITY_LABELS[entityType] }}</label>
                        <input id="entity-name" v-model="form.data.name" type="text" placeholder="Contoh: Informatika" :class="fieldClass" required />
                        <p v-if="form.errors.value.name" class="mt-1 text-xs text-red-600">{{ form.errors.value.name }}</p>
                    </div>
                    <div>
                        <label for="entity-code" class="block text-sm font-semibold text-gray-900 mb-1.5">Kode Singkat</label>
                        <input id="entity-code" v-model="form.data.code" type="text" placeholder="Contoh: IF" :class="fieldClass" required />
                        <p v-if="form.errors.value.code" class="mt-1 text-xs text-red-600">{{ form.errors.value.code }}</p>
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                    <Button type="button" @click="isModalOpen = false" variant="ghost" class="bg-gray-100 hover:bg-gray-200">Batal</Button>
                    <Button type="submit" :loading="form.processing.value">Simpan Entitas</Button>
                </div>
            </form>
        </Modal>

        <ConfirmDialog
            :show="deleteTarget !== null"
            :title="`Hapus ${deleteTarget ? ENTITY_LABELS[deleteTarget.type].toLowerCase() : ''}?`"
            :processing="deleteProcessing"
            :error="deleteError"
            @confirm="confirmDelete"
            @close="deleteTarget = null"
        >
            <strong>{{ deleteTarget?.entity.name }}</strong> akan dihapus. Penghapusan ditolak selama masih ada data yang memakainya.
        </ConfirmDialog>
    </SuperadminLayout>
</template>

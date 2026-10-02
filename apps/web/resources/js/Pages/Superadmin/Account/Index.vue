<script setup lang="ts">
import SuperadminLayout from '@/Layouts/SuperadminLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Info, Users } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import Button from '@/Components/ui/Button.vue';
import MetricCard from '@/Components/ui/MetricCard.vue';
import Card from '@/Components/ui/Card.vue';
import Modal from '@/Components/Modal.vue';
import Alert from '@/Components/ui/Alert.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { api, toApiError } from '@/lib/api';
import { initials, ROLE_LABELS, ROLES, userUnit } from '@/lib/labels';
import { queryParam } from '@/lib/query';
import { countOf, useCollection, usePaginatedList } from '@/composables/useApiList';
import { useApiForm } from '@/composables/useApiForm';
import type { Role, User } from '@/types';
import type { FacultySummary, Lecturer, Paginated, StudyProgramSummary } from '@/types/models';

const ROLE_BADGES: Record<Role, string> = {
    superadmin: 'bg-gray-100 text-gray-700',
    admin_fakultas: 'bg-blue-50 text-blue-700',
    admin_tpb: 'bg-purple-50 text-purple-700',
    admin_prodi: 'bg-indigo-50 text-indigo-700',
    dosen: 'bg-teal-50 text-teal-700',
    mahasiswa: 'bg-amber-50 text-amber-700',
};

const selectClass = 'rounded-xl border-gray-200 text-sm focus:border-primary focus:ring-primary text-gray-700';
const fieldClass = 'w-full rounded-xl border-gray-200 bg-gray-50 text-gray-900 text-sm focus:border-primary focus:ring-primary';

// Lookups for filters, the form and the "Fakultas / Prodi" column.
const { items: faculties } = useCollection<FacultySummary>('faculties');
const { items: programs } = useCollection<StudyProgramSummary>('study-programs');
const facultyById = computed(() => new Map(faculties.value.map((f) => [f.id, f])));
const programById = computed(() => new Map(programs.value.map((p) => [p.id, p])));

const { filters, items: users, pagination, page, loading, error: listError, reload } = usePaginatedList<User, {
    q: string;
    role: Role | '';
    faculty_id: number | '';
    study_program_id: number | '';
    is_active: boolean | '';
}>('users', { q: queryParam('q'), role: '', faculty_id: '', study_program_id: '', is_active: '' });

const filterPrograms = computed(() =>
    filters.faculty_id === '' ? programs.value : programs.value.filter((p) => p.faculty_id === filters.faculty_id),
);

watch(
    () => filters.faculty_id,
    () => {
        filters.study_program_id = '';
    },
);

// Metric cards: meta.total of filtered lists.
const metrics = ref({ total: 0, admins: 0, lecturers: 0, students: 0 });

async function loadMetrics(): Promise<void> {
    try {
        const [total, facultyAdmins, programAdmins, tpbAdmins, lecturers, students] = await Promise.all([
            countOf('users'),
            countOf('users', { role: 'admin_fakultas' }),
            countOf('users', { role: 'admin_prodi' }),
            countOf('users', { role: 'admin_tpb' }),
            countOf('users', { role: 'dosen' }),
            countOf('users', { role: 'mahasiswa', is_active: true }),
        ]);
        metrics.value = { total, admins: facultyAdmins + programAdmins + tpbAdmins, lecturers, students };
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
const editing = ref<User | null>(null);
const form = useApiForm(() => ({
    name: '',
    identity_number: '',
    email: '',
    role: '' as Role | '',
    faculty_id: '' as number | '',
    program_faculty_id: '' as number | '',
    study_program_id: '' as number | '',
    lecturer_id: '' as number | '',
    password: '',
}));

const needsFaculty = computed(() => form.data.role === 'admin_fakultas');
const needsProgram = computed(() => form.data.role === 'admin_prodi' || form.data.role === 'mahasiswa');
const needsLecturer = computed(() => form.data.role === 'dosen');

const formPrograms = computed(() =>
    form.data.program_faculty_id === '' ? programs.value : programs.value.filter((p) => p.faculty_id === form.data.program_faculty_id),
);

// Lecturers of the chosen program that have no account yet (or belong to the edited one).
const lecturerOptions = ref<Lecturer[]>([]);

async function loadLecturers(): Promise<void> {
    lecturerOptions.value = [];

    if (!needsLecturer.value || form.data.study_program_id === '') {
        return;
    }

    const response = await api.get<Paginated<Lecturer>>('lecturers', { params: { study_program_id: form.data.study_program_id, per_page: 100 } });
    lecturerOptions.value = response.data.data.filter((l) => l.user_id === null || l.user_id === editing.value?.id);
}

watch(() => [form.data.role, form.data.study_program_id], () => void loadLecturers());

function openCreate(): void {
    editing.value = null;
    form.reset();
    isModalOpen.value = true;
}

function openEdit(user: User): void {
    editing.value = user;
    const programId = user.study_program_id ?? user.lecturer?.study_program_id ?? '';
    form.reset({
        name: user.name,
        identity_number: user.role === 'dosen' ? '' : user.identity_number ?? '',
        email: user.email,
        role: user.role ?? '',
        faculty_id: user.faculty_id ?? '',
        program_faculty_id: programId === '' ? '' : programById.value.get(programId)?.faculty_id ?? '',
        study_program_id: programId,
        lecturer_id: user.lecturer_id ?? '',
    });
    isModalOpen.value = true;
}

/** Only the scope field of the chosen role is sent; the others are prohibited by the API. */
function payload(): Record<string, unknown> {
    const data: Record<string, unknown> = {
        name: form.data.name,
        email: form.data.email,
        role: form.data.role,
    };

    if (!needsLecturer.value) {
        data.identity_number = form.data.identity_number || null;
    }

    if (needsFaculty.value) data.faculty_id = form.data.faculty_id || null;
    if (needsProgram.value) data.study_program_id = form.data.study_program_id || null;
    if (needsLecturer.value) data.lecturer_id = form.data.lecturer_id || null;
    if (form.data.password) data.password = form.data.password;

    return data;
}

async function save(): Promise<void> {
    const saved = editing.value
        ? await form.submit<User>('patch', `users/${editing.value.id}`, payload())
        : await form.submit<User>('post', 'users', payload());

    if (saved) {
        isModalOpen.value = false;
        refresh(editing.value ? `Akun ${saved.name} diperbarui.` : `Akun ${saved.name} dibuat.`);
    }
}

// Activate / deactivate. Accounts are never deleted.
const statusTarget = ref<User | null>(null);
const statusProcessing = ref(false);
const statusError = ref<string | null>(null);

function askStatus(user: User): void {
    statusTarget.value = user;
    statusError.value = null;
}

async function toggleStatus(): Promise<void> {
    const user = statusTarget.value;

    if (!user) return;

    statusProcessing.value = true;

    try {
        await api.patch(`users/${user.id}/status`, { is_active: !user.is_active });
        statusTarget.value = null;
        refresh(user.is_active ? `Akun ${user.name} dinonaktifkan.` : `Akun ${user.name} diaktifkan.`);
    } catch (e) {
        const error = toApiError(e);
        statusError.value = Object.values(error.errors).flat().map(String)[0] ?? error.message;
    } finally {
        statusProcessing.value = false;
    }
}
</script>

<template>
    <Head title="Kelola Akun & Role" />

    <SuperadminLayout>
        <div class="p-6 md:p-8 space-y-8">
            <!-- Header section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Kelola Akun & Role</h2>
                </div>
                <div class="flex items-center gap-3">
                    <Button @click="openCreate" class="bg-primary hover:bg-primary-hover text-white">
                        + Tambah Akun
                    </Button>
                </div>
            </div>

            <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />

            <!-- Metrics grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <MetricCard title="Total Pengguna" :value="metrics.total.toLocaleString('id-ID')" unit="Akun" />
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-center">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Admin Institusi</p>
                    <h3 class="text-2xl font-bold text-gray-900 mt-1">{{ metrics.admins.toLocaleString('id-ID') }} <span class="text-xs font-medium text-gray-500">Admin</span></h3>
                    <p class="text-xs font-medium text-gray-400 mt-1">{{ faculties.length }} Fakultas • {{ programs.length }} Program Studi</p>
                </div>
                <MetricCard title="Akun Dosen" :value="metrics.lecturers.toLocaleString('id-ID')" unit="Dosen" />
                <MetricCard title="Mahasiswa Aktif" :value="metrics.students.toLocaleString('id-ID')" unit="Mahasiswa" />
            </div>

            <!-- Table & Filters Section -->
            <Card class="!p-0 border-none shadow-sm">
                <!-- Toolbar -->
                <div class="p-5 border-b border-gray-100 flex flex-col md:flex-row justify-between gap-4 bg-white">
                    <div class="flex flex-wrap gap-3">
                        <select v-model="filters.role" :class="[selectClass, 'w-40']" aria-label="Filter role">
                            <option value="">Semua Role</option>
                            <option v-for="role in ROLES" :key="role" :value="role">{{ ROLE_LABELS[role] }}</option>
                        </select>
                        <select v-model="filters.faculty_id" :class="[selectClass, 'w-56']" aria-label="Filter fakultas">
                            <option value="">Semua Fakultas</option>
                            <option v-for="faculty in faculties" :key="faculty.id" :value="faculty.id">{{ faculty.code }} - {{ faculty.name }}</option>
                        </select>
                        <select v-model="filters.study_program_id" :class="[selectClass, 'w-56']" aria-label="Filter program studi">
                            <option value="">Semua Program Studi</option>
                            <option v-for="program in filterPrograms" :key="program.id" :value="program.id">{{ program.name }}</option>
                        </select>
                        <select v-model="filters.is_active" :class="[selectClass, 'w-40']" aria-label="Filter status">
                            <option value="">Semua Status</option>
                            <option :value="true">Aktif</option>
                            <option :value="false">Nonaktif</option>
                        </select>
                    </div>
                    <div class="w-full md:w-64">
                        <input
                            v-model="filters.q"
                            type="search"
                            placeholder="Cari nama, email, NIP/NIM..."
                            class="w-full rounded-xl border-gray-200 text-sm focus:border-primary focus:ring-primary placeholder-gray-400"
                        />
                    </div>
                </div>

                <Alert v-if="listError" :title="listError.message" class="m-5" />

                <!-- Table -->
                <div class="overflow-x-auto w-full">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-500 border-b border-gray-100">
                                <th class="py-4 px-6 min-w-[200px]">Pengguna</th>
                                <th class="py-4 px-6">Email Institusi</th>
                                <th class="py-4 px-6">Role & Hak Akses</th>
                                <th class="py-4 px-6">Fakultas / Program Studi</th>
                                <th class="py-4 px-6 text-center">Status</th>
                                <th class="py-4 px-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm" :class="{ 'opacity-60': loading }">
                            <tr v-if="!loading && users.length === 0">
                                <td colspan="6" class="py-10 px-6 text-center text-gray-500">Tidak ada akun yang cocok dengan filter.</td>
                            </tr>
                            <tr
                                v-for="user in users"
                                :key="user.id"
                                class="hover:bg-gray-50/50 transition-colors"
                                :class="{ 'opacity-80': !user.is_active }"
                            >
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-10 h-10 rounded-lg flex items-center justify-center font-bold text-xs shrink-0"
                                            :class="user.is_active && user.role ? ROLE_BADGES[user.role] : 'bg-gray-100 text-gray-400'"
                                        >
                                            {{ initials(user.name) }}
                                        </div>
                                        <div>
                                            <p class="font-bold text-gray-900">{{ user.name }}</p>
                                            <p v-if="user.identity_number" class="text-xs text-gray-500">
                                                {{ user.role === 'mahasiswa' ? 'NIM' : 'NIP' }}. {{ user.identity_number }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6 text-gray-700 font-medium">{{ user.email }}</td>
                                <td class="py-4 px-6">
                                    <span v-if="user.role" class="px-2.5 py-1 rounded-md text-xs font-semibold" :class="ROLE_BADGES[user.role]">
                                        {{ ROLE_LABELS[user.role] }}
                                    </span>
                                </td>
                                <td class="py-4 px-6">
                                    <p class="font-semibold text-gray-900">{{ userUnit(user, programById, facultyById).title }}</p>
                                    <p class="text-xs text-gray-500">{{ userUnit(user, programById, facultyById).subtitle }}</p>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <span
                                        v-if="user.is_active"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-50 text-green-700 border border-green-200"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-600 animate-pulse"></span>Aktif
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-200"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-600"></span>Nonaktif
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button
                                            v-if="user.can_update"
                                            type="button"
                                            class="p-1.5 rounded hover:bg-gray-100 text-gray-400 hover:text-gray-900 transition-colors"
                                            @click="openEdit(user)"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            v-if="user.can_update_status"
                                            type="button"
                                            class="p-1.5 rounded transition-colors"
                                            :class="user.is_active ? 'text-gray-400 hover:bg-red-50 hover:text-red-600' : 'text-gray-400 hover:bg-green-50 hover:text-green-700'"
                                            @click="askStatus(user)"
                                        >
                                            {{ user.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-model:page="page" :pagination="pagination" label="akun" />
            </Card>
        </div>

        <!-- Create / Edit Modal -->
        <Modal :show="isModalOpen" @close="isModalOpen = false" maxWidth="2xl">
            <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50 rounded-t-lg">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary">
                        <Users class="w-5 h-5" aria-hidden="true" />
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">{{ editing ? 'Ubah Akun Pengguna' : 'Tambah Akun Pengguna Baru' }}</h2>
                        <p class="text-sm text-gray-500">Konfigurasi data akun institusi dan otorisasi role</p>
                    </div>
                </div>
                <button type="button" @click="isModalOpen = false" class="text-gray-400 hover:text-gray-900 transition">
                    Tutup
                </button>
            </div>
            <form @submit.prevent="save" class="p-6 space-y-6">
                <Alert v-if="form.failure.value" :title="form.failure.value.message" />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="user-name" class="block text-sm font-semibold text-gray-900 mb-1.5">Nama Lengkap</label>
                        <input id="user-name" v-model="form.data.name" type="text" placeholder="Nama lengkap beserta gelar..." :class="fieldClass" required />
                        <p v-if="form.errors.value.name" class="mt-1 text-xs text-red-600">{{ form.errors.value.name }}</p>
                    </div>
                    <div>
                        <label for="user-identity" class="block text-sm font-semibold text-gray-900 mb-1.5">Nomor Identitas (NIP/NIM)</label>
                        <input
                            id="user-identity"
                            v-model="form.data.identity_number"
                            type="text"
                            inputmode="numeric"
                            :placeholder="needsLecturer ? 'Diambil dari NIP data dosen' : 'Contoh: 198502152010121001'"
                            :disabled="needsLecturer"
                            :class="[fieldClass, 'disabled:opacity-60']"
                        />
                        <p v-if="form.errors.value.identity_number" class="mt-1 text-xs text-red-600">{{ form.errors.value.identity_number }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="user-email" class="block text-sm font-semibold text-gray-900 mb-1.5">Email Institusi</label>
                        <input id="user-email" v-model="form.data.email" type="email" placeholder="Contoh: dosen@itk.ac.id" :class="fieldClass" required />
                        <p v-if="form.errors.value.email" class="mt-1 text-xs text-red-600">{{ form.errors.value.email }}</p>
                    </div>
                    <div>
                        <label for="user-role" class="block text-sm font-semibold text-gray-900 mb-1.5">Role & Otoritas Akses</label>
                        <select id="user-role" v-model="form.data.role" :class="fieldClass" required>
                            <option value="" disabled>Pilih hak akses role...</option>
                            <option value="superadmin">Superadmin (Akses Sistem Penuh)</option>
                            <option value="admin_fakultas">Admin Fakultas (Ruangan & Data Fakultas)</option>
                            <option value="admin_tpb">Admin TPB (Mata Kuliah Bersama Tingkat Institut)</option>
                            <option value="admin_prodi">Koor Prodi (Setup Kurikulum & Jadwal)</option>
                            <option value="dosen">Dosen (Preferensi Waktu & Jadwal)</option>
                            <option value="mahasiswa">Mahasiswa (Lihat Jadwal)</option>
                        </select>
                        <p v-if="form.errors.value.role" class="mt-1 text-xs text-red-600">{{ form.errors.value.role }}</p>
                    </div>
                </div>

                <div v-if="needsFaculty">
                    <label for="user-faculty" class="block text-sm font-semibold text-gray-900 mb-1.5">Fakultas</label>
                    <select id="user-faculty" v-model="form.data.faculty_id" :class="fieldClass" required>
                        <option value="" disabled>Pilih fakultas...</option>
                        <option v-for="faculty in faculties" :key="faculty.id" :value="faculty.id">{{ faculty.code }} - {{ faculty.name }}</option>
                    </select>
                    <p v-if="form.errors.value.faculty_id" class="mt-1 text-xs text-red-600">{{ form.errors.value.faculty_id }}</p>
                </div>

                <div v-if="needsProgram || needsLecturer" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="user-program-faculty" class="block text-sm font-semibold text-gray-900 mb-1.5">Fakultas</label>
                        <select id="user-program-faculty" v-model="form.data.program_faculty_id" :class="fieldClass" @change="form.data.study_program_id = ''">
                            <option value="">Semua Fakultas</option>
                            <option v-for="faculty in faculties" :key="faculty.id" :value="faculty.id">{{ faculty.code }} - {{ faculty.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="user-program" class="block text-sm font-semibold text-gray-900 mb-1.5">Program Studi</label>
                        <select id="user-program" v-model="form.data.study_program_id" :class="fieldClass" required>
                            <option value="" disabled>Pilih program studi...</option>
                            <option v-for="program in formPrograms" :key="program.id" :value="program.id">{{ program.name }}</option>
                        </select>
                        <p v-if="form.errors.value.study_program_id" class="mt-1 text-xs text-red-600">{{ form.errors.value.study_program_id }}</p>
                    </div>
                </div>

                <div v-if="needsLecturer">
                    <label for="user-lecturer" class="block text-sm font-semibold text-gray-900 mb-1.5">Data Dosen</label>
                    <select id="user-lecturer" v-model="form.data.lecturer_id" :class="fieldClass" :disabled="form.data.study_program_id === ''" required>
                        <option value="" disabled>{{ form.data.study_program_id === '' ? 'Pilih program studi dulu' : 'Pilih dosen yang belum punya akun...' }}</option>
                        <option v-for="lecturer in lecturerOptions" :key="lecturer.id" :value="lecturer.id">{{ lecturer.name }} ({{ lecturer.nip }})</option>
                    </select>
                    <p v-if="form.errors.value.lecturer_id" class="mt-1 text-xs text-red-600">{{ form.errors.value.lecturer_id }}</p>
                </div>

                <div>
                    <label for="user-password" class="block text-sm font-semibold text-gray-900 mb-1.5">{{ editing ? 'Password Baru' : 'Password Awal' }}</label>
                    <input
                        id="user-password"
                        v-model="form.data.password"
                        type="password"
                        autocomplete="new-password"
                        :placeholder="editing ? 'Kosongkan jika tidak diubah' : 'Kosongkan untuk memakai password default'"
                        :class="fieldClass"
                    />
                    <p v-if="form.errors.value.password" class="mt-1 text-xs text-red-600">{{ form.errors.value.password }}</p>
                </div>

                <div class="p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-start gap-3">
                    <Info class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" aria-hidden="true" />
                    <p class="text-sm text-blue-800">
                        Setiap perubahan akun dan role dicatat di <strong>Log Aktivitas</strong>. Akun baru langsung aktif. Tanpa password awal,
                        akun memakai password default; minta pengguna menggantinya di halaman profil.
                    </p>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                    <Button type="button" @click="isModalOpen = false" variant="ghost" class="bg-gray-100 hover:bg-gray-200">Batal</Button>
                    <Button type="submit" :loading="form.processing.value">Simpan Akun</Button>
                </div>
            </form>
        </Modal>

        <ConfirmDialog
            :show="statusTarget !== null"
            :title="statusTarget?.is_active ? 'Nonaktifkan akun?' : 'Aktifkan akun?'"
            :confirm-label="statusTarget?.is_active ? 'Nonaktifkan' : 'Aktifkan'"
            :danger="statusTarget?.is_active ?? true"
            :processing="statusProcessing"
            :error="statusError"
            @confirm="toggleStatus"
            @close="statusTarget = null"
        >
            <template v-if="statusTarget?.is_active">
                <strong>{{ statusTarget?.name }}</strong> tidak bisa masuk lagi dan sesinya langsung berakhir. Akun bisa diaktifkan kembali kapan saja.
            </template>
            <template v-else>
                <strong>{{ statusTarget?.name }}</strong> bisa masuk lagi dengan password terakhirnya.
            </template>
        </ConfirmDialog>
    </SuperadminLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import { fetchAll } from '@/composables/useApiList';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import { toApiError } from '@/lib/api';
import type { Course, TpbGroup } from '@/types/models';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const courses = ref<Course[]>([]);
const groups = ref<TpbGroup[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

const classCount = computed(() => courses.value.reduce((total, course) => total + course.parallel_class_count, 0));
const assignedClassCount = computed(() => courses.value.reduce((total, course) => total + course.course_lecturers_count, 0));
const unassignedClassCount = computed(() => Math.max(0, classCount.value - assignedClassCount.value));
const scheduledGroups = computed(() => groups.value.filter((group) => group.time_slot_id !== null));
const unplacedGroups = computed(() => groups.value.filter((group) => group.time_slot_id === null));
const participantCount = computed(() => new Set(groups.value.flatMap((group) => group.study_programs.map((program) => program.id))).size);

async function loadDashboard(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        const [courseRows, groupRows] = await Promise.all([
            fetchAll<Course>('courses', { is_tpb: true }),
            fetchAll<TpbGroup>('tpb-groups'),
        ]);
        courses.value = courseRows;
        groups.value = groupRows;
    } catch (cause) {
        error.value = toApiError(cause).message;
    } finally {
        loading.value = false;
    }
}

onMounted(() => void loadDashboard());
onResourcesChanged(['courses', 'course_lecturers', 'tpb_groups', 'study_programs', 'time_slots'], () => void loadDashboard());
</script>

<template>
    <Head title="Dashboard Admin TPB" />

    <AppLayout>
        <div class="mx-auto w-full max-w-7xl space-y-6 p-6 md:p-8">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Dashboard Admin TPB</h1>
                    <p class="mt-1 text-sm text-gray-500">Ringkasan pengelolaan mata kuliah dan jadwal TPB lintas program studi.</p>
                </div>
                <button
                    type="button"
                    class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:opacity-50"
                    :disabled="loading"
                    @click="loadDashboard"
                >
                    {{ loading ? 'Memuat...' : 'Muat ulang' }}
                </button>
            </header>

            <div v-if="error" role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="font-semibold">Dashboard TPB tidak dapat dimuat.</p>
                <p class="mt-1">{{ error }}</p>
                <button type="button" class="mt-2 font-semibold underline" @click="loadDashboard">Coba lagi</button>
            </div>

            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Ringkasan TPB">
                <Card class="border border-gray-100 shadow-sm">
                    <p class="text-xs font-semibold text-gray-500">Mata Kuliah TPB</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ loading ? '—' : courses.length }}</p>
                    <Link href="/tpb" class="mt-2 inline-flex text-xs font-semibold text-primary hover:underline">Kelola mata kuliah</Link>
                </Card>
                <Card class="border border-gray-100 shadow-sm">
                    <p class="text-xs font-semibold text-gray-500">Kelas Paralel</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ loading ? '—' : classCount }}</p>
                    <p class="mt-2 text-xs text-gray-500">{{ assignedClassCount }} kelas sudah memiliki pengampu</p>
                </Card>
                <Card class="border border-gray-100 shadow-sm">
                    <p class="text-xs font-semibold text-gray-500">Grup TPB</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ loading ? '—' : groups.length }}</p>
                    <Link href="/tpb" class="mt-2 inline-flex text-xs font-semibold text-primary hover:underline">Kelola grup dan peserta</Link>
                </Card>
                <Card class="border border-gray-100 shadow-sm">
                    <p class="text-xs font-semibold text-gray-500">Grup dengan Slot</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ loading ? '—' : scheduledGroups.length }}</p>
                    <p class="mt-2 text-xs text-gray-500">dari {{ groups.length }} grup TPB</p>
                </Card>
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                <Card class="border border-gray-100 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900">Status Pengampu Kelas TPB</h2>
                            <p class="mt-1 text-xs text-gray-500">Kelengkapan pengampu untuk seluruh kelas paralel.</p>
                        </div>
                        <Link href="/tpb" class="text-xs font-semibold text-primary hover:underline">Atur pengampu</Link>
                    </div>
                    <dl class="mt-5 divide-y divide-gray-100">
                        <div class="flex items-center justify-between py-3 text-sm">
                            <dt class="text-gray-600">Total kelas</dt>
                            <dd class="font-bold text-gray-900">{{ loading ? '—' : classCount }}</dd>
                        </div>
                        <div class="flex items-center justify-between py-3 text-sm">
                            <dt class="text-gray-600">Sudah memiliki pengampu</dt>
                            <dd class="font-bold text-emerald-700">{{ loading ? '—' : assignedClassCount }}</dd>
                        </div>
                        <div class="flex items-center justify-between py-3 text-sm">
                            <dt class="text-gray-600">Belum memiliki pengampu</dt>
                            <dd class="font-bold" :class="unassignedClassCount ? 'text-amber-700' : 'text-gray-900'">{{ loading ? '—' : unassignedClassCount }}</dd>
                        </div>
                    </dl>
                </Card>

                <Card class="border border-gray-100 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900">Kesiapan Grup TPB</h2>
                            <p class="mt-1 text-xs text-gray-500">Penempatan slot dan cakupan program studi peserta.</p>
                        </div>
                        <Link href="/tpb" class="text-xs font-semibold text-primary hover:underline">Kelola data TPB</Link>
                    </div>
                    <dl class="mt-5 divide-y divide-gray-100">
                        <div class="flex items-center justify-between py-3 text-sm">
                            <dt class="text-gray-600">Total grup</dt>
                            <dd class="font-bold text-gray-900">{{ loading ? '—' : groups.length }}</dd>
                        </div>
                        <div class="flex items-center justify-between py-3 text-sm">
                            <dt class="text-gray-600">Sudah memiliki slot</dt>
                            <dd class="font-bold text-emerald-700">{{ loading ? '—' : scheduledGroups.length }}</dd>
                        </div>
                        <div class="flex items-center justify-between py-3 text-sm">
                            <dt class="text-gray-600">Program studi peserta</dt>
                            <dd class="font-bold text-gray-900">{{ loading ? '—' : participantCount }}</dd>
                        </div>
                    </dl>
                </Card>
            </section>

            <Card class="!p-0 overflow-hidden border border-gray-100 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 p-5">
                    <div>
                        <h2 class="text-sm font-bold text-gray-900">Grup TPB Belum Memiliki Slot</h2>
                        <p class="mt-1 text-xs text-gray-500">Grup yang perlu ditinjau sebelum jadwal disusun.</p>
                    </div>
                    <Link href="/tpb" class="text-xs font-semibold text-primary hover:underline">Buka Kelola Data TPB</Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr class="bg-gray-50/70 text-[11px] font-bold uppercase text-gray-500">
                                <th class="px-6 py-4">Mata Kuliah</th>
                                <th class="px-6 py-4">Grup</th>
                                <th class="px-6 py-4">Program Studi Peserta</th>
                                <th class="px-6 py-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <tr v-if="loading">
                                <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">Memuat grup TPB...</td>
                            </tr>
                            <tr v-else-if="unplacedGroups.length === 0">
                                <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">Semua grup TPB sudah memiliki slot.</td>
                            </tr>
                            <tr v-for="group in unplacedGroups.slice(0, 5)" :key="group.id">
                                <td class="px-6 py-4">
                                    <span class="font-semibold text-gray-900">{{ group.course.code }}</span>
                                    <span class="mt-1 block text-gray-500">{{ group.course.name }}</span>
                                </td>
                                <td class="px-6 py-4 font-medium">{{ group.code }}</td>
                                <td class="px-6 py-4">
                                    <span v-if="group.study_programs.length">{{ group.study_programs.map((program) => program.code).join(', ') }}</span>
                                    <span v-else class="text-gray-400">Belum ada prodi peserta</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Link href="/tpb" class="font-semibold text-primary hover:underline">Atur slot</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>

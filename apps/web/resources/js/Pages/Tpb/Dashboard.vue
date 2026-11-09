<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import DashboardChart from '@/Components/dashboard/DashboardChart.vue';
import DashboardSummary from '@/Components/dashboard/DashboardSummary.vue';
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
        <div class="dashboard-page w-full space-y-6 p-6 md:p-8">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-[#112D4E]">Dashboard</h1>
                    <p class="mt-1 text-sm text-[#112D4E]/70">Admin TPB · Pengelolaan mata kuliah dan grup lintas program studi</p>
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

            <DashboardSummary label="Ringkasan TPB" :figures="[
                { label: 'Mata kuliah TPB', value: loading || error ? '—' : courses.length },
                { label: 'Kelas paralel', value: loading || error ? '—' : classCount, detail: loading || error ? undefined : `${assignedClassCount} sudah memiliki pengampu` },
                { label: 'Grup TPB', value: loading || error ? '—' : groups.length },
                { label: 'Grup dengan slot', value: loading || error ? '—' : scheduledGroups.length, detail: loading || error ? undefined : `dari ${groups.length} grup` },
            ]" />

            <section class="grid gap-6 lg:grid-cols-5">
                <DashboardChart
                    v-if="!loading && !error"
                    class="lg:col-span-3"
                    title="Pengampu kelas TPB"
                    description="Jumlah kelas berdasarkan pengampu"
                    unit="kelas"
                    :items="[
                        { label: 'Sudah ada pengampu', value: assignedClassCount },
                        { label: 'Belum ada pengampu', value: unassignedClassCount },
                    ]"
                />
                <div v-else class="rounded-[20px] border border-[#DBE2EF] bg-white p-6 text-sm text-[#112D4E]/70 lg:col-span-3">
                    {{ loading ? 'Memuat grafik pengampu…' : 'Grafik pengampu tidak dapat dimuat.' }}
                </div>

                <Card class="border border-gray-100 shadow-sm lg:col-span-2">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-[#112D4E]">Kesiapan Grup TPB</h2>
                            <p class="mt-1 text-xs text-gray-500">Penempatan slot dan cakupan program studi peserta.</p>
                        </div>
                        <Link href="/tpb" class="text-xs font-semibold text-primary hover:underline">Kelola data TPB</Link>
                    </div>
                    <dl class="mt-5 divide-y divide-gray-100">
                        <div class="flex items-center justify-between py-3 text-sm">
                            <dt class="text-gray-600">Total grup</dt>
                            <dd class="font-bold text-gray-900">{{ loading || error ? '—' : groups.length }}</dd>
                        </div>
                        <div class="flex items-center justify-between py-3 text-sm">
                            <dt class="text-gray-600">Sudah memiliki slot</dt>
                            <dd class="font-bold text-emerald-700">{{ loading || error ? '—' : scheduledGroups.length }}</dd>
                        </div>
                        <div class="flex items-center justify-between py-3 text-sm">
                            <dt class="text-gray-600">Program studi peserta</dt>
                            <dd class="font-bold text-gray-900">{{ loading || error ? '—' : participantCount }}</dd>
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
                            <tr v-else-if="error">
                                <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">Daftar grup TPB tidak dapat dimuat.</td>
                            </tr>
                            <tr v-else-if="unplacedGroups.length === 0">
                                <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">Semua grup TPB sudah memiliki slot.</td>
                            </tr>
                            <tr v-for="group in loading || error ? [] : unplacedGroups.slice(0, 5)" :key="group.id">
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

<style scoped>
.dashboard-page { background: #F9F7F7; min-height: calc(100vh - 4rem); }
.dashboard-page :deep(.shadow-sm) { border-color: #DBE2EF; border-radius: 20px; box-shadow: none; }
</style>

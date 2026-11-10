<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import DashboardSummary from '@/Components/dashboard/DashboardSummary.vue';
import Alert from '@/Components/ui/Alert.vue';
import Card from '@/Components/ui/Card.vue';
import DashboardChart from '@/Components/dashboard/DashboardChart.vue';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import {
    FACULTY_PROGRAMS,
    FACULTY_PROGRAM_CLASSES,
    FACULTY_PROGRAM_SLOT_STORAGE_KEY,
} from '@/lib/facultyProgramSlotAllocation';
import type { FacultyDashboard } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';
import { FileSpreadsheet } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

defineProps<{ dashboard: FacultyDashboard }>();
const allocatedSlotCount = ref<number | null>(null);
const allocationsByProgram = ref<Record<string, number>>(Object.fromEntries(FACULTY_PROGRAMS.map((program) => [program.id, 0])));
const allocationStorageError = ref<string | null>(null);
const allocationChartItems = computed(() => FACULTY_PROGRAMS.map((program) => ({
    label: program.code,
    value: allocationsByProgram.value[program.id] ?? 0,
})));
const allocatedProgramCount = computed(() => allocationChartItems.value.filter((item) => item.value > 0).length);
const unallocatedProgramCount = computed(() => FACULTY_PROGRAMS.length - allocatedProgramCount.value);

const DAYS: Record<number, string> = { 1: 'Senin', 2: 'Selasa', 3: 'Rabu', 4: 'Kamis', 5: 'Jumat', 6: 'Sabtu', 7: 'Minggu' };
const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

function formatDate(value: string | null): string {
    if (!value) return 'Belum dipublikasikan';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : dateFormat.format(date);
}

onMounted(() => {
    try {
        const stored = localStorage.getItem(FACULTY_PROGRAM_SLOT_STORAGE_KEY);
        const parsed: unknown = stored ? JSON.parse(stored) : {};
        if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
            throw new Error('Format data alokasi tersimpan tidak valid.');
        }
        const counts = Object.fromEntries(FACULTY_PROGRAMS.map((program) => [program.id, 0]));
        for (const allocation of Object.values(parsed)) {
            if (
                !allocation
                || typeof allocation !== 'object'
                || !('programId' in allocation)
                || typeof allocation.programId !== 'string'
                || !FACULTY_PROGRAMS.some((program) => program.id === allocation.programId)
                || !('classLabel' in allocation)
                || typeof allocation.classLabel !== 'string'
                || !FACULTY_PROGRAM_CLASSES.some((label) => label === allocation.classLabel)
            ) {
                throw new Error('Format data alokasi tersimpan tidak valid.');
            }
            counts[allocation.programId] += 1;
        }
        allocationsByProgram.value = counts;
        allocatedSlotCount.value = Object.values(counts).reduce((total, count) => total + count, 0);
    } catch (error) {
        allocationStorageError.value = error instanceof Error ? error.message : 'Data alokasi tidak dapat dibaca.';
    }
});

onResourcesChanged(['scheduling_runs', 'schedule_details', 'study_programs', 'lecturers', 'courses', 'course_lecturers', 'lecturer_preferences'], () => {
    router.reload({ only: ['dashboard'] });
});
</script>

<template>
    <Head title="Dashboard Fakultas" />
    <AppLayout>
        <div class="dashboard-page w-full space-y-6 p-6 md:p-8">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-[#112D4E]">Dashboard</h1>
                    <p class="mt-1 text-sm text-[#112D4E]/70">Admin Fakultas · {{ dashboard.period }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link href="/fakultas/program-slot-allocation" class="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                        Input Alokasi Slot Prodi
                    </Link>
                    <Link href="/fakultas/tpb-import" class="inline-flex h-10 items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                        <FileSpreadsheet class="size-4" aria-hidden="true" />
                        Import Jadwal TPB
                    </Link>
                </div>
            </header>

            <Alert v-if="allocationStorageError" variant="error" title="Data alokasi slot tidak dapat dibaca">{{ allocationStorageError }}</Alert>

            <DashboardSummary label="Ringkasan fakultas" :figures="[
                { label: 'Kelas terjadwal', value: dashboard.active_run?.schedule_details_count ?? 0, detail: dashboard.active_run ? formatDate(dashboard.active_run.published_at) : 'Belum ada jadwal aktif' },
                { label: 'Masalah ruangan', value: dashboard.rooms.unassigned_count + dashboard.rooms.over_capacity_count, detail: `${dashboard.rooms.unassigned_count} tanpa ruang · ${dashboard.rooms.over_capacity_count} kapasitas kurang` },
                { label: 'Alokasi slot prodi', value: allocatedSlotCount ?? '—', detail: 'Tersimpan di browser ini' },
            ]" />

            <section class="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(240px,2fr)]">
                <DashboardChart
                    title="Alokasi Slot per Prodi"
                    description="Jumlah slot ruang yang sudah dialokasikan"
                    unit="slot"
                    :items="allocationChartItems"
                />
                <Card class="border border-gray-100 shadow-sm">
                    <h2 class="text-lg font-bold text-[#112D4E]">Status Alokasi Prodi</h2>
                    <p class="mt-1 text-sm text-gray-500">Prodi dengan dan tanpa slot yang dialokasikan.</p>
                    <dl class="mt-6 space-y-4">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm text-gray-600">Sudah dialokasikan</dt>
                            <dd class="text-lg font-bold tabular-nums text-green-700">{{ allocatedProgramCount }} prodi</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm text-gray-600">Belum dialokasikan</dt>
                            <dd class="text-lg font-bold tabular-nums text-gray-700">{{ unallocatedProgramCount }} prodi</dd>
                        </div>
                    </dl>
                    <ul class="mt-5 space-y-2 border-t border-gray-100 pt-4">
                        <li v-for="program in FACULTY_PROGRAMS" :key="program.id" class="flex items-center justify-between gap-3 text-sm">
                            <span class="min-w-0 truncate text-gray-700">{{ program.code }} · {{ program.name }}</span>
                            <span class="shrink-0 font-semibold tabular-nums" :class="allocationsByProgram[program.id] ? 'text-green-700' : 'text-gray-500'">
                                {{ allocationsByProgram[program.id] }} slot
                            </span>
                        </li>
                    </ul>
                </Card>
            </section>

            <Card class="!p-0 overflow-hidden border border-gray-100 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 p-5">
                  <div>
                    <h2 class="text-sm font-bold text-gray-900">Masalah Alokasi Ruangan</h2>
                    <p class="mt-1 text-xs text-gray-500">Daftar kelas yang perlu ditinjau pada jadwal aktif.</p>
                  </div>
                  <Link href="/fakultas/rooms" class="text-xs font-semibold text-primary hover:underline">Kelola alokasi ruang</Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead><tr class="bg-gray-50/70 text-[11px] font-bold uppercase text-gray-500"><th class="px-6 py-4">Jenis Masalah</th><th class="px-6 py-4">Mata Kuliah</th><th class="px-6 py-4">Hari / Sesi</th><th class="px-6 py-4">Ruang</th></tr></thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <tr v-if="dashboard.rooms.issues.length === 0"><td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">Tidak ada masalah alokasi ruang.</td></tr>
                            <tr v-for="issue in dashboard.rooms.issues" :key="issue.schedule_detail_id">
                                <td class="px-6 py-4"><span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="issue.type === 'unassigned' ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700'">{{ issue.type === 'unassigned' ? 'Tanpa ruang' : 'Kapasitas kurang' }}</span></td>
                                <td class="px-6 py-4"><span class="font-semibold text-gray-900">{{ issue.course }}</span><span class="block text-[11px] text-gray-500">Kelas {{ issue.class_label }}</span></td>
                                <td class="px-6 py-4">{{ DAYS[issue.day] ?? `Hari ${issue.day}` }} · Sesi {{ issue.session }}</td>
                                <td class="px-6 py-4">{{ issue.room ?? 'Belum ditentukan' }}</td>
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

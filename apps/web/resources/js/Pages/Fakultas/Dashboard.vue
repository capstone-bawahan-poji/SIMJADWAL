<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import PageContainer from '@/Components/shared/PageContainer.vue';
import DashboardSummary from '@/Components/dashboard/DashboardSummary.vue';
import Card from '@/Components/ui/Card.vue';
import DashboardChart from '@/Components/dashboard/DashboardChart.vue';
import SubmissionStatusBadge from '@/Components/scheduling/SubmissionStatusBadge.vue';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import { formatDateTime } from '@/lib/labels';
import type { FacultyDashboard } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';
import { FileSpreadsheet } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ dashboard: FacultyDashboard }>();
const DAYS: Record<number, string> = { 1: 'Senin', 2: 'Selasa', 3: 'Rabu', 4: 'Kamis', 5: 'Jumat', 6: 'Sabtu', 7: 'Minggu' };

const programs = computed(() => props.dashboard.schedules.programs);
const cellChartItems = computed(() => programs.value.map((row) => ({ label: row.study_program.code, value: row.cells_count })));
const handedIn = computed(() => (props.dashboard.schedules.by_status.submitted ?? 0) + (props.dashboard.schedules.by_status.published ?? 0));
const notHandedIn = computed(() => programs.value.length - handedIn.value);
const publicationDetail = computed(() => {
    if (props.dashboard.schedules.published) return `Dipublikasikan ${formatDateTime(props.dashboard.schedules.published_at)}`;
    return props.dashboard.schedules.can_publish ? 'Siap dipublikasikan' : 'Belum dipublikasikan';
});

onResourcesChanged(['scheduling_runs', 'schedule_details', 'room_slot_allocations', 'slot_requests', 'program_schedule_submissions', 'schedule_publications'], () => {
    router.reload({ only: ['dashboard'] });
});
</script>

<template>
    <Head title="Dashboard Fakultas" />
    <AppLayout bare>
        <div class="dashboard-page">
            <PageContainer class="space-y-6">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-[#112D4E]">Dashboard</h1>
                    <p class="mt-1 text-sm text-[#112D4E]/70">Admin Fakultas · {{ dashboard.period }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link href="/fakultas/tpb-import" class="inline-flex h-10 items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                        <FileSpreadsheet class="size-4" aria-hidden="true" />
                        Import Jadwal TPB
                    </Link>
                    <Link href="/fakultas/program-slot-allocation" class="inline-flex h-10 items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                        Slot Prodi
                    </Link>
                    <Link href="/fakultas/review" class="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                        Jadwal Prodi &amp; Publikasi
                    </Link>
                </div>
            </header>

            <DashboardSummary label="Ringkasan fakultas" :figures="[
                { label: 'Jadwal prodi diserahkan', value: `${handedIn}/${programs.length}`, detail: publicationDetail },
                { label: 'Belum menyerahkan', value: notHandedIn, detail: `${dashboard.schedules.pending_slot_requests} permintaan slot menunggu` },
                { label: 'Masalah ruangan', value: dashboard.rooms.unassigned_count + dashboard.rooms.over_capacity_count, detail: `${dashboard.rooms.unassigned_count} tanpa ruang, ${dashboard.rooms.over_capacity_count} kapasitas kurang` },
            ]" />

            <section class="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(240px,2fr)]">
                <DashboardChart
                    title="Slot Ruangan per Prodi"
                    description="Jumlah slot ruangan yang sudah diberikan"
                    unit="slot"
                    :items="cellChartItems"
                />
                <Card class="border border-gray-100 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-[#112D4E]">Status Jadwal Prodi</h2>
                            <p class="mt-1 text-sm text-gray-500">Jadwal yang dikirim program studi ke fakultas.</p>
                        </div>
                        <Link href="/fakultas/review" class="text-xs font-semibold text-primary hover:underline">Lihat</Link>
                    </div>
                    <ul class="mt-5 space-y-3">
                        <li v-if="programs.length === 0" class="text-sm text-gray-500">Belum ada program studi.</li>
                        <li v-for="row in programs" :key="row.study_program.id" class="flex items-center justify-between gap-3 text-sm">
                            <span class="min-w-0 truncate text-gray-700">
                                {{ row.study_program.code }} {{ row.study_program.name }}
                                <span v-if="row.has_pending_slot_request" class="ml-1 text-xs text-amber-700">minta slot</span>
                            </span>
                            <SubmissionStatusBadge :status="row.status" />
                        </li>
                    </ul>
                </Card>
            </section>

            <Card class="!p-0 overflow-hidden border border-gray-100 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 p-5">
                  <div>
                    <h2 class="text-sm font-bold text-gray-900">Masalah Alokasi Ruangan</h2>
                    <p class="mt-1 text-xs text-gray-500">Kelas yang perlu ditinjau pada jadwal aktif program studi.</p>
                  </div>
                  <Link href="/fakultas/rooms" class="text-xs font-semibold text-primary hover:underline">Lihat alokasi ruang</Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead><tr class="bg-gray-50/70 text-[11px] font-bold uppercase text-gray-500"><th class="px-6 py-4">Jenis Masalah</th><th class="px-6 py-4">Mata Kuliah</th><th class="px-6 py-4">Hari / Sesi</th><th class="px-6 py-4">Ruang</th></tr></thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <tr v-if="dashboard.rooms.issues.length === 0"><td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">Tidak ada masalah alokasi ruang.</td></tr>
                            <tr v-for="issue in dashboard.rooms.issues" :key="issue.schedule_detail_id">
                                <td class="px-6 py-4"><span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="issue.type === 'unassigned' ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700'">{{ issue.type === 'unassigned' ? 'Tanpa ruang' : 'Kapasitas kurang' }}</span></td>
                                <td class="px-6 py-4"><span class="font-semibold text-gray-900">{{ issue.course }}</span><span class="block text-[11px] text-gray-500">Kelas {{ issue.class_label }}</span></td>
                                <td class="px-6 py-4">{{ DAYS[issue.day] ?? `Hari ${issue.day}` }}, sesi {{ issue.session }}</td>
                                <td class="px-6 py-4">{{ issue.room ?? 'Belum ditentukan' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
            </PageContainer>
        </div>
    </AppLayout>
</template>

<style scoped>
.dashboard-page { background: #F9F7F7; min-height: calc(100vh - 4rem); }
.dashboard-page :deep(.shadow-sm) { border-color: #DBE2EF; border-radius: 20px; box-shadow: none; }
</style>

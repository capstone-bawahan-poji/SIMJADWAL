<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import DashboardChart from '@/Components/dashboard/DashboardChart.vue';
import DashboardSummary from '@/Components/dashboard/DashboardSummary.vue';
import Card from '@/Components/ui/Card.vue';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import type { ConstraintStatus, FacultyDashboard } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps<{ dashboard: FacultyDashboard }>();

const DAYS: Record<number, string> = { 1: 'Senin', 2: 'Selasa', 3: 'Rabu', 4: 'Kamis', 5: 'Jumat', 6: 'Sabtu', 7: 'Minggu' };
const STATUS: Record<ConstraintStatus, string> = { draft: 'Draft', submitted: 'Menunggu review', accepted: 'Diterima' };
const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

function formatDate(value: string | null): string {
    if (!value) return 'Belum dipublikasikan';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : dateFormat.format(date);
}

onResourcesChanged(['scheduling_runs', 'schedule_details', 'study_programs', 'lecturers', 'courses', 'course_lecturers', 'lecturer_preferences'], () => {
    router.reload({ only: ['dashboard'] });
});
</script>

<template>
    <Head title="Dashboard Fakultas" />
    <AppLayout>
        <div class="dashboard-page w-full space-y-6 p-6 md:p-8">
            <header>
                <h1 class="text-3xl font-bold tracking-tight text-[#112D4E]">Dashboard</h1>
                <p class="mt-1 text-sm text-[#112D4E]/70">Admin Fakultas · {{ dashboard.period }}</p>
            </header>

            <DashboardSummary label="Ringkasan fakultas" :figures="[
                { label: 'Constraint prodi', value: dashboard.constraints.total, detail: `${dashboard.constraints.submitted} menunggu review · ${dashboard.constraints.accepted} diterima` },
                { label: 'Kelas terjadwal', value: dashboard.active_run?.schedule_details_count ?? 0, detail: dashboard.active_run ? formatDate(dashboard.active_run.published_at) : 'Belum ada jadwal aktif' },
                { label: 'Tanpa ruangan', value: dashboard.rooms.unassigned_count },
                { label: 'Kapasitas kurang', value: dashboard.rooms.over_capacity_count },
            ]" />

            <section class="grid gap-6 lg:grid-cols-5">
                <DashboardChart
                    class="lg:col-span-3"
                    title="Status constraint prodi"
                    description="Jumlah constraint berdasarkan status"
                    unit="constraint"
                    :items="(['submitted', 'accepted', 'draft'] as ConstraintStatus[]).map((status) => ({
                        label: STATUS[status],
                        value: dashboard.constraints[status],
                    }))"
                />

                <Card class="border border-gray-100 shadow-sm lg:col-span-2">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <h2 class="text-lg font-bold text-[#112D4E]">Kualitas Jadwal Aktif</h2>
                        <Link href="/fakultas/constraints" class="text-xs font-semibold text-primary hover:underline">Buka review</Link>
                    </div>
                    <div>
                        <p class="mt-1 text-xs text-gray-500">Metrik run aktif untuk periode {{ dashboard.period }}.</p>
                    </div>
                    <div v-if="dashboard.active_run" class="mt-5 grid grid-cols-2 gap-4">
                        <div class="rounded-xl bg-gray-50 p-4">
                            <p class="text-xs text-gray-500">Fitness terbaik</p>
                            <p class="mt-1 text-xl font-bold text-gray-900">{{ dashboard.active_run.fitness_percent ?? '—' }}<span v-if="dashboard.active_run.fitness_percent !== null" class="text-sm">%</span></p>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-4">
                            <p class="text-xs text-gray-500">Generasi akhir</p>
                            <p class="mt-1 text-xl font-bold text-gray-900">{{ dashboard.active_run.final_generation ?? '—' }}</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-4">
                            <p class="text-xs text-gray-500">Pelanggaran hard constraint</p>
                            <p class="mt-1 text-xl font-bold" :class="dashboard.active_run.hc_violations ? 'text-red-700' : 'text-gray-900'">{{ dashboard.active_run.hc_violations }}</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-4">
                            <p class="text-xs text-gray-500">Soft constraint belum terpenuhi</p>
                            <p class="mt-1 text-xl font-bold text-gray-900">{{ dashboard.active_run.sc_unmet }}</p>
                        </div>
                    </div>
                    <p v-else class="mt-5 rounded-xl bg-gray-50 p-4 text-sm text-gray-500">Belum ada hasil penjadwalan aktif untuk periode ini.</p>
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

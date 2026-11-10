<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import DashboardChart from '@/Components/dashboard/DashboardChart.vue';
import DashboardSummary from '@/Components/dashboard/DashboardSummary.vue';
import Card from '@/Components/ui/Card.vue';
import { fetchAll } from '@/composables/useApiList';
import Panel from '@/Components/ui/Panel.vue';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import { APP_RELEASES_URL } from '@/lib/navigation';
import { COHORT_LABELS, DAY_LABELS } from '@/lib/labels';
import type { TpbCohort, TpbDashboard } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ dashboard: TpbDashboard }>();

// Same colours as the schedule matrix on /tpb/schedule.
const COHORT_BAR: Record<TpbCohort, string> = {
    maba: 'bg-blue-500',
    non_maba: 'bg-indigo-500',
    campuran: 'bg-teal-500',
    mengulang: 'bg-amber-500',
    pilihan: 'bg-gray-400',
};
const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
const number = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const linkButton = 'inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary';
const primaryButton = 'inline-flex h-10 shrink-0 items-center rounded-lg bg-primary px-4 text-sm font-semibold text-white shadow-md shadow-primary/20 hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2';

const run = computed(() => props.dashboard.schedule.active_run);
const staleNames = computed(() => props.dashboard.stale_faculties.map((f) => f.code).join(', '));
const periodLabel = computed(() => {
    const [year, term] = props.dashboard.period.split('-');
    return term ? `semester ${term.charAt(0)}${term.slice(1).toLowerCase()} ${year}` : props.dashboard.period;
});
const readiness = computed(() => [
    { label: 'Mata kuliah', done: props.dashboard.offerings.count > 0, href: '/tpb/mappings/import' },
    { label: 'Grup kelas', done: props.dashboard.offerings.count > 0 && props.dashboard.classes.ungrouped === 0, href: '/tpb/mappings?tab=groups' },
    { label: 'Dosen pengampu', done: props.dashboard.offerings.count > 0 && props.dashboard.classes.without_lecturer === 0, href: '/tpb/mappings?tab=classes' },
    { label: 'Jadwal aktif', done: run.value !== null, href: '/tpb/ga' },
]);
const STATE_LABEL: Record<TpbDashboard['schedule']['status'], string> = {
    none: 'Belum ada hasil GA',
    draft: 'Belum ditetapkan',
    active: 'Aktif, belum dipublikasikan',
    published: 'Dipublikasikan',
};
const qualityOk = computed(() => !!run.value && run.value.hc_violations === 0 && run.value.sc_unmet === 0 && run.value.unassigned_rooms === 0);

// The one thing the admin should do now. Data problems come first, then the state of the schedule.
const next = computed<{ tone: 'ok' | 'todo' | 'blocked'; title: string; text: string; label: string; href: string; external?: boolean }>(() => {
    const d = props.dashboard;
    if (d.offerings.count === 0) return { tone: 'blocked', title: 'Belum ada mata kuliah TPB di periode ini', text: 'Isi data lewat import Excel, lalu atur grup dan dosennya.', label: 'Import data', href: '/tpb/mappings/import' };
    if (d.classes.ungrouped > 0) return { tone: 'todo', title: `${d.classes.ungrouped} kelas belum masuk grup`, text: 'Kelas di luar grup tidak ikut dijadwalkan oleh GA.', label: 'Atur grup kelas', href: '/tpb/mappings?tab=groups' };
    if (d.classes.without_lecturer > 0) return { tone: 'todo', title: `${d.classes.without_lecturer} kelas belum punya dosen`, text: 'GA belum bisa dijalankan sebelum semua kelas punya dosen.', label: 'Lengkapi dosen', href: '/tpb/mappings?tab=classes' };
    switch (d.schedule.status) {
        case 'none': return { tone: 'todo', title: 'Data siap, belum ada hasil GA', text: 'Jalankan GA TPB di aplikasi desktop, lalu periksa hasilnya di sini.', label: 'Unduh aplikasi', href: APP_RELEASES_URL, external: true };
        case 'draft': return { tone: 'todo', title: 'Hasil GA belum ditetapkan', text: 'Pilih satu run sebagai jadwal aktif.', label: 'Buka Penjadwalan GA', href: '/tpb/ga' };
        case 'active': return { tone: 'todo', title: 'Jadwal aktif belum dipublikasikan', text: 'Fakultas sudah bisa menjadwalkan. Publikasikan agar dosen dan mahasiswa ikut melihatnya.', label: 'Publikasikan jadwal', href: '/tpb/ga' };
        default:
            return d.stale_faculties.length
                ? { tone: 'todo', title: `${d.stale_faculties.length} fakultas masih memakai jadwal TPB lama`, text: `${staleNames.value} perlu menjalankan GA lagi terhadap jadwal TPB terbaru.`, label: 'Lihat run TPB', href: '/tpb/ga' }
                : { tone: 'ok', title: 'Jadwal TPB sudah dipublikasikan', text: 'Fakultas bisa menjalankan GA dan dosen serta mahasiswa dapat melihat jadwalnya.', label: 'Lihat jadwal', href: '/tpb/schedule' };
    }
});
const stateBadge = computed(() => ({ ok: 'bg-green-50 text-green-700', todo: 'bg-amber-50 text-amber-800', blocked: 'bg-red-50 text-red-700' })[next.value.tone]);

const cohortTotal = computed(() => Object.values(props.dashboard.cohorts).reduce((sum, n) => sum + n, 0));
const cohortRows = computed(() => (Object.keys(COHORT_LABELS) as TpbCohort[]).map((cohort) => {
    const total = props.dashboard.cohorts[cohort] ?? 0;
    return { cohort, total, share: cohortTotal.value ? (total / cohortTotal.value) * 100 : 0 };
}));

function formatDate(value: string | null): string {
    if (!value) return '-';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : dateFormat.format(date);
}

onResourcesChanged(['scheduling_runs', 'tpb_offerings', 'tpb_groups', 'course_lecturers', 'courses'], () => {
    router.reload({ only: ['dashboard'] });
});
</script>

<template>
    <Head title="Dashboard TPB" />
    <AppLayout>
        <div class="mx-auto w-full max-w-7xl space-y-6 p-6 md:p-8">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Dashboard TPB</h1>
                    <p class="mt-1 text-sm text-gray-500">Status persiapan dan hasil jadwal TPB untuk {{ periodLabel }}.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Link href="/tpb/schedule" :class="linkButton">Buka Jadwal TPB</Link>
                </div>
            </header>

            <Panel>
                <div class="grid lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] lg:divide-x lg:divide-gray-200">
                    <section class="space-y-6 p-6" aria-label="Status penjadwalan TPB">
                        <div class="space-y-3">
                            <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="stateBadge">{{ STATE_LABEL[dashboard.schedule.status] }}</span>
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <h2 class="text-lg font-bold text-gray-900">{{ next.title }}</h2>
                                    <p class="mt-1 max-w-xl text-sm text-gray-600">{{ next.text }}</p>
                                </div>
                                <a v-if="next.external" :href="next.href" target="_blank" rel="noopener" :class="primaryButton">{{ next.label }}</a>
                                <Link v-else :href="next.href" :class="primaryButton">{{ next.label }}</Link>
                            </div>
                        </div>

                        <ol class="grid grid-cols-2 gap-x-4 gap-y-3 border-t border-gray-100 pt-5 sm:grid-cols-4" aria-label="Persiapan penjadwalan">
                            <li v-for="(step, i) in readiness" :key="step.label" class="flex items-center gap-2.5">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-bold" :class="step.done ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
                                    <Check v-if="step.done" class="size-3.5" aria-hidden="true" />
                                    <template v-else>{{ i + 1 }}</template>
                                </span>
                                <Link v-if="!step.done" :href="step.href" class="text-sm font-semibold text-primary hover:underline">{{ step.label }}</Link>
                                <span v-else class="text-sm text-gray-700">{{ step.label }}</span>
                            </li>
                        </ol>
                    </section>

                    <section class="p-6" aria-label="Kualitas jadwal aktif">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="text-sm font-bold text-gray-900">Kualitas jadwal aktif</h2>
                                <p class="mt-0.5 text-xs text-gray-500">{{ run ? `Selesai ${formatDate(run.finished_at)}` : 'Belum ada jadwal TPB aktif.' }}</p>
                            </div>
                            <Link v-if="run" href="/tpb/ga" class="shrink-0 text-xs font-semibold text-primary hover:underline">Run #{{ run.id }}</Link>
                        </div>

                        <template v-if="run">
                            <p class="mt-5 text-4xl font-bold tabular-nums tracking-tight text-gray-900">{{ run.fitness_percent !== null ? `${number.format(run.fitness_percent)}%` : '-' }}</p>
                            <p class="text-xs text-gray-500">Fitness terbaik</p>

                            <dl class="mt-5 divide-y divide-gray-100 text-sm">
                                <div class="flex items-center justify-between py-2.5">
                                    <dt class="text-gray-600">Pelanggaran hard constraint</dt>
                                    <dd class="font-semibold tabular-nums" :class="run.hc_violations ? 'rounded-md bg-red-50 px-2 py-0.5 text-red-700' : 'text-gray-900'">{{ run.hc_violations }}</dd>
                                </div>
                                <div class="flex items-center justify-between py-2.5">
                                    <dt class="text-gray-600">Soft constraint belum terpenuhi</dt>
                                    <dd class="font-semibold tabular-nums text-gray-900">{{ run.sc_unmet }}</dd>
                                </div>
                                <div class="flex items-center justify-between py-2.5">
                                    <dt class="text-gray-600">Kelas tanpa ruang</dt>
                                    <dd class="font-semibold tabular-nums" :class="run.unassigned_rooms ? 'rounded-md bg-amber-50 px-2 py-0.5 text-amber-800' : 'text-gray-900'">{{ run.unassigned_rooms }}</dd>
                                </div>
                            </dl>
                            <p v-if="qualityOk" class="mt-3 text-xs text-green-700">Tidak ada pelanggaran, dan semua kelas mendapat ruang.</p>
                        </template>
                        <p v-else class="mt-5 rounded-lg bg-gray-50 p-4 text-sm text-gray-500">Setelah GA dijalankan dan satu run ditetapkan aktif, kualitasnya muncul di sini.</p>
                    </section>
                </div>
            </Panel>

            <Panel>
                <div class="px-6 pt-5">
                    <h2 class="text-sm font-bold text-gray-900">Data TPB periode ini</h2>
                    <p class="mt-1 text-xs text-gray-500">Mata kuliah, dosen, ruang, dan kelas yang masuk jadwal TPB.</p>
                </div>
                <div class="grid gap-x-10 gap-y-6 p-6 lg:grid-cols-2">
                    <dl class="divide-y divide-gray-100">
                        <div class="flex items-start justify-between gap-4 py-3 text-sm">
                            <dt class="text-gray-600">Mata kuliah TPB</dt>
                            <dd class="text-right"><Link href="/tpb/courses" class="font-bold tabular-nums text-gray-900 hover:underline">{{ dashboard.offerings.count }} mata kuliah</Link><span class="block text-xs text-gray-500">{{ dashboard.offerings.groups }} grup, {{ dashboard.offerings.classes }} kelas, {{ dashboard.offerings.seats.toLocaleString('id-ID') }} kursi</span></dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 py-3 text-sm">
                            <dt class="text-gray-600">Dosen pengampu</dt>
                            <dd class="text-right"><Link href="/tpb/lecturers" class="font-bold tabular-nums text-gray-900 hover:underline">{{ dashboard.lecturers.count }} dosen</Link>
                                <span v-if="dashboard.lecturers.over_limit" class="mt-1 block rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-900">{{ dashboard.lecturers.over_limit }} dosen melebihi batas {{ dashboard.lecturers.limit }} SKS<template v-if="dashboard.lecturers.top">, tertinggi {{ dashboard.lecturers.top.name }} ({{ dashboard.lecturers.top.sks }} SKS)</template></span>
                                <span v-else class="block text-xs text-gray-500">Tidak ada dosen melebihi batas {{ dashboard.lecturers.limit }} SKS</span>
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 py-3 text-sm">
                            <dt class="text-gray-600">Ruang bersama saat terpadat</dt>
                            <dd class="text-right"><Link href="/tpb/rooms" class="font-bold tabular-nums text-gray-900 hover:underline">{{ dashboard.rooms.peak?.used ?? '-' }} dari {{ dashboard.rooms.shared }} ruang</Link><span class="block text-xs text-gray-500">{{ dashboard.rooms.peak ? `${DAY_LABELS[dashboard.rooms.peak.day]}, sesi ${dashboard.rooms.peak.session}` : 'Belum ada jadwal aktif' }}<template v-if="run">. {{ run.unassigned_rooms }} kelas belum dapat ruang</template></span></dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 py-3 text-sm">
                            <dt class="text-gray-600">Prodi dengan kelas mengulang</dt>
                            <dd class="text-right"><Link href="/tpb/schedule" class="font-bold tabular-nums text-gray-900 hover:underline">{{ dashboard.retake_programs }} prodi</Link></dd>
                        </div>
                    </dl>

                    <div>
                        <div class="flex items-center justify-between py-3 text-sm">
                            <h3 class="font-semibold text-gray-900">Jumlah kelas per kategori</h3>
                            <Link href="/tpb/mappings?tab=groups" class="text-xs font-semibold text-primary hover:underline">Buka grup kelas</Link>
                        </div>
                        <div class="flex h-3 overflow-hidden rounded-full bg-gray-100" role="img" :aria-label="`Komposisi ${cohortTotal} kelas: ${cohortRows.map((r) => `${COHORT_LABELS[r.cohort]} ${r.total}`).join(', ')}`">
                            <span v-for="row in cohortRows" :key="row.cohort" :class="COHORT_BAR[row.cohort]" :style="{ width: `${row.share}%` }" />
                        </div>
                        <dl class="mt-3 divide-y divide-gray-100">
                            <div v-for="row in cohortRows" :key="row.cohort" class="flex items-center justify-between py-2.5 text-sm">
                                <dt class="flex items-center gap-2 text-gray-600"><span class="size-3 rounded-sm" :class="COHORT_BAR[row.cohort]" aria-hidden="true" />{{ COHORT_LABELS[row.cohort] }}</dt>
                                <dd class="font-bold tabular-nums text-gray-900">{{ row.total }}<span class="ml-2 text-xs font-normal text-gray-500">{{ Math.round(row.share) }}%</span></dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </Panel>
        </div>
    </AppLayout>
</template>

<style scoped>
.dashboard-page { background: #F9F7F7; min-height: calc(100vh - 4rem); }
.dashboard-page :deep(.shadow-sm) { border-color: #DBE2EF; border-radius: 20px; box-shadow: none; }
</style>

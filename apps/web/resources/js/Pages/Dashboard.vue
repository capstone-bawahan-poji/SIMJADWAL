<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import StudentDashboard from '@/Pages/Mahasiswa/Dashboard.vue';
import TpbDashboard from '@/Pages/Tpb/Dashboard.vue';
import type { DashboardStats } from '@/types/models';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Building2, Clock, GraduationCap, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{ stats: DashboardStats | null }>();

const C = {
    paper: '#F9F7F7',
    mist: '#DBE2EF',
    blue: '#3F72AF',
    navy: '#112D4E',
    white: '#FFFFFF',
    muted: 'rgba(17, 45, 78, 0.65)',
    track: 'rgba(219, 226, 239, 0.6)',
};
const role = computed(() => usePage().props.auth.user.role);
const isStudent = computed(() => role.value === 'mahasiswa');
const isTpbAdmin = computed(() => role.value === 'admin_tpb');
const format = (value: number) => value.toLocaleString('id-ID');
const hoveredBar = ref<number | null>(null);
const PLOT_HEIGHT = 176;

const figures = computed(() => {
    if (!props.stats) return [];

    return [
        { label: 'Fakultas', value: format(props.stats.faculties) },
        { label: 'Program studi', value: format(props.stats.study_programs) },
        { label: 'Pengguna', value: format(props.stats.users.total) },
    ];
});

const roles = computed(() => {
    if (!props.stats) return [];

    return [
        { label: 'Super Admin', value: props.stats.users.by_role.superadmin },
        { label: 'Admin Fakultas', value: props.stats.users.by_role.admin_fakultas },
        { label: 'Admin Prodi', value: props.stats.users.by_role.admin_prodi },
        { label: 'Dosen', value: props.stats.users.by_role.dosen },
        { label: 'Mahasiswa', value: props.stats.users.by_role.mahasiswa },
    ].map((item) => ({
        ...item,
        percent: props.stats!.users.total > 0 ? Math.round((item.value / props.stats!.users.total) * 100) : 0,
    }));
});

const activePercent = computed(() =>
    props.stats && props.stats.users.total > 0 ? Math.round((props.stats.users.active / props.stats.users.total) * 100) : 0,
);
const activeArcLength = Math.PI * 52;

const tasks = computed(() => {
    if (!props.stats) return [];

    return [
        {
            label: 'Kelola data fakultas & prodi',
            value: format(props.stats.faculties),
            routeName: 'superadmin.faculty-program.index',
        },
        {
            label: 'Kelola akun pengguna',
            value: format(props.stats.users.total),
            routeName: 'superadmin.accounts.index',
        },
        {
            label: 'Kelola data ruangan',
            value: format(props.stats.rooms.total),
            routeName: 'superadmin.rooms.index',
        },
    ];
});

const steps = computed(() => {
    if (!props.stats) return [];

    return [
        {
            title: 'Fakultas & Prodi',
            icon: Building2,
            figure: format(props.stats.faculties),
            caption: `${format(props.stats.departments)} jurusan · ${format(props.stats.study_programs)} prodi`,
            routeName: 'superadmin.faculty-program.index',
        },
        {
            title: 'Akun pengguna',
            icon: Users,
            figure: format(props.stats.users.total),
            caption: `${format(props.stats.users.active)} akun aktif`,
            routeName: 'superadmin.accounts.index',
        },
        {
            title: 'Ruangan',
            icon: GraduationCap,
            figure: format(props.stats.rooms.total),
            caption: `${format(props.stats.rooms.shared)} ruangan bersama`,
            routeName: 'superadmin.rooms.index',
        },
        {
            title: 'Slot waktu',
            icon: Clock,
            figure: format(props.stats.time_slots.total),
            caption: `${props.stats.time_slots.days} hari · ${props.stats.time_slots.sessions_per_day} sesi`,
            routeName: 'superadmin.slots.index',
        },
    ];
});

const card = {
    backgroundColor: C.white,
    border: `1px solid ${C.mist}`,
    borderRadius: '20px',
    padding: '24px',
};
</script>

<template>
    <StudentDashboard v-if="isStudent" />
    <TpbDashboard v-else-if="isTpbAdmin" />
    <template v-else>
        <Head title="Dashboard Superadmin" />

        <AppLayout>
            <div v-if="!stats" class="w-full p-6 md:p-8" :style="{ backgroundColor: C.paper, minHeight: 'calc(100vh - 4rem)' }">
                <h1 class="text-3xl font-bold tracking-tight" :style="{ color: C.navy }">Dashboard</h1>
                <p class="mt-2 text-sm" :style="{ color: C.muted }">Ringkasan untuk role Anda belum tersedia.</p>
            </div>

            <div
                v-else
                class="flex w-full flex-col gap-6 p-6 md:p-8"
                :style="{ backgroundColor: C.paper, minHeight: 'calc(100vh - 4rem)' }"
            >
                <div>
                    <h1 class="text-3xl font-bold tracking-tight" :style="{ color: C.navy }">Dashboard</h1>
                    <p class="mt-1 text-sm" :style="{ color: C.muted }">Super Admin · Ringkasan data seluruh sistem</p>
                </div>

                <section :style="{ ...card, padding: '20px 24px' }" aria-label="Ringkasan sistem">
                    <dl style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); margin: 0">
                        <div
                            v-for="(figure, index) in figures"
                            :key="figure.label"
                            :style="{
                                paddingLeft: index === 0 ? '0' : '24px',
                                paddingRight: '12px',
                                borderLeft: index === 0 ? 'none' : `1px solid ${C.mist}`,
                            }"
                        >
                            <dt class="text-sm" :style="{ color: C.muted }">{{ figure.label }}</dt>
                            <dd class="mt-1 text-3xl font-bold tabular-nums tracking-tight" :style="{ color: C.navy }">{{ figure.value }}</dd>
                        </div>
                    </dl>
                </section>

                <div style="display: flex; flex-wrap: wrap; gap: 24px; align-items: stretch">
                    <section :style="{ ...card, flex: '1.6 1 32rem', minWidth: 0 }" aria-labelledby="roles-heading">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h2 id="roles-heading" class="text-lg font-bold" :style="{ color: C.navy }">Distribusi pengguna</h2>
                            <span class="text-xs" :style="{ color: C.muted }">Persentase akun berdasarkan role</span>
                        </div>

                        <div :style="{ display: 'flex', gap: '12px', paddingTop: '32px' }">
                            <div
                                class="text-xs tabular-nums"
                                :style="{
                                    color: C.muted,
                                    display: 'flex',
                                    flexDirection: 'column',
                                    justifyContent: 'space-between',
                                    height: `${PLOT_HEIGHT}px`,
                                    textAlign: 'right',
                                    width: '2.25rem',
                                    flexShrink: 0,
                                }"
                            >
                                <span>100%</span><span>50%</span><span>0%</span>
                            </div>

                            <div style="flex: 1; min-width: 0">
                                <div
                                    :style="{
                                        position: 'relative',
                                        height: `${PLOT_HEIGHT}px`,
                                        display: 'grid',
                                        gridTemplateColumns: 'repeat(5, minmax(0, 1fr))',
                                        gap: '16px',
                                        borderBottom: `1px solid ${C.mist}`,
                                    }"
                                >
                                    <div :style="{ position: 'absolute', left: 0, right: 0, top: 0, borderTop: `1px dashed ${C.mist}` }"></div>
                                    <div :style="{ position: 'absolute', left: 0, right: 0, top: '50%', borderTop: `1px dashed ${C.mist}` }"></div>

                                    <div
                                        v-for="(item, index) in roles"
                                        :key="item.label"
                                        tabindex="0"
                                        :aria-label="`${item.label}: ${item.percent}%, ${format(item.value)} akun`"
                                        :style="{ position: 'relative', display: 'flex', justifyContent: 'center', alignItems: 'flex-end', outline: 'none' }"
                                        @mouseenter="hoveredBar = index"
                                        @mouseleave="hoveredBar = null"
                                        @focus="hoveredBar = index"
                                        @blur="hoveredBar = null"
                                    >
                                        <div
                                            :style="{
                                                position: 'relative',
                                                width: '56px',
                                                maxWidth: '100%',
                                                height: '100%',
                                                backgroundColor: C.track,
                                                borderRadius: '14px 14px 0 0',
                                                overflow: 'hidden',
                                                display: 'flex',
                                                alignItems: 'flex-end',
                                            }"
                                        >
                                            <div
                                                :style="{
                                                    width: '100%',
                                                    height: `${item.percent > 0 ? Math.max(item.percent, 3) : 0}%`,
                                                    backgroundColor: hoveredBar === index ? C.navy : C.blue,
                                                    borderRadius: '14px 14px 0 0',
                                                    transition: 'background-color .15s',
                                                }"
                                            ></div>
                                        </div>

                                        <div
                                            v-if="hoveredBar === index"
                                            role="status"
                                            :style="{
                                                position: 'absolute',
                                                bottom: `calc(${item.percent}% + 10px)`,
                                                left: '50%',
                                                transform: 'translateX(-50%)',
                                                zIndex: 5,
                                                backgroundColor: C.navy,
                                                borderRadius: '10px',
                                                padding: '8px 12px',
                                                boxShadow: '0 6px 20px rgba(17,45,78,.25)',
                                                width: 'max-content',
                                                maxWidth: '14rem',
                                                pointerEvents: 'none',
                                            }"
                                        >
                                            <p class="tabular-nums" :style="{ fontWeight: 700, color: C.paper, fontSize: '1rem' }">{{ item.percent }}%</p>
                                            <p class="text-xs" :style="{ color: C.mist }">{{ format(item.value) }} akun {{ item.label }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 16px; margin-top: 10px">
                                    <span v-for="item in roles" :key="item.label" class="text-center text-xs" :style="{ color: C.muted }">
                                        {{ item.label }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div style="flex: 1 1 22rem; min-width: 0; display: flex; flex-direction: column; gap: 24px">
                        <section
                            :style="{
                                backgroundColor: C.navy,
                                borderRadius: '20px',
                                padding: '24px',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                gap: '16px',
                                flexWrap: 'wrap',
                            }"
                            aria-labelledby="active-heading"
                        >
                            <div style="flex: 1 1 11rem; min-width: 0">
                                <p id="active-heading" class="text-xs font-semibold uppercase tracking-wide" :style="{ color: C.mist }">Status akun</p>
                                <p class="mt-1.5 text-xl font-bold leading-snug" :style="{ color: C.paper }">
                                    {{ format(stats.users.active) }} akun aktif
                                </p>
                                <p class="mt-1 text-sm" :style="{ color: 'rgba(249, 247, 247, 0.72)' }">
                                    dari {{ format(stats.users.total) }} pengguna terdaftar
                                </p>
                            </div>

                            <div :style="{ position: 'relative', width: '9.5rem', flexShrink: 0, paddingBottom: '16px' }">
                                <svg viewBox="0 0 120 66" role="img" :aria-label="`${activePercent}% akun aktif`" style="display: block; width: 100%">
                                    <path d="M 8 60 A 52 52 0 0 1 112 60" fill="none" stroke="rgba(249, 247, 247, 0.16)" stroke-width="8" stroke-linecap="round" />
                                    <path
                                        v-if="activePercent > 0"
                                        d="M 8 60 A 52 52 0 0 1 112 60"
                                        fill="none"
                                        :stroke="C.mist"
                                        stroke-width="8"
                                        stroke-linecap="round"
                                        :stroke-dasharray="`${(activeArcLength * activePercent) / 100} ${activeArcLength}`"
                                    />
                                </svg>
                                <Link
                                    :href="route('superadmin.accounts.index')"
                                    aria-label="Kelola akun pengguna"
                                    :style="{
                                        position: 'absolute',
                                        left: '50%',
                                        bottom: '0',
                                        transform: 'translateX(-50%)',
                                        width: '30px',
                                        height: '30px',
                                        borderRadius: '9999px',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        backgroundColor: C.blue,
                                        color: C.white,
                                    }"
                                >
                                    <ArrowRight class="h-4 w-4" aria-hidden="true" />
                                </Link>
                                <span class="absolute inset-x-0 top-7 text-center text-sm font-bold" :style="{ color: C.paper }">{{ activePercent }}%</span>
                            </div>
                        </section>

                        <section :style="{ ...card, flex: 1 }" aria-labelledby="tasks-heading">
                            <h2 id="tasks-heading" class="text-lg font-bold" :style="{ color: C.navy }">Akses cepat</h2>
                            <ul style="list-style: none; padding: 0; margin: 12px 0 0">
                                <li
                                    v-for="(task, index) in tasks"
                                    :key="task.label"
                                    :style="{ borderTop: index === 0 ? 'none' : `1px solid ${C.mist}` }"
                                >
                                    <Link :href="route(task.routeName)" style="display: flex; align-items: center; gap: 12px; padding: 12px 0">
                                        <span
                                            :style="{
                                                width: '34px',
                                                height: '34px',
                                                borderRadius: '9999px',
                                                flexShrink: 0,
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                fontSize: '13px',
                                                fontWeight: 700,
                                                backgroundColor: C.mist,
                                                color: C.blue,
                                            }"
                                        >
                                            {{ index + 1 }}
                                        </span>
                                        <span class="text-sm" :style="{ color: C.navy, flex: 1, minWidth: 0 }">{{ task.label }}</span>
                                        <span class="text-sm font-bold tabular-nums" :style="{ color: C.navy }">{{ task.value }}</span>
                                    </Link>
                                </li>
                            </ul>
                        </section>
                    </div>
                </div>

                <section
                    aria-labelledby="flow-heading"
                    :style="{
                        backgroundColor: C.mist,
                        borderRadius: '24px',
                        padding: '24px',
                        display: 'flex',
                        flexWrap: 'wrap',
                        alignItems: 'center',
                        gap: '24px',
                    }"
                >
                    <div style="flex: 1 1 13rem; min-width: 0">
                        <h2 id="flow-heading" class="text-xl font-bold" :style="{ color: C.navy }">Pengelolaan sistem</h2>
                        <p class="mt-2 text-sm" :style="{ color: C.muted }">Pilih data utama yang ingin dikelola.</p>
                    </div>

                    <ol style="flex: 4 1 32rem; min-width: 0; display: flex; flex-wrap: wrap; gap: 16px; list-style: none; padding: 0; margin: 0">
                        <li v-for="step in steps" :key="step.title" style="flex: 1 1 9rem; min-width: 0">
                            <Link
                                :href="route(step.routeName)"
                                :style="{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    height: '100%',
                                    backgroundColor: C.white,
                                    borderRadius: '18px',
                                    padding: '16px',
                                    boxShadow: '0 8px 20px rgba(17,45,78,.08)',
                                }"
                            >
                                <span :style="{ width: '34px', height: '34px', borderRadius: '9999px', backgroundColor: C.blue, color: C.white, display: 'flex', alignItems: 'center', justifyContent: 'center' }">
                                    <component :is="step.icon" class="h-4 w-4" aria-hidden="true" />
                                </span>
                                <p class="mt-3 text-sm font-bold" :style="{ color: C.navy }">{{ step.title }}</p>
                                <p class="text-xs" :style="{ color: C.muted }">{{ step.caption }}</p>
                                <p class="mt-3 text-xl font-bold tabular-nums" :style="{ color: C.navy }">{{ step.figure }}</p>
                                <p class="mt-1 text-xs" :style="{ color: C.blue, fontWeight: 500 }">Kelola data</p>
                            </Link>
                        </li>

                        <li style="flex: 0 1 8rem; min-width: 0">
                            <Link
                                :href="route('superadmin.accounts.index')"
                                :style="{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    justifyContent: 'space-between',
                                    height: '100%',
                                    minHeight: '9rem',
                                    backgroundColor: C.navy,
                                    color: C.paper,
                                    borderRadius: '18px',
                                    padding: '16px',
                                }"
                            >
                                <span class="text-lg font-bold leading-tight">Kelola Akun</span>
                                <span :style="{ width: '30px', height: '30px', borderRadius: '9999px', backgroundColor: C.blue, color: C.white, display: 'flex', alignItems: 'center', justifyContent: 'center' }">
                                    <ArrowRight class="h-4 w-4" aria-hidden="true" />
                                </span>
                            </Link>
                        </li>
                    </ol>
                </section>
            </div>
        </AppLayout>
    </template>
</template>
